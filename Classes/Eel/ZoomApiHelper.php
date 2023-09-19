<?php
declare(strict_types=1);
namespace CodeQ\ZoomApi\Eel;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use CodeQ\ZoomApi\Domain\Service\ZoomApiService;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;

class ZoomApiHelper implements ProtectedContextAwareInterface {

    /**
     * @Flow\Inject
     * @var ZoomApiService
     */
    protected $zoomApiService;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * See also https://marketplace.zoom.us/docs/api-reference/zoom-api/cloud-recording/recordingget
     *
     * @param DateTime|string $from
     * @param DateTime|string $to
     *
     * @return array|false
     */
    public function getRecordings(DateTime|string $from, DateTime|string $to): array|false
    {
        try {
            return $this->zoomApiService->getRecordings($from, $to);
        } catch (Exception $e) {
            $this->systemLogger->error(sprintf('Could not get Zoom recordings, exception with code "%s" thrown: "%s"', $e->getCode(), $e->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
            return false;
        }
    }

    /**
     * See also https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetings
     *
     * @return array|false
     */
    public function getUpcomingMeetings(): array|false
    {
        try {
            return $this->zoomApiService->getUpcomingMeetings();
        } catch (Exception $e) {
            $this->systemLogger->error(sprintf('Could not get upcoming Zoom meetings, exception with code "%s" thrown: "%s"', $e->getCode(), $e->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
            return false;
        }
    }

    /**
     * All methods are considered safe, i.e. can be executed from within Eel
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName) {
        return true;
    }
}
