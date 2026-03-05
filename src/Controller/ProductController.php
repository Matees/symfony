<?php

namespace App\Controller;

use App\Entity\Product;
use App\Message\IndexProductMessage;
use App\Repository\ProductRepository;
use App\Service\ProductIndexer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/products')]
class ProductController extends AbstractController
{
    #[Route('', name: 'product_index', methods: ['GET'])]
    public function index(#[CurrentUser] $user, Request $request, ProductRepository $productRepository, ProductIndexer $productIndexer): Response
    {
        $query = $request->query->get('q', '');
        $results = [];
        $searchError = null;

        if ($query !== '') {
            try {
                $results = $productIndexer->search($query);
            } catch (\Throwable $e) {
                $searchError = $e->getMessage();
                $results = [];
            }
        }

        return $this->render('product/index.html.twig', [
            'query' => $query,
            'results' => $results,
            'allProducts' => $query === '' ? $productRepository->findAll() : [],
            'searchError' => $searchError,
        ]);
    }

    #[Route('/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProductRepository $productRepository, MessageBusInterface $bus): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $description = trim((string) $request->request->get('description', ''));
            $price = trim((string) $request->request->get('price', ''));

            if ($name === '' || $price === '') {
                $error = 'Name and price are required.';
            } elseif (!is_numeric($price) || (float) $price < 0) {
                $error = 'Price must be a non-negative number.';
            } else {
                $product = new Product();
                $product->setName($name);
                $product->setDescription($description !== '' ? $description : null);
                $product->setPrice(number_format((float) $price, 2, '.', ''));

                $em = $productRepository->getEntityManager();
                $em->persist($product);
                $em->flush();

                $bus->dispatch(new IndexProductMessage($product->getId()));

                $this->addFlash('success', sprintf('Product "%s" saved and queued for indexing.', $product->getName()));

                return $this->redirectToRoute('product_index');
            }
        }

        return $this->render('product/new.html.twig', [
            'error' => $error,
        ]);
    }
}
