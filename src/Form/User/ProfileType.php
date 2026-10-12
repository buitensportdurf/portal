<?php

namespace App\Form\User;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The email is not mapped: a new address only takes effect once it is confirmed.
 */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Full name'])
            ->add('newEmail', EmailType::class, [
                'label' => 'Email',
                'mapped' => false,
                'data' => $options['current_email'],
                'help' => 'A new address has to be confirmed via a link we send to it',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'The password fields must match.',
                'first_options' => ['label' => 'New password', 'attr' => ['autocomplete' => 'new-password'], 'help' => 'Leave empty to keep your current password'],
                'second_options' => ['label' => 'Repeat new password', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new Length(min: 8, max: 4096, minMessage: 'Your password should be at least {{ limit }} characters'),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current password',
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'help' => 'Needed to change your email or password',
            ])
            ->add('emailNotifications', EmailNotificationsType::class)
            ->add('save', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'current_email' => null,
        ]);
    }
}
