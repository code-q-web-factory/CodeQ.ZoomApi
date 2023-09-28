<?php

namespace CodeQ\ZoomApi\Domain\Model;

class ZoomApiAccessToken
{
    public readonly string $accessToken;
    public readonly array $scope;

    /**
     * @param string $accessToken
     * @param array  $scope
     */
    public function __construct(string $accessToken, array $scope)
    {
        $this->accessToken = $accessToken;
        $this->scope = $scope;
    }
}
