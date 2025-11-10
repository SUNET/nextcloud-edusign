<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Edusign\Listener;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use Psr\Log\LoggerInterface;

class CSPListener implements IEventListener
{
    public function __construct(
        private IAppConfig $appConfig,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Event $event): void
    {
        $this->logger->debug('Adding CSP for Edusign', ['app' => 'edusign']);
        if (!($event instanceof AddContentSecurityPolicyEvent)) {
            return;
        }
        $csp = new ContentSecurityPolicy();
        $idp = $this->appConfig->getAppValue('idp', '');
        $endpoint = $this->appConfig->getAppValue('edusign_endpoint', '');
        $sites = [$idp, $endpoint, 'https://signservice.test.edusign.sunet.se', 'https://signservice.edusign.sunet.se'];
        foreach ($sites as $site) {
            $url = parse_url($site);
            if ($url === false || !isset($url["scheme"]) || !isset($url["host"])) {
                $this->logger->warning('Invalid URL in CSP configuration: ' . $site, ['app' => 'edusign']);
                continue;
            }
            $http = $url["scheme"] . "://" . $url["host"];
            $csp->addAllowedFormActionDomain($http);
            $csp->addAllowedConnectDomain($http);
        }
        $event->addPolicy($csp);
    }
}
