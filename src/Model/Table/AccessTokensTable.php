<?php

namespace OAuthServer\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\I18n\Time;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\ORM\Query;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use OAuthServer\Lib\Utility\Map;
use OAuthServer\Model\Entity\AccessToken;
use OAuthServer\Lib\Data\Entity\AccessToken as AccessTokenData;
use OAuthServer\Model\Entity\AccessTokenScope;
use OAuthServer\Model\Table\Interfaces\CheckTokenScopesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function Functional\map;
use Exception;

/**
 * OAuth 2.0 access tokens table
 *
 * @property AccessTokenScopesTable|HasMany $AccessTokenScopes
 *
 * @method AccessToken get($primaryKey, $options = [])
 * @method AccessToken newEntity($data = null, array $options = [])
 * @method AccessToken[] newEntities(array $data, array $options = [])
 * @method AccessToken|bool save(EntityInterface $entity, $options = [])
 * @method AccessToken patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method AccessToken[] patchEntities($entities, array $data, array $options = [])
 */
class AccessTokensTable extends Table implements AccessTokenRepositoryInterface, CheckTokenScopesInterface
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->table('oauth_access_tokens');
        $this->setEntityClass('OAuthServer.AccessToken');
        $this->primaryKey('oauth_token');
        $this->hasMany('AccessTokenScopes', [
            'className'        => 'OAuthServer.AccessTokenScopes',
            'foreignKey'       => 'oauth_token',
            'saveStrategy'     => 'replace',
            'dependent'        => true,
            'cascadeCallbacks' => true,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $data = new AccessTokenData();
        $data->setClient($clientEntity);
        $data->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) {
            $data->addScope($scope);
        }
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $entity = $this->newEntity([
            'oauth_token'         => $accessTokenEntity->getIdentifier(),
            'expires'             => $accessTokenEntity->getExpiryDateTime()->getTimestamp(),
            'client_id'           => $accessTokenEntity->getClient()->getIdentifier(),
            'user_id'             => $accessTokenEntity->getUserIdentifier(),
            'access_token_scopes' => map($accessTokenEntity->getScopes(), fn(ScopeEntityInterface $scope) => [
                'oauth_token' => $accessTokenEntity->getIdentifier(),
                'scope_id'    => $scope->getIdentifier(),
            ]),
        ]);
        $this->saveOrFail($entity);
    }

    /**
     * @inheritDoc
     */
    public function revokeAccessToken($tokenId)
    {
        if ($entity = $this->get($tokenId)) {
            $this->delete($entity);
        }
    }

    /**
     * @inheritDoc
     */
    public function isAccessTokenRevoked($tokenId)
    {
        return !$this
            ->find()
            ->where([$this->aliasField($this->getPrimaryKey()) => $tokenId])
            ->count();
    }


    /**
     * Finds active (unexpired) access tokens based on the
     * given client_id and optionally user_id
     *
     * @param Query $query
     * @param array $options e.g. ['client_id' => '1234567891234567912', 'user_id' => null]
     * @return Query
     * @throws Exception
     */
    public function findActive(Query $query, array $options): Query
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setRequired(['client_id']);
        $optionsResolver->setDefault('user_id', null);
        $optionsResolver->setAllowedTypes('client_id', 'string');
        $optionsResolver->setAllowedTypes('user_id', ['string', 'null']);
        $options = $optionsResolver->resolve($options);
        // not checking refresh tokens depending on the extent of activity required may be added later
        return $query->where([$this->aliasField('expires') . ' >' => Time::now()->getTimestamp()] + $options);
    }

    /**
     * @inheritDoc
     */
    public function hasScopes(string $id, string ...$scope): bool
    {
        /** @var AccessToken $entity */
        if (!$entity = $this->find()->where(['oauth_token' => $id])->contain(['AccessTokenScopes'])->first()) {
            return false;
        }
        $dbScope = map($entity->access_token_scopes ?? [], fn(AccessTokenScope $a) => $a->scope_id);
        return Map::compareValues($dbScope, $scope);
    }
}
