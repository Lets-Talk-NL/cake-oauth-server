<?php

namespace OAuthServer\Exception;

use Cake\Core\Exception\Exception;

/**
 * Thrown when the given table is not a valid oauth repository
 */
class InvalidOAuthRepositoryException extends Exception
{
    /**
     * @inheritDoc
     */
    protected $_messageTemplate = 'Given value is not a valid oauth repository, expected implementation for %s';
}