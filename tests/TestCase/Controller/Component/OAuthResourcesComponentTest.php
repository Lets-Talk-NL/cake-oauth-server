<?php

namespace OAuthServer\Test\TestCase\Controller\Component;

use App\Controller\ResourcesController;
use Cake\TestSuite\TestCase;
use OAuthServer\Controller\Component\OAuthResourcesComponent;

class OAuthResourcesComponentTest extends TestCase
{
    protected OAuthResourcesComponent $component;

    public function setUp()
    {
        parent::setUp();
        $controller = new ResourcesController();
        $controller->initialize();
        $this->component = new OAuthResourcesComponent($controller->components());
    }

    public function testAllowNone(): void
    {
        $this->assertEquals([], $this->component->getAllowedActions());
    }

    public function testAllowAll(): void
    {
        $this->component->allow();
        $this->assertTrue(in_array('someResourceEndpoint', $this->component->getAllowedActions(), true));
    }

    public function testAllowString(): void
    {
        $this->component->allow('index');
        $this->assertEquals(['index'], $this->component->getAllowedActions());
    }

    public function testAllowArray(): void
    {
        $this->component->allow(['index']);
        $this->assertEquals(['index'], $this->component->getAllowedActions());
    }

    public function testDenyAll(): void
    {
        $this->assertEquals([], $this->component->getAllowedActions());
        $this->component->allow('index');
        $this->component->deny();
        $this->assertEquals([], $this->component->getAllowedActions());
    }

    public function testDenyString(): void
    {
        $this->assertEquals([], $this->component->getAllowedActions());
        $this->component->allow('index');
        $this->component->deny('index');
        $this->assertEquals([], $this->component->getAllowedActions());
    }

    public function testDenyArray(): void
    {
        $this->assertEquals([], $this->component->getAllowedActions());
        $this->component->allow('index');
        $this->component->deny(['index']);
        $this->assertEquals([], $this->component->getAllowedActions());
    }
}
