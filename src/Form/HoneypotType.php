<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field people never see but form-filling bots do. Controllers treat a filled-in value as spam.
 */
class HoneypotType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'label' => 'Website',
            'row_attr' => ['style' => 'position: absolute; left: -10000px;', 'aria-hidden' => 'true'],
            'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
