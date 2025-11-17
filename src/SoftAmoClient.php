<?php
declare(strict_types=1);

namespace Salesoftech\AmoCRM;

use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Salesoftech\AmoCRM\Model\AccountToken;
use Salesoftech\AmoCRM\Storage\TokenStorageInterface;

final class SoftAmoClient
{
    private TokenStorageInterface $storage;
    private string $redirectUri = '';
    private $logger = null;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function setRedirectUri(string $uri): self
    {
        $this->redirectUri = $uri;
        return $this;
    }

    public function setLogger(?callable $logger): self
    {
        // callable(string $message, array $context): void
        $this->logger = $logger;
        return $this;
    }

    /**
     * Создать клиента для уже авторизованной компании.
     */
    public function getClient(string|int $companyId): AmoCRMApiClient
    {
        $token = $this->storage->load($companyId);
        if (!$token) {
            throw new \RuntimeException("No amoCRM token for company {$companyId}");
        }

        if ($this->redirectUri === '') {
            throw new \RuntimeException("Redirect URI must be set via setRedirectUri()");
        }

        $client = new AmoCRMApiClient(
            $token->integrationId,
            $token->secret,
            $this->redirectUri
        );

        $accessToken = new AccessToken([
            'access_token'  => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires'       => $token->expiresAt,
            'baseDomain'    => $token->baseDomain,
        ]);

        $client
            ->setAccessToken($accessToken)
            ->setAccountBaseDomain($token->baseDomain)
            ->onAccessTokenRefresh(function (AccessTokenInterface $newToken, string $newBaseDomain)
                    use ($companyId): void {

                $dto = new AccountToken(
                    integrationId: $newToken->getValues()['client_id'] ?? '',
                    secret:        '',
                    baseDomain:    $newBaseDomain,
                    accessToken:   $newToken->getToken(),
                    refreshToken:  $newToken->getRefreshToken(),
                    expiresAt:     (int)$newToken->getExpires(),
                );

                $existing = $this->storage->load($companyId);
                if ($existing) {
                    $dto = new AccountToken(
                        integrationId: $existing->integrationId,
                        secret:        $existing->secret,
                        baseDomain:    $newBaseDomain,
                        accessToken:   $dto->accessToken,
                        refreshToken:  $dto->refreshToken,
                        expiresAt:     $dto->expiresAt,
                    );
                }

                $this->storage->save($companyId, $dto);
                $this->log('access_token_refreshed', ['company_id' => $companyId]);
            });

        // проактивное обновление за час до истечения
        $now = time();
        if ($token->expiresAt && ($token->expiresAt - $now) <= 3600) {
            $oauthClient = $client->getOAuthClient();
            $oauthClient->setBaseDomain($token->baseDomain);

            $newToken = $oauthClient->getAccessTokenByRefreshToken($accessToken);

            $updated = new AccountToken(
                integrationId: $token->integrationId,
                secret:        $token->secret,
                baseDomain:    $newToken->getValues()['baseDomain'] ?? $token->baseDomain,
                accessToken:   $newToken->getToken(),
                refreshToken:  $newToken->getRefreshToken(),
                expiresAt:     (int)$newToken->getExpires(),
            );
            $this->storage->save($companyId, $updated);

            $client
                ->setAccessToken($newToken)
                ->setAccountBaseDomain($updated->baseDomain);

            $this->log('access_token_proactively_refreshed', ['company_id' => $companyId]);
        }

        return $client;
    }

    /**
     * Первичная авторизация по коду (code) и сохранение токена.
     */
    public function authorize(
        string|int $companyId,
        string $integrationId,
        string $secret,
        string $baseDomain,
        string $authCode
    ): AmoCRMApiClient {
        if ($this->redirectUri === '') {
            throw new \RuntimeException("Redirect URI must be set via setRedirectUri()");
        }

        $client = new AmoCRMApiClient(
            $integrationId,
            $secret,
            $this->redirectUri
        );

        $client->setAccountBaseDomain($baseDomain);

        $oauthClient = $client->getOAuthClient();
        $oauthClient->setBaseDomain($baseDomain);

        $accessToken = $oauthClient->getAccessTokenByCode($authCode);

        $tokenDto = new AccountToken(
            integrationId: $integrationId,
            secret:        $secret,
            baseDomain:    $baseDomain,
            accessToken:   $accessToken->getToken(),
            refreshToken:  $accessToken->getRefreshToken(),
            expiresAt:     (int)$accessToken->getExpires(),
        );

        $this->storage->save($companyId, $tokenDto);

        $client->setAccessToken($accessToken);

        $this->log('authorized', ['company_id' => $companyId]);

        return $client;
    }

    private function log(string $msg, array $ctx = []): void
    {
        if ($this->logger) {
            ($this->logger)($msg, $ctx);
        }
    }
}
