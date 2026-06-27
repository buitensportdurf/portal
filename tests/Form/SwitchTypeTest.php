<?php

namespace App\Tests\Form;

use App\Form\SwitchType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Test\TypeTestCase;

class SwitchTypeTest extends TypeTestCase
{
    public function testInheritsFromCheckbox(): void
    {
        self::assertSame(CheckboxType::class, (new SwitchType())->getParent());
    }

    public function testViewHasSwitchClassAndIsNotRequired(): void
    {
        $view = $this->factory->create(SwitchType::class)->createView();

        self::assertStringContainsString('checkbox-switch', $view->vars['label_attr']['class']);
        self::assertFalse($view->vars['required']);
    }

    public function testPreservesExistingLabelClass(): void
    {
        $view = $this->factory
            ->create(SwitchType::class, null, ['label_attr' => ['class' => 'mb-2']])
            ->createView();

        self::assertSame('mb-2 checkbox-switch', $view->vars['label_attr']['class']);
    }
}
