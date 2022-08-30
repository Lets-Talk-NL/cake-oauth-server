<?php

namespace OAuthServer\Lib\Data\Entity\Traits;

/**
 * Helper trait that provides implementation methods for
 * OpenIDConnectServer\Entities\ClaimSetInterface
 * with the addition of a setter
 */
trait ClaimsetTrait
{
    /**
     * @var array
     */
    protected array $claims;

    /**
     * @return array
     */
    public function getClaims(): array
    {
        return $this->claims;
    }

    /**
     * @param array $claims
     * @return void
     */
    public function setClaims(array $claims): void
    {
        $this->claims = $claims;
    }
}