<?php

namespace App\Service;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class SalesAnalytics
{
    private $orderRepository;
    private $entityManager;

    public function __construct(OrderRepository $orderRepository, EntityManagerInterface $entityManager)
    {
        $this->orderRepository = $orderRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Récupère le total des ventes par jour sur les 30 derniers jours
     * 
     * @return array
     */
    public function getSalesDataForLast30Days(): array
    {
        $endDate = new \DateTime();
        $startDate = new \DateTime();
        $startDate->modify('-30 days');

        $conn = $this->entityManager->getConnection();
        
        $sql = '
            SELECT 
                DATE(o.created_at) as date,
                SUM(od.total) as total_sales,
                COUNT(DISTINCT o.id) as order_count
            FROM `order` o
            JOIN order_details od ON od.binded_order_id = o.id
            WHERE o.created_at BETWEEN :startDate AND :endDate
            AND o.state >= 2 -- Uniquement les commandes validées
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ';
        
        $resultSet = $conn->executeQuery($sql, [
            'startDate' => $startDate->format('Y-m-d H:i:s'),
            'endDate' => $endDate->format('Y-m-d H:i:s')
        ]);
        
        $results = $resultSet->fetchAllAssociative();
        
        // Formater les données pour le graphique
        $dates = [];
        $sales = [];
        $orders = [];
        
        foreach ($results as $row) {
            $dates[] = date('d/m', strtotime($row['date']));
            $sales[] = round($row['total_sales'] / 100, 2); // Conversion en euros
            $orders[] = (int)$row['order_count'];
        }
        
        return [
            'dates' => $dates,
            'sales' => $sales,
            'orders' => $orders
        ];
    }
    
    /**
     * Récupère les statistiques globales des ventes
     * 
     * @return array
     */
    public function getGlobalStats(): array
    {
        $conn = $this->entityManager->getConnection();
        
        // Total des ventes
        $sqlTotalSales = '
            SELECT SUM(od.total) as total_sales
            FROM order_details od
            JOIN `order` o ON od.binded_order_id = o.id
            WHERE o.state >= 2 -- Uniquement les commandes validées
        ';
        $totalSales = $conn->executeQuery($sqlTotalSales)->fetchOne();
        
        // Nombre de commandes
        $sqlOrderCount = '
            SELECT COUNT(id) as order_count
            FROM `order`
            WHERE state >= 2 -- Uniquement les commandes validées
        ';
        $orderCount = $conn->executeQuery($sqlOrderCount)->fetchOne();
        
        // Panier moyen
        $averageBasket = $orderCount > 0 ? $totalSales / $orderCount : 0;
        
        // Produits en rupture de stock
        $sqlOutOfStock = '
            SELECT COUNT(id) as out_of_stock
            FROM product
            WHERE stock = 0
        ';
        $outOfStock = $conn->executeQuery($sqlOutOfStock)->fetchOne();
        
        return [
            'totalSales' => round($totalSales / 100, 2), // Conversion en euros
            'orderCount' => (int)$orderCount,
            'averageBasket' => round($averageBasket / 100, 2), // Conversion en euros
            'outOfStock' => (int)$outOfStock
        ];
    }
}
