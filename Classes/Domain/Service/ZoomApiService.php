<?php
declare(strict_types=1);
namespace CodeQ\ZoomApi\Domain\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Exception as CacheException;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use CodeQ\ZoomApi\ZoomApiException;

/**
 * @Flow\Scope("singleton")
 */
class ZoomApiService
{
    private Client $client;

    /**
     * @Flow\Inject
     * @var ZoomApiAccessTokenFactory
     */
    protected $accessTokenFactory;

    /**
     * @var VariableFrontend
     */
    protected $requestsCache;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * @throws ZoomApiException|GuzzleException
     */
    public function initializeObject(): void
    {
        $accessToken = $this->accessTokenFactory->createFromConfiguration();
        $this->client = $this->buildClient($accessToken->accessToken);
    }

    protected function buildClient(string $accessToken): Client
    {
        return (new Client([
            'base_uri' => 'https://api.zoom.us/v2/',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
        ]));
    }

    /**
     * See also https://developers.zoom.us/docs/meeting-sdk/apis/#operation/meetings
     *
     * @param bool $skipCache Omits reading from the cache, to force fetching from the API
     *
     * @return array
     * @throws GuzzleException|ZoomApiException
     */
    public function getUpcomingMeetings(bool $skipCache = false): array
    {
        $cacheEntryIdentifier = 'upcomingMeetings';

        $upcomingMeetings = $this->getCacheEntry($cacheEntryIdentifier);
        if (!$skipCache && $upcomingMeetings !== false) {
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
            $this->systemLogger->error($e->getMessage(), [
                ...LogEnvironment::fromMethodName(__METHOD__),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $upcomingMeetings;
    }

    /**
     * See also https://developers.zoom.us/docs/meeting-sdk/apis/#operation/recordingsList
     *
     * @param DateTime|string $from
     * @param DateTime|string $to
     * @param bool            $skipCache Omits reading from the cache, to force fetching from the API
     *
     * @return array
     * @throws GuzzleException|ZoomApiException|Exception
     */
    public function getRecordings(DateTime|string $from, DateTime|string $to, bool $skipCache = false): array
    {
        list($from, $to) = $this->convertFromAndToAsDateTimeImmutable($from, $to);

        $cacheEntryIdentifier = sprintf('recordings_%s_%s', $from->format('Y-m-d'), $to->format('Y-m-d'));
        $recordings = $this->getCacheEntry($cacheEntryIdentifier);
        if (!$skipCache && $recordings !== false) {
            return $recordings;
        }

        $recordings = $this->fetchDataForDateRange($from, $to);

        try {
            $this->requestsCache->set($cacheEntryIdentifier, $recordings);
        } catch (CacheException $e) {
            // If CacheException is thrown we just go on
            $this->systemLogger->error($e->getMessage(), [
                ...LogEnvironment::fromMethodName(__METHOD__),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $recordings;
    }

    /**
     * @param DateTimeImmutable $from
     * @param DateTimeImmutable $to
     *
     * @return array
     * @throws GuzzleException|ZoomApiException
     */
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

    /**
     * @param        $uri
     * @param string $paginatedDataKey
     *
     * @return array
     * @throws GuzzleException|ZoomApiException
     */
    private function fetchData($uri, string $paginatedDataKey): array
    {
        $aggregatedData = [];
        $nextPageToken = '';

        do {
            $responseData = $this->fetchPaginatedData("$uri&next_page_token=$nextPageToken&page_size=300", $paginatedDataKey);

            if (!array_key_exists($paginatedDataKey, $responseData)) {
                throw new ZoomApiException("Could not find key $paginatedDataKey. Response data: "
                    . print_r($aggregatedData,
                        true));
            }

            $aggregatedData = array_merge($aggregatedData, $responseData[$paginatedDataKey]);
            $nextPageToken = $responseData['next_page_token'];
        } while ($nextPageToken != '');

        return $aggregatedData;
    }

    /**
     * @param string $uri
     * @param string $paginatedDataKey
     *
     * @return array
     * @throws GuzzleException|ZoomApiException
     */
    private function fetchPaginatedData(string $uri, string $paginatedDataKey): array
    {
        $response = $this->client->get($uri);
        if ($response->getStatusCode() !== 200) {
            throw new ZoomApiException(sprintf('Could not fetch Zoom paginated data for data key "%s", returned status "%s"', $paginatedDataKey, $response->getStatusCode()), 1695239983421);
        }
        $bodyContents = $response->getBody()->getContents();
        $bodyContentsArray = json_decode($bodyContents, true);

        if ($bodyContentsArray === null || !array_key_exists($paginatedDataKey, $bodyContentsArray)) {
            throw new ZoomApiException(sprintf('Could not fetch Zoom paginated data for data key "%s", returned empty response', $paginatedDataKey), 1695828849253);
        }

        return $bodyContentsArray;
    }

    private function dateDifferenceIsBiggerThanOneMonth(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        $dateDifference = $from->diff($to);
        $differenceInMonths = $dateDifference->y * 12 + $dateDifference->m;
        return $differenceInMonths > 0;
    }

    /**
     * @param string $entryIdentifier
     *
     * @return array|bool
     */
    private function getCacheEntry(string $entryIdentifier): array|bool
    {
        return $this->requestsCache->get($entryIdentifier);
    }

    /**
     * @param DateTime|string $from
     * @param DateTime|string $to
     *
     * @return DateTimeImmutable[]
     * @throws Exception
     */
    protected function convertFromAndToAsDateTimeImmutable(DateTime|string $from, DateTime|string $to): array
    {
        if (is_string($from)) {
            $from = new DateTimeImmutable($from);
        } else {
            $from = DateTimeImmutable::createFromMutable($from);
        }

        if (is_string($to)) {
            $to = new DateTimeImmutable($to);
        } else {
            $to = DateTimeImmutable::createFromMutable($to);
        }

        if ($from > $to) {
            throw new InvalidArgumentException('The from date must be after the to date');
        }
        return array($from, $to);
    }
}
