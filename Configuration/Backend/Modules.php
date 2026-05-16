<?php

use Ideative\T3BeLogin\Controller\LoginAppearanceController;

return [
    'id_be_login' => [
        'parent' => 'system',
        'position' => ['after' => 'extensionmanager'],
        'access' => 'admin',
        'path' => '/module/system/login-appearance',
        'iconIdentifier' => 'module-id-be-login',
        'labels' => 'id_be_login.modules.login_appearance',
        'routes' => [
            '_default' => [
                'target' => LoginAppearanceController::class . '::handleRequest',
            ],
        ],
    ],
];
