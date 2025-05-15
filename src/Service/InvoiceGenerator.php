<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class InvoiceGenerator
{
    private $twig;
    private $projectDir;
    private $pdfGenerator;
    
    public function __construct(Environment $twig, string $projectDir, PdfGenerator $pdfGenerator)
    {
        $this->twig = $twig;
        $this->projectDir = $projectDir;
        $this->pdfGenerator = $pdfGenerator;
    }
    
    /**
     * Generates HTML invoice for an order
     * 
     * @param Order $order The order to generate an invoice for
     * @param string $stateLabel Current state label of the order
     * @return string HTML content
     */
    public function generateInvoiceHtml(Order $order, string $stateLabel = ''): string
    {
        // Create HTML content for the invoice using Twig
        $html = $this->twig->render('emails/invoice.html.twig', [
            'order' => $order,
            'date' => new \DateTime(),
            'stateLabel' => $stateLabel
        ]);
        
        return $html;
    }
    
    /**
     * Prepares an Email object with the invoice as PDF attachment
     * 
     * @param Order $order The order
     * @param string $recipientEmail Email address of the recipient
     * @param string $subject Email subject
     * @param string $stateLabel Current state label of the order
     * @return Email Email object ready to be sent
     */
    public function createInvoiceEmail(Order $order, string $recipientEmail, string $subject, string $stateLabel = ''): Email
    {
        // Générer le HTML pour le corps de l'email
        $htmlEmail = $this->twig->render('emails/order_update.html.twig', [
            'order' => $order,
            'date' => new \DateTime(),
            'stateLabel' => $stateLabel
        ]);
        
        // Version texte de l'email (simplifiée)
        $text = 'Facture #' . $order->getReference() . "\n\n";
        $text .= 'Date: ' . (new \DateTime())->format('d/m/Y') . "\n";
        $text .= 'Client: ' . $order->getUser()->getFullname() . "\n";
        $text .= 'Statut de la commande: ' . $stateLabel . "\n";
        $text .= 'Total: ' . number_format($order->getTotal()/100, 2, ',', ' ') . ' €' . "\n\n";
        $text .= 'Merci pour votre commande!';
        
        // Générer le fichier PDF de la facture
        $pdfPath = $this->generatePdf($order, $stateLabel);
        
        // Créer l'email avec le PDF en pièce jointe
        $email = new Email();
        $email
            ->from('contact@labootique.fr')
            ->to($recipientEmail)
            ->subject($subject)
            ->html($htmlEmail)
            ->text($text)
            ->attachFromPath($pdfPath, 'facture-'.$order->getReference().'.pdf', 'application/pdf');
        
        return $email;
    }
    
    /**
     * Generate a real PDF invoice using PdfGenerator service
     * 
     * @param Order $order Order entity
     * @param string $stateLabel Current state label of the order
     * @return string Path to the generated PDF file
     */
    public function generatePdf(Order $order, string $stateLabel = ''): string
    {
        // Utiliser le service PdfGenerator pour générer un vrai PDF
        return $this->pdfGenerator->generateInvoicePdf($order, $stateLabel);
    }
}
