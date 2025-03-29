<?php

namespace OAuthServer\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Table;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAuthServer\Lib\Factory;
use OAuthServer\Model\Entity\Client;

/**
 * OAuth 2.0 clients table
 *
 * @method Client get($primaryKey, $options = [])
 * @method Client newEntity($data = null, array $options = [])
 * @method Client[] newEntities(array $data, array $options = [])
 * @method Client|bool save(EntityInterface $entity, $options = [])
 * @method Client patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Client[] patchEntities($entities, array $data, array $options = [])
 */
class ClientsTable extends Table implements ClientRepositoryInterface
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('oauth_clients');
        $this->setEntityClass('OAuthServer.Client');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');
    }

    /**
     * @param Event  $event  Event object
     * @param Client $client Client entity
     * @return void
     */
    public function beforeSave(\Cake\Event\EventInterface $event, Client $client)
    {
        if ($client->isNew()) {
            $client->id            = Factory::clientId();
            $client->client_secret = Factory::clientSecret();
        }
    }

    public function getClientEntity($clientIdentifier)
    {
        /** @var Client|null $client */
        $client = $this->find()->where([$this->aliasField($this->getPrimaryKey()) => $clientIdentifier])->first();
        if ($client) {
            return $client->transformToDTO();
        }
        return null;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        $event = new Event('OAuthServer.validateClient', $this, [$clientIdentifier, $clientSecret, $grantType]);
        $this->getEventManager()->dispatch($event);
        if ($event->isStopped()) {
            return false;
        }
        /** @var Client|null $entity */
        $entity = $this->find()->where([$this->aliasField($this->getPrimaryKey()) => $clientIdentifier])->first();
        if (!$entity) {
            return false;
        }
        if ($entity->client_secret !== $clientSecret) {
            return false;
        }
        return true;
    }
}
