<?php

namespace App\Form;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * The site's languages, each named in its own language.
 */
class LocaleType extends AbstractType
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $locales,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];
        foreach ($this->locales as $locale) {
            $choices[ucfirst(Locales::getName($locale, $locale))] = $locale;
        }

        $resolver->setDefaults([
            'choices' => $choices,
            'choice_translation_domain' => false,
            'label' => t('Language'),
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
