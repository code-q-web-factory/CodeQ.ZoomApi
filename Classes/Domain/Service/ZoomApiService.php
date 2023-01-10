<?php
declare(strict_types=1);
namespace CodeQ\ZoomApi\Domain\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Exception as CacheException;

/**
 * @Flow\Scope("singleton")
 */
class ZoomApiService
{
    private Client $client;

    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected array $settings;

    /**
     * @var VariableFrontend
     */
    protected $requestsCache;

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
     * @param bool $skipCache Omits reading from the cache, to force fetching from the API
     *
     * @return array
     */
    public function getUpcomingMeetings(bool $skipCache = false): array
    {
        $cacheEntryIdentifier = 'upcomingMeetings';

        /** @var array|bool $upcomingMeetings */
        if (!$skipCache && ($upcomingMeetings = $this->requestsCache->get($cacheEntryIdentifier)) !== false) {
            return $upcomingMeetings;
        }

        $upcomingMeetings = $this->fetchData(
            "users/me/meetings?type=upcoming",
            'meetings'
        );

        try {
            $this->requestsCache->set($cacheEntryIdentifier, $upcomingMeetings);
        } catch (CacheException $e) {
            // If CacheException is thrown we just go on and fetch the recordings directly from Zoom
        }

        return $upcomingMeetings;
    }

    /**
     * See also https://marketplace.zoom.us/docs/api-reference/zoom-api/cloud-recording/recordingget
     *
     * @param DateTime|string $from
     * @param DateTime|string $to
     * @param bool            $skipCache Omits reading from the cache, to force fetching from the API
     *
     * @return array
     */
    public function getRecordings(mixed $from, mixed $to, bool $skipCache = false): array
    {
        $cacheEntryIdentifier = sprintf('recordings_%s_%s', is_string($from) ? $from : $from->format('Y-m-d'), is_string($to) ? $to : $to->format('Y-m-d'));

        /** @var array|bool $recordings */
        if (!$skipCache && ($recordings = $this->requestsCache->get($cacheEntryIdentifier)) !== false) {
            return $recordings;
        }

        if (is_string($from)) {
            $from = new DateTimeImmutable($from);
        } elseIf ($from instanceof DateTime) {
            $from = DateTimeImmutable::createFromMutable($from);
        }

        if (is_string($to)) {
            $to = new DateTimeImmutable($to);
        } elseIf ($to instanceof DateTime) {
            $to = DateTimeImmutable::createFromMutable($to);
        }

        if ($from > $to) {
            throw new InvalidArgumentException('The from date must be after the to date');
        }

        $recordings = $this->fetchDataForDateRange($from, $to);

        try {
            $this->requestsCache->set($cacheEntryIdentifier, $recordings);
        } catch (CacheException $e) {
            // If CacheException is thrown we just go on
        }

        return $recordings;
    }

    private function fetchDataForDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $aggregatedData = [];
        $fromOriginal = clone $from;
        $isFirstIteration = true;

        do {
            $getMoreMonths = false;

            // The zoom API only returns up to one month per request, so if the date range between $from and $to is
            // bigger than one month we chunk our requests. We start by our $to date and subtract one month from it.
            if ($this->dateDifferenceIsBiggerThanOneMonth($from, $to)) {
                $from = $to->sub(DateInterval::createFromDateString('1 month'));
                $getMoreMonths = true;
            }

            // If the current iteration is not the first iteration we have to subtract one day from our to-date
            // because otherwise we query the recordings from this date twice.
            if (!$isFirstIteration) {
                $to = $to->sub(DateInterval::createFromDateString('1 day'));
            }

            $responseData = $this->fetchData(
                "users/me/recordings?from={$from->format('Y-m-d')}&to={$to->format('Y-m-d')}",
                'meetings'
            );

            $aggregatedData = array_merge($aggregatedData, $responseData);

            if ($getMoreMonths) {
                $isFirstIteration = false;
                $to = $from;
                $from = $fromOriginal;
            }
        } while ($getMoreMonths);

        return $aggregatedData;
    }

    private function fetchData($uri, string $paginatedDataKey): array
    {
        $aggregatedData = [];
        try {
            $nextPageToken = '';

            do {
                $responseData = $this->fetchPaginatedData("$uri&next_page_token=$nextPageToken&page_size=300");

                if (!array_key_exists($paginatedDataKey, $responseData)) {
                    throw new Exception("Could not find key $paginatedDataKey. Response data: "
                        . print_r($aggregatedData,
                            true));
                }

                $aggregatedData = array_merge($aggregatedData, $responseData[$paginatedDataKey]);
                $nextPageToken = $responseData['next_page_token'];
            } while ($nextPageToken != '');
        } catch (RequestException $e) {
        }

        return $aggregatedData;
    }

    private function fetchPaginatedData(string $uri): array
    {
        $response = $this->client->get($uri);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function dateDifferenceIsBiggerThanOneMonth(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        $dateDifference = $from->diff($to);
        $differenceInMonths = $dateDifference->y * 12 + $dateDifference->m;
        return $differenceInMonths > 0;
    }
}
