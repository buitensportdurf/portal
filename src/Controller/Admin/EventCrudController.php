<?php

namespace App\Controller\Admin;

use App\Entity\Event\Event;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
        }

        yield TextField::new('name');
        yield TextField::new('description');
        yield TextField::new('location');
        yield DateTimeField::new('startDate');
        yield DateTimeField::new('subscriptionDeadline');
        yield AssociationField::new('tags');
    }
}
