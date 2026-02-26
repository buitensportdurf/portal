<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:sentry',
    description: 'Throw a test exception to verify Sentry/Bugsink integration',
)]
class SentryTestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Throwing a test exception...');

        throw new \RuntimeException('This is a test exception from the Symfony command to verify Bugsink integration');
    }
}
