<?php

namespace App\Controller;

use App\Form\SearchType;
use App\Repository\ProductRepository;
use App\Model\Search;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/articles', name: 'product')]
    public function index(ProductRepository $repository, Request $request): Response
    {
        // Si recherche exécutée, $products contiendra les résultats filtrés
        $search = new Search();
        $form = $this->createForm(SearchType::class, $search);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $products = $repository->findWithSearch($search);
            // Dans le cas d'une recherche, on n'organise pas par catégorie
            $productsByCategory = null;
            $allCategories = null;
        } else {
            // Récupérer toutes les catégories
            $allCategories = $this->getDoctrine()->getRepository('App\\Entity\\Category')->findAll();
            
            // Organiser les produits par catégorie
            $productsByCategory = [];
            foreach ($allCategories as $category) {
                $productsInCategory = $repository->findBy(['category' => $category]);
                
                // Ne pas inclure les catégories vides
                if (count($productsInCategory) > 0) {
                    $productsByCategory[$category->getId()] = [
                        'category' => $category,
                        'products' => $productsInCategory
                    ];
                }
            }
            
            // Pour la compatibilité avec le code existant
            $products = $repository->findAll();
        }
        
        return $this->renderForm('product/index.html.twig', [
            'products' => $products,
            'productsByCategory' => $productsByCategory,
            'allCategories' => $allCategories,
            'form' => $form,
        ]);
    }

    #[Route('/articles/{slug}', name: 'product_show')]
    public function show(ProductRepository $repository, string $slug): Response
    {
        $product = $repository->findOneBySlug($slug);

        if (!$product) {
            return $this->redirectToRoute('product');
        }
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }
}


