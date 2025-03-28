<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Service\SmtpMailService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    private $mailService;
    private $em;

    public function __construct(SmtpMailService $mailService, EntityManagerInterface $em)
    {
        $this->mailService = $mailService;
        $this->em = $em;
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureActions(Actions $actions): Actions 
    {
        return $actions
            ->add('index', 'detail')
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            DateTimeField::new('createdAt', 'Créée le'),
            TextField::new('user.fullname', 'Acheteur'),
            MoneyField::new('total')->setCurrency('EUR')->hideOnForm(),
            MoneyField::new('carrierPrice', 'Frais livraison')->setCurrency('EUR'),
            ChoiceField::new('state', 'Etat')->setChoices([
                'Non payée' => 0,
                'Payée' => 1,
                'Préparation en cours' => 2,
                'Expédiée' => 3,
            ]),
            ArrayField::new('orderDetails', 'Produits achetés')->hideOnIndex()->hideOnForm()
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityUpdatedEvent::class => ['onOrderUpdate'],
            AfterEntityPersistedEvent::class => ['onOrderCreate'],
        ];
    }

    public function onOrderUpdate(AfterEntityUpdatedEvent $event)
    {
        $entity = $event->getEntityInstance();
        
        if (!$entity instanceof Order) {
            return;
        }

        $this->handleOrderStateChange($entity);
    }

    public function onOrderCreate(AfterEntityPersistedEvent $event)
    {
        $entity = $event->getEntityInstance();
        
        if (!$entity instanceof Order) {
            return;
        }

        // Envoyer un email de confirmation de création de commande si nécessaire
        // $this->sendOrderConfirmationEmail($entity);
    }

    private function handleOrderStateChange(Order $order)
    {
        $unitOfWork = $this->em->getUnitOfWork();
        $originalData = $unitOfWork->getOriginalEntityData($order);
        
        // Vérifier si l'état a changé
        if (isset($originalData['state']) && $originalData['state'] !== $order->getState()) {
            $this->sendStateChangeEmail($order, $originalData['state']);
        }
    }

    private function sendStateChangeEmail(Order $order, int $previousState)
    {
        $user = $order->getUser();
        $stateLabel = $this->getStateLabel($order->getState());
        $previousStateLabel = $this->getStateLabel($previousState);
    
        $subject = "Mise à jour de votre commande #{$order->getReference()}";
    
        // Texte brut pour les clients email en mode texte
        $textContent = "Bonjour {$user->getFullName()},\n\n";
        $textContent .= "Le statut de votre commande #{$order->getReference()} a été mis à jour :\n";
        $textContent .= "Ancien statut : {$previousStateLabel}\n";
        $textContent .= "Nouveau statut : {$stateLabel}\n\n";
        $textContent .= "DÉTAILS DE LA COMMANDE :\n";
        $textContent .= "========================\n";
    
        foreach ($order->getOrderDetails() as $item) {
            $textContent .= "- {$item->getProduct()} x {$item->getQuantity()} : " . ($item->getPrice()/100) . " €\n";
        }
    
        $textContent .= "\nFrais de livraison : " . ($order->getCarrierPrice()/100) . " €\n";
        $textContent .= "TOTAL : " . ($order->getTotal()/100) . " €\n\n";
        $textContent .= "Merci pour votre confiance !\n";
    
        // Version HTML avec design professionnel
        $htmlContent = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                .order-info { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .status-update { background: #e8f4fd; padding: 10px; border-left: 4px solid #3498db; margin: 15px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { text-align: left; background: #f2f2f2; padding: 10px; }
                td { padding: 10px; border-bottom: 1px solid #ddd; }
                .total { font-weight: bold; background: #f9f9f9; }
                .footer { margin-top: 30px; font-size: 0.9em; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Votre commande #'.$order->getReference().'</h2>
            </div>
    
            <div class="status-update">
                <p><strong>Mise à jour du statut :</strong> '.$previousStateLabel.' → <span style="color: #3498db;">'.$stateLabel.'</span></p>
            </div>
    
            <div class="order-info">
                <h3>Détails de la commande</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th>Prix unitaire</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>';
    
        foreach ($order->getOrderDetails() as $item) {
            $htmlContent .= '
                        <tr>
                            <td>'.htmlspecialchars($item->getProduct()).'</td>
                            <td>'.$item->getQuantity().'</td>
                            <td>'.number_format($item->getPrice()/100, 2, ',', ' ').' €</td>
                            <td>'.number_format(($item->getPrice() * $item->getQuantity())/100, 2, ',', ' ').' €</td>
                        </tr>';
        }
    
        $htmlContent .= '
                        <tr class="total">
                            <td colspan="3" style="text-align: right;">Frais de livraison</td>
                            <td>'.number_format($order->getCarrierPrice()/100, 2, ',', ' ').' €</td>
                        </tr>
                        <tr class="total">
                            <td colspan="3" style="text-align: right;">Total TTC</td>
                            <td>'.number_format($order->getTotal()/100, 2, ',', ' ').' €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
    
            <div class="footer">
                <p>Merci pour votre confiance !</p>
                <p>Pour toute question concernant votre commande, veuillez répondre à cet email.</p>
            </div>
        </body>
        </html>';
    
        try {
            $this->mailService->send(
                $user->getEmail(),
                $user->getFullName(),
                $subject,
                $textContent,
                $htmlContent
            );
        } catch (\Exception $e) {
            // Log l'erreur
            error_log("Erreur d'envoi email commande #{$order->getId()}: ".$e->getMessage());
        }
    }
}