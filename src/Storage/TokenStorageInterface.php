<?php
declare(strict_types=1);

namespace Salesoftech\AmoCRM\Storage;

use Salesoftech\AmoCRM\Model\AccountToken;

interface TokenStorageInterface
{
    public function load(string|int $companyId): ?AccountToken;

    public function save(string|int $companyId, AccountToken $token): void;

    public function delete(string|int $companyId): void;
}
