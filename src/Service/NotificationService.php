<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Formation;
use App\Entity\Session;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationAuditService;

class NotificationService
{
    private EntityManagerInterface $em;
    private NotificationRepository $notificationRepo;
    private NotificationAuditService $notificationAuditService;

    public function __construct(EntityManagerInterface $em, NotificationRepository $notificationRepo, NotificationAuditService $notificationAuditService)
    {
        $this->em = $em;
        $this->notificationRepo = $notificationRepo;
        $this->notificationAuditService = $notificationAuditService;
    }

    /**
     * Créer une notification pour un utilisateur
     */
    public function creerNotification(
        User $destinataire,
        string $titre,
        string $message,
        string $type = 'general',
        ?string $lien = null,
        ?Formation $formation = null,
        ?Session $session = null
    ): Notification {
        $notification = new Notification();
        $notification->setDestinataire($destinataire);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setLien($lien);
        $notification->setFormation($formation);
        $notification->setSession($session);

        $this->em->persist($notification);
        $this->em->flush();

        // AuditLog pour chaque notification envoyée
        $this->notificationAuditService->enregistrerNotification(
            $destinataire,
            $type,
            $destinataire->getEmail(),
            $titre,
            $message,
            $lien,
            $formation ? $formation->getId() : null,
            $session ? $session->getId() : null
        );

        return $notification;
    }

    /**
     * Notifier le créateur de la formation (RH)
     */
    public function notifierCreateurFormation(Formation $formation, User $createur): void
    {
        $this->creerNotification(
            $createur,
            'Formation créée avec succès',
            sprintf(
                'La formation "%s" a été créée avec succès. ' .
                'Responsable désigné : %s %s. ' .
                'Veuillez vérifier les détails dans votre espace administrateur.',
                $formation->getSujet(),
                $formation->getResponsable() ? $formation->getResponsable()->getPrenom() : 'Non défini',
                $formation->getResponsable() ? $formation->getResponsable()->getNom() : ''
            ),
            'creation',
            '/admin/formations',
            $formation
        );
    }

    /**
     * Notifier le responsable d'une nouvelle formation
     */
    public function notifierResponsableFormation(Formation $formation): void
    {
        $responsable = $formation->getResponsable();
        if (!$responsable) {
            return;
        }

        $this->creerNotification(
            $responsable,
            'Nouvelle formation créée',
            sprintf(
                'Une nouvelle formation "%s" a été créée et vous avez été désigné(e) comme responsable. ' .
                'Veuillez la valider dans votre espace administrateur.',
                $formation->getSujet()
            ),
            'formation',
            '/admin/formations',
            $formation
        );
    }

    /**
     * Notifier les participants d'une inscription
     */
    public function notifierParticipantInscription(User $participant, Formation $formation, Session $session): void
    {
        $this->creerNotification(
            $participant,
            'Inscription à une formation',
            sprintf(
                'Vous avez été inscrit(e) à la formation "%s" pour la session "%s". ' .
                'Veuillez confirmer votre participation.',
                $formation->getSujet(),
                $session->getTitre()
            ),
            'inscription',
            '/user/formations',
            $formation,
            $session
        );
    }

    /**
     * Notifier les participants qu'une session commence (statut changé vers "en cours")
     */
    public function notifierParticipantSession(User $participant, Session $session, string $formationTitre): void
    {
        $this->creerNotification(
            $participant,
            'Session en cours',
            sprintf(
                'La session "%s" de la formation "%s" commence maintenant ! ' .
                'Veuillez vous rendre au lieu de formation : %s. ' .
                'Horaires : %s à %s.',
                $session->getTitre(),
                $formationTitre,
                $session->getSalle() ? $session->getSalle()->getNom() : $session->getEmplacement(),
                $session->getDateDebut()->format('d/m/Y H:i'),
                $session->getDateFin()->format('H:i')
            ),
            'session_en_cours',
            '/user/sessions',
            $session->getFormation(),
            $session
        );
    }

