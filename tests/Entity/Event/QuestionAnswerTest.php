<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\Event\Question;
use App\Entity\Event\QuestionAnswer;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class QuestionAnswerTest extends TestCase
{
    private function createSubscription(): EventSubscription
    {
        $event = new Event();
        $event->name = 'Event';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT2H');

        $user = new User();
        $user->username = 'user';
        $user->name = 'User';
        $user->password = 'hashed';

        $sub = new EventSubscription();
        $sub->createdUser = $user;
        $sub->amount = 1;
        $sub->event = $event;

        return $sub;
    }

    // --- Question ---

    public function testQuestionToString(): void
    {
        $q = new Question();
        $q->question = 'Do you have allergies?';
        self::assertSame('Do you have allergies?', (string) $q);
    }

    public function testQuestionToStringNullReturnsEmpty(): void
    {
        $q = new Question();
        self::assertSame('', (string) $q);
    }

    public function testQuestionAnswersEmptyByDefault(): void
    {
        $q = new Question();
        self::assertCount(0, $q->answers);
    }

    // --- QuestionAnswer ---

    public function testAnswerToString(): void
    {
        $qa = new QuestionAnswer();
        $qa->answer = 'No allergies';
        self::assertSame('No allergies', (string) $qa);
    }

    public function testAnswerToStringNullReturnsDash(): void
    {
        $qa = new QuestionAnswer();
        self::assertSame('-', (string) $qa);
    }

    public function testSettingSubscriptionAddsToCollection(): void
    {
        $sub = $this->createSubscription();
        $qa = new QuestionAnswer();
        $qa->subscription = $sub;

        self::assertTrue($sub->questionAnswers->contains($qa));
    }

    public function testSettingSubscriptionIdempotent(): void
    {
        $sub = $this->createSubscription();
        $qa = new QuestionAnswer();
        $qa->subscription = $sub;
        $qa->subscription = $sub; // set again

        $count = $sub->questionAnswers->filter(fn($item) => $item === $qa)->count();
        self::assertSame(1, $count);
    }

    public function testQuestionAndAnswerRelationship(): void
    {
        $q = new Question();
        $q->question = 'Dietary restrictions?';
        $q->required = true;
        $q->type = 'text';

        $qa = new QuestionAnswer();
        $qa->question = $q;
        $qa->answer = 'Vegetarian';

        self::assertSame($q, $qa->question);
        self::assertSame('Vegetarian', $qa->answer);
    }
}
