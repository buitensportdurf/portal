<?php

namespace App\Command;

use App\Entity\EmailNotification;
use App\Entity\Event\EventSubscription;
use App\Repository\Event\EventRepository;
use App\Service\SubscriptionMailer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reminds subscribers of events starting within the next two days, once per event. Runs daily from
 * cron, but only sends when EVENT_REMINDERS_ENABLED is on.
 */
#[AsCommand(name: 'app:events:send-reminders', description: 'Email subscribers of events starting soon')]
class SendEventRemindersCommand extends Command
{
    public const string AHEAD = '+2 days';

    public function __construct(
        private readonly EventRepository        $repository,
        private readonly SubscriptionMailer     $mailer,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'bool:EVENT_REMINDERS_ENABLED')]
        private readonly bool                   $enabled = false,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show who would get a reminder')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Send even though EVENT_REMINDERS_ENABLED is off')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        if (!$this->enabled && !$dryRun && !$input->getOption('force')) {
            $io->writeln('Event reminders are disabled (EVENT_REMINDERS_ENABLED).', OutputInterface::VERBOSITY_VERBOSE);
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($this->repository->findNeedingReminder(new DateTimeImmutable(self::AHEAD)) as $event) {
            $subscriptions = $event->getEventSubscriptions()->filter(
                fn(EventSubscription $subscription) => $this->mailer->wants($subscription, EmailNotification::Reminder),
            );
            $rows[] = [$event->name, $event->startDate->format('Y-m-d H:i'), $subscriptions->count()];
            if ($dryRun) {
                continue;
            }
            foreach ($subscriptions as $subscription) {
                $this->mailer->sendReminder($subscription);
            }
            $event->reminderSentAt = new DateTimeImmutable();
            $this->em->flush();
        }

        if ($rows !== []) {
            $io->table(['Event', 'Starts', $dryRun ? 'Would remind' : 'Reminded'], $rows);
        }

        return Command::SUCCESS;
    }
}
