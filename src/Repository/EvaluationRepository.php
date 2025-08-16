<?php

namespace App\Repository;

use App\Entity\Evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\Session;

/**
 * @extends ServiceEntityRepository<Evaluation>
 */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluation::class);
    }

    //    /**
    //     * @return Evaluation[] Returns an array of Evaluation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Evaluation
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Trouver une évaluation par utilisateur et session
     */
    public function findOneByUserAndSession(User $user, Session $session): ?Evaluation
    {
        return $this->findOneBy([
            'user' => $user,
            'session' => $session
        ]);
    }

    /**
     * Récupérer toutes les évaluations avec détails pour la pagination
     */
    public function findAllWithDetails(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.session', 's')
            ->leftJoin('s.formation', 'f')
            ->leftJoin('e.user', 'u')
            ->addSelect('s', 'f', 'u')
            ->orderBy('e.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Calculer la moyenne globale
     */
    public function getMoyenneGlobale(): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('AVG(e.noteGlobale) as moyenne')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $result ? (float) $result : 0.0;
    }

    /**
     * Calculer la moyenne de clarté
     */
    public function getMoyenneClarte(): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('AVG(e.clarte) as moyenne')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $result ? (float) $result : 0.0;
    }

    /**
     * Calculer la moyenne de pertinence
     */
    public function getMoyennePertinence(): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('AVG(e.pertinence) as moyenne')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $result ? (float) $result : 0.0;
    }

    /**
     * Obtenir la répartition des notes
     */
    public function getRepartitionNotes(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.noteGlobale, COUNT(e.id) as nombre')
            ->groupBy('e.noteGlobale')
            ->orderBy('e.noteGlobale', 'ASC');
        
        $results = $qb->getQuery()->getResult();
        
        $repartition = [];
        for ($i = 1; $i <= 5; $i++) {
            $repartition[$i] = 0;
        }
        
        foreach ($results as $result) {
            $repartition[$result['noteGlobale']] = $result['nombre'];
        }
        
        return $repartition;
    }
}
