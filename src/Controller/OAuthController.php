<?php

namespace OAuthServer\Controller;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use OAuthServer\Controller\Component\OAuthComponent;
use OAuthServer\Exception\ServiceNotAvailableException;
use OAuthServer\Plugin;
use UnexpectedValueException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Exception as PhpException;
use RuntimeException;

/**
 * OAuth 2.0 process controller
 *
 * Uses AppController alias in the current namespace
 * from bootstrap and config OAuthServer.appController
 *
 * @property OAuthComponent $OAuth
 */
class OAuthController extends AppController
{
    /**
     * @inheritDoc
     */
    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('OAuthServer.OAuth');
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
        $this->Auth->allow(['oauth', 'accessToken', 'status']);
        $this->Auth->deny(['authorize']);
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
        $authServer  = $this->OAuth->getAuthorizationServer();
        $authRequest = $authServer->validateAuthorizationRequest($this->request);
        $clientId    = $authRequest->getClient()->getIdentifier();

        // 'redirect_uri' is considered an optional argument but grant implementations dont always
        // seem to implement the fallback from the client. Set it anyway here
        if ($authRequest->getRedirectUri() === null && ($authRequest->getClient() && $authRequest->getClient()->getRedirectUri())) {
            $authRequest->setRedirectUri($authRequest->getClient()->getRedirectUri());
        }

        // Once the user has logged in set the user on the AuthorizationRequest
        if ($user = $this->OAuth->getSessionUserData()) {
            $authRequest->setUser($user);
        }

        $eventManager = Plugin::instance()->getEventManager();
        $eventManager->dispatch(new Event('OAuthServer.beforeAuthorize', $this));

        try {
            // immediately approve authorization request if already has active tokens
            if ($this->OAuth->hasActiveAccessTokens($clientId, $user->getIdentifier())) {
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
     * @return Response
     * @TODO JSON seems to be the standard, but improve content type handling?
     * @TODO improve exception handling?
     */
    public function accessToken(): Response
    {
        if (Configure::read('OAuthServer.serviceDisabled')) {
            throw new ServiceNotAvailableException();
        }
        $request  = $this->request;
        $response = $this->response;
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
     * @return Response
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
        return $this->getResponse()
                    ->withType('json')
                    ->withStringBody(json_encode($status));
    }
}