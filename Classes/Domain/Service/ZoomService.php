<?php
declare(strict_types=1);
namespace CodeQ\ZoomApi\Domain\Service;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class ZoomApiService
{
    /**
     * @var VariableFrontend
     */
    protected $entriesCache;

    private Client $client;

    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected array $settings;

    /**
     * @throws Exception
     */
    public function initializeObject(): void
    {
        $zoomApiKey = $this->settings['auth']['apiKey'];
        $zoomApiSecret = $this->settings['auth']['apiSecret'];
        if(!$zoomApiKey || !$zoomApiSecret) {
            throw new Exception('Please set a Zoom API Key and Secret for CodeQ.ZoomApi to be able to authenticate.');
        }

        $this->client = (new Client([
            'base_uri' => 'https://api.zoom.us/v2/',
            'headers' => [
                'Authorization' => "Bearer {$this->generateJwt($zoomApiKey, $zoomApiSecret)}",
                'Content-Type' => 'application/json',
            ],
        ]));
    }

    private function generateJwt(string $zoomApiKey, string $zoomApiSecret): string
    {
        return JWT::encode([
            "iss" => $zoomApiKey,
            "exp" => time() + 60,
        ], $zoomApiSecret, 'HS256');
    }

    /**
     * See also https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetings
     * 
     * @return array
     * @throws Exception
     */
    public function getUpcomingMeetings(): array
    {
        return $this->fetchData(
            "users/me/meetings?type=upcoming",
            'meetings'
        );
    }

    /**
     * See also https://marketplace.zoom.us/docs/api-reference/zoom-api/cloud-recording/recordingget
     * 
     * @param DateTime|string $from
     * @param DateTime|string $to
     * @return array
     * @throws Exception
     */
    public function getRecordings($from, $to): array
    {
        if(is_string($from)) {
            $from = new DateTime($from);
        }
        if(is_string($to)) {
            $to = new DateTime($to);
        }
        return $this->fetchData(
            "users/me/recordings?from={$from->format('Y-m-d')}&to={$to->format('Y-m-d')}",
            'meetings'
        );
    }

    /**
     * @throws Exception
     */
    private function fetchData($uri, string $paginatedDataKey): array
    {
        $aggregatedData = [];
        $nextPageToken = '';

        do {
            $responseData = $this->fetchPaginatedData("$uri&next_page_token=$nextPageToken&page_size=300");

            if (!array_key_exists($paginatedDataKey, $responseData)) {
                throw new Exception("Could not find key $paginatedDataKey. Response data: ".print_r($aggregatedData, true));
            }

            $aggregatedData = array_merge($aggregatedData, $responseData[$paginatedDataKey]);
            $nextPageToken = $responseData['next_page_token'];
        } while ($nextPageToken != '');

        return $aggregatedData;
    }

    private function fetchPaginatedData(string $uri): array
    {
        $response = $this->client->get($uri);
        return json_decode($response->getBody()->getContents(), true);
    }

}
