<?php

namespace CodeQ\ZoomApi\Domain\Model;

class ZoomApiAccessToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly array $scope
    ) {}
}