    /**
     * Notifier les participants qu'une session se termine (statut changé vers "terminé")
     */
    public function notifierFinSession(User $participant, Session $session, string $formationTitre): void
    {
        // Créer la notification
        $notification = $this->creerNotification(
            $participant,
            'Session terminée - Évaluation requise',
            sprintf(
                'La session "%s" de la formation "%s" est maintenant terminée. ' .
                'Merci pour votre participation ! ' .
                'Horaires : %s à %s. ' .
                'Veuillez évaluer cette session en cliquant sur le lien ci-dessous.',
                $session->getTitre(),
                $formationTitre,
                $session->getDateDebut()->format('d/m/Y H:i'),
                $session->getDateFin()->format('H:i')
            ),
            'session_terminee',
            sprintf('/user/sessions/%d/evaluation', $session->getId()),
            $session->getFormation(),
            $session
        );
        
        // Mettre à jour la colonne evaluationsEnvoyee dans l'inscription
        $this->mettreAJourEvaluationEnvoyee($participant, $session);
    }

    /**
     * Créer une notification avec lien personnalisé et actions spécifiques
     */
    public function creerNotificationAvancee(
        User $destinataire,
        string $titre,
        string $message,
        string $type,
        array $actions = [],
        ?Formation $formation = null,
        ?Session $session = null
    ): Notification {
        $notification = new Notification();
        $notification->setDestinataire($destinataire);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setType($type);
        
        // Lien principal (première action)
        if (!empty($actions)) {
            $notification->setLien($actions[0]['url']);
        }
        
        $notification->setFormation($formation);
        $notification->setSession($session);

        $this->em->persist($notification);
        $this->em->flush();

        // AuditLog pour chaque notification envoyée
        $this->notificationAuditService->enregistrerNotification(
            $destinataire,
            $type,
            $destinataire->getEmail(),
            $titre,
            $message,
            $actions[0]['url'] ?? null,
            $formation ? $formation->getId() : null,
            $session ? $session->getId() : null
        );

        return $notification;
    }
    
