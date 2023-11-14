<?php

namespace App\Form\Event;

use App\Entity\Event\Event;
use App\Form\ImageType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate')
            ->add('duration', DateIntervalType::class, [
                'widget' => 'integer',
                'with_years' => false,
                'with_months' => false,
                'with_days' => true,
                'with_hours' => true,
                'with_minutes' => true,
            ])
            ->add('name')
            ->add('description')
            ->add('subscriptionDeadline')
            ->add('location')
            ->add('image', ImageType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
