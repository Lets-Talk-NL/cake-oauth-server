<?php
declare(strict_types=1);

use PhpCsFixer\Config;

/** @var Config $config */
$config = include __DIR__ . '/vendor/letstalk/code-quality/php/code-style/cakephp-4.php';

// Do not strict type
$rules                               = $config->getRules();
$rules['@PHP81Migration']            = true;
$rules['declare_strict_types']       = false;
$rules['no_superfluous_phpdoc_tags'] = [
    'remove_inheritdoc' => true,
];
$config->setRules($rules);

return $config;