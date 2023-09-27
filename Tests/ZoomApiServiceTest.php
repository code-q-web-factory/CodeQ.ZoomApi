<?php

namespace CodeQ\ZoomApi\Tests;

use CodeQ\ZoomApi\Domain\Service\ZoomApiService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Tests\UnitTestCase;

use function json_encode;

class ZoomApiServiceTest extends UnitTestCase
{
    /** @test * */
    public function it_can_fetch_data()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], json_encode(['meetings' => ['meeting2'], 'next_page_token' => ''])),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);


        $cacheMock = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturn(false);

        $service = $this->getService($client, $cacheMock);
        $service->initializeObject();


        $result = $service->getUpcomingMeetings();


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test * */
    public function it_can_return_cached_data()
    {
        $client = new Client();

        $cacheMock = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturn(['cached123']);

        $service = $this->getService($client, $cacheMock);
        $service->initializeObject();


        $result = $service->getUpcomingMeetings();

        $this->assertEquals(['cached123'], $result);
    }

    /** @test * */
    public function skipping_cache_returns_new_data()
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


        $result = $service->getUpcomingMeetings(true);


        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /** @test * */
    public function tmp()
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
//                new Response(200, [], json_encode(['meetings' => ['meeting1'], 'next_page_token' => '1234567890'])),
                new Response(200, [], ''),
            ])
        );
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);
        $service->initializeObject();


        $result = $service->getUpcomingMeetings(true);

        $this->assertEquals(['meeting1', 'meeting2'], $result);
    }

    /**
     * @param  \GuzzleHttp\Client  $client
     * @param $cacheMock
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getService(Client $client, $cacheMock = null): \PHPUnit\Framework\MockObject\MockObject
    {
        $service = $this->getAccessibleMock(ZoomApiService::class, ['buildClient', 'getAccessToken'], [], '', false);
        $service->method('buildClient')->willReturn($client);
        $service->method('getAccessToken')->willReturn('1234567890');
        $this->inject(
            $service,
            'settings',
            ['auth' => ['accountId' => '1234567890', 'clientId' => '1234567890', 'clientSecret' => '1234567890']]
        );
        if ($cacheMock) {
            $this->inject($service, 'requestsCache', $cacheMock);
        }

        return $service;
    }
}
