<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class SwitchType extends AbstractType
{
    public function getParent(): string
    {
        return CheckboxType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['label_attr']['class'] = trim(($view->vars['label_attr']['class'] ?? '') . ' checkbox-switch');
        $view->vars['required'] = false;
    }
}
