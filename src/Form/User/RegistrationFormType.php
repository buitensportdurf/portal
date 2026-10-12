<?php

namespace App\Form\User;

use App\Entity\User;
use App\Form\HoneypotType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => t('Full name (first and last name)')])
            ->add('email', EmailType::class, [
                'label' => t('Email'),
                'help' => t('Needed for activation, password resets and event updates'),
                'constraints' => [new NotBlank(), new Email()],
            ])
        ;
        AccountFields::add($builder);
        $builder
            ->add('website', HoneypotType::class)
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'You should agree to our terms.',
                    ),
                ],
                'label' => t('I agree to the privacy statement found below'),
            ])
            ->add('register', SubmitType::class, ['label' => t('Register')])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
