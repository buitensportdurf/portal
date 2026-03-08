<?php

namespace App\Command;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:test-scenarios',
    description: 'Seed test data for manual testing of confirmation modals',
)]
class SeedTestScenariosCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // --- Users ---
        $admin = $this->findOrCreateUser('testadmin', 'Test Admin', 'testadmin@example.com', ['ROLE_ADMIN']);
        $member = $this->findOrCreateUser('testmember', 'Test Member', 'testmember@example.com');
        $guest = $this->findOrCreateUser('testguest', 'Test Guest', 'testguest@example.com', guest: true);
        $disabled = $this->findOrCreateUser('testdisabled', 'Disabled User', 'disabled@example.com', enabled: false);

        $io->success('Users created: testadmin, testmember, testguest, testdisabled (password: "test" for all)');

        // --- Tag ---
        $tag = $this->em->getRepository(Tag::class)->findOneBy(['name' => 'test_modals']);
        if (!$tag) {
            $tag = new Tag();
            $tag->setName('test_modals');
            $this->em->persist($tag);
        }

        // --- Event 1: Future event with subscriptions (test unsubscribe modal) ---
        $event1 = $this->createEvent(
            'Modal Test - Unsubscribe',
            '+2 weeks',
            'Test Location',
            'Subscribe to this event, then test the unsubscribe confirmation modal.',
        );
        $event1->addTag($tag);
        $this->em->persist($event1);

        $sub1 = $this->createSubscription($event1, $member);
        $this->em->persist($sub1);

        $sub2 = $this->createSubscription($event1, $admin);
        $this->em->persist($sub2);

        $io->info('Event 1: "Modal Test - Unsubscribe" — member and admin subscribed');

        // --- Event 2: Past deadline, admin subscribed (test admin unsubscribe outside term) ---
        $event2 = $this->createEvent(
            'Modal Test - Admin Unsubscribe Past Deadline',
            '+1 week',
            'Test Location',
            'Deadline has passed. Admin unsubscribe should show confirmation modal.',
        );
        $event2->setSubscriptionDeadline(new DateTimeImmutable('-1 day'));
        $event2->addTag($tag);
        $this->em->persist($event2);

        $sub3 = $this->createSubscription($event2, $member);
        $this->em->persist($sub3);

        $io->info('Event 2: "Modal Test - Admin Unsubscribe Past Deadline" — member subscribed, deadline passed');

        // --- Event 3: Deletable event (test delete event modal) ---
        $event3 = $this->createEvent(
            'Modal Test - Delete Me',
            '+3 weeks',
            'Test Location',
            'This event exists to test the delete confirmation modal.',
        );
        $event3->addTag($tag);
        $this->em->persist($event3);

        $io->info('Event 3: "Modal Test - Delete Me" — for testing delete modal');

        // --- Event 4: Guest-allowed event (test guest subscribe flow) ---
        $event4 = $this->createEvent(
            'Modal Test - Guest Event',
            '+2 weeks',
            'Test Location',
            'Guests are allowed to subscribe to this event.',
        );
        $event4->setGuestsAllowed(true);
        $event4->addTag($tag);
        $this->em->persist($event4);

        $sub4 = $this->createSubscription($event4, $guest);
        $this->em->persist($sub4);

        $io->info('Event 4: "Modal Test - Guest Event" — guest subscribed, guests allowed');

        // --- Recurring Event (test delete events modal) ---
        $recurring = new RecurringEvent();
        $recurring->setName('Modal Test - Recurring Weekly');
        $recurring->setLocation('Test Location');
        $recurring->setStartDate(new DateTimeImmutable('-2 weeks'));
        $recurring->setDuration(new DateInterval('PT2H'));
        $recurring->setRecurrenceRule('1 week');
        $recurring->addTag($tag);
        $this->em->persist($recurring);

        for ($i = 0; $i < 4; $i++) {
            $child = $recurring->createNextEvent();
            $child->addTag($tag);
            $this->em->persist($child);
        }

        $io->info('Recurring Event: "Modal Test - Recurring Weekly" — 4 child events created');

        $this->em->flush();

        $io->success('All test scenarios seeded. Filter by tag "test_modals" to find them.');
        $io->table(
            ['Scenario', 'What to test'],
            [
                ['Unsubscribe (member)', 'Log in as testmember, unsubscribe from "Modal Test - Unsubscribe"'],
                ['Unsubscribe (admin, outside term)', 'Log in as testadmin, unsubscribe member from event with past deadline'],
                ['Delete event', 'Log in as testadmin, delete "Modal Test - Delete Me"'],
                ['Delete recurring events', 'Log in as testadmin, go to recurring event, click "Delete events"'],
                ['Delete recurring event', 'Log in as testadmin, edit recurring event, click "Delete"'],
                ['Enable user', 'Log in as testadmin, go to admin user list, enable "Disabled User"'],
                ['Guest unsubscribe', 'Log in as testguest, unsubscribe from "Modal Test - Guest Event"'],
            ],
        );

        return Command::SUCCESS;
    }

    private function findOrCreateUser(
        string $username,
        string $name,
        string $email,
        array  $roles = [],
        bool   $guest = false,
        bool   $enabled = true,
    ): User {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($user) {
            return $user;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setName($name);
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setGuest($guest);
        $user->setEnabled($enabled);
        $user->setPassword($this->hasher->hashPassword($user, 'test'));
        $this->em->persist($user);

        return $user;
    }

    private function createEvent(string $name, string $startDate, string $location, string $description): Event
    {
        $event = new Event();
        $event->setName($name);
        $event->setStartDate(new DateTimeImmutable($startDate));
        $event->setDuration(new DateInterval('PT2H'));
        $event->setLocation($location);
        $event->setDescription($description);

        return $event;
    }

    private function createSubscription(Event $event, User $user): EventSubscription
    {
        $sub = new EventSubscription();
        $sub->setCreatedUser($user);
        $sub->setCreatedDateNowNoSeconds();
        $sub->setAmount(1);
        $sub->setEvent($event);

        return $sub;
    }
}
