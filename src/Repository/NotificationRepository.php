<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Trouver les notifications non lues d'un utilisateur
     */
    public function findNonLuesByUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.lu = :lu')
            ->setParameter('user', $user)
            ->setParameter('lu', false)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les notifications non lues d'un utilisateur
     */
    public function countNonLuesByUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.lu = :lu')
            ->setParameter('user', $user)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver toutes les notifications d'un utilisateur (lues et non lues)
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLue(Notification $notification): void
    {
        $notification->setLu(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function marquerToutesCommeLues(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', ':lu')
            ->set('n.dateLecture', ':dateLecture')
            ->where('n.destinataire = :user')
            ->andWhere('n.lu = :nonLu')
            ->setParameter('lu', true)
            ->setParameter('dateLecture', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->setParameter('nonLu', false)
            ->getQuery()
            ->execute();
    }
} 