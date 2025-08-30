<?php

namespace App\Service;

use App\Entity\Session;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SessionSchedulerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionRepository $sessionRepository,
        private AuditLogService $auditLogService,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

  
    public function verifierEtMettreAJourSessions(): array
    {
        $resultat = [
            'sessions_verifiees' => 0,
            'sessions_modifiees' => 0,
            'notifications_envoyees' => 0,
            'erreurs' => [],
            'details' => []
        ];

        $now = new \DateTime();
        
        // Message de confirmation que le scheduler s'exécute
        echo sprintf(
            "\n🔄 SCHEDULER ACTIF - Exécution à %s - Vérification des sessions en cours...\n",
            $now->format('H:i:s')
        );

        try {
            // Récupérer toutes les sessions avec statut "créée"
            $sessionsACheck = $this->sessionRepository->findBy(['status' => 'créée']);
            $resultat['sessions_verifiees'] += count($sessionsACheck);

            if (!empty($sessionsACheck)) {
                $this->traiterSessionsDebut($sessionsACheck, $resultat);
            }

            // Récupérer toutes les sessions avec statut "en cours"
            $sessionsEnCours = $this->sessionRepository->findBy(['status' => 'en cours']);
            $resultat['sessions_verifiees'] += count($sessionsEnCours);

            if (!empty($sessionsEnCours)) {
                $this->traiterSessionsFin($sessionsEnCours, $resultat);
            }

            // Flush tous les changements
            if ($resultat['sessions_modifiees'] > 0) {
                $this->entityManager->flush();
            }

            // Message de fin d'exécution
            $totalParticipantsAbsence = array_sum(array_column($resultat['details'], 'participants_marques_absence') ?? [0]);
            echo sprintf(
                "✅ SCHEDULER TERMINÉ - %d sessions vérifiées, %d modifiées, %d participants marqués absence - Prochaine exécution dans 40 secondes\n",
                $resultat['sessions_verifiees'],
                $resultat['sessions_modifiees'],
                $totalParticipantsAbsence
            );

        } catch (\Exception $e) {
            $erreur = sprintf('Erreur générale du scheduler: %s', $e->getMessage());
            $resultat['erreurs'][] = $erreur;
            echo "❌ ERREUR: $erreur\n";
        }

        return $resultat;
    }

    /**
     * Traite les sessions qui doivent commencer (statut "créée" → "en cours")
     */
    private function traiterSessionsDebut(array $sessions, array &$resultat): void
    {
        $now = new \DateTime();

        foreach ($sessions as $session) {
            try {
                $dateDebut = $session->getDateDebut();
                
                
                if ($dateDebut <= $now) {
                    $ancienStatut = $session->getStatus();
                    
                    
                    $session->setStatus('en cours');
                    
                    
                    $this->entityManager->persist($session);
                    
                  
                    $this->auditLogService->enregistrer(
                        null,
                        sprintf('Changement automatique de statut session (ID: %d) - Début', $session->getId()),
                        $ancienStatut,
                        'en cours'
                    );
                    
                    // Console log session start
                    echo sprintf("\n🟢 Session passe 'en cours' | ID:%d | Titre:%s | Début:%s\n",
                        $session->getId(),
                        $session->getTitre(),
                        $dateDebut->format('Y-m-d H:i:s')
                    );

                    $participantsMarquesAbsence = $this->marquerParticipantsAbsence($session);
                    
                    // Envoyer des notifications aux participants acceptés
                    $notificationsEnvoyees = $this->notifierParticipantsSession($session);
                    $resultat['notifications_envoyees'] += $notificationsEnvoyees;
                    
                    // Afficher un résumé des inscriptions de la session
                    $this->echoInscriptionsSession($session);
                    
                    $resultat['sessions_modifiees']++;
                    $resultat['details'][] = [
                        'session_id' => $session->getId(),
                        'titre' => $session->getTitre(),
                        'ancien_statut' => $ancienStatut,
                        'nouveau_statut' => 'en cours',
                        'type_changement' => 'debut',
                        'date_debut' => $dateDebut->format('Y-m-d H:i:s'),
                        'timestamp' => $now->format('Y-m-d H:i:s'),
                        'notifications_envoyees' => $notificationsEnvoyees,
                        'participants_marques_absence' => $participantsMarquesAbsence
                    ];
                }
            } catch (\Exception $e) {
                $erreur = sprintf(
                    'Erreur lors du traitement de la session ID %d (début): %s',
                    $session->getId(),
                    $e->getMessage()
                );
                $resultat['erreurs'][] = $erreur;
                $this->logger->error($erreur);
            }
        }
    }

    /**
     * Traite les sessions qui doivent se terminer (statut "en cours" → "terminé")
     */
    private function traiterSessionsFin(array $sessions, array &$resultat): void
    {
        $now = new \DateTime();

        foreach ($sessions as $session) {
            try {
                $dateFin = $session->getDateFin();
                
                // Vérifier si la date de fin est maintenant ou dans le passé
                if ($dateFin <= $now) {
                    $ancienStatut = $session->getStatus();
                    
                    // Mettre à jour le statut
                    $session->setStatus('terminé');
                    
                    // Persister les changements
                    $this->entityManager->persist($session);
                    
                    // Créer un audit log pour ce changement
                    $this->auditLogService->enregistrer(
                        null, // Pas d'utilisateur spécifique pour les changements automatiques
                        sprintf('Changement automatique de statut session (ID: %d) - Fin', $session->getId()),
                        $ancienStatut,
                        'terminé'
                    );
                    
                    // Envoyer des notifications de fin avancées aux participants
                    $notificationsEnvoyees = $this->notifierParticipantsFinSessionAvancee($session);
                    $resultat['notifications_envoyees'] += $notificationsEnvoyees;
                    
                    $resultat['sessions_modifiees']++;
                    $resultat['details'][] = [
                        'session_id' => $session->getId(),
                        'titre' => $session->getTitre(),
                        'ancien_statut' => $ancienStatut,
                        'nouveau_statut' => 'terminé',
                        'type_changement' => 'fin',
                        'date_fin' => $dateFin->format('Y-m-d H:i:s'),
                        'timestamp' => $now->format('Y-m-d H:i:s'),
                        'notifications_envoyees' => $notificationsEnvoyees
                    ];
                }
            } catch (\Exception $e) {
                $erreur = sprintf(
                    'Erreur lors du traitement de la session ID %d (fin): %s',
                    $session->getId(),
                    $e->getMessage()
                );
                $resultat['erreurs'][] = $erreur;
                $this->logger->error($erreur);
            }
        }
    }

    /**
     * Envoie des notifications aux participants ACCEPTÉS d'une session
     */
    private function notifierParticipantsSession(Session $session): int
    {
        $notificationsEnvoyees = 0;
        
        try {
            // Récupérer uniquement les inscriptions ACCEPTÉES pour cette session
            $inscriptionsAcceptees = $this->entityManager->getRepository('App\Entity\Inscription')
                ->createQueryBuilder('i')
                ->where('i.session = :session')
                ->andWhere('i.statutParticipation = :statut')
                ->setParameter('session', $session)
                ->setParameter('statut', 'accepté')
                ->getQuery()
                ->getResult();
            
            if (empty($inscriptionsAcceptees)) {
                return 0;
            }

            $formation = $session->getFormation();
            $formationTitre = $formation ? $formation->getSujet() : 'Formation inconnue';
            
            foreach ($inscriptionsAcceptees as $inscription) {
                try {
                    $participant = $inscription->getUser();
                    
                    if ($participant) {
                        // Envoyer la notification uniquement aux participants acceptés
                        $this->notificationService->notifierParticipantSession(
                            $participant,
                            $session,
                            $formationTitre
                        );
                        
                        $notificationsEnvoyees++;
                        
                        $this->logger->info(sprintf(
                            'Notification de début envoyée au participant accepté %s (ID: %d) pour la session %s (ID: %d)',
                            $participant->getEmail(),
                            $participant->getId(),
                            $session->getTitre(),
                            $session->getId()
                        ));
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Erreur lors de l\'envoi de notification au participant accepté de la session ID %d: %s',
                        $session->getId(),
                        $e->getMessage()
                    ));
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erreur lors de la récupération des participants acceptés de la session ID %d: %s',
                $session->getId(),
                $e->getMessage()
            ));
        }
        
        return $notificationsEnvoyees;
    }

    /**
     * Envoie des notifications de fin de session aux participants ACCEPTÉS uniquement
     */
    private function notifierParticipantsFinSession(Session $session): int
    {
        $notificationsEnvoyees = 0;
        
        try {
            // Récupérer uniquement les inscriptions ACCEPTÉES pour cette session
            $inscriptionsAcceptees = $this->entityManager->getRepository('App\Entity\Inscription')
                ->createQueryBuilder('i')
                ->where('i.session = :session')
                ->andWhere('i.statutParticipation = :statut')
                ->setParameter('session', $session)
                ->setParameter('statut', 'accepté')
                ->getQuery()
                ->getResult();
            
            if (empty($inscriptionsAcceptees)) {
                return 0;
            }

            $formation = $session->getFormation();
            $formationTitre = $formation ? $formation->getSujet() : 'Formation inconnue';
            
            foreach ($inscriptionsAcceptees as $inscription) {
                try {
                    $participant = $inscription->getUser();
                    
                    if ($participant) {
                        // Envoyer la notification de fin uniquement aux participants acceptés
                        $this->notificationService->notifierFinSession(
                            $participant,
                            $session,
                            $formationTitre
                        );
                        
                        $notificationsEnvoyees++;
                        
                        $this->logger->info(sprintf(
                            'Notification de fin envoyée au participant accepté %s (ID: %d) pour la session %s (ID: %d)',
                            $participant->getEmail(),
                            $participant->getId(),
                            $session->getTitre(),
                            $session->getId()
                        ));
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Erreur lors de l\'envoi de notification de fin au participant accepté de la session ID %d: %s',
                        $session->getId(),
                        $e->getMessage()
                    ));
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erreur lors de la récupération des participants acceptés de la session ID %d: %s',
                $session->getId(),
                $e->getMessage()
            ));
        }
        
        return $notificationsEnvoyees;
    }

    /**
     * Envoie des notifications de fin de session avancées aux participants ACCEPTÉS uniquement
     */
    private function notifierParticipantsFinSessionAvancee(Session $session): int
    {
        $notificationsEnvoyees = 0;
        
        try {
            // Récupérer uniquement les inscriptions ACCEPTÉES pour cette session
            $inscriptionsAcceptees = $this->entityManager->getRepository('App\Entity\Inscription')
                ->createQueryBuilder('i')
                ->where('i.session = :session')
                ->andWhere('i.statutParticipation = :statut')
                ->setParameter('session', $session)
                ->setParameter('statut', 'accepté')
                ->getQuery()
                ->getResult();
            
            if (empty($inscriptionsAcceptees)) {
                return 0;
            }

            $formation = $session->getFormation();
            $formationTitre = $formation ? $formation->getSujet() : 'Formation inconnue';
            
            foreach ($inscriptionsAcceptees as $inscription) {
                try {
                    $participant = $inscription->getUser();
                    
                    if ($participant) {
                        // Envoyer la notification de fin avancée uniquement aux participants acceptés
                        $this->notificationService->notifierFinSessionAvancee(
                            $participant,
                            $session,
                            $formationTitre
                        );
                        
                        $notificationsEnvoyees++;
                        
                        $this->logger->info(sprintf(
                            'Notification de fin avancée envoyée au participant accepté %s (ID: %d) pour la session %s (ID: %d)',
                            $participant->getEmail(),
                            $participant->getId(),
                            $session->getTitre(),
                            $session->getId()
                        ));
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Erreur lors de l\'envoi de notification de fin avancée au participant accepté de la session ID %d: %s',
                        $session->getId(),
                        $e->getMessage()
                    ));
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erreur lors de la récupération des participants acceptés de la session ID %d: %s',
                $session->getId(),
                $e->getMessage()
            ));
        }
        
        return $notificationsEnvoyees;
    }

    /**
     * Vérifie si une session spécifique doit être mise à jour
     */
    public function verifierSessionSpecifique(Session $session): bool
    {
        if ($session->getStatus() !== 'créée') {
            return false;
        }

        $now = new \DateTime();
        $dateDebut = $session->getDateDebut();

        return $dateDebut <= $now;
    }

    /**
     * Met à jour le statut d'une session spécifique
     */
    public function mettreAJourSession(Session $session): bool
    {
        if (!$this->verifierSessionSpecifique($session)) {
            return false;
        }

        try {
            $ancienStatut = $session->getStatus();
            $session->setStatus('en cours');
            
            $this->entityManager->persist($session);
            $this->entityManager->flush();
            
            // Marquer les participants non validés comme "absence"
            $this->marquerParticipantsAbsence($session);
            
            // Audit log
            $this->auditLogService->enregistrer(
                null,
                sprintf('Changement automatique de statut session (ID: %d)', $session->getId()),
                $ancienStatut,
                'en cours'
            );
            
            // Envoyer des notifications aux participants
            $this->notifierParticipantsSession($session);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erreur lors de la mise à jour de la session ID %d: %s',
                $session->getId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Marque tous les participants non validés comme "absence" quand une session commence
     */
    private function marquerParticipantsAbsence(Session $session): int
    {
        $participantsMarques = 0;
        
        try {
            // Récupérer uniquement les inscriptions avec statut "en attente" pour cette session
            $inscriptionsNonValidees = $this->entityManager->getRepository('App\Entity\Inscription')
                ->createQueryBuilder('i')
                ->where('i.session = :session')
                ->andWhere('i.statutParticipation = :statutEnAttente')
                ->setParameter('session', $session)
                ->setParameter('statutEnAttente', 'en attente')
                ->getQuery()
                ->getResult();
            
            if (empty($inscriptionsNonValidees)) {
                echo sprintf(
                    "   ↪ Aucun participant en attente pour session ID:%d\n",
                    $session->getId()
                );
                return 0;
            }

            $formation = $session->getFormation();
            $formationTitre = $formation ? $formation->getSujet() : 'Formation inconnue';
            
            foreach ($inscriptionsNonValidees as $inscription) {
                $ancienStatut = $inscription->getStatutParticipation() ?? 'en attente';
                
                // Changer le statut à "absence"
                $inscription->setStatutParticipation('absence');
                
                // Persister le changement
                $this->entityManager->persist($inscription);
                $participant = $inscription->getUser();
                echo sprintf(
                    "   ✳️ Inscription ID:%d | User:%s | %s → absence\n",
                    $inscription->getId(),
                    $participant ? $participant->getEmail() : 'inconnu',
                    $ancienStatut
                );
                
                // Créer un audit log pour ce changement
                $this->auditLogService->enregistrer(
                    null, // Pas d'utilisateur spécifique pour les changements automatiques
                    sprintf('Changement automatique statut participation (Session: %s, Formation: %s)', 
                        $session->getTitre(), 
                        $formationTitre
                    ),
                    $ancienStatut,
                    'absence'
                );
                
                // Envoyer une notification au participant pour l'informer de son absence
                $this->notifierParticipantAbsence($inscription, $session, $formationTitre);
                
                $participantsMarques++;
            }
            
            // Flush tous les changements
            $this->entityManager->flush();
            echo sprintf(
                "   ✅ Total marqués 'absence' pour session ID:%d → %d\n",
                $session->getId(),
                $participantsMarques
            );
            
            $this->logger->info(sprintf(
                'Session ID %d (%s) : %d participants marqués comme absence',
                $session->getId(),
                $session->getTitre(),
                $participantsMarques
            ));
            
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erreur lors du marquage des participants en absence pour la session ID %d: %s',
                $session->getId(),
                $e->getMessage()
            ));
        }
        
        return $participantsMarques;
    }

    /**
     * Notifie un participant qu'il sera absent de la session
     */
    private function notifierParticipantAbsence($inscription, Session $session, string $formationTitre): void
    {
        try {
            $participant = $inscription->getUser();
            
            if ($participant) {
                // Créer la notification d'absence
                $this->notificationService->creerNotification(
                    $participant,
                    'Absence automatique - Session de formation',
                    sprintf(
                        'Bonjour %s %s,<br><br>' .
                        'La session de formation "%s" (Formation: %s) commence maintenant.<br><br>' .
                        'Votre participation n\'ayant pas été validée à temps, vous êtes automatiquement marqué(e) comme <strong>ABSENT(E)</strong> de cette session.<br><br>' .
                        'Si vous souhaitez participer à une prochaine session de cette formation, veuillez contacter le responsable.<br><br>' .
                        'Cordialement,<br>' .
                        'Système de gestion des formations',
                        $participant->getPrenom(),
                        $participant->getNom(),
                        $session->getTitre(),
                        $formationTitre
                    ),
                    'absence_automatique',
                    json_encode([
                        'session_id' => $session->getId(),
                        'formation_id' => $session->getFormation() ? $session->getFormation()->getId() : null,
                        'type' => 'absence_automatique'
                    ])
                );
                
                $this->logger->info(sprintf(
                    'Notification d\'absence envoyée au participant %s (ID: %d) pour la session %s (ID: %d)',
                    $participant->getEmail(),
                    $participant->getId(),
                    $session->getTitre(),
                    $session->getId()
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Erreur lors de l\'envoi de la notification d\'absence au participant de la session ID %d: %s',
                $session->getId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Affiche dans la console la liste des inscriptions d'une session et un résumé par statut
     */
    private function echoInscriptionsSession(Session $session): void
    {
        try {
            $repo = $this->entityManager->getRepository('App\\Entity\\Inscription');
            $inscriptions = $repo->createQueryBuilder('i')
                ->leftJoin('i.user', 'u')
                ->addSelect('u')
                ->where('i.session = :session')
                ->setParameter('session', $session)
                ->orderBy('i.id', 'ASC')
                ->getQuery()
                ->getResult();

            $counts = [
                'accepté' => 0,
                'en attente' => 0,
                'absence' => 0,
                'autre' => 0,
            ];

            echo "   📋 Inscriptions de la session:\n";
            foreach ($inscriptions as $inscription) {
                $user = $inscription->getUser();
                $email = $user ? $user->getEmail() : 'inconnu';
                $statut = $inscription->getStatutParticipation() ?? 'en attente';
                if (!isset($counts[$statut])) {
                    $counts['autre']++;
                } else {
                    $counts[$statut]++;
                }
                echo sprintf(
                    "      • ID:%d | User:%s | Statut:%s\n",
                    $inscription->getId(),
                    $email,
                    $statut
                );
            }

            $total = count($inscriptions);
            echo sprintf(
                "   🔎 Résumé: total=%d | accepté=%d | en attente=%d | absence=%d | autre=%d\n",
                $total,
                $counts['accepté'] ?? 0,
                $counts['en attente'] ?? 0,
                $counts['absence'] ?? 0,
                $counts['autre'] ?? 0
            );
        } catch (\Throwable $e) {
            echo sprintf("   ⚠️ Erreur affichage inscriptions: %s\n", $e->getMessage());
        }
    }
}
