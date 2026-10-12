<?php

namespace App\Form\User;

use App\Entity\Group;
use App\Entity\User;
use App\Form\HoneypotType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

class GuestForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => t('Name')])
            ->add('email', EmailType::class, [
                'label' => t('Email'),
                'help' => t('We email you a link to finish subscribing'),
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('website', HoneypotType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
