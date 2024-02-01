<?php

namespace App\Controller\Admin;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\Event\Tag;
use App\Entity\Group;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action as AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator->setController(UserCrudController::class)->generateUrl());

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Durf Admin Page');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoRoute('Back to home', 'fas fa-home', 'home');

        yield MenuItem::section('Administration', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('Users', 'fas fa-list', User::class);
        yield MenuItem::linkToCrud('Groups', 'fas fa-list', Group::class);

        yield MenuItem::section('Events', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('Events', 'fas fa-list', Event::class);
        yield MenuItem::linkToCrud('Event subscriptions', 'fas fa-list', EventSubscription::class);
        yield MenuItem::linkToCrud('Tags', 'fas fa-list', Tag::class);
    }

    public function configureCrud(): Crud
    {
        $crud = Crud::new();
        $crud->showEntityActionsInlined();
        return $crud;
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, AdminAction::DETAIL)
            ->update(Crud::PAGE_EDIT, AdminAction::SAVE_AND_RETURN, function (AdminAction $action) {
                return $action->setIcon('fa fa-save');
            })
            ->add(Crud::PAGE_EDIT, AdminAction::DELETE)
            ->add(Crud::PAGE_EDIT, AdminAction::DETAIL)
            ->update(Crud::PAGE_EDIT, AdminAction::DETAIL, function (AdminAction $action) {
                return $action->setIcon('fa fa-info')->setLabel('Details');
            })
            ->update(Crud::PAGE_DETAIL, AdminAction::EDIT, function (AdminAction $action) {
                return $action->setIcon('fa fa-edit');
            })
            ->update(Crud::PAGE_DETAIL, AdminAction::INDEX, function (AdminAction $action) {
                return $action->setIcon('fa fa-arrow-left');
            })
            ->update(Crud::PAGE_INDEX, AdminAction::EDIT, function (AdminAction $action) {
                return $action->setIcon('fa fa-edit');
            })
            ->update(Crud::PAGE_INDEX, AdminAction::NEW, function (AdminAction $action) {
                return $action->setIcon('fa fa-plus');
            })
            ->update(Crud::PAGE_INDEX, AdminAction::DETAIL, function (AdminAction $action) {
                return $action->setIcon('fa fa-info')->setLabel('Details');
            })
            ->update(Crud::PAGE_INDEX, AdminAction::DELETE, function (AdminAction $action) {
                return $action->setIcon('fa fa-trash');
            });
    }
}
