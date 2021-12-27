<?php
declare(strict_types=1);
namespace CodeQ\ZoomApi\Eel;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use CodeQ\ZoomApi\Domain\Service\ZoomApiService;
use Neos\Flow\Annotations as Flow;
use Neos\Eel\ProtectedContextAwareInterface;

class ZoomApiHelper implements ProtectedContextAwareInterface {

    /**
     * @Flow\Inject
     * @var ZoomApiService
     */
    protected $zoomApiService;

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
        return $this->zoomApiService->getRecordings($from, $to);
    }

    /**
     * See also https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetings
     * 
     * @return array
     * @throws Exception
     */
    public function getUpcomingMeetings(): array
    {
        return $this->zoomApiService->getUpcomingMeetings();
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
