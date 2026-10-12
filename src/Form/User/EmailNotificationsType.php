<?php

namespace App\Form\User;

use App\Entity\EmailNotification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class EmailNotificationsType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => EmailNotification::class,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'label' => t('Email me about events I subscribed to'),
            'choice_label' => fn(EmailNotification $notification) => $notification->label(),
            'help' => t('Emails about your account, like a changed password, are always sent.'),
        ]);
    }

    public function getParent(): string
    {
        return EnumType::class;
    }
}
