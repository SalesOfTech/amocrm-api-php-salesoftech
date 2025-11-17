<?php
declare(strict_types=1);

namespace SalesOfTech\AmoCRM\Storage;

use PDO;
use SalesOfTech\AmoCRM\Model\AccountToken;

final class SqlTokenStorage implements TokenStorageInterface
{
    private PDO $pdo;
    private string $table;
    private string $driver;

    public function __construct(PDO $pdo, string $table = 'amocrm_api_php_salesoftech_tokens')
    {
        $this->pdo   = $pdo;
        $this->table = $table;
        $this->driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->initSchema();
    }

    private function initSchema(): void
    {
        // Универсальная схема под MySQL / PostgreSQL / SQLite
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$this->table} (
    id_company      VARCHAR(64) PRIMARY KEY,
    id_integration  VARCHAR(128) NOT NULL,
    secret          VARCHAR(128) NOT NULL,
    base_domain     VARCHAR(255) NOT NULL,
    access_token    TEXT NOT NULL,
    refresh_token   TEXT NOT NULL,
    expires         INTEGER NOT NULL,
    updated_at      INTEGER NOT NULL
)
SQL;
        $this->pdo->exec($sql);
    }

    public function load(string|int $companyId): ?AccountToken
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id_company = :id LIMIT 1"
        );
        $stmt->execute(['id' => (string)$companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new AccountToken(
            integrationId: (string)$row['id_integration'],
            secret:        (string)$row['secret'],
            baseDomain:    (string)$row['base_domain'],
            accessToken:   (string)$row['access_token'],
            refreshToken:  (string)$row['refresh_token'],
            expiresAt:     (int)$row['expires'],
        );
    }

    public function save(string|int $companyId, AccountToken $token): void
    {
        $params = [
            'id_company'     => (string)$companyId,
            'id_integration' => $token->integrationId,
            'secret'         => $token->secret,
            'base_domain'    => $token->baseDomain,
            'access_token'   => $token->accessToken,
            'refresh_token'  => $token->refreshToken,
            'expires'        => $token->expiresAt,
            'updated_at'     => time(),
        ];

        if ($this->driver === 'mysql') {
            // MySQL: INSERT ... ON DUPLICATE KEY UPDATE
            $sql = <<<SQL
INSERT INTO {$this->table} (
    id_company, id_integration, secret, base_domain,
    access_token, refresh_token, expires, updated_at
) VALUES (
    :id_company, :id_integration, :secret, :base_domain,
    :access_token, :refresh_token, :expires, :updated_at
)
ON DUPLICATE KEY UPDATE
    id_integration = VALUES(id_integration),
    secret         = VALUES(secret),
    base_domain    = VALUES(base_domain),
    access_token   = VALUES(access_token),
    refresh_token  = VALUES(refresh_token),
    expires        = VALUES(expires),
    updated_at     = VALUES(updated_at)
SQL;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        if ($this->driver === 'pgsql' || $this->driver === 'sqlite') {
            // PostgreSQL / SQLite: INSERT ... ON CONFLICT (id_company) DO UPDATE
            $sql = <<<SQL
INSERT INTO {$this->table} (
    id_company, id_integration, secret, base_domain,
    access_token, refresh_token, expires, updated_at
) VALUES (
    :id_company, :id_integration, :secret, :base_domain,
    :access_token, :refresh_token, :expires, :updated_at
)
ON CONFLICT (id_company) DO UPDATE SET
    id_integration = EXCLUDED.id_integration,
    secret         = EXCLUDED.secret,
    base_domain    = EXCLUDED.base_domain,
    access_token   = EXCLUDED.access_token,
    refresh_token  = EXCLUDED.refresh_token,
    expires        = EXCLUDED.expires,
    updated_at     = EXCLUDED.updated_at
SQL;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        // Fallback для других драйверов: UPDATE, если 0 строк — INSERT
        $updateSql = <<<SQL
UPDATE {$this->table} SET
    id_integration = :id_integration,
    secret         = :secret,
    base_domain    = :base_domain,
    access_token   = :access_token,
    refresh_token  = :refresh_token,
    expires        = :expires,
    updated_at     = :updated_at
WHERE id_company = :id_company
SQL;
        $stmt = $this->pdo->prepare($updateSql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            $insertSql = <<<SQL
INSERT INTO {$this->table} (
    id_company, id_integration, secret, base_domain,
    access_token, refresh_token, expires, updated_at
) VALUES (
    :id_company, :id_integration, :secret, :base_domain,
    :access_token, :refresh_token, :expires, :updated_at
)
SQL;
            $insertStmt = $this->pdo->prepare($insertSql);
            $insertStmt->execute($params);
        }
    }

    public function delete(string|int $companyId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE id_company = :id"
        );
        $stmt->execute(['id' => (string)$companyId]);
    }
}
