<?php

namespace CodeQ\ZoomApi\Tests\Unit;

use CodeQ\ZoomApi\Domain\Model\ZoomApiAccessToken;
use CodeQ\ZoomApi\Domain\Service\ZoomApiAccessTokenFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ZoomApiAccessTokenFactoryTest extends UnitTestCase
{
    /** @test */
    public function createFromConfigurationWillReturnAccessToken(): void
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(200, [], json_encode(['access_token' => '1234567890', 'scope' => 'user:read:admin, recording:read:admin, meeting:read:admin']))
            ])
        );
        $factoryMock = $this->getFactory($handlerStack);
        $accessToken = $factoryMock->createFromConfiguration();
        $this->assertInstanceOf(ZoomApiAccessToken::class, $accessToken);
    }

    private function getFactory(HandlerStack $handlerStack = null): ZoomApiAccessTokenFactory|MockObject
    {
        $factory = $this->getAccessibleMock(ZoomApiAccessTokenFactory::class, ['buildClient'], [], '', false);
        $client = new Client(['handler' => $handlerStack]);
        $factory->method('buildClient')->willReturn($client);
        $this->inject(
            $factory,
            'settings',
            ['auth' => ['accountId' => '1234567890', 'clientId' => '1234567890', 'clientSecret' => '1234567890']]
        );
        $factory->initializeObject();
        return $factory;
    }
}
