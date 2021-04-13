<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Entity\Ledger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * LedgerRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LedgerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Ledger::class);
    }

    public function typeaheadQuery($q) {
        $qb = $this->createQueryBuilder('e');
        $qb->innerJoin('e.notary', 'n');
        $qb->andWhere('n.name LIKE :q');
        $qb->orderBy('n.name');
        $qb->orderBY('e.year');
        $qb->setParameter('q', "{$q}%");

        return $qb->getQuery()->execute();
    }
}
