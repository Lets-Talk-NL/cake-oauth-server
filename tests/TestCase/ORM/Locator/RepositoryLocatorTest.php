<?php

namespace OAuthServer\Test\TestCase\ORM\Locator;

use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use OAuthServer\Exception\InvalidOAuthRepositoryException;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\ORM\Locator\RepositoryLocator;
use UnexpectedValueException;
use InvalidArgumentException;
use RuntimeException;

class RepositoryLocatorTest extends TestCase
{
    //region Properties
    /**
     * @var RepositoryLocator
     */
    protected RepositoryLocator $locator;
    //endregion

    //region Lifecycle
    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        TableRegistry::getTableLocator()->clear();
        $this->locator = new RepositoryLocator(Configure::read('OAuthServer.repositories'));
    }
    //endregion

    //region Test misc
    /**
     * @return void
     */
    public function testImplementation(): void
    {
        $this->assertInstanceOf(LocatorInterface::class, $this->locator);
    }
    //endregion

    //region Test methods
    /**
     * @return void
     */
    public function testGetRepositoryAliasFullyQualifiedInterfaceName(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->locator->getRepositoryAliasFullyQualifiedInterfaceName('nonexisting');
        $this->expectException(InvalidArgumentException::class);
        $this->locator->getRepositoryAliasFullyQualifiedInterfaceName(123);
        foreach (Repository::values() as $repository) {
            $value = $repository->getValue();
            $this->assertEquals($value, $this->locator->getRepositoryAliasFullyQualifiedInterfaceName($repository));
            $this->assertEquals($value, $this->locator->getRepositoryAliasFullyQualifiedInterfaceName($repository->getValue()));
        }
    }

    /**
     * @return void
     */
    public function testLoad(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->locator->load('nonexisting');
        $this->assertInstanceOf(Repository::AUTH_CODE, $this->locator->load(Repository::AUTH_CODE));
    }

    /**
     * @return void
     */
    public function testSetConfigOne(): void
    {
        $this->locator->setConfig(Repository::ACCESS_TOKEN(), ['random' => 'something']);
        $this->locator->setConfig(Repository::ACCESS_TOKEN, ['random' => 'something']);
        $this->assertEquals($this->locator->getConfig(Repository::ACCESS_TOKEN), ['random' => 'something']);
        $this->expectException(UnexpectedValueException::class);
        $this->locator->setConfig('nonexisting', ['random' => 'something']);
    }

    /**
     * @return void
     */
    public function testSetConfigTwo(): void
    {
        $this->locator->get(Repository::ACCESS_TOKEN);
        $this->expectException(RuntimeException::class);
        $this->locator->setConfig(Repository::ACCESS_TOKEN, ['random' => 'something']);
        $this->locator->setConfig(Repository::ACCESS_TOKEN(), ['random' => 'something']);
    }

    /**
     * @return void
     */
    public function testGetConfig(): void
    {
        $this->locator->setConfig(Repository::ACCESS_TOKEN, ['random' => 'something']);
        $this->assertEquals($this->locator->getConfig(Repository::ACCESS_TOKEN), ['random' => 'something']);
        $this->assertEquals($this->locator->getConfig(Repository::ACCESS_TOKEN()), ['random' => 'something']);
        $this->expectException(UnexpectedValueException::class);
        $this->locator->getConfig('nonexisting');
    }

    /**
     * @return void
     */
    public function testConfig(): void
    {
        $this->assertEquals($this->locator->config(Repository::ACCESS_TOKEN, ['random' => 'something']), ['random' => 'something']);
        $this->assertEquals($this->locator->config(Repository::ACCESS_TOKEN(), ['random' => 'something']), ['random' => 'something']);
    }

    /**
     * @return void
     */
    public function testGet(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertInstanceOf($className, $this->locator->get($className));
        }
    }

    /**
     * @return void
     */
    public function testExists(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertFalse($this->locator->exists($className));
            $this->locator->get($className);
            $this->assertTrue($this->locator->exists($className));
        }
    }

    /**
     * @return void
     */
    public function testSet(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertInstanceOf($className, $this->locator->set($className, $this->locator->get($className)));
            $this->assertInstanceOf($className, $this->locator->get($className));
        }
        $this->expectException(UnexpectedValueException::class);
        $this->locator->set('nonexisting', new Table());
    }

    /**
     * @return void
     */
    public function testSetInvalidRepositoryException(): void
    {
        $this->expectException(InvalidOAuthRepositoryException::class);
        $this->locator->set(Repository::ACCESS_TOKEN, new Table());
    }

    /**
     * @return void
     */
    public function testClear(): void
    {
        $this->locator->clear();
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->assertFalse($this->locator->exists($className));
        }
    }

    /**
     * @return void
     */
    public function testRemove(): void
    {
        foreach (Repository::values() as $repository) {
            $className = $repository->getValue();
            $this->locator->remove($className);
            $this->assertFalse($this->locator->exists($className));
        }
    }
    //endregion
}