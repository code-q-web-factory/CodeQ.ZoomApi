<?php

namespace CodeQ\ZoomApi\Domain\Service;

use CodeQ\ZoomApi\Domain\Model\ZoomApiAccessToken;
use CodeQ\ZoomApi\ZoomApiException;
use GuzzleHttp\Client;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class ZoomApiAccessTokenFactory
{
    private Client $client;

    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected array $settings;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $systemLogger;

    /**
     * @return void
     * @throws ZoomApiException
     */
    public function initializeObject(): void
    {
        $zoomApiClientId = $this->settings['auth']['clientId'];
        $zoomApiClientSecret = $this->settings['auth']['clientSecret'];
        if (!$zoomApiClientId || !$zoomApiClientSecret) {
            throw new ZoomApiException('Please set a Zoom Account ID, Client ID and Secret for CodeQ.ZoomApi to be able to authenticate.', 1695830249149);
        }
        $this->client = $this->buildClient($zoomApiClientId, $zoomApiClientSecret);
    }

    /**
     * @return ZoomApiAccessToken
     * @throws ZoomApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createFromConfiguration(): ZoomApiAccessToken
    {
        $zoomApiAccountId = $this->settings['auth']['accountId'];
        if (!$zoomApiAccountId) {
            throw new ZoomApiException('Please set a Zoom Account ID for CodeQ.ZoomApi to be able to authenticate.', 1695904285296);
        }
        $response = $this->client->post('oauth/token', [
            'form_params' => [
                'grant_type' => 'account_credentials',
                'account_id' => $zoomApiAccountId
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new ZoomApiException('Could not fetch Zoom access token. Please check the settings for account ID, client ID and client secret, as well as your Zoom app.', 1695040346621);
        }

        $responseBodyAsArray = json_decode($response->getBody()->getContents(), true);

        if (!str_contains($responseBodyAsArray['scope'], 'user:read:admin') || !str_contains($responseBodyAsArray['scope'], 'recording:read:admin') || !str_contains($responseBodyAsArray['scope'], 'meeting:read:admin')) {
            throw new ZoomApiException('Please ensure your Zoom app has the following scopes: user:read:admin, recording:read:admin, meeting:read:admin', 1695040540417);
        }

        return new ZoomApiAccessToken(
            $responseBodyAsArray['access_token'],
            explode(',', $responseBodyAsArray['scope']));
    }

    /**
     * @param string $zoomApiClientId
     * @param string $zoomApiClientSecret
     *
     * @return Client
     */
    protected function buildClient(string $zoomApiClientId, string $zoomApiClientSecret): Client
    {
        return (new Client([
            'base_uri' => 'https://zoom.us/',
            'headers' => [
                'Authorization' => "Basic " . base64_encode($zoomApiClientId . ':' . $zoomApiClientSecret),
                'Content-Type' => 'application/json',
            ],
        ]));
    }
}
