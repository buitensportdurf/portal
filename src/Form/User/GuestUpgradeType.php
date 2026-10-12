<?php

namespace App\Form\User;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GuestUpgradeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        AccountFields::add($builder);
        $builder
            ->add('name', TextType::class, ['label' => 'Full name (first and last name)'])
            ->add('save', SubmitType::class, ['label' => 'Create account'])
        ;
        // Guests get a generated username; start the member account with an empty one
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (PostSetDataEvent $event) {
            $event->getForm()->get('username')->setData(null);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
