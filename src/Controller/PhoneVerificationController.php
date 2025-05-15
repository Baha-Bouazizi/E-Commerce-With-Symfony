<?php

namespace App\Controller;

use App\Service\TwilioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class PhoneVerificationController extends AbstractController
{
    private $twilioService;
    private $security;
    private $em;
    
    public function __construct(
        TwilioService $twilioService,
        Security $security,
        EntityManagerInterface $em
    ) {
        $this->twilioService = $twilioService;
        $this->security = $security;
        $this->em = $em;
    }
    
    #[Route('/compte/verification-telephone', name: 'app_phone_verification')]
    public function verifyPhone(Request $request): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Si le téléphone est déjà vérifié, rediriger vers le compte
        if ($user->isPhoneVerified()) {
            $this->addFlash('success', 'Votre numéro de téléphone est déjà vérifié.');
            return $this->redirectToRoute('account');
        }
        
        // Formulaire pour demander le numéro de téléphone
        $phoneForm = $this->createFormBuilder(null, ['attr' => ['id' => 'phone-verification-form']])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Votre numéro de téléphone',
                'attr' => [
                    'placeholder' => '+33612345678',
                    'class' => 'form-control'
                ],
                'data' => $user->getPhoneNumber()
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer le code',
                'attr' => ['class' => 'btn btn-primary', 'id' => 'phone-submit-btn']
            ])
            ->getForm();
            
        $phoneForm->handleRequest($request);
        
        // Formulaire pour la vérification du code OTP
        $otpForm = $this->createFormBuilder()
            ->add('otpCode', TextType::class, [
                'label' => 'Code de vérification',
                'attr' => [
                    'placeholder' => '123456',
                    'class' => 'form-control'
                ]
            ])
            ->add('verify', SubmitType::class, [
                'label' => 'Vérifier',
                'attr' => ['class' => 'btn btn-success']
            ])
            ->getForm();
            
        $otpForm->handleRequest($request);
        
        // Bouton temporaire pour sauter la vérification
        $skipForm = $this->createFormBuilder()
            ->add('skip', SubmitType::class, [
                'label' => 'Sauter la vérification (temporaire)',
                'attr' => ['class' => 'btn btn-warning mt-3']
            ])
            ->getForm();
            
        $skipForm->handleRequest($request);
        
        // Traitement du bouton de contournement
        if ($skipForm->isSubmitted() && $skipForm->isValid()) {
            // Marquer le téléphone comme vérifié sans code
            $user->setIsPhoneVerified(true);
            $this->em->persist($user);
            $this->em->flush();
            
            $this->addFlash('success', 'Verification téléphonique contournée temporairement.');
            return $this->redirectToRoute('account');
        }
        
        // Traitement du formulaire de téléphone
        if ($phoneForm->isSubmitted() && $phoneForm->isValid()) {
            $data = $phoneForm->getData();
            $phoneNumber = $data['phoneNumber'];
            
            // Mettre à jour le numéro de téléphone de l'utilisateur
            $user->setPhoneNumber($phoneNumber);
            $this->em->persist($user);
            $this->em->flush();
            
            // Debug du numéro de téléphone
            $debug_phone = $user->getPhoneNumber();
            error_log("Numéro de téléphone saisi: " . $debug_phone);
            
            // Envoyer le code de vérification
            $sent = $this->twilioService->sendVerificationCode($user);
            
            if ($sent) {
                $this->addFlash('success', 'Un code de vérification a été envoyé à votre numéro de téléphone.');
            } else {
                // Récupérer l'erreur de la session native
                if (isset($_SESSION['twilio_error'])) {
                    $errorMessage = $_SESSION['twilio_error'];
                    $this->addFlash('error', $errorMessage);
                    unset($_SESSION['twilio_error']);
                    
                    // Log l'erreur pour débogage
                    error_log("Erreur Twilio affichée: " . $errorMessage);
                } else {
                    $this->addFlash('error', 'Impossible d\'envoyer le code de vérification. Veuillez vérifier votre numéro et réessayer.');
                }
                
                // Pour le développement: toujours générer un code pour les tests
                $this->addFlash('info', 'Mode développement: vous pouvez utiliser le code généré dans la base de données pour tester.');
            }
            
            return $this->redirectToRoute('app_phone_verification');
        }
        
        // Traitement du formulaire OTP
        if ($otpForm->isSubmitted() && $otpForm->isValid()) {
            $data = $otpForm->getData();
            $otpCode = $data['otpCode'];
            
            $verified = $this->twilioService->verifyCode($user, $otpCode);
            
            if ($verified) {
                $this->addFlash('success', 'Votre numéro de téléphone a été vérifié avec succès !');
                return $this->redirectToRoute('account');
            } else {
                $this->addFlash('error', 'Code de vérification invalide ou expiré. Veuillez réessayer.');
            }
        }
        
        // Ajouter un indicateur de mode développement et le code OTP pour faciliter les tests
        $isDevelopment = true; // En production, mettez cette valeur à false
        
        return $this->render('account/phone_verification.html.twig', [
            'phoneForm' => $phoneForm->createView(),
            'otpForm' => $otpForm->createView(),
            'skipForm' => $skipForm->createView(),
            'user' => $user,
            'isDevelopment' => $isDevelopment,
            'otpCode' => $isDevelopment ? $user->getOtpCode() : null
        ]);
    }
}
