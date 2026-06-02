<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\BackgroundJob;

use OCA\Edusign\Service\CleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Periodically removes incomplete/abandoned eduSign signing requests.
 *
 * Runs once per day by default; the interval is configurable through the
 * `cleanup_interval` appconfig value (seconds).
 */
class CleanupJob extends TimedJob
{
    public function __construct(
        ITimeFactory $time,
        private CleanupService $cleanupService,
    ) {
        parent::__construct($time);
        $this->setInterval($this->cleanupService->getInterval());
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    protected function run($argument): void
    {
        $this->cleanupService->cleanup();
    }
}
