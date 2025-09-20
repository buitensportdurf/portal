<?php

namespace App\Form\Event;

use App\Entity\Event\EventSubscription;
use App\Entity\Event\Question;
use App\Entity\Event\QuestionAnswer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestionAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $builder = $event->getForm();

            /** @var QuestionAnswer $data */
            $data = $event->getData();

            $builder
                ->add('answer', null, [
                    'label' => $data->question,
                    'required' => $data->question->required,
                ])
            ;
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuestionAnswer::class,
            'question' => null,
        ]);
    }
}
