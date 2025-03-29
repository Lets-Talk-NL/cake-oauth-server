<?php

namespace OAuthServer\Auth;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Log;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use OAuthServer\Exception\Exception;
use OAuthServer\OAuthServerPlugin;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The CakePHP OAuth 2.0 Authenticate object
 *
 * This 'Authenticate' object is the protection layer
 * of the accessible resources (resource server section of the application)
 * that are made available to the access token
 */
class OAuthAuthenticate
{
    /**
     * OAuth 2.0 resource server object
     */
    protected ResourceServer $_resourceServer;

    /**
     * Exception that was thrown by OAuth 2.0 server
     */
    protected ?OAuthServerException $_exception;

    /**
     * Attributes the resource server adds upon authenticating the request
     * that identify the request to a user or client
     *
     * @var string[]
     */
    protected array $userIdentifiableAttributes = [
        'oauth_access_token_id',
        'oauth_client_id',
        'oauth_user_id',
        'oauth_scopes',
    ];

    public function __construct()
    {
        $this->_resourceServer = OAuthServerPlugin::instance()->getResourceServer();
    }

    public function authenticate(ServerRequestInterface $request)
    {
        return $this->getUser($request);
    }

    /**
     * @throws Exception
     */
    public function getUser(ServerRequestInterface $request)
    {
        if (!$request = $this->getValidatedRequestWithAuthAttributes($request)) {
            return false;
        }
        $user = $this->getUserIdentifiableAttributesFromRequest($request);
        if (!$this->dispatchGetUserEvent($request, $user)) {
            return false;
        }
        return $user;
    }

    /**
     * Validate and add authentication attributes to the given request.
     * Will set exception to $this->_exception if thrown from validation of request
     *
     * @return ServerRequestInterface|null Will return modified request or null if failed to validate
     */
    public function getValidatedRequestWithAuthAttributes(ServerRequestInterface $request): ?ServerRequestInterface
    {
        try {
            // modified request
            $request = $this->_resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            Log::error($e);
            $this->_exception = $e;
            return null;
        }
        return $request;
    }

    /**
     * Extract request attributes to return as an identified OAuth 2.0 user
     *
     * @throws Exception
     * @return array e.g. ['oauth_client_id' => '123', ...]
     */
    public function getUserIdentifiableAttributesFromRequest(ServerRequestInterface $request): array
    {
        $user = array_intersect_key($request->getAttributes(), array_flip($this->userIdentifiableAttributes));
        if (empty($user)) {
            throw new Exception('Resource server is always expected to fulfill user attributes at this point');
        }
        return $user;
    }

    /**
     * Throw event for any required user data hooks/mutations
     *
     * @return bool False if stopped
     */
    public function dispatchGetUserEvent(ServerRequestInterface $request, array &$user): bool
    {
        $event = new Event('OAuthServer.getUser', $request, $user);
        EventManager::instance()->dispatch($event);
        if (is_array($event->getResult())) {
            $user = $event->getResult();
        }
        if ($event->isStopped()) {
            $msg = 'event %s was stopped for user %s';
            Log::warning(sprintf($msg, $event->getName(), json_encode($user)));
            return false;
        }
        return true;
    }

    /**
     * Return exception that was thrown by OAuth 2.0 server
     */
    public function getException(): ?OAuthServerException
    {
        return $this->_exception;
    }
}
