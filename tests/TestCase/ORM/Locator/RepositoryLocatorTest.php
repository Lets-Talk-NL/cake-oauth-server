<?php

namespace OAuthServer\Test\TestCase\ORM\Locator;

use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorInterface;
use Cake\TestSuite\TestCase;
use OAuthServer\Exception\NotImplementedException;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\ORM\Locator\RepositoryLocator;
use UnexpectedValueException;
use InvalidArgumentException;

class RepositoryLocatorTest extends TestCase
{
    /**
     * @var RepositoryLocator
     */
    protected RepositoryLocator $emptyLocator;

    /**
     * @var RepositoryLocator
     */
    protected RepositoryLocator $configuredLocator;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->emptyLocator      = new RepositoryLocator([]);
        $this->configuredLocator = new RepositoryLocator(Configure::read('OAuthServer.repositories'));
    }

    /**
     * @return void
     */
    public function testImplementation(): void
    {
        $this->assertInstanceOf(LocatorInterface::class, $this->emptyLocator);
        $this->assertInstanceOf(LocatorInterface::class, $this->configuredLocator);
    }

    /**
     * @return void
     */
    public function testCheckAlias(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->emptyLocator->checkAlias('nonexisting');
        $this->expectException(InvalidArgumentException::class);
        $this->emptyLocator->checkAlias(123);
        foreach (Repository::values() as $repository) {
            $value = $repository->getValue();
            $this->assertEquals($value, $this->emptyLocator->checkAlias($repository));
            $this->assertEquals($value, $this->emptyLocator->checkAlias($repository->getValue()));
        }
    }

    /**
     * @return void
     */
    public function testConfig(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->emptyLocator->config(Repository::IDENTITY, []);
    }

    /**
     * @return void
     */
    public function testExists(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertTrue($this->configuredLocator->exists($className));
        }
    }

    /**
     * @return void
     */
    public function testGet(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertInstanceOf($className, $this->configuredLocator->get($className));
        }
    }

    /**
     * @return void
     */
    public function testSet(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertInstanceOf($className, $this->emptyLocator->set($className, $this->configuredLocator->get($className)));
            $this->assertInstanceOf($className, $this->emptyLocator->get($className));
        }
    }

    /**
     * @return void
     */
    public function testClear(): void
    {
        $this->configuredLocator->clear();
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertFalse($this->configuredLocator->exists($className));
        }
    }

    /**
     * @return void
     */
    public function testRemove(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->configuredLocator->remove($className);
            $this->assertFalse($this->configuredLocator->exists($className));
        }
    }
}