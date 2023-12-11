<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\EmailFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(
    name: 'app:test:mail',
    description: 'Add a short description for your command',
)]
class TestMailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository  $userRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username of the user to send the test email to');
//            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
//        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->userRepository->findOneBy(['username' => $input->getArgument('username')]);
        $email = EmailFactory::signupEmail($user);

        //->cc('cc@example.com')
        //->bcc('bcc@example.com')
        //->replyTo('fabien@example.com')
        //->priority(Email::PRIORITY_HIGH)

        $this->mailer->send($email);

        $io->success('Send test email to ' . $user->getEmail());

        return Command::SUCCESS;
    }
}
