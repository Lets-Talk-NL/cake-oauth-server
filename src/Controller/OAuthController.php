<?php

namespace OAuthServer\Controller;

use AllowDynamicProperties;
use App\Controller\AppController;
use Authentication\Controller\Component\AuthenticationComponent;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Http\StreamFactory;
use Exception;
use Exception as PhpException;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuthServer\Controller\Component\OAuthResourcesComponent;
use OAuthServer\Controller\Component\OAuthServerComponent;
use OAuthServer\Exception\ServiceNotAvailableException;
use OAuthServer\Model\Table\Interfaces\CheckTokenScopesInterface;
use OAuthServer\OAuthServerPlugin;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * OAuth 2.0 process controller
 *
 * Uses AppController alias in the current namespace
 * from bootstrap and config OAuthServer.appController
 *
 * @property OAuthServerComponent    $OAuthServer
 * @property OAuthResourcesComponent $OAuthResources
 * @mixin Controller
 */
#[AllowDynamicProperties]
class OAuthController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('OAuthServer.OAuthServer');
        $this->loadComponent('OAuthServer.OAuthResources');
        $this->OAuthResources->allow();
        $this->OAuthResources->deny('userInfo');
    }

    public function beforeFilter(EventInterface $event): ?Response
    {
        parent::beforeFilter($event);
        if (!$this->components()->has('Authentication')) {
            throw new RuntimeException('OAuthServer requires Authentication component to be loaded and properly configured');
        }
        /** @var AuthenticationComponent $authenticationComponent */
        $authenticationComponent = $this->components()->get('Authentication');
        if ($this->components()->has('Authentication')) {
            $authenticationComponent->setConfig('requireIdentity', true);
            $authenticationComponent->allowUnauthenticated(['oauth', 'accessToken', 'status', 'userInfo']);
        }

        // The UserInfo Endpoint SHOULD support the use of Cross Origin Resource Sharing (CORS) [CORS]
        // and or other methods as appropriate to enable Java Script Clients to access the endpoint.
        if ($this->getRequest()->getParam('action') === 'userInfo') {
            $this->setResponse($this->getResponse()->withHeader('Access-Control-Allow-Origin', '*'));
        }

        return null;
    }

    /**
     * Index action handler
     *
     * @throws UnexpectedValueException
     * @throws NotFoundException
     */
    public function index(): Response
    {
        if (Configure::read('OAuthServer.serviceDisabled')) {
            throw new ServiceNotAvailableException();
        }
        if (Configure::read('OAuthServer.indexRedirectDisabled')) {
            throw new NotFoundException();
        }
        return $this->redirect([
            'action' => 'authorize',
            '_ext'   => $this->getRequest()->getParam('_ext'),
            '?'      => $this->getRequest()->getQuery(),
        ], 301);
    }

    /**
     * Authorize action handler
     *
     * @link https://www.rfc-editor.org/rfc/rfc6749.html#page-18
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @TODO improve exception handling?
     */
    public function authorize(): ResponseInterface
    {
        if (Configure::read('OAuthServer.serviceDisabled')) {
            throw new ServiceNotAvailableException();
        }

        // Start authorization request
        $authServer  = $this->OAuthServer->getAuthorizationServer();
        $authRequest = $authServer->validateAuthorizationRequest($this->getRequest());
        $clientId    = $authRequest->getClient()->getIdentifier();

        // 'redirect_uri' is considered an optional argument but grant implementations dont always
        // seem to implement the fallback from the client. Set it anyway here
        if ($authRequest->getRedirectUri() === null && ($authRequest->getClient() && $authRequest->getClient()->getRedirectUri())) {
            $authRequest->setRedirectUri($authRequest->getClient()->getRedirectUri());
        }

        // Once the user has logged in set the user on the AuthorizationRequest
        if ($user = $this->OAuthServer->getSessionUserData()) {
            $authRequest->setUser($user);
        }

        $eventManager = OAuthServerPlugin::instance()->getEventManager();
        $eventManager->dispatch(new Event('OAuthServer.beforeAuthorize', $this));

        try {
            // immediately approve authorization request if already has active tokens
            if ($this->OAuthServer->hasActiveAccessTokens($clientId, $user->getIdentifier())) {
                $authRequest->setAuthorizationApproved(true);
                $eventManager->dispatch(new Event('OAuthServer.afterAuthorize', $this));
                // redirect
                return $authServer->completeAuthorizationRequest($authRequest, $this->getResponse());
            }

            // handle form posted UI confirmation of client authorization approval
            if ($this->getRequest()->is('post')) {
                $authRequest->setAuthorizationApproved(false);
                if ($this->getRequest()->getData('authorization') === 'Approve') {
                    $authRequest->setAuthorizationApproved(true);
                    $eventManager->dispatch(new Event('OAuthServer.afterAuthorize', $this));
                } else {
                    $eventManager->dispatch(new Event('OAuthServer.afterDeny', $this));
                }
                // redirect
                return $authServer->completeAuthorizationRequest($authRequest, $this->getResponse());
            }
        } catch (OAuthServerException $exception) {
            // @TODO this is a JSON response ..?
            return $exception->generateHttpResponse($this->getResponse());
        } catch (Exception $exception) {
            $body = (new StreamFactory())->createStream($exception->getMessage());
            // @TODO this is a blank page with an exception message?
            return $this->getResponse()->withStatus(500)->withBody($body);
        }

        $this->set('authRequest', $authRequest);
        return $this->render();
    }

    /**
     * Access token action handler
     *
     * @link https://www.rfc-editor.org/rfc/rfc6749.html#page-23
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @TODO improve exception handling?
     */
    public function accessToken(): ResponseInterface
    {
        if (Configure::read('OAuthServer.serviceDisabled')) {
            throw new ServiceNotAvailableException();
        }
        $authServer = $this->OAuthServer->getAuthorizationServer();
        $request    = $this->getRequest();
        $response   = $this->getResponse();
        try {
            return $authServer->respondToAccessTokenRequest($request, $response);
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        } catch (PhpException $exception) {
            return (new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500))
                ->generateHttpResponse($response);
        }
        return $response;
    }

    /**
     * Service status, documentation and operation parameters
     *
     * NOTE: This is NOT the same as the OpenID Connect discovery endpoint but a custom status endpoint
     *
     * @TODO implement just enough parts of https://openid.net/specs/openid-connect-discovery-1_0.html to provide discovery without WebFinger?
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @throws ServiceNotAvailableException
     */
    public function status(): Response
    {
        if (Configure::read('OAuthServer.statusDisabled')) {
            throw new ServiceNotAvailableException();
        }
        if (!$this->getRequest()->is('json')) {
            throw new NotFoundException();
        }
        $status = OAuthServerPlugin::instance()->getStatus();
        return $this->getResponse()
            ->withType('json')
            ->withStringBody(json_encode($status));
    }

    /**
     * @link https://openid.net/specs/openid-connect-core-1_0.html#UserInfo
     * @link https://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
     */
    public function userInfo(): ResponseInterface
    {
        if (Configure::read('OAuthServer.userInfoDisabled')) {
            // does not fall under section 5.3.3.
            throw new ServiceNotAvailableException();
        }
        if (!$this->getRequest()->is('json')) {
            // does not fall under section 5.3.3.
            throw new NotFoundException();
        }
        try {
            $user = $this->OAuthResources->getUser();
        } catch (PhpException $e) {
            return OAuthServerException::serverError('Erroneous attributes')->generateHttpResponse($this->getResponse());
        }
        if (!$user || !$userId = $user->getUserId()) {
            // When an error condition occurs, the UserInfo Endpoint returns an
            // Error Response as defined in Section 3 of OAuth 2.0 Bearer Token Usage [RFC6750]
            return OAuthServerException::accessDenied('Unrecognised user')->generateHttpResponse($this->getResponse());
        }
        // Get user DTO using the user id from the access token
        if (!$entity = $this->OAuthServer->Users->getUserEntityByIdentifier($userId)) {
            return OAuthServerException::accessDenied('User not found')->generateHttpResponse($this->getResponse());
        }
        // Does an additional scope validity check by token id (if AccessTokens repository has implemented the CheckTokenScopes interface)
        if ($this->OAuthServer->AccessTokens instanceof CheckTokenScopesInterface
            && !$this->OAuthServer->AccessTokens->hasScopes($user->getAccessTokenId(), ...$user->getScopes())) {
            return OAuthServerException::accessDenied('Scope mismatch')->generateHttpResponse($this->getResponse());
        }

        $stdClaims        = [];
        $stdClaims['aud'] = $user->getClientId();
        $stdClaims['sub'] = $user->getUserId();

        $claims = [];
        if ($entity instanceof ClaimSetInterface) {
            $claims = $entity->getClaims();
        }
        $claimExtractor = OAuthServerPlugin::instance()->createOpenIDConnectClaimExtractor();
        $claims         = $claimExtractor->extract($user->getScopes(), $claims);
        $claims         = $stdClaims + $claims;

        return $this->getResponse()
            ->withType('json')
            ->withStringBody(json_encode($claims));
    }
}
