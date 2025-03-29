<?php

namespace OAuthServer\ORM\Locator;

use Cake\ORM\Exception\MissingTableClassException;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;
use League\OAuth2\Server\Repositories\RepositoryInterface;
use OAuthServer\Exception\Exception;
use OAuthServer\Exception\InvalidOAuthRepositoryException;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Factory;
use RuntimeException;
use UnexpectedValueException;

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
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @return string e.g. '\League\OAuth2\Server\Repositories\...Interface'
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
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws MissingTableClassException
     * @return Table|RepositoryInterface
     */
    public function load($alias, array $options = [])
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        if (!isset($this->mapping[$name])) {
            $label = Repository::labels($name);
            throw new MissingTableClassException(sprintf('Unmapped %s', $label));
        }
        $table = TableRegistry::getTableLocator()->get($this->mapping[$name], $options + $this->getConfig($alias));
        return $this->set($alias, $table);
    }

    public function setConfig($alias, $options = null): static
    {
        if (is_array($alias)) {
            $this->config = $alias;
            return $this;
        }
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        if (isset($this->instances[$name])) {
            throw new RuntimeException(sprintf('You cannot configure "%s", it has already been loaded.', $alias));
        }
        $this->config[$name] = $options;
        return $this;
    }

    public function getConfig($alias = null): array
    {
        if ($alias === null) {
            return $this->config;
        }
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        return $this->config[$name] ?? [];
    }

    /**
     * Either loads the table for the mapped alias of
     */
    public function get($alias, array $options = []): Table
    {
        if (!$this->exists($alias)) {
            // lazy load the object corresponding with the alias
            return $this->load($alias, $options);
        }
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        return $this->instances[$name];
    }

    public function exists($alias): bool
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        return array_key_exists($name, $this->instances);
    }

    /**
     * @throws InvalidOAuthRepositoryException
     */
    public function set($alias, Table|\Cake\Datasource\RepositoryInterface $repository): Table
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        if (!$repository instanceof RepositoryInterface) {
            $label = Repository::labels($alias);
            throw new InvalidOAuthRepositoryException($label);
        }
        return $this->instances[$name] = $repository;
    }

    public function clear(): void
    {
        $this->instances = [];
        $this->config    = [];
    }

    public function remove($alias): void
    {
        $name = $this->getRepositoryAliasFullyQualifiedInterfaceName($alias);
        unset($this->instances[$name], $this->config[$name]);
    }
}
