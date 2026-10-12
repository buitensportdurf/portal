<?php

namespace App\Form\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Question;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', null, ['label' => t('Question')])
            ->add('required', null, ['label' => t('Required')])
            ->add('type', ChoiceType::class, [
                'label' => t('Type'),
                'choices' => ['text'],
                'choice_label' => fn(string $type) => match ($type) {
                    'text' => t('Open'),
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
        ]);
    }
}
