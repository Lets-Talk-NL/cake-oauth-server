<?php

namespace OAuthServer\Model\Table\Interfaces;

/**
 * Token repositories may implement this interface to
 * allow for scope comparisons by token id
 */
interface CheckTokenScopesInterface
{
    /**
     * Check whether token with $id has all given scope
     * strings in subsequent arguments
     *
     * @param string $id
     * @param string ...$scope
     * @return bool
     */
    public function hasScopes(string $id, string ...$scope): bool;
}