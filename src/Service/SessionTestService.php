<?php

namespace App\Service;

use App\Entity\Session;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SessionTestService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionRepository $sessionRepository,
        private SessionSchedulerService $sessionSchedulerService,
        private LoggerInterface $logger
    ) {}

    /**
     * Teste le changement de statut d'une session spécifique
     */
    public function testerChangementStatut(int $sessionId): array
    {
        $resultat = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        try {
            // Récupérer la session
            $session = $this->sessionRepository->find($sessionId);
            
            if (!$session) {
                $resultat['message'] = sprintf('Session ID %d non trouvée', $sessionId);
                return $resultat;
            }

            $resultat['details']['session'] = [
                'id' => $session->getId(),
                'titre' => $session->getTitre(),
                'statut_actuel' => $session->getStatus(),
                'date_debut' => $session->getDateDebut()->format('Y-m-d H:i:s'),
                'date_fin' => $session->getDateFin()->format('Y-m-d H:i:s'),
                'formation' => $session->getFormation() ? $session->getFormation()->getSujet() : 'Aucune'
            ];

            // Vérifier si la session peut être mise à jour
            if ($session->getStatus() !== 'créée') {
                $resultat['message'] = sprintf(
                    'La session "%s" a déjà le statut "%s". Impossible de la mettre à jour.',
                    $session->getTitre(),
                    $session->getStatus()
                );
                return $resultat;
            }

            // Simuler le changement de statut
            $ancienStatut = $session->getStatus();
            $session->setStatus('en cours');
            
            $this->entityManager->persist($session);
            $this->entityManager->flush();

            $resultat['details']['changement'] = [
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => 'en cours',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

            // Tester l'envoi de notifications
            $notificationsEnvoyees = $this->testerNotifications($session);
            $resultat['details']['notifications'] = $notificationsEnvoyees;

            $resultat['success'] = true;
            $resultat['message'] = sprintf(
                'Session "%s" mise à jour avec succès. %d notification(s) envoyée(s).',
                $session->getTitre(),
                $notificationsEnvoyees
            );

        } catch (\Exception $e) {
            $resultat['message'] = sprintf('Erreur lors du test: %s', $e->getMessage());
            $this->logger->error(sprintf('Erreur SessionTestService: %s', $e->getMessage()));
        }

        return $resultat;
    }

    /**
     * Teste l'envoi de notifications pour une session
     */
    private function testerNotifications(Session $session): array
    {
        $resultat = [
            'total_envoyees' => 0,
            'participants_notifies' => [],
            'erreurs' => []
        ];

        try {
            $inscriptions = $session->getInscriptions();
            
            if ($inscriptions->isEmpty()) {
                $resultat['erreurs'][] = 'Aucun participant trouvé pour cette session';
                return $resultat;
            }

            foreach ($inscriptions as $inscription) {
                try {
                    $participant = $inscription->getUser();
                    
                    if ($participant) {
                        $resultat['participants_notifies'][] = [
                            'id' => $participant->getId(),
                            'nom' => $participant->getNom(),
                            'prenom' => $participant->getPrenom(),
                            'email' => $participant->getEmail()
                        ];
                        
                        $resultat['total_envoyees']++;
                    }
                } catch (\Exception $e) {
                    $resultat['erreurs'][] = sprintf(
                        'Erreur participant ID %d: %s',
                        $inscription->getId(),
                        $e->getMessage()
                    );
                }
            }
            
        } catch (\Exception $e) {
            $resultat['erreurs'][] = sprintf('Erreur générale: %s', $e->getMessage());
        }

        return $resultat;
    }

    /**
     * Liste toutes les sessions avec leur statut
     */
    public function listerSessions(): array
    {
        $sessions = $this->sessionRepository->findAll();
        $resultat = [];

        foreach ($sessions as $session) {
            $resultat[] = [
                'id' => $session->getId(),
                'titre' => $session->getTitre(),
                'statut' => $session->getStatus(),
                'date_debut' => $session->getDateDebut()->format('Y-m-d H:i:s'),
                'date_fin' => $session->getDateFin()->format('Y-m-d H:i:s'),
                'formation' => $session->getFormation() ? $session->getFormation()->getSujet() : 'Aucune',
                'participants' => $session->getInscriptions()->count()
            ];
        }

        return $resultat;
    }

    /**
     * Remet une session au statut "créée" pour les tests
     */
    public function remettreSessionCreee(int $sessionId): bool
    {
        try {
            $session = $this->sessionRepository->find($sessionId);
            
            if (!$session) {
                return false;
            }

            $session->setStatus('créée');
            $this->entityManager->persist($session);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Erreur remise session créée: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * Teste la fin d'une session (changement de statut vers "terminé")
     */
    public function testerFinSession(int $sessionId): array
    {
        $resultat = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        try {
            // Récupérer la session
            $session = $this->sessionRepository->find($sessionId);
            
            if (!$session) {
                $resultat['message'] = sprintf('Session ID %d non trouvée', $sessionId);
                return $resultat;
            }

            $resultat['details']['session'] = [
                'id' => $session->getId(),
                'titre' => $session->getTitre(),
                'statut_actuel' => $session->getStatus(),
                'date_debut' => $session->getDateDebut()->format('Y-m-d H:i:s'),
                'date_fin' => $session->getDateFin()->format('Y-m-d H:i:s'),
                'formation' => $session->getFormation() ? $session->getFormation()->getSujet() : 'Aucune'
            ];

            // Vérifier si la session peut être mise à jour
            if ($session->getStatus() !== 'en cours') {
                $resultat['message'] = sprintf(
                    'La session "%s" a le statut "%s". Impossible de la terminer.',
                    $session->getTitre(),
                    $session->getStatus()
                );
                return $resultat;
            }

            // Simuler le changement de statut
            $ancienStatut = $session->getStatus();
            $session->setStatus('terminé');
            
            $this->entityManager->persist($session);
            $this->entityManager->flush();

            $resultat['details']['changement'] = [
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => 'terminé',
                'type_changement' => 'fin',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

            // Tester l'envoi de notifications de fin
            $notificationsEnvoyees = $this->testerNotificationsFin($session);
            $resultat['details']['notifications'] = $notificationsEnvoyees;

            $resultat['success'] = true;
            $resultat['message'] = sprintf(
                'Session "%s" terminée avec succès. %d notification(s) envoyée(s).',
                $session->getTitre(),
                $notificationsEnvoyees
            );

        } catch (\Exception $e) {
            $resultat['message'] = sprintf('Erreur lors du test de fin: %s', $e->getMessage());
            $this->logger->error(sprintf('Erreur SessionTestService (fin): %s', $e->getMessage()));
        }

        return $resultat;
    }

    /**
     * Teste l'envoi de notifications de fin pour une session
     */
    private function testerNotificationsFin(Session $session): array
    {
        $resultat = [
            'total_envoyees' => 0,
            'participants_notifies' => [],
            'erreurs' => []
        ];

        try {
            $inscriptions = $session->getInscriptions();
            
            if ($inscriptions->isEmpty()) {
                $resultat['erreurs'][] = 'Aucun participant trouvé pour cette session';
                return $resultat;
            }

            foreach ($inscriptions as $inscription) {
                try {
                    $participant = $inscription->getUser();
                    
                    if ($participant) {
                        $resultat['participants_notifies'][] = [
                            'id' => $participant->getId(),
                            'nom' => $participant->getNom(),
                            'prenom' => $participant->getPrenom(),
                            'email' => $participant->getEmail()
                        ];
                        
                        $resultat['total_envoyees']++;
                    }
                } catch (\Exception $e) {
                    $resultat['erreurs'][] = sprintf(
                        'Erreur participant ID %d: %s',
                        $inscription->getId(),
                        $e->getMessage()
                    );
                }
            }
            
        } catch (\Exception $e) {
            $resultat['erreurs'][] = sprintf('Erreur générale: %s', $e->getMessage());
        }

        return $resultat;
    }
}
