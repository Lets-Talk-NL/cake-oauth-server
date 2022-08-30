<?php

namespace App\Model\Table;

use Cake\ORM\Table;
use OAuthServer\Lib\Data\Entity\User;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;

/**
 * CLASS FOR TESTING PURPOSES
 */
class UsersTable extends Table implements UserRepositoryInterface, IdentityProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity)
    {
        $entity = $this
            ->find()
            ->where(compact('username', 'password'))
            ->first();
        if (!$entity) {
            return null;
        }
        $data = new User();
        $data->setIdentifier($entity->id);
        return $data;
    }

    /**
     * @inheritDoc
     *
     * @return ClaimSetInterface|UserEntityInterface|null
     * @link https://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
     */
    public function getUserEntityByIdentifier($identifier)
    {
        $entity = $this
            ->find()
            ->where([$this->aliasField($this->_primaryKey) => $identifier])
            ->first();
        if (!$entity) {
            return null;
        }
        $dto = new User();
        $dto->setIdentifier($entity->id);
        $dto->setClaims([
            // profile
            'name'                  => 'John Smith',
            'family_name'           => 'Smith',
            'given_name'            => 'John',
            'middle_name'           => 'Doe',
            'nickname'              => 'JDog',
            'preferred_username'    => 'jdogsmith77',
            'profile'               => '',
            'picture'               => 'avatar.png',
            'website'               => 'http://www.google.com',
            'gender'                => 'M',
            'birthdate'             => '01/01/1990',
            'zoneinfo'              => '',
            'locale'                => 'US',
            'updated_at'            => '01/01/2018',
            // email
            'email'                 => 'john.doe@example.com',
            'email_verified'        => true,
            // phone
            'phone_number'          => '(866) 555-5555',
            'phone_number_verified' => true,
            // address
            'address'               => '50 any street, any state, 55555',
        ]);
        return $dto;
    }
}