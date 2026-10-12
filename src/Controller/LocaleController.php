<?php

namespace App\Controller;

use App\Entity\User;
use App\EventListener\LocaleListener;
use App\Security\LocalPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    /**
     * @param list<string> $locales
     */
    #[Route('/language/{locale}', name: 'locale_switch')]
    public function switch(
        Request                $request,
        string                 $locale,
        EntityManagerInterface $em,
        #[Autowire(param: 'kernel.enabled_locales')]
        array                  $locales,
    ): Response
    {
        if (!in_array($locale, $locales, true)) {
            throw $this->createNotFoundException();
        }

        $request->getSession()->set(LocaleListener::SESSION_KEY, $locale);
        $user = $this->getUser();
        if ($user instanceof User && !$this->isGranted('IS_IMPERSONATOR')) {
            $user->locale = $locale;
            $em->flush();
        }

        return $this->redirect(LocalPath::filter($request->query->get('return')) ?? $this->generateUrl('home'));
    }
}
