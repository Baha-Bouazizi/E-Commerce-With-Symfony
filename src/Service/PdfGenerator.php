<?php

namespace App\Service;

use Twig\Environment;
use App\Entity\Order;

class PdfGenerator
{
    private $twig;
    private $projectDir;
    
    public function __construct(Environment $twig, string $projectDir)
    {
        $this->twig = $twig;
        $this->projectDir = $projectDir;
    }
    
    /**
     * Génère un PDF à partir d'un template Twig
     * 
     * @param string $template Le template Twig à utiliser
     * @param array $data Les données à passer au template
     * @return string Chemin du fichier PDF généré
     */
    public function generatePdf(string $template, array $data): string
    {
        // Générer le HTML à partir du template
        $html = $this->twig->render($template, $data);
        
        // Créer le répertoire des factures s'il n'existe pas
        $invoiceDir = $this->projectDir . '/public/invoices';
        if (!is_dir($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        
        // Définir le nom de fichier unique basé sur timestamp et référence si disponible
        $reference = isset($data['order']) && method_exists($data['order'], 'getReference') 
            ? $data['order']->getReference() 
            : '';
            
        $filename = $invoiceDir . '/facture_' . ($reference ? $reference . '_' : '') . uniqid() . '.pdf';
        
        // Convertir HTML en PDF
        $this->htmlToPdf($html, $filename);
        
        return $filename;
    }
    
    /**
     * Génère un PDF de facture pour une commande
     * 
     * @param Order $order La commande pour laquelle générer la facture
     * @param string $stateLabel Le libellé de l'état actuel de la commande
     * @return string Chemin vers le fichier PDF généré
     */
    public function generateInvoicePdf(Order $order, string $stateLabel = ''): string
    {
        return $this->generatePdf('emails/invoice.html.twig', [
            'order' => $order,
            'date' => new \DateTime(),
            'stateLabel' => $stateLabel
        ]);
    }
    
    /**
     * Convertit du HTML en PDF en utilisant principalement Dompdf ou des alternatives
     * 
     * @param string $html Le contenu HTML
     * @param string $outputPath Le chemin de sortie du fichier PDF
     * @return void
     */
    private function htmlToPdf(string $html, string $outputPath): void
    {
        // Ajouter les styles et en-têtes nécessaires pour un document PDF
        $fullHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture</title>
    <style>
        @page { margin: 0.5cm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 20px; }
        .invoice-title { font-size: 24px; color: #3498db; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background-color: #f8f9fa; text-align: left; padding: 10px; border-bottom: 2px solid #ddd; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #f8f9fa; }
    </style>
</head>
<body>' . $html . '</body>
</html>';

        // Tenter d'utiliser Dompdf en priorité
        if (class_exists('\Dompdf\Dompdf')) {
            try {
                $this->createPdfWithDompdf($fullHtml, $outputPath);
                return;
            } catch (\Exception $e) {
                // Si Dompdf échoue, on continue avec les autres méthodes
                error_log("Erreur Dompdf: " . $e->getMessage());
            }
        }
        
        // Essayer d'utiliser mPDF si Dompdf n'est pas disponible ou a échoué
        if (class_exists('\Mpdf\Mpdf')) {
            try {
                $this->createPdfWithMpdf($fullHtml, $outputPath);
                return;
            } catch (\Exception $e) {
                error_log("Erreur mPDF: " . $e->getMessage());
            }
        }
        
        // Essayer wkhtmltopdf en dernier recours
        if ($this->isWkhtmltopdfInstalled()) {
            try {
                $this->createPdfWithWkhtmltopdf($fullHtml, $outputPath);
                return;
            } catch (\Exception $e) {
                error_log("Erreur wkhtmltopdf: " . $e->getMessage());
            }
        }
        
        // Si toutes les méthodes ont échoué, utiliser une méthode de secours
        $this->createCustomPdf($fullHtml, $outputPath);
    }
    
    /**
     * Vérifie si wkhtmltopdf est installé sur le système
     * 
     * @return bool
     */
    private function isWkhtmltopdfInstalled(): bool
    {
        // Vérifier sous Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return file_exists('C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe');
        }
        
        // Vérifier sous Linux/Mac
        $result = shell_exec('which wkhtmltopdf');
        return !empty($result);
    }
    
    /**
     * Crée un PDF en utilisant mPDF
     * 
     * @param string $html Contenu HTML
     * @param string $outputPath Chemin de sortie
     */
    private function createPdfWithMpdf(string $html, string $outputPath): void
    {
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($html);
        $mpdf->Output($outputPath, 'F');
    }
    
    /**
     * Crée un PDF en utilisant Dompdf
     * 
     * @param string $html Contenu HTML
     * @param string $outputPath Chemin de sortie
     */
    private function createPdfWithDompdf(string $html, string $outputPath): void
    {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        
        file_put_contents($outputPath, $dompdf->output());
    }
    
    /**
     * Crée un PDF en utilisant wkhtmltopdf
     * 
     * @param string $html Contenu HTML
     * @param string $outputPath Chemin de sortie
     */
    private function createPdfWithWkhtmltopdf(string $html, string $outputPath): void
    {
        // Créer un fichier HTML temporaire
        $tempHtmlFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.html';
        file_put_contents($tempHtmlFile, $html);
        
        // Déterminer le chemin de wkhtmltopdf selon l'OS
        $wkhtmltopdfBin = 'wkhtmltopdf';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $wkhtmltopdfBin = 'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe';
        }
        
        // Exécuter wkhtmltopdf
        $command = sprintf('%s %s %s', 
            escapeshellarg($wkhtmltopdfBin),
            escapeshellarg($tempHtmlFile),
            escapeshellarg($outputPath)
        );
        
        shell_exec($command);
        
        // Supprimer le fichier temporaire
        unlink($tempHtmlFile);
    }
    
    /**
     * Crée un PDF simplifié (en dernier recours)
     * Utilise une méthode native pour générer un PDF très basique
     * 
     * @param string $html Contenu HTML
     * @param string $outputPath Chemin de sortie
     */
    private function createCustomPdf(string $html, string $outputPath): void
    {
        // Convertir les caractères spéciaux pour éviter les problèmes d'encodage
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        // En-têtes PDF de base
        $pdfContent = "%PDF-1.4
";
        
        // Convertir le HTML en texte simple
        $text = strip_tags($html);
        $text = str_replace(array("
", "", "
"), " ", $text);
        
        // Ajouter du contenu minimal pour que ce soit un fichier PDF valide
        $pdfContent .= "1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
";
        $pdfContent .= "2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
";
        $pdfContent .= "3 0 obj
<< /Type /Page /Parent 2 0 R /Resources 4 0 R /MediaBox [0 0 595 842] /Contents 5 0 R >>
endobj
";
        $pdfContent .= "4 0 obj
<< /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >>
endobj
";
        
        // Contenu de la page (texte brut)
        $content = "BT
/F1 12 Tf
36 806 Td
(Facture) Tj
ET
";
        $content .= "BT
/F1 10 Tf
36 780 Td
($text) Tj
ET
";
        
        $pdfContent .= "5 0 obj
<< /Length " . strlen($content) . " >>
stream
$content
endstream
endobj
";
        
        // Finaliser le PDF
        $pdfContent .= "xref
0 6
0000000000 65535 f
0000000015 00000 n
0000000066 00000 n
0000000125 00000 n
0000000226 00000 n
0000000336 00000 n
trailer
<< /Size 6 /Root 1 0 R >>
startxref
465
%%EOF";
        
        // Écrire le PDF dans le fichier
        file_put_contents($outputPath, $pdfContent);
    }
}
