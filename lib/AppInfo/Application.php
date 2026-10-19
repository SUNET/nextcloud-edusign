<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\AppInfo;

use OCA\Edusign\Listener\CSPListener;
use OCA\Edusign\Listener\LoadAdditionalScriptsListener;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'edusign';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }
    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
        $context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
    }
    public function boot(IBootContext $context): void {}
}
