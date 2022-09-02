<?php

namespace OAuthServer\Lib\Data\Request;

/**
 * OAuth 2.0 resource user
 */
class ResourceUser
{
    /**
     * @var string
     */
    protected string $accessTokenId;

    /**
     * @var string
     */
    protected string $clientId;

    /**
     * @var string
     */
    protected string $userId;

    /**
     * @var array
     */
    protected array $scopes;

    /**
     * @param string $accessTokenId
     * @param string $clientId
     * @param string $userId
     * @param array  $scopes
     */
    public function __construct(string $accessTokenId, string $clientId, string $userId, array $scopes)
    {
        $this->accessTokenId = $accessTokenId;
        $this->clientId      = $clientId;
        $this->userId        = $userId;
        $this->scopes        = $scopes;
    }

    /**
     * @return string
     */
    public function getAccessTokenId(): string
    {
        return $this->accessTokenId;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return string
     */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @return array
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}