<?php
declare(strict_types=1);

namespace Salesoftech\AmoCRM\Storage;

use Salesoftech\AmoCRM\Model\AccountToken;

final class FileTokenStorage implements TokenStorageInterface
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    private function path(string|int $companyId): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'company_' . $companyId . '.json';
    }

    public function load(string|int $companyId): ?AccountToken
    {
        $path = $this->path($companyId);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }

        return new AccountToken(
            integrationId: (string)$data['id_integration'],
            secret:        (string)$data['secret'],
            baseDomain:    (string)$data['base_domain'],
            accessToken:   (string)$data['access_token'],
            refreshToken:  (string)$data['refresh_token'],
            expiresAt:     (int)$data['expires'],
        );
    }

    public function save(string|int $companyId, AccountToken $token): void
    {
        $path = $this->path($companyId);
        $data = [
            'id_integration' => $token->integrationId,
            'secret'         => $token->secret,
            'base_domain'    => $token->baseDomain,
            'access_token'   => $token->accessToken,
            'refresh_token'  => $token->refreshToken,
            'expires'        => $token->expiresAt,
            'updated_at'     => time(),
        ];
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }

    public function delete(string|int $companyId): void
    {
        $path = $this->path($companyId);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
