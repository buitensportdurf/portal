<?php

namespace App\Tests\Form\Event;

use App\Entity\Event\Question;
use App\Entity\Event\QuestionAnswer;
use App\Form\Event\QuestionAnswerType;
use Symfony\Component\Form\Test\TypeTestCase;

class QuestionAnswerTypeTest extends TypeTestCase
{
    private function answerFor(string $text, bool $required): QuestionAnswer
    {
        $question = new Question();
        $question->question = $text;
        $question->required = $required;

        $answer = new QuestionAnswer();
        $answer->question = $question;

        return $answer;
    }

    public function testAnswerFieldIsLabelledWithQuestionAndRequiredForMandatoryQuestion(): void
    {
        $form = $this->factory->create(QuestionAnswerType::class, $this->answerFor('Are you a member?', true));

        $answer = $form->get('answer');

        self::assertSame('Are you a member?', $answer->getConfig()->getOption('label')->question);
        self::assertTrue($answer->getConfig()->getOption('required'));
    }

    public function testAnswerFieldIsOptionalForOptionalQuestion(): void
    {
        $form = $this->factory->create(QuestionAnswerType::class, $this->answerFor('Any allergies?', false));

        self::assertFalse($form->get('answer')->getConfig()->getOption('required'));
    }

    public function testFormBindsValueOnSubmit(): void
    {
        $data = $this->answerFor('What size?', true);
        $form = $this->factory->create(QuestionAnswerType::class, $data);

        $form->submit(['answer' => 'M']);

        self::assertTrue($form->isSynchronized());
        self::assertSame('M', $data->answer);
    }
}
