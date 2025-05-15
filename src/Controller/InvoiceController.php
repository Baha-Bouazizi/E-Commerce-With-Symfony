<?php

namespace App\Controller;

use App\Entity\Order;
use App\Service\InvoiceGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends AbstractController
{
    private $invoiceGenerator;
    
    public function __construct(InvoiceGenerator $invoiceGenerator)
    {
        $this->invoiceGenerator = $invoiceGenerator;
    }
    
    /**
     * Permet de visualiser et télécharger une facture au format PDF
     * 
     * @Route("/compte/facture/{reference}", name="download_invoice")
     */
    public function downloadInvoice(Order $order): Response
    {
        // Vérifier que l'utilisateur a le droit d'accéder à cette facture
        $this->denyAccessUnlessGranted('view', $order);
        
        // Générer la facture si elle n'existe pas encore
        $pdfPath = $this->invoiceGenerator->generatePdf($order, 'Payée');
        
        // Envoyer la réponse avec le fichier PDF
        $response = new BinaryFileResponse($pdfPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            'facture-' . $order->getReference() . '.pdf'
        );
        
        return $response;
    }
    
    /**
     * Permet de générer une facture PDF pour une commande existante
     * 
     * @Route("/admin/facture/{id}", name="admin_generate_invoice")
     */
    public function generateInvoice(Order $order): Response
    {
        // Vérifier les droits d'admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Générer ou régénérer la facture
        $pdfPath = $this->invoiceGenerator->generatePdf($order, 'Générée manuellement');
        
        // Rediriger avec un message de succès
        $this->addFlash('success', 'La facture a été générée avec succès');
        
        // Envoyer la réponse avec le fichier PDF
        $response = new BinaryFileResponse($pdfPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            'facture-' . $order->getReference() . '.pdf'
        );
        
        return $response;
    }
}
