<?php

namespace CodeQ\ZoomApi\Tests\Unit;

use CodeQ\ZoomApi\Domain\Service\ZoomApiService;
use CodeQ\ZoomApi\ZoomApiException;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Mockery;
use Neos\Cache\Exception;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ZoomApiServiceTest extends UnitTestCase
{
    protected MockObject $cacheMock;
    private LoggerInterface $systemLoggerMock;

    protected function setUp(): void
    {
        $this->cacheMock = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $this->systemLoggerMock = Mockery::mock(LoggerInterface::class);
    }

    /** @test */
    public function it_can_fetch_data()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);



        $this->cacheMock->expects($this->once())
            ->method('get')
            ->willReturn(false);

        $service = $this->getService($client);
        $service->initializeObject();


        $result = $service->getUpcomingMeetings();


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test */
    public function getRecordingsWithStringDatesAndValidCacheReturnsCachedData()
    {
        $client = new Client();

        $this->cacheMock = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->willReturn(['cached123']);

        $service = $this->getService($client);
        $service->initializeObject();


        $result = $service->getRecordings('2023-01-01', '2023-01-02');

        $this->assertEquals(['cached123'], $result);
    }

    /** @test */
    public function getRecordingsWithStringDatesEmptyCacheReturnsFetchedData()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);


        $result = $service->getRecordings('2023-01-01', '2023-01-02');


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test */
    public function getRecordingsWithObjectDatesEmptyCacheReturnsFetchedData()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);


        $result = $service->getRecordings(new DateTime('2023-01-01'), new DateTime('2023-01-02'));


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test */
    public function getRecordingsWithObjectDatesAndCacheExceptionReturnsFetchedData()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => ''])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
                new Response(200, [], json_encode(['meetings' => ['meeting3'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting4'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);
        $this->cacheMock->method('set')->willThrowException(new Exception());
        $this->systemLoggerMock->shouldReceive('error')->once();

        $result = $service->getRecordings(new DateTime('2023-01-01'), new DateTime('2023-03-01'));


        $this->assertEquals(['meeting1', 'meeting2', 'meeting3', 'meeting4'], $result);
    }

    public function getRecordingsThrowsExceptionIfFromIsBiggerThanTo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The from date must be after the to date');

        $client = new Client();

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);


        $service->getRecordings(new DateTime('2023-01-02'), new DateTime('2023-01-01'));
    }

    /** @test */
    public function upcomingMeetingsReturnsValidCachedData()
    {
        $client = new Client();

        $this->cacheMock = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $this->cacheMock->expects($this->once())
            ->method('get')
            ->willReturn(['cached123']);

        $service = $this->getService($client);
        $service->initializeObject();


        $result = $service->getUpcomingMeetings();

        $this->assertEquals(['cached123'], $result);
    }

    /** @test */
    public function upcomingMeetingsWithEmptyCacheReturnsNewData()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);


        $result = $service->getUpcomingMeetings(true);


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test */
    public function upcomingMeetingsWithCacheExceptionReturnsNewData()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);
        $this->cacheMock->method('set')->willThrowException(new Exception());
        $this->systemLoggerMock->shouldReceive('error')->once();


        $result = $service->getUpcomingMeetings(true);


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test */
    public function getUpcomingMeetingsWithEmptyResponseReturnsEmptyResponse()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], ''),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();
        $this->cacheMock->method('get')->willReturn(false);


        $result = $service->getUpcomingMeetings(true);

        $this->assertEquals([], $result);
    }

    /**
     * @param  \GuzzleHttp\Client  $client
     * @return MockObject
     */
    private function getService(Client $client): MockObject
    {
        $service = $this->getAccessibleMock(ZoomApiService::class, ['buildClient', 'getAccessToken'], [], '', false);
        $service->method('buildClient')->willReturn($client);
        $service->method('getAccessToken')->willReturn('1234567890');
        $this->inject(
            $service,
            'settings',
            ['auth' => ['accountId' => '1234567890', 'clientId' => '1234567890', 'clientSecret' => '1234567890']]
        );
        $this->inject($service, 'requestsCache', $this->cacheMock);
        $this->inject($service, 'systemLogger', $this->systemLoggerMock);

        return $service;
    }
}
