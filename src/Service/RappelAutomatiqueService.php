<?php

namespace App\Service;

use App\Entity\Formation;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;

class RappelAutomatiqueService
{
    private EntityManagerInterface $em;
    private NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $em, 
        NotificationService $notificationService
    ) {
        $this->em = $em;
        $this->notificationService = $notificationService;
    }

    /**
     * Vérifier et envoyer automatiquement les rappels J-1
     * Cette méthode est appelée une fois par jour via la commande
     */
    public function verifierEtEnvoyerRappelsJ1(): void
    {
        // Vérifier si les rappels ont déjà été envoyés aujourd'hui
        $aujourdhui = new \DateTime('today');
        $cacheKey = 'rappel_j1_' . $aujourdhui->format('Y-m-d');
        
        if ($this->rappelDejaEnvoyeAujourdhui($cacheKey)) {
            return;
        }

        $this->envoyerRappelsJ1();
        $this->marquerRappelEnvoye($cacheKey);
    }

    /**
     * Envoyer les rappels J-1 pour les formations et sessions
     */
    private function envoyerRappelsJ1(): void
    {
        $demain = new \DateTime('tomorrow');
        $demain->setTime(0, 0, 0);
        
        $apresDemain = clone $demain;
        $apresDemain->modify('+1 day');

        // 1. Rappels aux responsables de formation
        $this->envoyerRappelsFormation($demain, $apresDemain);

        // 2. Rappels aux participants de session
        $this->envoyerRappelsSession($demain, $apresDemain);
    }

    /**
     * Envoyer les rappels J-1 aux responsables de formation
     */
    private function envoyerRappelsFormation(\DateTime $demain, \DateTime $apresDemain): void
    {
        $formations = $this->em->getRepository(Formation::class)
            ->createQueryBuilder('f')
            ->where('f.dateDebut >= :demain')
            ->andWhere('f.dateDebut < :apresDemain')
            ->andWhere('f.responsable IS NOT NULL')
            ->setParameter('demain', $demain)
            ->setParameter('apresDemain', $apresDemain)
            ->getQuery()
            ->getResult();

        foreach ($formations as $formation) {
            try {
                $this->notificationService->notifierRappelJ1Responsable($formation);
            } catch (\Exception $e) {
                // Log l'erreur mais continue avec les autres formations
                error_log("Erreur lors de l'envoi du rappel J-1 formation: " . $e->getMessage());
            }
        }
    }

    /**
     * Envoyer les rappels J-1 aux participants de session
     */
    private function envoyerRappelsSession(\DateTime $demain, \DateTime $apresDemain): void
    {
        $sessions = $this->em->getRepository(Session::class)
            ->createQueryBuilder('s')
            ->where('s.dateDebut >= :demain')
            ->andWhere('s.dateDebut < :apresDemain')
            ->setParameter('demain', $demain)
            ->setParameter('apresDemain', $apresDemain)
            ->getQuery()
            ->getResult();

        foreach ($sessions as $session) {
            try {
                $this->notificationService->notifierRappelJ1Participants($session);
            } catch (\Exception $e) {
                // Log l'erreur mais continue avec les autres sessions
                error_log("Erreur lors de l'envoi du rappel J-1 session: " . $e->getMessage());
            }
        }
    }

    /**
     * Vérifier si les rappels ont déjà été envoyés aujourd'hui
     */
    private function rappelDejaEnvoyeAujourdhui(string $cacheKey): bool
    {
        // Utiliser un fichier simple pour stocker l'état
        $cacheFile = sys_get_temp_dir() . '/rappel_j1_cache.txt';
        
        if (!file_exists($cacheFile)) {
            return false;
        }

        $cache = file_get_contents($cacheFile);
        $cacheData = json_decode($cache, true) ?: [];

        return isset($cacheData[$cacheKey]) && $cacheData[$cacheKey] === true;
    }

    /**
     * Marquer que les rappels ont été envoyés aujourd'hui
     */
    private function marquerRappelEnvoye(string $cacheKey): void
    {
        $cacheFile = sys_get_temp_dir() . '/rappel_j1_cache.txt';
        
        $cacheData = [];
        if (file_exists($cacheFile)) {
            $cache = file_get_contents($cacheFile);
            $cacheData = json_decode($cache, true) ?: [];
        }

        $cacheData[$cacheKey] = true;
        
        file_put_contents($cacheFile, json_encode($cacheData));
    }
} 