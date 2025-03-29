<?php

namespace OAuthServer\Lib\Data\Request;

/**
 * OAuth 2.0 resource user
 */
class ResourceUser
{
    protected string $accessTokenId;

    protected string $clientId;

    protected string $userId;

    protected array $scopes;

    public function __construct(string $accessTokenId, string $clientId, string $userId, array $scopes)
    {
        $this->accessTokenId = $accessTokenId;
        $this->clientId      = $clientId;
        $this->userId        = $userId;
        $this->scopes        = $scopes;
    }

    public function getAccessTokenId(): string
    {
        return $this->accessTokenId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}
