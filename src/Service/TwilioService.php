<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twilio\Rest\Client;

class TwilioService
{
    private $twilio;
    private $twilioPhoneNumber;
    private $em;
    
    public function __construct(
        ParameterBagInterface $params,
        EntityManagerInterface $entityManager
    ) {
        // Ces valeurs devraient être définies dans votre fichier .env
        $sid = $params->get('twilio_account_sid');
        $token = $params->get('twilio_auth_token');
        $this->twilioPhoneNumber = $params->get('twilio_phone_number');
        
        if ($sid && $token) {
            $this->twilio = new Client($sid, $token);
        }
        
        $this->em = $entityManager;
    }
    
    /**
     * Génère un code OTP pour un utilisateur et l'envoie par SMS
     */
    public function sendVerificationCode(User $user): bool
    {
        if (!$this->twilio) {
            // Log message si Twilio n'est pas configuré
            return false;
        }
        
        // Générer un code à 6 chiffres
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Sauvegarder le code dans la base de données
        $user->setOtpCode($code);
        
        // Définir l'expiration (15 minutes)
        $expires = new \DateTime();
        $expires->modify('+15 minutes');
        $user->setOtpExpires($expires);
        
        $this->em->persist($user);
        $this->em->flush();
        
        // Préparer le message
        $message = "Votre code de vérification pour La Boot'ique est: " . $code . ". Il expire dans 15 minutes.";
        
        try {
            // Vérifier que le numéro de téléphone est présent
            if (empty($user->getPhoneNumber())) {
                // Utiliser le système de session Symfony via $_SESSION global (pas idéal mais fonctionnel)
                $_SESSION['twilio_error'] = 'Erreur: numéro de téléphone manquant';
                return false;
            }
            
            // Nettoyer le numéro de téléphone (enlever les espaces)
            $phoneNumber = preg_replace('/\s+/', '', $user->getPhoneNumber());
            
            // Log pour débogage
            error_log("Envoi de SMS à " . $phoneNumber . " depuis " . $this->twilioPhoneNumber);
            
            // Envoyer le SMS via Twilio
            $this->twilio->messages->create(
                $phoneNumber,  // Le numéro de téléphone du destinataire
                [
                    'from' => $this->twilioPhoneNumber,  // Votre numéro Twilio
                    'body' => $message
                ]
            );
            
            return true;
        } catch (\Exception $e) {
            // Log l'erreur pour débogage
            error_log("Erreur Twilio: " . $e->getMessage());
            
            // Stocker l'erreur dans la session pour l'afficher à l'utilisateur
            // Utiliser le système de session Symfony via $_SESSION global (pas idéal mais fonctionnel)
            $_SESSION['twilio_error'] = 'Erreur Twilio: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Vérifie si le code OTP fourni est valide pour l'utilisateur
     */
    public function verifyCode(User $user, string $code): bool
    {
        // Vérifier si le code est valide et non expiré
        if ($user->getOtpCode() === $code && $user->isOtpValid()) {
            // Marquer le téléphone comme vérifié
            $user->setIsPhoneVerified(true);
            
            // Effacer le code OTP
            $user->setOtpCode(null);
            $user->setOtpExpires(null);
            
            $this->em->persist($user);
            $this->em->flush();
            
            return true;
        }
        
        return false;
    }
}
