<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterType;
use App\Security\LoginAuthenticator;
use App\Service\Mail;
use App\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * Formulaire d'inscription
 * Une fois inscrit, l'utilisateur est automatiquement authentifié.
 */
class RegisterController extends AbstractController
{
    private TwilioService $twilioService;
    private Mail $mailer;

    public function __construct(TwilioService $twilioService, Mail $mailer)
    {
        $this->twilioService = $twilioService;
        $this->mailer = $mailer;
    }

    #[Route('/inscription', name: 'register')]
    public function index(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginAuthenticator $authenticator,
        EntityManagerInterface $em
    ): Response {
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword($user, $form->get('password')->getData());
            $user->setPassword($hashedPassword);

            // Ensure phone is marked as not verified before saving
            $user->setIsPhoneVerified(false);

            $em->persist($user);
            $em->flush();

            // Send confirmation email
            $content = sprintf("Bonjour %s, nous vous remercions de votre inscription.", $user->getFirstname());
            $this->mailer->send($user->getEmail(), $user->getFirstname(), "Bienvenue sur la Boot'ique", $content);

            // Authenticate the user first
            $userAuthenticator->authenticateUser($user, $authenticator, $request);
            
            // Log pour débogage
            error_log("Utilisateur authentifié: " . $user->getEmail() . " avec téléphone: " . $user->getPhoneNumber());
            
            // Ne pas envoyer de code ici, ce sera fait dans le contrôleur de vérification
            // pour éviter les duplications et donner une meilleure expérience utilisateur
            
            // Ajouter un message d'information
            $this->addFlash('success', 'Votre compte a été créé avec succès. Veuillez maintenant vérifier votre numéro de téléphone.');
            
            // Rediriger explicitement vers la vérification téléphonique
            return $this->redirectToRoute('app_phone_verification');
        }

        return $this->renderForm('register/index.html.twig', [
            'form' => $form,
        ]);
    }
}
