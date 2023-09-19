<?php
declare(strict_types=1);
namespace CodeQ\ZoomApi\Domain\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
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
        $zoomApiAccountId = $this->settings['auth']['accountId'];
        $zoomApiClientId = $this->settings['auth']['clientId'];
        $zoomApiClientSecret = $this->settings['auth']['clientSecret'];
        if(!$zoomApiAccountId || !$zoomApiClientId || !$zoomApiClientSecret) {
            throw new Exception('Please set a Zoom Account ID, Client ID and Secret for CodeQ.ZoomApi to be able to authenticate.');
        }

        $accessToken = $this->getAccessToken($zoomApiAccountId, $zoomApiClientId, $zoomApiClientSecret);

        $this->client = (new Client([
            'base_uri' => 'https://api.zoom.us/v2/',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
        ]));
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

        /** @var array $upcomingMeetings */
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
            // If CacheException is thrown we just go on and fetch the items directly from Zoom
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

        /** @var array $recordings */
        if (!$skipCache && ($recordings = $this->requestsCache->get($cacheEntryIdentifier)) !== false) {
            return $recordings;
        }

        if (is_string($from)) {
            $from = new DateTimeImmutable($from);
        } elseif ($from instanceof DateTime) {
            $from = DateTimeImmutable::createFromMutable($from);
        }

        if (is_string($to)) {
            $to = new DateTimeImmutable($to);
        } elseif ($to instanceof DateTime) {
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
            // because otherwise we query the items from this date twice.
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
        $nextPageToken = '';

        do {
            $responseData = $this->fetchPaginatedData("$uri&next_page_token=$nextPageToken&page_size=300", $paginatedDataKey);

            if (!array_key_exists($paginatedDataKey, $responseData)) {
                throw new Exception("Could not find key $paginatedDataKey. Response data: "
                    . print_r($aggregatedData,
                        true));
            }

            $aggregatedData = array_merge($aggregatedData, $responseData[$paginatedDataKey]);
            $nextPageToken = $responseData['next_page_token'];
        } while ($nextPageToken != '');

        return $aggregatedData;
    }

    private function fetchPaginatedData(string $uri, string $paginatedDataKey): array
    {
        $response = $this->client->get($uri);
        $bodyContents = $response->getBody()->getContents();
        if ($bodyContents === "") {
            return [
                $paginatedDataKey => [],
                "next_page_token" => ""
            ];
        }
        return json_decode($bodyContents, true);
    }

    private function dateDifferenceIsBiggerThanOneMonth(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        $dateDifference = $from->diff($to);
        $differenceInMonths = $dateDifference->y * 12 + $dateDifference->m;
        return $differenceInMonths > 0;
    }

    /**
     * @param string $accountId
     * @param string $zoomApiClientId
     * @param string $zoomApiClientSecret
     *
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getAccessToken(string $accountId, string $zoomApiClientId, string $zoomApiClientSecret): ?string
    {
        $client = new Client([
            'base_uri' => 'https://zoom.us/',
            'headers' => [
                'Authorization' => "Basic " . base64_encode($zoomApiClientId . ':' . $zoomApiClientSecret),
                'Content-Type' => 'application/json',
            ],
        ]);
        $response = $client->post('oauth/token', [
            'form_params' => [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Could not fetch Zoom access token. Please check the settings for account ID, client ID and client secret, as well as your Zoom app.', 1695040346621);
        }

        $responseBodyAsArray = json_decode($response->getBody()->getContents(), true);

        if (!str_contains($responseBodyAsArray['scope'], 'user:read:admin') || !str_contains($responseBodyAsArray['scope'], 'recording:read:admin') || !str_contains($responseBodyAsArray['scope'], 'meeting:read:admin')) {
            throw new Exception('Please ensure your Zoom app has the following scopes: user:read:admin, recording:read:admin, meeting:read:admin', 1695040540417);
        }

        return $responseBodyAsArray['access_token'];
    }
}
