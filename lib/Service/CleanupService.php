<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\Service;

use OCA\Edusign\AppInfo\Application;
use OCA\Edusign\Db\SignRequestMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;

/**
 * Removes signing requests that were never completed (failed or abandoned
 * flows). Completed flows are deleted inline by the controller as soon as the
 * signed document lands back in Nextcloud, so anything this service finds is by
 * definition stale.
 *
 * Both the nightly background job and the `occ edusign:cleanup` command go
 * through here so they behave identically.
 */
class CleanupService
{
    /** Default interval between automatic cleanups: once per day (nightly). */
    public const DEFAULT_INTERVAL = 86400;

    /** Default age above which an incomplete request is considered abandoned. */
    public const DEFAULT_MAX_AGE = 86400;

    public const CONFIG_INTERVAL = 'cleanup_interval';
    public const CONFIG_MAX_AGE = 'cleanup_max_age';

    public function __construct(
        private SignRequestMapper $mapper,
        private IAppConfig $appConfig,
        private ITimeFactory $timeFactory,
    ) {
    }

    /** Interval in seconds between automatic cleanup runs. */
    public function getInterval(): int
    {
        return max(60, $this->appConfig->getValueInt(Application::APP_ID, self::CONFIG_INTERVAL, self::DEFAULT_INTERVAL));
    }

    /** Age in seconds above which an incomplete request is removed. */
    public function getMaxAge(): int
    {
        return max(0, $this->appConfig->getValueInt(Application::APP_ID, self::CONFIG_MAX_AGE, self::DEFAULT_MAX_AGE));
    }

    /**
     * Remove every request older than $maxAgeSeconds (falls back to the
     * configured max age when null).
     *
     * @return int number of rows removed
     */
    public function cleanup(?int $maxAgeSeconds = null): int
    {
        $maxAgeSeconds ??= $this->getMaxAge();
        $threshold = $this->timeFactory->getTime() - $maxAgeSeconds;
        return $this->mapper->deleteOlderThan($threshold);
    }
}
