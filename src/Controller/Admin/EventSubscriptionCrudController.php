<?php

namespace App\Controller\Admin;

use App\Entity\Event\EventSubscription;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class EventSubscriptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EventSubscription::class;
    }

    public function configureFields(string $pageName): iterable
    {
//            IdField::new('id'),
        yield IntegerField::new('amount');
        yield DateTimeField::new('createdDate');
        yield AssociationField::new('event');
        yield AssociationField::new('createdUser');
    }
}
