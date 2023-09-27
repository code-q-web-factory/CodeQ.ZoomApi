<?php

namespace CodeQ\ZoomApi\Tests\Unit;

use CodeQ\ZoomApi\Domain\Service\ZoomApiService;
use CodeQ\ZoomApi\Eel\ZoomApiHelper;
use Mockery;
use Neos\Flow\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

class ZoomApiHelperTest extends UnitTestCase
{
    /**
     * @var ZoomApiHelper
     */
    protected $zoomApiHelper;
    private ZoomApiService $zoomApiServiceMock;
    private LoggerInterface $systemLoggerMock;

    protected function setUp(): void
    {

        $this->zoomApiHelper = new ZoomApiHelper();

        $this->systemLoggerMock = Mockery::mock(LoggerInterface::class);
        $this->inject(
            $this->zoomApiHelper,
            'systemLogger',
            $this->systemLoggerMock
        );

        $this->zoomApiServiceMock = $this->createMock(ZoomApiService::class);
        $this->inject(
            $this->zoomApiHelper,
            'zoomApiService',
            $this->zoomApiServiceMock
        );
    }

    /** @test * */
    public function canGetRecordings(): void
    {
        $this->zoomApiServiceMock
            ->expects($this->once())
            ->method('getRecordings')
            ->with('2021-01-01', '2021-01-02')
            ->willReturn([]);


        $result = $this->zoomApiHelper->getRecordings('2021-01-01', '2021-01-02');


        $this->assertSame([], $result);
    }

    /** @test * */
    public function canHandleGetRecordingsException(): void
    {
        $this->zoomApiServiceMock
            ->expects($this->once())
            ->method('getRecordings')
            ->with('2021-01-01', '2021-01-02')
            ->willThrowException(new \Exception('Test Exception'));

        $this->systemLoggerMock->shouldReceive('error')->once();


        $result = $this->zoomApiHelper->getRecordings('2021-01-01', '2021-01-02');


        $this->assertFalse($result);
    }
}
