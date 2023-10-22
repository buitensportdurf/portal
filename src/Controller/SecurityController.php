<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\User\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        // controller can be blank: it will never be called!
        throw new Exception('Don\'t forget to activate logout in security.yaml');
    }

    #[Route('/register', name: 'register')]
    public function register(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository              $repository,
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('plainPassword')->getData() !== $form->get('plainPasswordRepeated')->getData()) {
                $this->addFlash('error', 'Password and password repeated must be the same');
            } else {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                )
                    ->setEnabled(false);

                $repository->add($user);
                // do anything else you need here, like send an email
                $this->addFlash('success', sprintf('Successfully registered user %s', $user->getUsername()));

                return $this->redirectToRoute('register');
            }
        }

        return $this->render('user/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
