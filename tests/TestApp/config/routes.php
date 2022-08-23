<?php

use Cake\Routing\Router;

Router::connect('/users/login', [
    'controller' => 'Users',
    'action'     => 'login',
]);

Router::connect('/api/:action', [
    'controller' => 'Resources',
    'action'     => ':action',
]);