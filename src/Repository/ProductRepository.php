<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return iterable<Product>
     */
    public function findAllForReindex(): iterable
    {
        $qb = $this->createQueryBuilder('p');

        foreach ($qb->getQuery()->toIterable() as $product) {
            yield $product;
        }
    }
}
