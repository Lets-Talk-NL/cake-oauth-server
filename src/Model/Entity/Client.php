<?php

namespace OAuthServer\Model\Entity;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use OAuthServer\Lib\Data\Entity\Client as ClientData;

/**
 * OAuth 2.0 client entity
 *
 * @property string        $id
 * @property string        $client_secret
 * @property string        $name
 * @property string        $redirect_uri
 *
 * @property Client[]|null $clients
 */
class Client extends Entity
{
    /**
     * Transforms the ORM Entity object into an OAuth 2.0 server DTO object
     *
     * @return ClientData
     */
    public function transformToDTO(): ClientData
    {
        $dto = new ClientData();
        $dto->setIdentifier($this->id);
        $dto->setName($this->name);
        $dto->setRedirectUri($this->redirect_uri);
        $dto->setIsConfidential(!empty($this->client_secret));
        return $dto;
    }
}