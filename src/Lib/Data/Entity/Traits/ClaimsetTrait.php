<?php

namespace OAuthServer\Lib\Data\Entity\Traits;

/**
 * Helper trait that provides implementation methods for
 * OpenIDConnectServer\Entities\ClaimSetInterface
 * with the addition of a setter
 */
trait ClaimsetTrait
{
    protected array $claims;

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function setClaims(array $claims): void
    {
        $this->claims = $claims;
    }
}
