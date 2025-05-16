<?php

namespace App\Controller;

use App\Form\User\ProfileType;
use App\Service\LastRelease;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/user', name: 'user')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/profile', name: '_profile')]
    public function profile(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        LastRelease                 $lastRelease,
    ): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);

        if ($user->isGuest()) {
            throw $this->createAccessDeniedException('You cannot access this page with a guest account');
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!empty($form->get('plainPassword')->getData())) {
                if ($form->get('plainPassword')->getData() !== $form->get('plainPasswordRepeated')->getData()) {
                    $this->addFlash('error', 'Password and password repeated must be the same, password not changed');
                } else {
                    $this->addFlash('success', 'Password updated successfully');
                    $user->setPassword(
                        $userPasswordHasher->hashPassword(
                            $user,
                            $form->get('plainPassword')->getData()
                        )
                    );
                }
            }
            $this->addFlash('success', 'Profile updated successfully');

            $this->em->persist($user);
            $this->em->flush();
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
            'release' => $lastRelease,
        ]);
    }

//    #[Route('/apikey/generate', name: '_apikey_generate')]
//    public function apiKeyGenerate(): Response
//    {
//        /** @var User $user */
//        $user = $this->getUser();
//
//        $apiKey = Uuid::v4()->toBase58();
//        $user->setApiKey($apiKey);
//        $this->em->persist($user);
//        $this->em->flush();
//
//        $this->addFlash('success', sprintf('Successfully generated new api key: "%s"', $apiKey));
//
//        return $this->redirectToRoute('user_profile');
//    }
}