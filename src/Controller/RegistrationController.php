<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\RegistrationFormType;
use App\Repository\UsersRepository;
use App\Security\UsersAuthenticator;
use App\Service\JWTService;
use App\Service\SendMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, UsersAuthenticator $authenticator, EntityManagerInterface $entityManager, SendMailService $mail, JWTService $jwt): Response
    {
        // Création d'un nouvel utilisateur et du formulaire d'inscription
        $user = new Users();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        // Lors de la soumission du formulaire et si les champs du formulaire est valide
        if ($form->isSubmitted() && $form->isValid()) {
            
            // Récupération et hashage du mot de passe de l'utilisateur  
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            // Prépare la données
            $entityManager->persist($user);
            // Ajoute en base de données
            $entityManager->flush();

            // Préparation et génération du token de sécurité
            $header = ['typ' => 'JWT', 'alg' => 'HS256'];
            $payload = ['user_id' => $user->getId()];
            $token = $jwt->generate($header, $payload, $this->getParameter('app.jwtsecret'));
            // Envoi de mail activation du compte
            $mail->send('no-reply@addToCard.com', $user->getEmail(), 'Activation Compte', 'register', compact('user', 'token'));

            // On authentifie l'utilisateur
            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verif/{token}', name: 'verify_user')]
    public function verifyUser($token, JWTService $jwt, UsersRepository $usersrepository, EntityManagerInterface $em): Response
    {
        if($jwt->isValid($token) && !$jwt->isExpired($token) && $jwt->checkSignature($token, $this->getParameter('app.jwtsecret')))
        {
            $payload = $jwt->getPayload($token);
            
            $user = $usersrepository->find($payload['user_id']);
            
            if($user && !$user->getIsVerified()){
                $user->setIsVerified(true);
                $em->flush($user);
                
                $this->addFlash('success', 'Validation du compte effectué !');
                return $this->redirectToRoute('profile_index');
            }
            
        }
        
        $this->addFlash('danger', 'Le token est invalide ou a expiré');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/returnvalidation', name: 'resend_verif')]
    public function resendVerif(JWTService $jwt, SendMailService $mail, UsersRepository $usersRepository): Response
    {
        $user = $this->getUser();
        if(!$user){
            $this->addFlash('danger', 'Vous devez être connecté pour accéder à cet page');
            return $this->redirectToRoute('app_login');
        }
        if($user->getIsVerified()){
            $this->addFlash('warning', 'Cet utilisateur est déja activé !');
            return $this->redirectToRoute('profile_index');
        }
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = ['user_id' => $user->getId()];
        $token = $jwt->generate($header, $payload, $this->getParameter('app.jwtsecret'));
        // do anything else you need here, like send an email
        $mail->send('no-reply@mon-site.com', $user->getEmail(), 'Activation Compte', 'register', compact('user', 'token'));

        $this->addFlash('success', 'Email de vérification envoyé');
            return $this->redirectToRoute('app_main');
    }
}
