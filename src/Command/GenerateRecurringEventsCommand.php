<?php

namespace App\Command;

use App\Recurrence\SeriesPlanner;
use App\Recurrence\SeriesSynchronizer;
use App\Repository\Event\RecurringEventRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tops every series up to the horizon. Only adds missing occurrences, so it is safe to run as
 * often as you like (it runs nightly from cron).
 */
#[AsCommand(name: 'app:recurring-events:generate', description: 'Create upcoming occurrences of all recurring events')]
class GenerateRecurringEventsCommand extends Command
{
    public function __construct(
        private readonly RecurringEventRepository $repository,
        private readonly SeriesPlanner            $planner,
        private readonly SeriesSynchronizer       $synchronizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be created');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $rows = [];
        foreach ($this->repository->findAll() as $series) {
            $plan = $this->planner->planExtension($series);
            if ($plan->occurrences === []) {
                continue;
            }
            $rows[] = [
                $series->name,
                count($plan->occurrences),
                implode(', ', array_map(fn($change) => $change->slot->format('Y-m-d'), $plan->occurrences)),
            ];
            if (!$dryRun) {
                $this->synchronizer->apply($plan);
            }
        }

        if ($rows === []) {
            $io->writeln('All recurring events are up to date.', OutputInterface::VERBOSITY_VERBOSE);
            return Command::SUCCESS;
        }
        $io->table(['Series', $dryRun ? 'Would create' : 'Created', 'Dates'], $rows);

        return Command::SUCCESS;
    }
}