    /**
     * Mettre à jour la colonne evaluationsEnvoyee dans l'inscription
     */
    private function mettreAJourEvaluationEnvoyee(User $participant, Session $session): void
    {
        try {
            // Récupérer l'inscription pour ce participant et cette session
            $inscription = $this->em->getRepository('App\Entity\Inscription')
                ->findOneBy([
                    'user' => $participant,
                    'session' => $session
                ]);
            
            if ($inscription) {
                // Mettre à jour la colonne evaluationsEnvoyee
                $inscription->setEvaluationsEnvoyee(true);
                $this->em->persist($inscription);
                $this->em->flush();
                
                // Créer un audit log pour cette mise à jour
                $this->notificationAuditService->enregistrerNotification(
                    $participant,
                    'evaluation_envoyee',
                    $participant->getEmail(),
                    'Notification d\'évaluation envoyée',
                    sprintf('Notification d\'évaluation envoyée pour la session "%s"', $session->getTitre()),
                    sprintf('/user/sessions/%d/evaluation', $session->getId()),
                    $session->getFormation() ? $session->getFormation()->getId() : null,
                    $session->getId()
                );
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer l'envoi de la notification
            error_log(sprintf(
                'Erreur lors de la mise à jour de evaluationsEnvoyee pour participant %s, session %s: %s',
                $participant->getEmail(),
                $session->getId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Notifier la fin de session avec interface d'évaluation avancée
     */
    public function notifierFinSessionAvancee(User $participant, Session $session, string $formationTitre): void
    {
        $actions = [
            [
                'url' => sprintf('/user/sessions/%d/evaluation', $session->getId()),
                'label' => 'Évaluer la session',
                'icon' => 'star',
                'color' => 'primary'
            ],
            [
                'url' => sprintf('/user/sessions/%d/certificat', $session->getId()),
                'label' => 'Télécharger certificat',
                'icon' => 'download',
                'color' => 'success'
            ],
            [
                'url' => sprintf('/user/sessions/%d/feedback', $session->getId()),
                'label' => 'Donner un feedback',
                'icon' => 'message-circle',
                'color' => 'info'
            ]
        ];

        $this->creerNotificationAvancee(
            $participant,
            'Session terminée - Actions disponibles',
            sprintf(
                '🎉 Félicitations ! La session "%s" de la formation "%s" est terminée. ' .
                'Horaires : %s à %s. ' .
                'Vous pouvez maintenant :' . "\n" .
                '• Évaluer la qualité de la session' . "\n" .
                '• Télécharger votre certificat' . "\n" .
                '• Donner un feedback détaillé',
                $session->getTitre(),
                $formationTitre,
                $session->getDateDebut()->format('d/m/Y H:i'),
                $session->getDateFin()->format('H:i')
            ),
            'session_terminee_avancee',
            $actions,
            $session->getFormation(),
            $session
        );
        
        // Mettre à jour la colonne evaluationsEnvoyee dans l'inscription
        $this->mettreAJourEvaluationEnvoyee($participant, $session);
    }

    /**
     * Notifier d'un rappel de session
     */
    public function notifierRappelSession(User $participant, Session $session): void
    {
        $formation = $session->getFormation();
        $this->creerNotification(
            $participant,
            'Rappel de session',
            sprintf(
                'Rappel : La session "%s" de la formation "%s" commence dans 24h. ' .
                'Lieu : %s',
                $session->getTitre(),
                $formation->getSujet(),
                $session->getSalle() ? $session->getSalle()->getNom() : $session->getEmplacement()
            ),
            'rappel',
            '/user/sessions',
            $formation,
            $session
        );
    }

    /**
     * Notifier le responsable d'un rappel J-1 avant le début de la formation
     */
    public function notifierRappelJ1Responsable(Formation $formation): void
    {
        $responsable = $formation->getResponsable();
        if (!$responsable) {
            return;
        }

        $sessions = $formation->getSessions();
        $sessionsInfo = [];
        
        foreach ($sessions as $session) {
            $sessionsInfo[] = sprintf(
                '- Session "%s" : %s à %s - Lieu : %s',
                $session->getTitre(),
                $session->getDateDebut()->format('d/m/Y H:i'),
                $session->getDateFin()->format('H:i'),
                $session->getSalle() ? $session->getSalle()->getNom() : $session->getEmplacement()
            );
        }

        $this->creerNotification(
            $responsable,
            'Rappel J-1 - Début de formation',
            sprintf(
                'Rappel : La formation "%s" commence demain. ' .
                'Veuillez vérifier que tout est prêt pour les sessions suivantes :' .
                "\n\n%s\n\n" .
                'Nombre total de participants : %d',
                $formation->getSujet(),
                implode("\n", $sessionsInfo),
                $this->compterParticipantsFormation($formation)
            ),
            'rappel_responsable',
            '/admin/formations',
            $formation
        );
    }

    /**
     * Compter le nombre total de participants pour une formation
     */
    private function compterParticipantsFormation(Formation $formation): int
    {
        $total = 0;
        foreach ($formation->getSessions() as $session) {
            $inscriptions = $this->em->getRepository('App\Entity\Inscription')->findBy(['session' => $session]);
            $total += count($inscriptions);
        }
        return $total;
    }

    /**
     * Envoyer un rappel J-1 aux participants d'une session
     */
    public function notifierRappelJ1Participants(Session $session): void
    {
        $formation = $session->getFormation();
        $dateDebut = $session->getDateDebut()->format('d/m/Y');
        $heureDebut = $session->getDateDebut()->format('H:i');
        $emplacement = $session->getSalle() ? $session->getSalle()->getNom() : $session->getEmplacement();

        // Récupérer tous les participants de cette session
        $inscriptions = $this->em->getRepository('App\Entity\Inscription')->findBy(['session' => $session]);
        
        foreach ($inscriptions as $inscription) {
            $participant = $inscription->getUser();
            
            $this->creerNotification(
                $participant,
                'Rappel J-1 - Session commence demain',
                sprintf(
                    'Bonjour %s %s,<br><br>' .
                    'La session "%s" de la formation "%s" commence demain le %s à %s.<br><br>' .
                    'Lieu : %s<br><br>' .
                    'N\'oubliez pas de vous préparer et d\'arriver à l\'heure.',
                    $participant->getPrenom(),
                    $participant->getNom(),
                    $session->getTitre(),
                    $formation->getSujet(),
                    $dateDebut,
                    $heureDebut,
                    $emplacement
                ),
                'rappel_j1_session',
                '/user/sessions',
                null,
                $session
            );
        }
    }


    /**
     * Notifier d'une validation de formation
     */
    public function notifierValidationFormation(Formation $formation): void
    {
        $sessions = $formation->getSessions();
        foreach ($sessions as $session) {
            $inscriptions = $this->em->getRepository('App\Entity\Inscription')->findBy(['session' => $session]);
            
            foreach ($inscriptions as $inscription) {
                $participant = $inscription->getUser();
                $this->creerNotification(
                    $participant,
                    'Formation validée',
                    sprintf(
                        'La formation "%s" a été validée par le responsable. ' .
                        'La session "%s" est confirmée.',
                        $formation->getSujet(),
                        $session->getTitre()
                    ),
                    'validation',
                    '/user/formations',
                    $formation,
                    $session
                );
            }
        }
    }

    /**
     * Notifier d'une annulation de session
     */
    public function notifierAnnulationSession(Session $session): void
    {
        $formation = $session->getFormation();
        $inscriptions = $this->em->getRepository('App\Entity\Inscription')->findBy(['session' => $session]);
        
        foreach ($inscriptions as $inscription) {
            $participant = $inscription->getUser();
            $this->creerNotification(
                $participant,
                'Session annulée',
                sprintf(
                    'La session "%s" de la formation "%s" a été annulée.',
                    $session->getTitre(),
                    $formation->getSujet()
                ),
                'annulation',
                '/user/formations',
                $formation,
                $session
            );
        }
    }

    /**
     * Retourne le créateur d'une formation via l'AuditLog (user_id du log où valeurApres.formation_id = formation->getId())
     */
    private function getCreateurFormation(Formation $formation): ?User
    {
        $auditLogRepo = $this->em->getRepository('App\\Entity\\AuditLog');
        $logs = $auditLogRepo->createQueryBuilder('a')
            ->where('a.action = :action')
            ->setParameter('action', 'Création formation')
            ->orderBy('a.horodatage', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($logs as $log) {
            $apres = json_decode($log->getValeurApres(), true);
            if (is_array($apres)) {
                if (
                    (isset($apres['formation_id']) && $apres['formation_id'] == $formation->getId()) ||
                    (isset($apres['formation']) && $apres['formation'] == $formation->getId())
                ) {
                    return $log->getUser();
                }
            }
        }
        return null;
    }

    /**
     * Notifier lors de la modification d'une formation
     */
    public function notifierModificationFormation(Formation $formation, User $acteur, string $typeModif = 'modification')
    {
        $responsable = $formation->getResponsable();
        $createur = $this->getCreateurFormation($formation);
        // Récupérer tous les participants de toutes les sessions de la formation
        $usersCibles = [];
        foreach ($formation->getSessions() as $session) {
            foreach ($session->getInscriptions() as $insc) {
                $user = $insc->getUser();
                if ($user) $usersCibles[$user->getId()] = $user;
            }
        }
        // Message personnalisé
        $titre = 'Formation modifiée';
        $message = sprintf('La formation "%s" a été modifiée. Merci de vérifier les détails.', $formation->getSujet());
        if ($typeModif === 'ajout') {
            $titre = 'Nouvelle formation ajoutée';
            $message = sprintf('Une nouvelle formation "%s" a été ajoutée.', $formation->getSujet());
        }
        if ($createur && $acteur->getId() === $createur->getId()) {
            // Créateur modifie : notifier users ciblés (hors responsable) + responsable
            $dejaNotifie = [];
            foreach ($usersCibles as $user) {
                if ($responsable && $user->getId() === $responsable->getId()) {
                    $dejaNotifie[$responsable->getId()] = true;
                    continue;
                }
                $this->creerNotification($user, $titre, $message, 'modification', null, $formation);
                $dejaNotifie[$user->getId()] = true;
            }
            // Notifier le responsable (sauf si c'est le créateur ou déjà notifié)
            if ($responsable && $acteur->getId() !== $responsable->getId() && !isset($dejaNotifie[$responsable->getId()])) {
                $this->creerNotification($responsable, $titre, $message, 'modification', null, $formation);
            }
        } else if ($responsable && $acteur->getId() === $responsable->getId()) {
            // Responsable modifie : notifier users ciblés + créateur (éviter doublon)
            $dejaNotifie = [];
            if ($createur && $responsable->getId() !== $createur->getId()) {
                $this->creerNotification($createur, $titre, $message, 'modification', null, $formation);
                $dejaNotifie[$createur->getId()] = true;
            }
            foreach ($usersCibles as $user) {
                if ($createur && $user->getId() === $createur->getId()) continue; // déjà notifié
                $this->creerNotification($user, $titre, $message, 'modification', null, $formation);
            }
        } else {
            // Admin (RH) ou autre utilisateur modifie : notifier TOUS les participants + responsable + créateur
            $dejaNotifie = [];
            
            // Notifier tous les participants
            foreach ($usersCibles as $user) {
                $this->creerNotification($user, $titre, $message, 'modification', null, $formation);
                $dejaNotifie[$user->getId()] = true;
            }
            
            // Notifier le responsable (sauf si c'est l'acteur)
            if ($responsable && $acteur->getId() !== $responsable->getId() && !isset($dejaNotifie[$responsable->getId()])) {
                $this->creerNotification($responsable, $titre, $message, 'modification', null, $formation);
                $dejaNotifie[$responsable->getId()] = true;
            }
            
            // Notifier le créateur (sauf si c'est l'acteur ou déjà notifié)
            if ($createur && $acteur->getId() !== $createur->getId() && !isset($dejaNotifie[$createur->getId()])) {
                $this->creerNotification($createur, $titre, $message, 'modification', null, $formation);
            }
        }
        // Log debug notification
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF FORMATION: acteur=" . ($acteur ? $acteur->getId() . ' ' . $acteur->getEmail() : 'null') . ", createur=" . ($createur ? $createur->getId() . ' ' . $createur->getEmail() : 'null') . ", responsable=" . ($responsable ? $responsable->getId() . ' ' . $responsable->getEmail() : 'null') . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF FORMATION - CASE: " . ($createur && $acteur->getId() === $createur->getId() ? "CREATEUR" : ($responsable && $acteur->getId() === $responsable->getId() ? "RESPONSABLE" : "AUTRE")) . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF FORMATION - PARTICIPANTS COUNT: " . count($usersCibles) . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF FORMATION - RESPONSABLE NOTIFIED: " . ($responsable && $acteur->getId() !== $responsable->getId() ? "YES" : "NO") . "\n", FILE_APPEND);
    }

    /**
     * Notifier lors de la modification ou ajout d'une session
     */
    public function notifierModificationSession(Session $session, User $acteur, string $typeModif = 'modification')
    {
        $formation = $session->getFormation();
        $responsable = $formation ? $formation->getResponsable() : null;
        $createur = $formation ? $this->getCreateurFormation($formation) : null;
        // Récupérer tous les participants de la session directement via la table inscription
        $usersCibles = [];
        if ($session && $session->getId()) {
            $inscriptions = $this->em->getRepository('App\\Entity\\Inscription')->findBy(['session' => $session]);
            foreach ($inscriptions as $insc) {
                $user = $insc->getUser();
                if ($user) $usersCibles[$user->getId()] = $user;
            }
        }
        // Message personnalisé
        $titre = 'Session modifiée';
        $message = sprintf('La session "%s" de la formation "%s" a été modifiée. Merci de vérifier les détails.', $session->getTitre(), $formation ? $formation->getSujet() : '');
        if ($typeModif === 'ajout') {
            $titre = 'Nouvelle session ajoutée';
            $message = sprintf('Une nouvelle session "%s" a été ajoutée à la formation "%s".', $session->getTitre(), $formation ? $formation->getSujet() : '');
        }
        if ($createur && $acteur->getId() === $createur->getId()) {
            // Créateur modifie : notifier users ciblés (hors responsable) + responsable
            $dejaNotifie = [];
            foreach ($usersCibles as $user) {
                if ($responsable && $user->getId() === $responsable->getId()) {
                    $dejaNotifie[$responsable->getId()] = true;
                    continue;
                }
                $this->creerNotification($user, $titre, $message, 'modification', null, $formation, $session);
                $dejaNotifie[$user->getId()] = true;
            }
            // Notifier le responsable (sauf si c'est le créateur ou déjà notifié)
            if ($responsable && $acteur->getId() !== $responsable->getId() && !isset($dejaNotifie[$responsable->getId()])) {
                $this->creerNotification($responsable, $titre, $message, 'modification', null, $formation, $session);
            }
        } else if ($responsable && $acteur->getId() === $responsable->getId()) {
            // Responsable modifie : notifier TOUS les users ciblés + créateur (éviter doublon)
            $dejaNotifie = [];
            foreach ($usersCibles as $user) {
                if ($createur && $user->getId() === $createur->getId()) {
                    $dejaNotifie[$user->getId()] = true;
                    continue;
                }
                $this->creerNotification($user, $titre, $message, 'modification', null, $formation, $session);
                $dejaNotifie[$user->getId()] = true;
            }
            if ($createur && $responsable->getId() !== $createur->getId() && !isset($dejaNotifie[$createur->getId()])) {
                $this->creerNotification($createur, $titre, $message, 'modification', null, $formation, $session);
            }
        } else {
            // Admin (RH) ou autre utilisateur modifie : notifier TOUS les participants + responsable + créateur
            $dejaNotifie = [];
            
            // Notifier tous les participants
            foreach ($usersCibles as $user) {
                $this->creerNotification($user, $titre, $message, 'modification', null, $formation, $session);
                $dejaNotifie[$user->getId()] = true;
            }
            
            // Notifier le responsable (sauf si c'est l'acteur)
            if ($responsable && $acteur->getId() !== $responsable->getId() && !isset($dejaNotifie[$responsable->getId()])) {
                $this->creerNotification($responsable, $titre, $message, 'modification', null, $formation, $session);
                $dejaNotifie[$responsable->getId()] = true;
            }
            
            // Notifier le créateur (sauf si c'est l'acteur ou déjà notifié)
            if ($createur && $acteur->getId() !== $createur->getId() && !isset($dejaNotifie[$createur->getId()])) {
                $this->creerNotification($createur, $titre, $message, 'modification', null, $formation, $session);
            }
        }
        // Log debug notification session
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF SESSION: acteur=" . ($acteur ? $acteur->getId() . ' ' . $acteur->getEmail() : 'null') . ", createur=" . ($createur ? $createur->getId() . ' ' . $createur->getEmail() : 'null') . ", responsable=" . ($responsable ? $responsable->getId() . ' ' . $responsable->getEmail() : 'null') . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF SESSION - CASE: " . ($createur && $acteur->getId() === $createur->getId() ? "CREATEUR" : ($responsable && $acteur->getId() === $responsable->getId() ? "RESPONSABLE" : "AUTRE")) . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF SESSION - RESPONSABLE NOTIFIED: " . ($responsable && $acteur->getId() !== $responsable->getId() ? "YES" : "NO") . "\n", FILE_APPEND);
        // Log debug participants ciblés
        $debugCibles = array_map(function($u) { return $u->getId() . ' ' . $u->getEmail(); }, $usersCibles);
        file_put_contents(__DIR__ . '/../../public/users_debug.log', "NOTIF SESSION CIBLES: [" . implode(', ', $debugCibles) . "]\n", FILE_APPEND);
    }

    /**
     * Obtenir les notifications non lues d'un utilisateur
     */
    public function getNotificationsNonLues(User $user): array
    {
        return $this->notificationRepo->findNonLuesByUser($user);
    }

    /**
     * Compter les notifications non lues d'un utilisateur
     */
    public function countNotificationsNonLues(User $user): int
    {
        return $this->notificationRepo->countNonLuesByUser($user);
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLue(Notification $notification): void
    {
        $this->notificationRepo->marquerCommeLue($notification);
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function marquerToutesCommeLues(User $user): void
    {
        $this->notificationRepo->marquerToutesCommeLues($user);
    }
} 