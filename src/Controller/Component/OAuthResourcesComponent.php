<?php

namespace OAuthServer\Controller\Component;

use Cake\Controller\Controller;
use Cake\Controller\Component;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator;
use OAuthServer\Auth\OAuthAuthenticate;
use Cake\Http\Response;
use OAuthServer\Lib\Data\Request\ResourceUser;
use ReflectionException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use LogicException;

/**
 * OAuth 2.0 resources process controller helper component
 *
 * Useful when protecting resources when requiring resource
 * protection other than using Cake's AuthComponent which
 * requires a redirect URL
 */
class OAuthResourcesComponent extends Component
{
    use EventDispatcherTrait;

    /**
     * @var OAuthAuthenticate
     */
    protected OAuthAuthenticate $authenticate;

    /**
     * Default config
     *
     * - `checkAuthIn` - Name of event for which initial auth checks should be done.
     *   Defaults to 'Controller.startup'. You can set it to 'Controller.initialize'
     *   if you want the check to be done before controller's beforeFilter() is run.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'checkAuthIn' => 'Controller.startup',
    ];

    /**
     * Current user data
     *
     * @var array|null
     */
    protected ?array $user = null;

    /**
     * Controller actions for which user validation is not required.
     *
     * @var string[]
     * @see OAuthResourcesComponent::allow()
     * @see OAuthResourcesComponent::deny()
     */
    protected array $allowedActions = [];

    /**
     * @inheritDoc
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->setEventManager($this->_registry->getController()->getEventManager());
        $this->authenticate = new OAuthAuthenticate($this->_registry, []);
    }

    /**
     * Callback for Controller.startup event.
     *
     * @param Event $event Event instance.
     * @return Response|null
     */
    public function startup(Event $event): ?Response
    {
        return $this->authCheck($event);
    }

    /**
     * Events supported by this component.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Controller.initialize' => 'authCheck',
            'Controller.startup'    => 'startup',
        ];
    }

    /**
     * Takes a list of actions in the current controller for which authentication is not required, or
     * no parameters to allow all actions.
     *
     * You can use allow with either an array or a simple string.
     *
     * ```
     * $this->Auth->allow('view');
     * $this->Auth->allow(['edit', 'add']);
     * ```
     * or to allow all actions
     * ```
     * $this->Auth->allow();
     * ```
     *
     * @param string|string[]|null $actions Controller action name or array of actions
     * @return void
     * @link https://book.cakephp.org/3/en/controllers/components/authentication.html#making-actions-public
     */
    public function allow($actions = null): void
    {
        if ($actions === null) {
            $controller           = $this->_registry->getController();
            $this->allowedActions = get_class_methods($controller);
            return;
        }
        $this->allowedActions = array_merge($this->allowedActions, (array)$actions);
    }

    /**
     * Removes items from the list of allowed/no authentication required actions.
     *
     * You can use deny with either an array or a simple string.
     *
     * ```
     * $this->Auth->deny('view');
     * $this->Auth->deny(['edit', 'add']);
     * ```
     * or
     * ```
     * $this->Auth->deny();
     * ```
     * to remove all items from the allowed list
     *
     * @param string|string[]|null $actions Controller action name or array of actions
     * @return void
     * @see  \Cake\Controller\Component\AuthComponent::allow()
     * @link https://book.cakephp.org/3/en/controllers/components/authentication.html#making-actions-require-authorization
     */
    public function deny($actions = null): void
    {
        if ($actions === null) {
            $this->allowedActions = [];

            return;
        }
        foreach ((array)$actions as $action) {
            $i = array_search($action, $this->allowedActions, true);
            if (is_int($i)) {
                unset($this->allowedActions[$i]);
            }
        }
        $this->allowedActions = array_values($this->allowedActions);
    }

    /**
     * Checks whether current action is accessible without authentication.
     *
     * @param Controller $controller A reference to the instantiating
     *                               controller object
     * @return bool True if action is accessible without authentication else false
     */
    protected function _isAllowed(Controller $controller): bool
    {
        $action = strtolower($controller->getRequest()->getParam('action'));
        return in_array($action, array_map('strtolower', $this->allowedActions));
    }

    /**
     * Main execution method, handles authentication check
     *
     * The auth check is done when event name is same as the one configured in
     * `checkAuthIn` config.
     *
     * @param Event $event Event instance.
     * @return Response|null
     * @throws ReflectionException
     */
    public function authCheck(Event $event): ?Response
    {
        if ($this->_config['checkAuthIn'] !== $event->getName()) {
            return null;
        }

        /** @var Controller $controller */
        $controller = $event->getSubject();
        $request    = $controller->getRequest();
        $response   = $controller->getResponse();

        $action = strtolower($request->getParam('action'));
        if (!$controller->isAction($action)) {
            return null;
        }

        if ($this->_isAllowed($controller)) {
            return null;
        }
        if ($user = $this->authenticate->authenticate($request, $response)) {
            $this->user = $user;
            return null;
        }
        if ($exception = $this->authenticate->getException()) {
            return $exception->generateHttpResponse($response);
        }

        return $response->withStatus(403);
    }

    /**
     * Will return a ResourceUser object if successfully authenticated for resources
     *
     * @return ResourceUser|null
     * @throws LogicException
     * @see BearerTokenValidator::validateAuthorization
     */
    public function getUser(): ?ResourceUser
    {
        if (!$this->user) {
            return null;
        }
        $resolver = new OptionsResolver();
        $resolver->setRequired(['oauth_access_token_id', 'oauth_client_id', 'oauth_user_id', 'oauth_scopes']);
        $resolver->setAllowedTypes('oauth_access_token_id', 'string');
        $resolver->setAllowedTypes('oauth_client_id', 'string');
        $resolver->setAllowedTypes('oauth_user_id', 'string');
        $resolver->setAllowedTypes('oauth_scopes', 'array');
        $user = $resolver->resolve($this->user);
        return new ResourceUser(
            $user['oauth_access_token_id'],
            $user['oauth_client_id'],
            $user['oauth_user_id'],
            $user['oauth_scopes']
        );
    }

    /**
     * Returns all controller actions that are always allowed
     * access inessential of OAuth access authentication
     *
     * @return string[]
     */
    public function getAllowedActions(): array
    {
        return $this->allowedActions;
    }
}