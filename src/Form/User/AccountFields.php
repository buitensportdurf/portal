<?php

namespace App\Form\User;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

use function Symfony\Component\Translation\t;

/**
 * The username and password fields shared by registration and guest upgrade.
 */
final class AccountFields
{
    public static function add(FormBuilderInterface $builder): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => t('Username (for login)'),
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 180),
                    // You can also log in with your email, so a username must never look like one
                    new Regex(pattern: '/^[^@\s]+$/', message: 'A username cannot contain spaces or an @.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'The password fields must match.',
                'required' => true,
                'first_options' => ['label' => t('Password'), 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => t('Repeat Password'), 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password'),
                    new Length(min: 8, max: 4096, minMessage: 'Your password should be at least {{ limit }} characters'),
                ],
            ])
        ;
    }
}
