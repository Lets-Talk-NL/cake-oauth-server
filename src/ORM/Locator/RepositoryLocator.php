<?php

namespace OAuthServer\ORM\Locator;

use Cake\ORM\Table;
use League\OAuth2\Server\Repositories\RepositoryInterface;
use OAuthServer\Exception\Exception;
use OAuthServer\Exception\NotImplementedException;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Factory;
use UnexpectedValueException;
use Cake\ORM\Locator\LocatorInterface;
use InvalidArgumentException;

/**
 * Configured enum mapping based CakePHP table implementing OAuth 2.0 repository locator
 */
class RepositoryLocator implements LocatorInterface
{
    /**
     * @var Table[]
     */
    protected array $repositories = [];

    /**
     * @param array $mapping e.g. [Repository::AUTH_CODE => 'MyPlugin.MyTable', ...]
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function __construct(array $mapping)
    {
        foreach (Factory::repositories($mapping) as $repository => $table) {
            $this->set($repository, $table);
        }
    }

    /**
     * Checks the given alias is a value of the enumerated repository types
     *
     * @param string|Repository $alias
     * @return Repository
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function checkAlias($alias): string
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
     * @inheritDoc
     * @throws NotImplementedException
     */
    public function config($alias = null, $options = null)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     * @return Table|RepositoryInterface
     */
    public function get($alias, array $options = [])
    {
        $alias = $this->checkAlias($alias);
        return $this->repositories[$alias];
    }

    /**
     * @inheritDoc
     */
    public function exists($alias)
    {
        $alias = $this->checkAlias($alias);
        return array_key_exists($alias, $this->repositories);
    }

    /**
     * @inheritDoc
     */
    public function set($alias, Table $object)
    {
        if (!$object instanceof RepositoryInterface) {
            throw new InvalidArgumentException('given value is not a valid oauth repository');
        }
        $alias                      = $this->checkAlias($alias);
        $this->repositories[$alias] = $object;
        return $object;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->repositories = [];
    }

    /**
     * @inheritDoc
     */
    public function remove($alias)
    {
        $alias = $this->checkAlias($alias);
        unset($this->repositories[$alias]);
    }
}