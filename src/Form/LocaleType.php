<?php

namespace App\Form;

use App\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * The site's languages, each named in its own language.
 */
class LocaleType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Language::class,
            'choice_label' => fn(Language $language) => $language->label(),
            'choice_translation_domain' => false,
            'label' => t('Language'),
        ]);
    }

    public function getParent(): string
    {
        return EnumType::class;
    }
}
