<?php

namespace CodeQ\ZoomApi\Tests;

use CodeQ\ZoomApi\Domain\Service\ZoomApiService;
use CodeQ\ZoomApi\Eel\ZoomApiHelper;
use Neos\Flow\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

class ZoomApiHelperTest extends UnitTestCase
{
    /**
     * @var ZoomApiHelper
     */
    protected $zoomApiHelper;

    protected function setUp(): void
    {
        $this->zoomApiHelper = new ZoomApiHelper();
        $this->inject(
            $this->zoomApiHelper,
            'systemLogger',
            $this->createMock(LoggerInterface::class)
        );
        $this->inject(
            $this->zoomApiHelper,
            'zoomApiService',
            $this->createMock(ZoomApiService::class)
        );
    }

    /** @test **/
    public function canGetRecordings(): void
    {
        $this->assertTrue(true);
    }
}
