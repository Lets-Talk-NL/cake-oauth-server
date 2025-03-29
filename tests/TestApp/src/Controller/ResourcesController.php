<?php

namespace App\Controller;

/**
 * CLASS FOR TESTING PURPOSES
 */
class ResourcesController extends TestAppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->disableAutoRender();
    }

    public function someResourceEndpoint()
    {
        return $this->response->withStringBody(json_encode($this->Auth->user()));
    }
}
