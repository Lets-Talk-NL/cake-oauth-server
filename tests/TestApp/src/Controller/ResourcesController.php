<?php

namespace App\Controller;

/**
 * CLASS FOR TESTING PURPOSES
 */
class ResourcesController extends TestAppController
{
    /**
     * @inheritDoc
     */
    public function initialize()
    {
        parent::initialize();
        $this->disableAutoRender();
    }

    /**
     * @inheritDoc
     */
    public function someResourceEndpoint()
    {
        return $this->response->withStringBody(json_encode($this->Auth->user()));
    }
}