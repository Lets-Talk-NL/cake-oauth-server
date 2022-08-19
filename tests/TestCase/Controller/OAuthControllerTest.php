<?php

namespace OAuthServer\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use OAuthServer\Controller\OAuthController;
use App\Controller\TestAppController;
use App\Model\Table\UsersTable;
use Cake\Core\Configure;

class OAuthControllerTest extends IntegrationTestCase
{
    /**
     * @inheritDoc
     */
    public $fixtures = [
        'plugin.OAuthServer.Users',
        'plugin.OAuthServer.AccessTokenScopes',
        'plugin.OAuthServer.AccessTokens',
        'plugin.OAuthServer.AuthCodeScopes',
        'plugin.OAuthServer.AuthCodes',
        'plugin.OAuthServer.Clients',
        'plugin.OAuthServer.RefreshTokens',
        'plugin.OAuthServer.Scopes',
    ];

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        TableRegistry::clear();
        TableRegistry::getTableLocator()->set('Users', new UsersTable()); // in TestAppController the AuthComponent is loaded by this alias
    }

    /**
     * @param string $path
     * @param string $ext
     * @return string
     */
    private function url(string $path, ?string $ext = null): string
    {
        $ext = $ext ? ".$ext" : '';
        return $path . $ext;
    }

    /**
     * @return void
     */
    public function testInstanceOfClassFromConfig(): void
    {
        $controller = new OAuthController();
        $this->assertInstanceOf(TestAppController::class, $controller);
    }

    /**
     * @return void
     */
    public function testOAuthIndexRedirectsToAuthorize(): void
    {
        Configure::write('OAuthServer.indexRedirectDisabled', false);
        $this->session(['Auth.User.id' => 4]);
        $this->get($this->url("/oauth") . "?client_id=CID&anything=at_all");
        $this->assertRedirect(['controller' => 'OAuth', 'action' => 'authorize', '?' => ['client_id' => 'CID', 'anything' => 'at_all']]);
    }

    /**
     * @return void
     */
    public function testOAuthIndexRedirectsToDisabled(): void
    {
        Configure::write('OAuthServer.indexRedirectDisabled', true);
        $this->session(['Auth.User.id' => 4]);
        $this->get($this->url("/oauth") . "?client_id=CID&anything=at_all");
        $this->assertResponseCode(404);
    }

    /**
     * @return void
     */
    public function testAuthorizeInvalidParams(): void
    {
        $this->session(['Auth.User.id' => 4]);
        $_GET = ['client_id' => 'INVALID', 'redirect_uri' => 'http://www.example.com', 'response_type' => 'code', 'scope' => 'test'];
        $this->get($this->url('/oauth/authorize') . '?' . http_build_query($_GET));
        $this->assertResponseError();
    }

    /**
     * @return void
     */
    public function testAuthorizeLoginRedirect(): void
    {
        $_GET         = ['client_id' => 'TEST', 'redirect_uri' => 'http://www.example.com', 'response_type' => 'code', 'scope' => 'test'];
        $authorizeUrl = $this->url('/oauth/authorize') . '?' . http_build_query($_GET);
        $this->get($authorizeUrl);
        $this->assertRedirect(['controller' => 'Users', 'action' => 'login', '?' => ['redirect' => $authorizeUrl]]);
    }

    /**
     * @return void
     */
    public function testAuthorizationCodeWithOpenIdConnect(): void
    {
        $this->session(['Auth.User.id' => 4]);

        $scope       = 'openid email';
        $redirectUri = 'http://www.example.com';
        $query       = ['client_id' => 'TEST', 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'scope' => $scope];

        $authorizeUrl = $this->url('/oauth/authorize') . '?' . http_build_query($query);
        $this->get($authorizeUrl);
        $this->assertResponseCode(200);
        $this->post($authorizeUrl, ['authorization' => 'Approve']);
        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertInternalType('string', $location);
        $prefix = $redirectUri . '?code=';
        $this->assertTextStartsWith($prefix, $location);
        $code                 = substr($location, strlen($prefix));
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        $accessTokenUrl       = $this->url('/oauth/access_token', 'json');
        $this->post($accessTokenUrl, [
            'grant_type'    => 'authorization_code',
            'client_id'     => 'TEST',
            'client_secret' => 'TestSecret',
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
            'scope'         => $scope,
        ]);
        $this->assertResponseContains('"id_token":');
    }

    /**
     * @return void
     */
    public function testStoreCurrentUserAndDefaultAuth(): void
    {
        $this->session(['Auth.User.id' => 4]);

        $_GET = ['client_id' => 'TEST', 'redirect_uri' => 'http://www.example.com', 'response_type' => 'code', 'scope' => 'test'];
        $this->post('/oauth/authorize' . '?' . http_build_query($_GET), ['authorization' => 'Approve']);

        $authCodes = TableRegistry::get('OAuthServer.AuthCodes');
        $this->assertTrue($authCodes->exists(['client_id' => 'TEST', 'user_id' => 4]), 'Auth token in database was not correctly assigned');
    }

    /**
     * @return void
     */
    public function testStatus(): void
    {
        $this->get('/oauth/status');
        $this->assertResponseCode(404);
        $this->get('/oauth/status.json');
        $this->assertResponseOk();
        $this->assertResponseContains('{');
    }
}