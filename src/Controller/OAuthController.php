<?php

namespace OAuthServer\Controller;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use OAuthServer\Controller\Component\OAuthResourcesComponent;
use OAuthServer\Controller\Component\OAuthServerComponent;
use OAuthServer\Exception\ServiceNotAvailableException;
use OAuthServer\Model\Table\Interfaces\CheckTokenScopesInterface;
use OAuthServer\Plugin;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use UnexpectedValueException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Exception as PhpException;
use RuntimeException;
use Cake\Controller\Controller;

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
class OAuthController extends AppController
{
    /**
     * @inheritDoc
     */
    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('OAuthServer.OAuthServer');
        $this->loadComponent('OAuthServer.OAuthResources');
        $this->OAuthResources->allow();
        $this->OAuthResources->deny('userInfo');
    }

    /**
     * @inheritDoc
     */
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        if (!$this->components()->has('Auth')) {
            throw new RuntimeException('OAuthServer requires Auth component to be loaded and properly configured');
        }
        $this->Auth->allow(['oauth', 'accessToken', 'status', 'userInfo']);
        $this->Auth->deny(['authorize']);

        // The UserInfo Endpoint SHOULD support the use of Cross Origin Resource Sharing (CORS) [CORS]
        // and or other methods as appropriate to enable Java Script Clients to access the endpoint.
        if ($this->request->getParam('action') === 'userInfo') {
            $this->response = $this->response->withHeader('Access-Control-Allow-Origin', '*');
        }
    }

    /**
     * Index action handler
     *
     * @return Response
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
            '_ext'   => $this->request->param('_ext'),
            '?'      => $this->request->query,
        ], 301);
    }

    /**
     * Authorize action handler
     *
     * @link https://www.rfc-editor.org/rfc/rfc6749.html#page-18
     * @return Response
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @TODO improve exception handling?
     */
    public function authorize(): Response
    {
        if (Configure::read('OAuthServer.serviceDisabled')) {
            throw new ServiceNotAvailableException();
        }

        // Start authorization request
        $authServer  = $this->OAuthServer->getAuthorizationServer();
        $authRequest = $authServer->validateAuthorizationRequest($this->request);
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

        $eventManager = Plugin::instance()->getEventManager();
        $eventManager->dispatch(new Event('OAuthServer.beforeAuthorize', $this));

        try {
            // immediately approve authorization request if already has active tokens
            if ($this->OAuthServer->hasActiveAccessTokens($clientId, $user->getIdentifier())) {
                $authRequest->setAuthorizationApproved(true);
                $eventManager->dispatch(new Event('OAuthServer.afterAuthorize', $this));
                // redirect
                return $authServer->completeAuthorizationRequest($authRequest, $this->response);
            }

            // handle form posted UI confirmation of client authorization approval
            if ($this->request->is('post')) {
                $authRequest->setAuthorizationApproved(false);
                if ($this->request->data('authorization') === 'Approve') {
                    $authRequest->setAuthorizationApproved(true);
                    $eventManager->dispatch(new Event('OAuthServer.afterAuthorize', $this));
                } else {
                    $eventManager->dispatch(new Event('OAuthServer.afterDeny', $this));
                }
                // redirect
                return $authServer->completeAuthorizationRequest($authRequest, $this->response);
            }
        } catch (OAuthServerException $exception) {
            // @TODO this is a JSON response ..?
            return $exception->generateHttpResponse($this->response);
        } catch (Exception $exception) {
            $body = new Stream('php://temp', 'r+');
            $body->write($exception->getMessage());
            // @TODO this is a blank page with an exception message?
            return $response->withStatus(500)->withBody($body);
        }

        $this->set('authRequest', $authRequest);
        return $this->render();
    }

    /**
     * Access token action handler
     *
     * @link https://www.rfc-editor.org/rfc/rfc6749.html#page-23
     * @return Response
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @TODO improve exception handling?
     */
    public function accessToken(): Response
    {
        if (Configure::read('OAuthServer.serviceDisabled')) {
            throw new ServiceNotAvailableException();
        }
        $authServer = $this->OAuthServer->getAuthorizationServer();
        $request    = $this->request;
        $response   = $this->response;
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
     * @return Response
     * @TODO implement just enough parts of https://openid.net/specs/openid-connect-discovery-1_0.html to provide discovery without WebFinger?
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @throws ServiceNotAvailableException
     */
    public function status(): Response
    {
        if (Configure::read('OAuthServer.statusDisabled')) {
            throw new ServiceNotAvailableException();
        }
        if (!$this->request->is('json')) {
            throw new NotFoundException();
        }
        $status = Plugin::instance()->getStatus();
        return $this->response
            ->withType('json')
            ->withStringBody(json_encode($status));
    }

    /**
     * @link https://openid.net/specs/openid-connect-core-1_0.html#UserInfo
     * @link https://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
     * @return Response
     */
    public function userInfo(): Response
    {
        if (Configure::read('OAuthServer.userInfoDisabled')) {
            // does not fall under section 5.3.3.
            throw new ServiceNotAvailableException();
        }
        if (!$this->request->is('json')) {
            // does not fall under section 5.3.3.
            throw new NotFoundException();
        }
        try {
            $user = $this->OAuthResources->getUser();
        } catch (PhpException $e) {
            return OAuthServerException::serverError('Erroneous attributes')->generateHttpResponse($this->response);
        }
        if (!$userId = $user->getUserId()) {
            // When an error condition occurs, the UserInfo Endpoint returns an
            // Error Response as defined in Section 3 of OAuth 2.0 Bearer Token Usage [RFC6750]
            return OAuthServerException::accessDenied('Unrecognised user')->generateHttpResponse($this->response);
        }
        // Get user DTO using the user id from the access token
        if (!$entity = $this->OAuthServer->Users->getUserEntityByIdentifier($userId)) {
            return OAuthServerException::accessDenied('User not found')->generateHttpResponse($this->response);
        }
        // Does an additional scope validity check by token id (if AccessTokens repository has implemented the CheckTokenScopes interface)
        if ($this->OAuthServer->AccessTokens instanceof CheckTokenScopesInterface
            && !$this->OAuthServer->AccessTokens->hasScopes($user->getAccessTokenId(), ...$user->getScopes())) {
            return OAuthServerException::accessDenied('Scope mismatch')->generateHttpResponse($this->response);
        }

        $stdClaims        = [];
        $stdClaims['aud'] = $user->getClientId();
        $stdClaims['sub'] = $user->getUserId();

        $claims = [];
        if ($entity instanceof ClaimSetInterface) {
            $claims = $entity->getClaims();
        }
        $claimExtractor = Plugin::instance()->createOpenIDConnectClaimExtractor();
        $claims         = $claimExtractor->extract($user->getScopes(), $claims);
        $claims         = $stdClaims + $claims;

        return $this->response
            ->withType('json')
            ->withStringBody(json_encode($claims));
    }
}