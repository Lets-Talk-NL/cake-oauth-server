<?php

namespace OAuthServer\Lib\Data\Entity;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OAuthServer\Lib\Data\Entity\Traits\ClaimsetTrait;
use OpenIDConnectServer\Entities\ClaimSetInterface;

/**
 * OAuth 2.0 implementation User DTO
 */
class User implements UserEntityInterface, ClaimSetInterface
{
    use EntityTrait;
    use ClaimsetTrait;
}