<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\Command;

use OCA\Edusign\Service\CleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ edusign:cleanup [--days=N]
 *
 * Removes incomplete/abandoned signing requests. By default it uses the same
 * age threshold as the nightly background job; pass --days to override it (for
 * example --days=0 to remove every request not created within the current
 * second, i.e. effectively everything still pending).
 */
class Cleanup extends Command
{
    public function __construct(
        private CleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('edusign:cleanup')
            ->setDescription('Remove old/abandoned eduSign signing requests from the database')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Remove requests older than this many days (defaults to the configured cleanup_max_age)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $daysOption = $input->getOption('days');
        $maxAge = null;
        if ($daysOption !== null) {
            if (!is_numeric($daysOption) || (int) $daysOption < 0) {
                $output->writeln('<error>--days must be zero or a positive integer</error>');
                return 1;
            }
            $maxAge = (int) $daysOption * 86400;
        }

        $removed = $this->cleanupService->cleanup($maxAge);
        $output->writeln(sprintf('Removed %d incomplete eduSign signing request(s).', $removed));
        return 0;
    }
}
