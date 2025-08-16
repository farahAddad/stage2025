<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\User;
use App\Entity\Evaluation;
use App\Entity\Inscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des données du tableau de bord admin
 */
class AdminDashboardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * Récupérer les statistiques globales du tableau de bord
     */
    public function getGlobalStats(): array
    {
        $em = $this->getEntityManager();
        
        // Nombre total de formations
        $totalFormations = $em->getRepository(Formation::class)->count([]);
        
        // Nombre total d'utilisateurs
        $totalUsers = $em->getRepository(User::class)->count([]);
        
        // Taux de participation
        $totalParticipants = $em->getRepository(Inscription::class)
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        $participantsAcceptes = $em->getRepository(Inscription::class)
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.statutParticipation = :statutAccepte')
            ->setParameter('statutAccepte', 'accepté')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        $tauxParticipation = $totalParticipants > 0 ? round(($participantsAcceptes / $totalParticipants) * 100, 2) : 0;
        
        // Taux de satisfaction moyen - moyenne simple de toutes les évaluations
        $totalEvaluations = $em->getRepository(Evaluation::class)->count([]);
        
        if ($totalEvaluations > 0) {
            // Moyenne de toutes les notes globales
            $moyenneGlobale = $em->getRepository(Evaluation::class)
                ->createQueryBuilder('e')
                ->select('AVG(e.noteGlobale)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Moyenne de toutes les notes de clarté
            $moyenneClarte = $em->getRepository(Evaluation::class)
                ->createQueryBuilder('e')
                ->select('AVG(e.clarte)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Moyenne de toutes les notes de pertinence
            $moyennePertinence = $em->getRepository(Evaluation::class)
                ->createQueryBuilder('e')
                ->select('AVG(e.pertinence)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $tauxSatisfaction = round($moyenneGlobale, 2);
        } else {
            $tauxSatisfaction = 0;
            $moyenneGlobale = 0;
            $moyenneClarte = 0;
            $moyennePertinence = 0;
        }
        
        // Total des évaluations pour l'affichage
        $totalEvaluations = $em->getRepository(Evaluation::class)->count([]);
        
        // Statistiques détaillées de toutes les sessions
        $statsSessions = $em->getRepository(Evaluation::class)
            ->createQueryBuilder('e')
            ->select('
                s.id as sessionId,
                s.titre as sessionTitre,
                f.sujet as formationSujet,
                COUNT(e.id) as nbEvaluations,
                AVG(e.noteGlobale) as moyenneGlobale,
                AVG(e.clarte) as moyenneClarte,
                AVG(e.pertinence) as moyennePertinence,
                MIN(e.noteGlobale) as noteMin,
                MAX(e.noteGlobale) as noteMax
            ')
            ->join('e.session', 's')
            ->join('s.formation', 'f')
            ->groupBy('s.id, s.titre, f.sujet')
            ->orderBy('moyenneGlobale', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Heures de formation totales
        $heuresFormation = $this->createQueryBuilder('f')
            ->select('SUM(f.duree)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Heures par utilisateur
        $heuresParUtilisateur = $totalUsers > 0 ? round($heuresFormation / $totalUsers, 2) : 0;
        
        // Formations par responsable
        $formationsParResponsable = $this->createQueryBuilder('f')
            ->select('COUNT(DISTINCT f.responsable)')
            ->where('f.responsable IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        return [
            'totalFormations' => $totalFormations,
            'totalUsers' => $totalUsers,
            'tauxParticipation' => $tauxParticipation,
            'totalEvaluations' => $totalEvaluations,
            'tauxSatisfaction' => $tauxSatisfaction,
            'heuresFormation' => $heuresFormation,
            'heuresParUtilisateur' => $heuresParUtilisateur,
            'formationsParResponsable' => $formationsParResponsable,
            'statsSessions' => $statsSessions,
            'moyenneGlobale' => $moyenneGlobale,
            'moyenneClarte' => $moyenneClarte,
            'moyennePertinence' => $moyennePertinence,
        ];
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
     * Récupérer les statistiques par responsable
     */
    public function getStatsByResponsable(int $responsableId): array
    {
        // Total des formations du responsable
        $totalFormations = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.responsable = :responsableId')
            ->setParameter('responsableId', $responsableId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Total des sessions
        $totalSessions = $this->createQueryBuilder('f')
            ->select('COUNT(s.id)')
            ->join('f.sessions', 's')
            ->where('f.responsable = :responsableId')
            ->setParameter('responsableId', $responsableId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Total des participants inscrits
        $totalParticipants = $this->createQueryBuilder('f')
            ->select('COUNT(DISTINCT i.user)')
            ->join('f.sessions', 's')
            ->join('s.inscriptions', 'i')
            ->where('f.responsable = :responsableId')
            ->setParameter('responsableId', $responsableId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Participants validés
        $participantsValides = $this->createQueryBuilder('f')
            ->select('COUNT(DISTINCT i.user)')
            ->join('f.sessions', 's')
            ->join('s.inscriptions', 'i')
            ->where('f.responsable = :responsableId')
            ->andWhere('i.statutParticipation = :statutValide')
            ->setParameter('responsableId', $responsableId)
            ->setParameter('statutValide', 'accepté')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Taux de participation
        $tauxParticipation = $totalParticipants > 0 ? round(($participantsValides / $totalParticipants) * 100, 1) : 0;

        // Heures totales de formation
        $heuresFormation = $this->createQueryBuilder('f')
            ->select('SUM(f.duree)')
            ->where('f.responsable = :responsableId')
            ->setParameter('responsableId', $responsableId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'totalFormations' => $totalFormations,
            'totalSessions' => $totalSessions,
            'totalParticipants' => $totalParticipants,
            'participantsValides' => $participantsValides,
            'tauxParticipation' => $tauxParticipation,
            'heuresFormation' => $heuresFormation,
        ];
    }

    /**
     * Récupérer les formations avec leur taux de participation
     */
    public function getFormationsWithParticipation(): array
    {
        $em = $this->getEntityManager();
        
        // Récupérer les formations avec leur nombre de sessions
        $formationsAvecParticipation = $this->createQueryBuilder('f')
            ->select('f.id, f.sujet, f.dateDebut, f.duree, COUNT(DISTINCT s.id) as totalSessions')
            ->leftJoin('f.sessions', 's')
            ->groupBy('f.id')
            ->orderBy('f.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();

        // Ajouter les statistiques de participation pour chaque formation
        foreach ($formationsAvecParticipation as &$formation) {
            // Participants validés
            $participantsValides = $em->getRepository(Inscription::class)
                ->createQueryBuilder('i')
                ->select('COUNT(DISTINCT i.user)')
                ->join('i.session', 's')
                ->where('s.formation = :formationId')
                ->andWhere('i.statutParticipation = :statutValide')
                ->setParameter('formationId', $formation['id'])
                ->setParameter('statutValide', 'accepté')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Total des participants inscrits (tous statuts confondus)
            $totalParticipants = $em->getRepository(Inscription::class)
                ->createQueryBuilder('i')
                ->select('COUNT(DISTINCT i.user)')
                ->join('i.session', 's')
                ->where('s.formation = :formationId')
                ->setParameter('formationId', $formation['id'])
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $formation['participantsValides'] = $participantsValides;
            $formation['totalParticipants'] = $totalParticipants;
            
            // Calculer le taux de participation
            if ($totalParticipants > 0) {
                $formation['tauxParticipation'] = round(($participantsValides / $totalParticipants) * 100, 1);
            } else {
                $formation['tauxParticipation'] = 0;
            }
        }

        return $formationsAvecParticipation;
    }

    /**
     * Récupérer les statistiques mensuelles pour le graphique
     */
    public function getMonthlyStats(): array
    {
        $em = $this->getEntityManager();
        
        // Récupérer les données de janvier 2025 à janvier 2026
        $monthlyData = [];
        
        // Créer une date de début : 1er janvier 2025
        $startDate = new \DateTime('2025-01-01');
        
        // Générer les 13 mois (janvier 2025 à janvier 2026)
        for ($i = 0; $i < 13; $i++) {
            $date = clone $startDate;
            $date->modify("+{$i} months");
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->format('M');
            
            // Calculer le début et fin du mois
            $startOfMonth = clone $date;
            $startOfMonth->modify('first day of this month');
            $startOfMonth->setTime(0, 0, 0);
            
            $endOfMonth = clone $date;
            $endOfMonth->modify('last day of this month');
            $endOfMonth->setTime(23, 59, 59);
            
            // Formations créées ce mois
            $formationsCount = $this->createQueryBuilder('f')
                ->select('COUNT(f.id)')
                ->where('f.dateDebut >= :startDate')
                ->andWhere('f.dateDebut <= :endDate')
                ->setParameter('startDate', $startOfMonth)
                ->setParameter('endDate', $endOfMonth)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Participants ce mois
            $participantsCount = $em->getRepository(Inscription::class)
                ->createQueryBuilder('i')
                ->select('COUNT(DISTINCT i.user)')
                ->join('i.session', 's')
                ->where('s.dateDebut >= :startDate')
                ->andWhere('s.dateDebut <= :endDate')
                ->setParameter('startDate', $startOfMonth)
                ->setParameter('endDate', $endOfMonth)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Satisfaction moyenne ce mois
            $satisfactionAvg = $em->getRepository(Evaluation::class)
                ->createQueryBuilder('e')
                ->select('AVG(e.noteGlobale)')
                ->join('e.session', 's')
                ->where('s.dateDebut >= :startDate')
                ->andWhere('s.dateDebut <= :endDate')
                ->setParameter('startDate', $startOfMonth)
                ->setParameter('endDate', $endOfMonth)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $monthlyData[] = [
                'month' => $monthLabel,
                'monthKey' => $monthKey,
                'formations' => $formationsCount,
                'participants' => $participantsCount,
                'satisfaction' => round($satisfactionAvg, 1)
            ];
        }
        
        return $monthlyData;
    }
    
    /**
     * Récupérer des statistiques supplémentaires pour les graphiques
     */
    public function getAdditionalChartStats(): array
    {
        $em = $this->getEntityManager();
        
        // Top 5 des formations par participation
        $topFormations = $this->createQueryBuilder('f')
            ->select('f.sujet, COUNT(DISTINCT i.user) as participantCount')
            ->join('f.sessions', 's')
            ->join('s.inscriptions', 'i')
            ->groupBy('f.id')
            ->orderBy('participantCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Répartition des statuts de participation
        $statutsParticipation = $em->getRepository(Inscription::class)
            ->createQueryBuilder('i')
            ->select('i.statutParticipation, COUNT(i.id) as count')
            ->groupBy('i.statutParticipation')
            ->getQuery()
            ->getResult();
        
        return [
            'topFormations' => $topFormations,
            'statutsParticipation' => $statutsParticipation
        ];
    }

    /**
     * Récupérer les statistiques de satisfaction pour le graphique
     * Selon vos besoins : 4-5 = Très satisfait, 2-3 = Satisfait, 1 = Non satisfait
     */
    public function getSatisfactionStats(): array
    {
        $em = $this->getEntityManager();
        
        // Compter les évaluations par catégorie de satisfaction
        $stats = $em->getRepository(Evaluation::class)
            ->createQueryBuilder('e')
            ->select('
                CASE 
                    WHEN e.noteGlobale >= 4 THEN \'Très satisfait (4-5)\'
                    WHEN e.noteGlobale >= 2 THEN \'Satisfait (2-3)\'
                    ELSE \'Non satisfait (1)\'
                END as categorie,
                COUNT(e.id) as nombre
            ')
            ->groupBy('categorie')
            ->orderBy('nombre', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Initialiser toutes les catégories avec 0
        $categories = [
            'Très satisfait (4-5)' => 0,
            'Satisfait (2-3)' => 0,
            'Non satisfait (1)' => 0
        ];
        
        // Remplir avec les vraies données
        foreach ($stats as $stat) {
            $categories[$stat['categorie']] = (int) $stat['nombre'];
        }
        
        return $categories;
    }
}
