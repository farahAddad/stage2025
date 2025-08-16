<?php

namespace App\Service;

use App\Entity\Session;
use App\Entity\Formation;
use App\Entity\Salle;
use App\Entity\Inscription;
use App\Repository\SessionRepository;
use App\Repository\InscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

class SessionValidationService
{
    private EntityManagerInterface $em;
    private SessionRepository $sessionRepository;
    private InscriptionRepository $inscriptionRepository;

    public function __construct(EntityManagerInterface $em, SessionRepository $sessionRepository, InscriptionRepository $inscriptionRepository)
    {
        $this->em = $em;
        $this->sessionRepository = $sessionRepository;
        $this->inscriptionRepository = $inscriptionRepository;
    }

    /**
     * Valide qu'une session respecte toutes les contraintes
     */
    public function validateSession(Session $session, Formation $formation, array $userIds = []): array
    {
        $errors = [];

        // 1. Vérifier que la date de début de session >= date de début de formation
        if (!$this->isSessionDateValid($session, $formation)) {
            $errors[] = 'La date de début de session doit être supérieure ou égale à la date de début de formation (' . $formation->getDateDebut()->format('d/m/Y') . ')';
        }

        // 2. Vérifier que la salle est disponible (si session interne)
        if ($session->getType() === 'interne' && $session->getSalle()) {
            if (!$this->isSalleAvailable($session)) {
                $errors[] = 'La salle "' . $session->getSalle()->getNom() . '" n\'est pas disponible pour cette période. Veuillez choisir une autre salle ou modifier les horaires.';
            }
        }

        // 3. Vérifier que les utilisateurs ciblés n'ont pas de conflit d'horaires
        if (!empty($userIds)) {
            $userConflicts = $this->checkUserConflicts($session, $userIds);
            if (!empty($userConflicts)) {
                $errors[] = 'Conflit d\'horaires pour les utilisateurs : ' . implode(', ', $userConflicts);
            }
        }

        return $errors;
    }

    /**
     * Vérifie que la date de début de session est >= date de début de formation
     */
    private function isSessionDateValid(Session $session, Formation $formation): bool
    {
        $sessionDate = $session->getDateDebut();
        $formationDate = $formation->getDateDebut();

        // Comparer seulement les dates (sans l'heure)
        $sessionDateOnly = clone $sessionDate;
        $sessionDateOnly->setTime(0, 0, 0);
        
        $formationDateOnly = clone $formationDate;
        $formationDateOnly->setTime(0, 0, 0);

        return $sessionDateOnly >= $formationDateOnly;
    }

    /**
     * Vérifie que la salle est disponible pour la période demandée
     */
    private function isSalleAvailable(Session $session): bool
    {
        $salle = $session->getSalle();
        $dateDebut = $session->getDateDebut();
        $dateFin = $session->getDateFin();

        // Rechercher les sessions existantes qui utilisent la même salle
        // et qui se chevauchent avec la période demandée
        $conflictingSessions = $this->sessionRepository->createQueryBuilder('s')
            ->where('s.salle = :salle')
            ->andWhere('s.id != :currentSessionId') // Exclure la session actuelle si elle existe déjà
            ->andWhere(
                '(s.dateDebut <= :dateDebut AND s.dateFin > :dateDebut) OR ' . // La session existante commence avant et finit après le début de la nouvelle
                '(s.dateDebut < :dateFin AND s.dateFin >= :dateFin) OR ' . // La session existante commence avant la fin et finit après la fin de la nouvelle
                '(s.dateDebut >= :dateDebut AND s.dateFin <= :dateFin)' // La session existante est complètement incluse dans la nouvelle
            )
            ->setParameter('salle', $salle)
            ->setParameter('currentSessionId', $session->getId() ?? 0)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult();

        return empty($conflictingSessions);
    }

    /**
     * Vérifie les conflits d'horaires pour les utilisateurs ciblés
     */
    private function checkUserConflicts(Session $session, array $userIds): array
    {
        $conflicts = [];
        $dateDebut = $session->getDateDebut();
        $dateFin = $session->getDateFin();

        foreach ($userIds as $userId) {
            // Rechercher toutes les inscriptions de cet utilisateur qui se chevauchent
            $conflictingInscriptions = $this->inscriptionRepository->createQueryBuilder('i')
                ->join('i.session', 's')
                ->join('i.user', 'u')
                ->where('u.id = :userId')
                ->andWhere('s.id != :currentSessionId') // Exclure la session actuelle
                ->andWhere(
                    '(s.dateDebut <= :dateDebut AND s.dateFin > :dateDebut) OR ' . // La session existante commence avant et finit après le début de la nouvelle
                    '(s.dateDebut < :dateFin AND s.dateFin >= :dateFin) OR ' . // La session existante commence avant la fin et finit après la fin de la nouvelle
                    '(s.dateDebut >= :dateDebut AND s.dateFin <= :dateFin)' // La session existante est complètement incluse dans la nouvelle
                )
                ->setParameter('userId', $userId)
                ->setParameter('currentSessionId', $session->getId() ?? 0)
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin)
                ->getQuery()
                ->getResult();

            if (!empty($conflictingInscriptions)) {
                $user = $this->em->getRepository(\App\Entity\User::class)->find($userId);
                if ($user) {
                    $conflictSessions = [];
                    foreach ($conflictingInscriptions as $inscription) {
                        $conflictSessions[] = $inscription->getSession()->getTitre();
                    }
                    $conflicts[] = $user->getNom() . ' ' . $user->getPrenom() . ' (sessions: ' . implode(', ', $conflictSessions) . ')';
                }
            }
        }

        return $conflicts;
    }

    /**
     * Valide une liste de sessions pour une formation
     */
    public function validateSessions(array $sessionsData, Formation $formation): array
    {
        $errors = [];
        
        foreach ($sessionsData as $index => $sessionData) {
            $sessionErrors = [];
            
            // Créer un objet Session temporaire pour la validation
            $tempSession = new Session();
            $tempSession->setDateDebut(new \DateTime($sessionData['dateDebut']));
            $tempSession->setDateFin(new \DateTime($sessionData['dateFin']));
            $tempSession->setType($sessionData['type']);
            
            // Si c'est une session interne avec une salle
            if ($sessionData['type'] === 'interne' && !empty($sessionData['salle'])) {
                $salle = $this->em->getRepository(\App\Entity\Salle::class)->find($sessionData['salle']);
                if ($salle) {
                    $tempSession->setSalle($salle);
                }
            }

            // Valider la session
            $userIds = $sessionData['users'] ?? [];
            $sessionErrors = $this->validateSession($tempSession, $formation, $userIds);
            
            if (!empty($sessionErrors)) {
                $errors["session_$index"] = $sessionErrors;
            }
        }

        return $errors;
    }
} 