<?php
declare(strict_types=1);

namespace Salesoftech\AmoCRM\Model;

final class AccountToken
{
    public function __construct(
        public readonly string $integrationId,
        public readonly string $secret,
        public readonly string $baseDomain,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int    $expiresAt
    ) {}
}
