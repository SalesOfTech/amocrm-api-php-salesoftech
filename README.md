# SalesOfTech AmoCRM API Wrapper

**EN:** Composer library for multi-tenant work with AmoCRM/Kommo (OAuth2) on top of `amocrm/amocrm-api-php`.  
**RU:** Composer-библиотека для мульти-клиентской работы с AmoCRM/Kommo (OAuth2) поверх `amocrm/amocrm-api-php`.

- Multi-company support via `companyId`
- Automatic token refresh (on 401 / proactively 1 hour before expiration)
- Pluggable token storage (SQL / file / custom)
- No changes to `amocrm/amocrm-api-php` (vendor stays clean)

---

## Installation / Установка

```bash
composer require salesoftech/amocrm-api-php-salesoftech
```

This will also install `amocrm/amocrm-api-php` and `league/oauth2-client`.  
При этом будут установлены `amocrm/amocrm-api-php` и `league/oauth2-client`.

---

## Namespaces / Пространства имён

Root namespace: `SalesOfTech\AmoCRM`

- `SalesOfTech\AmoCRM\SoftAmoClient`
- `SalesOfTech\AmoCRM\Model\AccountToken`
- `SalesOfTech\AmoCRM\Storage\TokenStorageInterface`
- `SalesOfTech\AmoCRM\Storage\SqlTokenStorage`
- `SalesOfTech\AmoCRM\Storage\FileTokenStorage`

---

## Quick Start (EN)

### 1. SQL storage (MySQL/PostgreSQL/SQLite)

```php
use SalesOfTech\AmoCRM\SoftAmoClient;
use SalesOfTech\AmoCRM\Storage\SqlTokenStorage;

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=soft;charset=utf8mb4',
    'user',
    'pass',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Storage will automatically create table `amocrm_api_php_salesoftech_tokens` if it does not exist
$storage = new SqlTokenStorage($pdo);

$amo = (new SoftAmoClient($storage))
    ->setRedirectUri('https://example.com/amocrm/oauth/callback');

// Get client for a specific company (tenant)
$client = $amo->getClient(1);

// Use standard amocrm/amocrm-api-php services
$leadsService = $client->leads();
$leads        = $leadsService->get();
```

### 2. Initial authorization (by `code`)

```php
$client = $amo->authorize(
    companyId:    1,
    integrationId:'CLIENT_ID',
    secret:       'CLIENT_SECRET',
    baseDomain:   'example.amocrm.ru',
    authCode:     $_GET['code']
);
```

### 3. File storage (Not recommended :D better to use it for testing only.)

```php
$storage = new FileTokenStorage(__DIR__ . '/amocrm_tokens');
$amo = (new SoftAmoClient($storage))->setRedirectUri('https://example.com/callback');
$client = $amo->getClient(1);
```

### 4. Multi-tenant usage

```php
$client1 = $amo->getClient(1);
$client3 = $amo->getClient(3);
$client7  = $amo->getClient(7);
```

### 5. Custom storage example (Redis)

(… full code omitted for brevity, original answer contains full example …)

---

## Быстрый старт (RU)

### 1. SQL-хранилище

```php
$storage = new SqlTokenStorage($pdo);
$amo = (new SoftAmoClient($storage))->setRedirectUri('https://example.com/callback');
$client = $amo->getClient(1);
```

### 2. Первичная авторизация

```php
$client = $amo->authorize(
    companyId: 1,
    integrationId:'CLIENT_ID',
    secret:'CLIENT_SECRET',
    baseDomain:'example.amocrm.ru',
    authCode:$_GET['code']
);
```

### 3. Файловое хранилище (Не рекомендуется :D лучше использовать только для тестов)

```php
$storage = new FileTokenStorage(__DIR__.'/tokens');
$amo = (new SoftAmoClient($storage))->setRedirectUri('https://example.com/callback');
$client = $amo->getClient(1);
```

### 4. Мульти-клиентская работа

```php
$client1 = $amo->getClient(1);
$client3 = $amo->getClient(3);
$client7  = $amo->getClient(7);
```

### 5. Свое хранилище

Реализуй интерфейс `TokenStorageInterface` и передай в `SoftAmoClient`.

---

## SQL Table Structure

```sql
CREATE TABLE amocrm_api_php_salesoftech_tokens (
    id_company      VARCHAR(64) PRIMARY KEY,
    id_integration  VARCHAR(128) NOT NULL,
    secret          VARCHAR(128) NOT NULL,
    base_domain     VARCHAR(255) NOT NULL,
    access_token    TEXT NOT NULL,
    refresh_token   TEXT NOT NULL,
    expires         INTEGER NOT NULL,
    updated_at      INTEGER NOT NULL
);
```

---

## License

MIT
