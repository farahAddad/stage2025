<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\Session;
use App\Entity\User;
use App\Entity\Salle;
use App\Entity\Inscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 *
 * @method Formation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Formation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Formation[]    findAll()
 * @method Formation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function save(Formation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Formation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupérer tous les responsables avec leurs formations
     */
    public function getAllResponsables(): array
    {
        return $this->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id, u.nom, u.prenom')
            ->join('u.formations', 'f')
            ->groupBy('u.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer tous les utilisateurs cibles (excluant RH et Responsable)
     */
    public function getUsersCibles(): array
    {
        return $this->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.role NOT IN (:excludedRoles)')
            ->setParameter('excludedRoles', ['ROLE_RH', 'ROLE_RESPONSABLE'])
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer tous les responsables par rôle
     */
    public function getResponsablesByRole(): array
    {
        return $this->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', 'ROLE_RESPONSABLE')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer toutes les salles
     */
    public function getAllSalles(): array
    {
        return $this->getEntityManager()
            ->getRepository(Salle::class)
            ->findAll();
    }

    /**
     * Vérifier la disponibilité d'une salle pour une période donnée
     */
    public function checkSalleAvailability(int $salleId, \DateTime $dateDebut, \DateTime $dateFin, ?int $sessionId = null): array
    {
        $salle = $this->getEntityManager()->getRepository(Salle::class)->find($salleId);
        if (!$salle) {
            return ['available' => false, 'message' => 'Salle non trouvée'];
        }

        $sessionsEnConflit = $this->getEntityManager()
            ->getRepository(Session::class)
            ->createQueryBuilder('s')
            ->where('s.salle = :salle')
            ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
            ->andWhere('s.id != :sessionId')
            ->setParameter('salle', $salle)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->setParameter('sessionId', $sessionId ?: 0)
            ->getQuery()
            ->getResult();

        if (count($sessionsEnConflit) > 0) {
            $titresConflit = array_map(function($s) { return $s->getTitre(); }, $sessionsEnConflit);
            return [
                'available' => false, 
                'conflictingSessions' => $titresConflit,
                'message' => 'Salle déjà réservée à cet horaire'
            ];
        }

        return ['available' => true, 'message' => 'Salle disponible'];
    }

    /**
     * Vérifier les conflits d'horaires pour des utilisateurs
     */
    public function checkUserConflicts(array $userIds, \DateTime $dateDebut, \DateTime $dateFin, ?int $sessionId = null): array
    {
        $conflicts = [];
        
        foreach ($userIds as $userId) {
            $conflit = $this->getEntityManager()
                ->getRepository(Inscription::class)
                ->createQueryBuilder('i')
                ->join('i.session', 's')
                ->where('i.user = :user')
                ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
                ->andWhere('s.id != :sessionId')
                ->setParameter('user', $userId)
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin)
                ->setParameter('sessionId', $sessionId ?: 0)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($conflit) {
                $user = $this->getEntityManager()->getRepository(User::class)->find($userId);
                if ($user) {
                    $conflicts[] = $user->getNom() . ' ' . $user->getPrenom() . ' (' . $user->getEmail() . ')';
                }
            }
        }

        return $conflicts;
    }

    /**
     * Récupérer les utilisateurs pour une formation donnée
     */
    public function getUsersForFormation(int $formationId): array
    {
        return $this->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.role NOT IN (:excludedRoles)')
            ->setParameter('excludedRoles', ['ROLE_RH', 'ROLE_RESPONSABLE'])
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les responsables pour une formation donnée
     */
    public function getResponsablesForFormation(int $formationId): array
    {
        return $this->getEntityManager()
            ->getRepository(User::class)
            ->findBy(['role' => 'ROLE_RESPONSABLE']);
    }

    /**
     * Récupérer une formation avec toutes ses relations
     */
    public function findFormationWithDetails(int $id): ?Formation
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.sessions', 's')
            ->leftJoin('f.responsable', 'r')
            ->addSelect('s', 'r')
            ->where('f.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupérer les sessions d'une formation avec leurs inscriptions
     */
    public function getSessionsWithInscriptions(int $formationId): array
    {
        return $this->getEntityManager()
            ->getRepository(Session::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.inscriptions', 'i')
            ->leftJoin('i.user', 'u')
            ->addSelect('i', 'u')
            ->where('s.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les statistiques d'une formation
     */
    public function getFormationStats(int $formationId): array
    {
        $formation = $this->find($formationId);
        if (!$formation) {
            return [];
        }

        $sessions = $this->getEntityManager()
            ->getRepository(Session::class)
            ->createQueryBuilder('s')
            ->where('s.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->getQuery()
            ->getResult();

        $totalSessions = count($sessions);
        $totalInscriptions = 0;
        $inscriptionsAcceptees = 0;

        foreach ($sessions as $session) {
            $inscriptions = $this->getEntityManager()
                ->getRepository(Inscription::class)
                ->findBy(['session' => $session]);
            
            $totalInscriptions += count($inscriptions);
            
            foreach ($inscriptions as $inscription) {
                if ($inscription->getStatutParticipation() === 'accepté') {
                    $inscriptionsAcceptees++;
                }
            }
        }

        return [
            'totalSessions' => $totalSessions,
            'totalInscriptions' => $totalInscriptions,
            'inscriptionsAcceptees' => $inscriptionsAcceptees,
            'tauxParticipation' => $totalInscriptions > 0 ? round(($inscriptionsAcceptees / $totalInscriptions) * 100, 1) : 0
        ];
    }
}
