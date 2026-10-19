<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\Listener;

use OCA\Edusign\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadAdditionalScriptsListener implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        Util::addScript(Application::APP_ID, 'edusign-main');
    }
}
