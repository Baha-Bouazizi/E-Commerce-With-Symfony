<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class StockNotifier
{
    private $mailer;
    private $productRepository;
    private $adminEmail;

    public function __construct(MailerInterface $mailer, ProductRepository $productRepository, string $adminEmail = 'admin@labootique.com')
    {
        $this->mailer = $mailer;
        $this->productRepository = $productRepository;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Envoie une notification par email pour les produits en rupture de stock
     */
    public function sendLowStockNotification(Product $product)
    {
        $email = (new Email())
            ->from('no-reply@labootique.com')
            ->to($this->adminEmail)
            ->subject('Alerte stock : '.$product->getName().' est en rupture de stock')
            ->html('
                <h1>Alerte de stock</h1>
                <p>Le produit suivant est en rupture de stock :</p>
                <ul>
                    <li><strong>Nom :</strong> '.$product->getName().'</li>
                    <li><strong>Référence :</strong> '.$product->getId().'</li>
                    <li><strong>Stock actuel :</strong> '.$product->getStock().'</li>
                </ul>
                <p>Veuillez réapprovisionner ce produit dès que possible.</p>
                <p><a href="/admin?crudAction=edit&crudControllerFqcn=App%5CController%5CAdmin%5CProductCrudController&entityId='.$product->getId().'">Modifier ce produit</a></p>
            ');

        $this->mailer->send($email);
    }

    /**
     * Vérifie les produits en rupture de stock et envoie des notifications
     */
    public function checkLowStockProducts()
    {
        $lowStockProducts = $this->productRepository->findBy(['stock' => 0]);
        
        foreach ($lowStockProducts as $product) {
            $this->sendLowStockNotification($product);
        }
        
        return count($lowStockProducts);
    }
}
