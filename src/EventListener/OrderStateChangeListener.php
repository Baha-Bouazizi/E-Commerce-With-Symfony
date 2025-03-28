<?php

namespace App\EventListener;

use App\Entity\Order;
use App\Service\SmtpMailService;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class OrderStateChangeListener
{
    private SmtpMailService $mailService;

    public function __construct(SmtpMailService $mailService)
    {
        $this->mailService = $mailService;
    }

    // Déclenché avant une mise à jour en base
    public function preUpdate(Order $order, PreUpdateEventArgs $event): void
    {
        // Vérifie si le champ "state" a changé
        if ($event->hasChangedField('state')) {
            $oldState = $event->getOldValue('state');
            $newState = $event->getNewValue('state');

            if ($oldState !== $newState) {
                $this->sendEmailConfirmation($order);
            }
        }
    }

    private function sendEmailConfirmation(Order $order): void
    {
        $user = $order->getUser();
        
        // Version texte
        $textContent = "Bonjour {$user->getFirstname()},\n\nVotre commande (Référence : {$order->getReference()}) a changé de statut.\n\n";
        $textContent .= "Détails de la commande :\n";
        
        foreach ($order->getOrderDetails() as $item) {
            $textContent .= "- {$item->getProduct()} x {$item->getQuantity()} (prix : {$item->getPrice()} EUR)\n";
        }

        $textContent .= "\nFrais de livraison : {$order->getCarrierPrice()} EUR\n";
        $textContent .= "Total : {$order->getTotal()} EUR\n";
        $textContent .= "Nouvel état de la commande : " . $this->getStateLabel($order->getState()) . "\n";

        // Version HTML
        $htmlContent = "<p>Bonjour {$user->getFirstname()},</p>
                      <p>Votre commande (Référence : <strong>{$order->getReference()}</strong>) a changé de statut.</p>
                      <h3>Détails de la commande :</h3>
                      <ul>";

        foreach ($order->getOrderDetails() as $item) {
            $htmlContent .= "<li>{$item->getProduct()} x {$item->getQuantity()} (prix : {$item->getPrice()} EUR)</li>";
        }

        $htmlContent .= "</ul>
                       <p>Frais de livraison : {$order->getCarrierPrice()} EUR</p>
                       <p><strong>Total : {$order->getTotal()} EUR</strong></p>
                       <p>Nouvel état de la commande : <strong>" . $this->getStateLabel($order->getState()) . "</strong></p>";

        $this->mailService->send(
            $user->getEmail(),
            $user->getFirstname(),
            "Mise à jour de votre commande {$order->getReference()}",
            $textContent,
            $htmlContent
        );
    }

    private function getStateLabel(int $state): string
    {
        return match ($state) {
            0 => 'Non payée',
            1 => 'Payée',
            2 => 'Préparation en cours',
            3 => 'Expédiée',
            default => 'Inconnu',
        };
    }
}