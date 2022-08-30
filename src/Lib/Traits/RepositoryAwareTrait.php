<?php

namespace OAuthServer\Lib\Traits;

use Cake\Datasource\RepositoryInterface;
use OAuthServer\Exception\Exception;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Plugin;

/**
 * Helper trait that sets the repository on the calling object
 */
trait RepositoryAwareTrait
{
    /**
     * Get the Cake repository representation of the given OAuth 2.0 server repository requirement
     *
     * @param Repository $repository
     * @return RepositoryInterface
     * @throws Exception
     */
    public function getRepository(Repository $repository): RepositoryInterface
    {
        return Plugin::instance()->getTableLocator()->get($repository);
    }

    /**
     * Loads the Cake repository/table object that corresponds with the
     * configured enumerated repository value (e.g. Repository::ACCESS_TOKEN)
     * and sets it on a property called $name on the calling object
     *
     * @param string     $name       Used to set it on the object
     * @param Repository $repository Value from repository enumeration
     * @return RepositoryInterface
     */
    public function loadRepository(string $name, Repository $repository): RepositoryInterface
    {
        return $this->{$name} = $this->getRepository($repository);
    }
}
