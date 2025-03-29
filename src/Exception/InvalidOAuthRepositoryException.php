<?php

namespace OAuthServer\Exception;

/**
 * Thrown when the given table is not a valid oauth repository
 */
class InvalidOAuthRepositoryException extends \Cake\Core\Exception\CakeException
{
    protected string $_messageTemplate = 'Given value is not a valid oauth repository, expected implementation for %s';
}
