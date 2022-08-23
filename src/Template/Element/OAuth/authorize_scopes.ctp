<?php

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use OAuthServer\Lib\Data\Entity\Scope as ScopeData;

/* @var AuthorizationRequest $authRequest */
?>
<ul>
    <?php foreach ($authRequest->getScopes() as $scope): ?>
        <?php if ($scope instanceof ScopeData && $scope->getDescription()): ?>
            <li>
                <?= $scope->getDescription() // only show scope to user if there is a description  ?>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>