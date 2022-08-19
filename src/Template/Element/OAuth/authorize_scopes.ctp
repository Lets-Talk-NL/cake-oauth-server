<?php

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use OAuthServer\Lib\Data\Entity\Scope as ScopeData;

/* @var AuthorizationRequest $authRequest */
?>
<ul>
    <?php foreach ($authRequest->getScopes() as $scope): ?>
        <li>
            <?php if ($scope instanceof ScopeData): ?>
                <?= $scope->getIdentifier() ?>: <?= $scope->getDescription() ?>
            <?php else: ?>
                <?= $scope->getIdentifier() ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>