<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Service\SmtpMailService;
use App\Service\InvoiceGenerator;
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
use Symfony\Component\Mime\Email;

class OrderCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    private $mailService;
    private $em;
    private $invoiceGenerator;

    public function __construct(SmtpMailService $mailService, EntityManagerInterface $em, InvoiceGenerator $invoiceGenerator)
    {
        $this->mailService = $mailService;
        $this->em = $em;
        $this->invoiceGenerator = $invoiceGenerator;
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

    /**
     * Get a label for a given order state
     * 
     * @param int $state The state code
     * @return string The label for this state
     */
    private function getStateLabel(int $state): string
    {
        $stateLabels = [
            0 => 'Non payée',
            1 => 'Payée',
            2 => 'Préparation en cours',
            3 => 'Expédiée',
        ];
        
        return $stateLabels[$state] ?? 'État inconnu';
    }
    
    private function sendStateChangeEmail(Order $order, int $previousState)
    {
        $user = $order->getUser();
        $stateLabel = $this->getStateLabel($order->getState());
        $previousStateLabel = $this->getStateLabel($previousState);
    
        $subject = "Mise à jour de votre commande #{$order->getReference()} - {$stateLabel}";
    
        try {
            // Générer l'email avec la facture PDF en pièce jointe pour tout changement d'état
            $email = $this->invoiceGenerator->createInvoiceEmail(
                $order,
                $user->getEmail(),
                $subject,
                $stateLabel  // Passer l'état actuel pour l'afficher dans la facture
            );
            
            // Envoyer l'email avec la facture PDF
            $this->mailService->sendEmail($email);
            
            // Log pour l'audit
            error_log("Email avec facture PDF envoyé pour la commande #{$order->getId()} (état: {$stateLabel}) au client {$user->getEmail()}");
        } catch (\Exception $e) {
            // Log l'erreur
            error_log("Erreur d'envoi email commande #{$order->getId()}: ".$e->getMessage());
        }
    }
}