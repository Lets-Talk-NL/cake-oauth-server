<?php

namespace OAuthServer\ORM\Locator;

use Cake\ORM\Exception\MissingTableClassException;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use League\OAuth2\Server\Repositories\RepositoryInterface;
use OAuthServer\Exception\Exception;
use OAuthServer\Exception\InvalidOAuthRepositoryException;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Factory;
use UnexpectedValueException;
use Cake\ORM\Locator\LocatorInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * This CakePHP table locator locates tables based on OAuth 2.0 repository interface
 * name qualifiers which are mapped by constants in a repository enumeration
 *
 * @link Repository
 */
class RepositoryLocator implements LocatorInterface
{
    /**
     * Enum values mapped to table aliases of the
     * repository interface implementation objects
     *
     * @link RepositoryLocator::__construct
     * @var array
     */
    protected array $mapping = [];

    /**
     * Already loaded table objects
     *
     * @var Table[]
     */
    protected array $instances = [];

    /**
     * Configuration of objects
     *
     * @var array
     */
    protected array $config = [];

    /**
     * @param array $mapping e.g. [Repository::AUTH_CODE => 'MyPlugin.MyTable', ...]
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function __construct(array $mapping)
    {
        $this->mapping = Factory::completeRepositoryMapping($mapping);
    }

    /**
     * Checks the given alias is a value of the enumerated repository types
     *
     * @param string|Repository $alias e.g. Repository::ACCESS_TOKEN or Repository::ACCESS_TOKEN()
     * @return string e.g. '\League\OAuth2\Server\Repositories\...Interface'
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getRepositoryAliasFullyQualifiedInterfaceName($alias): string
    {
        if (is_string($alias)) {
            $alias = new Repository($alias);
        }
        if ($alias instanceof Repository) {
            return $alias->getValue();
        }
        throw new InvalidArgumentException();
    }

    /**
     * Loads the table for the given alias value of the enumerated repository types
     *
     * @param string|Repository $alias e.g. Repository::ACCESS_TOKEN or Repository::ACCESS_TOKEN()
     * @param array             $options
     * @return Table|RepositoryInterface
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws MissingTableClassException
     */
    public function load($alias, array $options = [])
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        if (!isset($this->mapping[$name])) {
            $label = Repository::labels($name);
            throw new MissingTableClassException(sprintf('Unmapped %s', $label));
        }
        $table = TableRegistry::getTableLocator()->get($this->mapping[$name], $options + $this->config($alias));
        return $this->set($alias, $table);
    }

    /**
     * @inheritDoc
     */
    public function setConfig($alias, $options = null)
    {
        if (is_array($alias)) {
            $this->config = $alias;
            return $this;
        }
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        if (isset($this->instances[$name])) {
            throw new RuntimeException(sprintf(
                'You cannot configure "%s", it has already been loaded.',
                $alias
            ));
        }
        $this->config[$name] = $options;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getConfig($alias = null)
    {
        if ($alias === null) {
            return $this->config;
        }
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        return isset($this->config[$name]) ? $this->config[$name] : [];
    }

    /**
     * @inheritDoc
     */
    public function config($alias = null, $options = null)
    {
        deprecationWarning(
            'RepositoryLocator::config() is deprecated. ' .
            'Use getConfig()/setConfig() instead.' .
            'Deprecation to ensure future CakePHP compatibility of this plugin in case the I changes.'
        );
        if ($alias !== null) {
            if (is_string($alias) && $options === null) {
                return $this->getConfig($alias);
            }
            $this->setConfig($alias, $options);
        }
        return $this->getConfig($alias);
    }

    /**
     * Either loads the table for the mapped alias or
     *
     * @inheritDoc
     * @return Table|RepositoryInterface
     */
    public function get($alias, array $options = [])
    {
        if (!$this->exists($alias)) {
            // lazy load the object corresponding with the alias
            return $this->load($alias, $options);
        }
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        return $this->instances[$name];
    }

    /**
     * @inheritDoc
     */
    public function exists($alias)
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        return array_key_exists($name, $this->instances);
    }

    /**
     * @inheritDoc
     * @throws InvalidOAuthRepositoryException
     */
    public function set($alias, Table $object)
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        if (!$object instanceof RepositoryInterface) {
            $label = Repository::labels($alias);
            throw new InvalidOAuthRepositoryException($label);
        }
        return $this->instances[$name] = $object;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->instances = [];
        $this->config    = [];
    }

    /**
     * @inheritDoc
     */
    public function remove($alias)
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        unset($this->instances[$name]);
        unset($this->config[$name]);
    }
}