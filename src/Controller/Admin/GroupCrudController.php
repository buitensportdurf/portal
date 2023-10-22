<?php

namespace App\Controller\Admin;

use App\Entity\Group;
use App\Service\RoleService;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GroupCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RoleService $roles,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name');
        yield ChoiceField::new('roles')->allowMultipleChoices()->setChoices($this->roles->getRoleChoices());
    }
}
