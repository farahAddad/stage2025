<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Trouve tous les logs d'audit entre deux dates
     */
    public function findAuditLogsBetweenDates(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->andWhere('a.horodatage >= :startDate')
            ->andWhere('a.horodatage <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.horodatage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return AuditLog[] Returns an array of AuditLog objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    /**
     * Récupère les audit logs avec pagination
     */
    public function findAllPaginated(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.horodatage', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les audit logs avec filtres
     */
    public function findByFilters(?string $user = null, ?string $action = null, ?string $dateFrom = null, ?string $dateTo = null, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.horodatage', 'DESC');
        
        if ($user) {
            $qb->andWhere('u.email LIKE :user')
               ->setParameter('user', '%' . $user . '%');
        }
        
        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }
        
        if ($dateFrom) {
            $qb->andWhere('a.horodatage >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom . ' 00:00:00'));
        }
        
        if ($dateTo) {
            $qb->andWhere('a.horodatage <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }
        
        return $qb->setFirstResult($offset)
                 ->setMaxResults($limit)
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Compte les audit logs avec filtres
     */
    public function countByFilters(?string $user = null, ?string $action = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->leftJoin('a.user', 'u');
        
        if ($user) {
            $qb->andWhere('u.email LIKE :user')
               ->setParameter('user', '%' . $user . '%');
        }
        
        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }
        
        if ($dateFrom) {
            $qb->andWhere('a.horodatage >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom . ' 00:00:00'));
        }
        
        if ($dateTo) {
            $qb->andWhere('a.horodatage <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }
        
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère toutes les actions distinctes pour les filtres
     */
    public function getActions(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getScalarResult();
        
        return array_column($result, 'action');
    }

    /**
     * Récupère tous les utilisateurs distincts pour les filtres
     */
    public function getUsers(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT u.email')
            ->leftJoin('a.user', 'u')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getScalarResult();
        
        return array_column($result, 'email');
    }
}
