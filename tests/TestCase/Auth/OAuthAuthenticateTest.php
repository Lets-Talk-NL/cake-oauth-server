<?php

namespace OAuthServer\Test\TestCase\Auth;

use Cake\Controller\ComponentRegistry;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use OAuthServer\Auth\OAuthAuthenticate;

class OAuthAuthenticateTest extends TestCase
{
    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->Collection = $this->getMockBuilder(ComponentRegistry::class)->getMock();
        $this->auth       = new OAuthAuthenticate($this->Collection, []);
        TableRegistry::clear();
        $this->response = $this->getMockBuilder(Response::class)->getMock();
    }

    /**
     * @return void
     */
    public function testAuthenticate(): void
    {
        $request       = new Request('posts/index');
        $request->data = [];
        $this->assertFalse($this->auth->authenticate($request, $this->response));
    }
}
