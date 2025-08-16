<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Session;
use App\Entity\Inscription;
use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des données du calendrier
 */
class CalendarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Récupérer toutes les sessions d'un mois donné
     */
    public function findSessionsByMonth(int $month, int $year): array
    {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        return $this->createQueryBuilder('s')
            ->andWhere('s.dateDebut >= :startDate')
            ->andWhere('s.dateDebut <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer toutes les inscriptions acceptées d'un mois donné
     */
    public function findAcceptedInscriptionsByMonth(int $month, int $year): array
    {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i', 's', 'u')
            ->from(Inscription::class, 'i')
            ->join('i.session', 's')
            ->join('i.user', 'u')
            ->andWhere('s.dateDebut >= :startDate')
            ->andWhere('s.dateDebut <= :endDate')
            ->andWhere('i.statutParticipation = :statut')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statut', 'accepté')
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer tous les utilisateurs
     */
    public function findAllUsers(): array
    {
        return $this->getEntityManager()
            ->getRepository(User::class)
            ->findAll();
    }

    /**
     * Récupérer tous les logs d'audit d'un mois donné
     */
    public function findAuditLogsByMonth(int $month, int $year): array
    {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('a', 'u')
            ->from(AuditLog::class, 'a')
            ->leftJoin('a.user', 'u')
            ->andWhere('a.horodatage >= :startDate')
            ->andWhere('a.horodatage <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.horodatage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Organiser les données du calendrier pour un mois donné
     */
    public function organizeCalendarData(int $month, int $year): array
    {
        $inscriptions = $this->findAcceptedInscriptionsByMonth($month, $year);
        
        // Créer la structure du calendrier
        $startDate = new \DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');
        
        $currentDate = clone $startDate;
        $currentDate->modify('first day of this month');
        $currentDate->modify('monday this week');
        
        $endWeek = clone $endDate;
        $endWeek->modify('sunday this week');
        
        $weeks = [];
        $currentWeek = [];
        
        while ($currentDate <= $endWeek) {
            $currentWeek[] = clone $currentDate;
            $currentDate->modify('+1 day');
            
            if (count($currentWeek) == 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }
        
        // Organiser les sessions par date avec gestion intelligente des participants
        $sessionsByDate = [];
        
        // Grouper les inscriptions par session
        $inscriptionsBySession = [];
        foreach ($inscriptions as $inscription) {
            $sessionId = $inscription->getSession()->getId();
            if (!isset($inscriptionsBySession[$sessionId])) {
                $inscriptionsBySession[$sessionId] = [];
            }
            $inscriptionsBySession[$sessionId][] = $inscription;
        }
        
        // Créer les entrées de session avec tous les utilisateurs
        foreach ($inscriptionsBySession as $sessionId => $sessionInscriptions) {
            $firstInscription = $sessionInscriptions[0];
            $session = $firstInscription->getSession();
            $dateKey = $session->getDateDebut()->format('Y-m-d');
            
            if (!isset($sessionsByDate[$dateKey])) {
                $sessionsByDate[$dateKey] = [];
            }
            
            // Vérifier si cette session existe déjà dans cette date
            $existingSessionIndex = -1;
            foreach ($sessionsByDate[$dateKey] as $index => $existingSession) {
                if ($existingSession['session']->getId() === $sessionId) {
                    $existingSessionIndex = $index;
                    break;
                }
            }
            
            // Récupérer tous les utilisateurs qui participent à cette session
            $participants = [];
            foreach ($sessionInscriptions as $inscription) {
                $participants[] = [
                    'nom' => $inscription->getUser()->getNom(),
                    'prenom' => $inscription->getUser()->getPrenom(),
                    'inscription' => $inscription
                ];
            }
            
            if ($existingSessionIndex >= 0) {
                // Session existe déjà, mettre à jour la liste des participants
                $sessionsByDate[$dateKey][$existingSessionIndex]['participants'] = $participants;
            } else {
                // Nouvelle session, l'ajouter
                $sessionsByDate[$dateKey][] = [
                    'type' => 'session',
                    'title' => $session->getTitre(),
                    'session' => $session,
                    'participants' => $participants,
                    'color' => '#28a745'
                ];
            }
        }
        
        return [
            'weeks' => $weeks,
            'sessionsByDate' => $sessionsByDate
        ];
    }
}
