<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Edusign\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
  'resources' => [],
  'routes' => [
    ['name' => 'api#query', 'url' => '/query', 'verb' => 'GET'],
    ['name' => 'api#register', 'url' => '/register', 'verb' => 'POST'],
    ['name' => 'api#remove', 'url' => '/remove', 'verb' => 'GET'],
    ['name' => 'api#request', 'url' => '/request', 'verb' => 'GET'],
    ['name' => 'api#response', 'url' => '/response', 'verb' => 'POST'],
  ]
];
