<?php

namespace App\Controller\Responsable;

use App\Entity\User;
use App\Entity\Notification;
use App\Entity\Salle;
use App\Entity\Session;
use App\Entity\Inscription;
use App\Entity\Formation;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Service\AuditLogService;

#[Route('/responsable')]
class ResponsableController extends AbstractController
{
    #[Route('/', name: 'responsable_dashboard')]
    public function index(NotificationService $notificationService, EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur est bien responsable
        if (!in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        $notifications = $notificationService->getNotificationsNonLues($user);
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        
        // Récupérer les formations dont l'utilisateur est responsable
        $formations = $em->getRepository(Formation::class)->findBy(['responsable' => $user]);
        
        // Statistiques
        $totalFormations = count($formations);
        $formationsEnCours = 0;
        $formationsTerminees = 0;
        
        foreach ($formations as $formation) {
            $sessions = $formation->getSessions();
            foreach ($sessions as $session) {
                if ($session->getDateFin() > new \DateTime()) {
                    $formationsEnCours++;
                } else {
                    $formationsTerminees++;
                }
            }
        }
        
        return $this->render('Responsable/index.html.twig', [
            'user' => $user,
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'formations' => $formations,
            'totalFormations' => $totalFormations,
            'formationsEnCours' => $formationsEnCours,
            'formationsTerminees' => $formationsTerminees
        ]);
    }

    #[Route('/notifications', name: 'responsable_notifications')]
    public function notifications(NotificationService $notificationService, EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur est bien responsable
        if (!in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        // Récupérer TOUTES les notifications de l'utilisateur (pas seulement les non lues)
        $notifications = $em->getRepository('App\Entity\Notification')
            ->createQueryBuilder('n')
            ->where('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
            
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        
        return $this->render('Responsable/notifications.html.twig', [
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'user' => $user
        ]);
    }

    #[Route('/notification/marquer-lue/{id}', name: 'responsable_marquer_notification_lue', methods: ['POST'])]
    public function marquerNotificationLue(int $id, NotificationService $notificationService, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();
            
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Utilisateur non connecté']);
            }
            
            // Vérifier que l'utilisateur est bien responsable
            if (!in_array('ROLE_RESPONSABLE', $user->getRoles())) {
                return new JsonResponse(['success' => false, 'message' => 'Vous devez être responsable pour effectuer cette action']);
            }
            
            $notification = $em->getRepository(Notification::class)->find($id);
            
            if (!$notification) {
                return new JsonResponse(['success' => false, 'message' => 'Notification non trouvée']);
            }
            
            // Vérifier que l'utilisateur connecté est bien le destinataire de la notification
            $destinataire = $notification->getDestinataire();
            if (!$destinataire || $destinataire->getId() !== $user->getId()) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'êtes pas autorisé à modifier cette notification']);
            }
            
            $notificationService->marquerCommeLue($notification);
            
            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            // Log l'erreur
            error_log('Erreur dans marquerNotificationLue: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Erreur interne: ' . $e->getMessage()]);
        }
    }

    #[Route('/notifications/marquer-toutes-lues', name: 'responsable_marquer_toutes_notifications_lues', methods: ['POST'])]
    public function marquerToutesNotificationsLues(NotificationService $notificationService, EntityManagerInterface $em): JsonResponse
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non connecté']);
        }
        
        $notificationService->marquerToutesCommeLues($user);
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/formations', name: 'responsable_formations')]
    public function formations(EntityManagerInterface $em, NotificationService $notificationService): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur est bien responsable
        if (!in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        // Récupérer les formations dont l'utilisateur est responsable
        $formations = $em->getRepository(Formation::class)->findBy(['responsable' => $user]);
        
        // Récupérer les notifications pour la navbar
        $notifications = $notificationService->getNotificationsNonLues($user);
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        
        // Statistiques
        $totalFormations = count($formations);
        $formationsEnCours = 0;
        $formationsTerminees = 0;
        $totalSessions = 0;
        $sessionsEnCours = 0;
        $sessionsTerminees = 0;
        $totalParticipants = 0;
        
        foreach ($formations as $formation) {
            $sessions = $formation->getSessions();
            $totalSessions += count($sessions);
            
            foreach ($sessions as $session) {
                if ($session->getDateFin() > new \DateTime()) {
                    $sessionsEnCours++;
                } else {
                    $sessionsTerminees++;
                }
                
                // Compter les participants
                $inscriptions = $em->getRepository(Inscription::class)->findBy(['session' => $session]);
                $totalParticipants += count($inscriptions);
            }
            
            // Déterminer le statut de la formation
            $hasActiveSessions = false;
            foreach ($sessions as $session) {
                if ($session->getDateFin() > new \DateTime()) {
                    $hasActiveSessions = true;
                    break;
                }
            }
            
            if ($hasActiveSessions) {
                $formationsEnCours++;
            } else {
                $formationsTerminees++;
            }
        }
        
        return $this->render('Responsable/formations.html.twig', [
            'formations' => $formations,
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'user' => $user,
            'totalFormations' => $totalFormations,
            'formationsEnCours' => $formationsEnCours,
            'formationsTerminees' => $formationsTerminees,
            'totalSessions' => $totalSessions,
            'sessionsEnCours' => $sessionsEnCours,
            'sessionsTerminees' => $sessionsTerminees,
            'totalParticipants' => $totalParticipants
        ]);
    }

    #[Route('/nouvelle-formation', name: 'responsable_nouvelle_formation')]
    public function nouvelleFormation(NotificationService $notificationService): Response
    {
        // Rediriger vers le contrôleur admin avec le template responsable
        return $this->redirectToRoute('ajouter_formation');
    }

    #[Route('/formation/{id}', name: 'responsable_formation_details')]
    public function formationDetails(int $id, EntityManagerInterface $em, NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        if (!in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        $formation = $em->getRepository(Formation::class)->find($id);
        if (!$formation || $formation->getResponsable() !== $user) {
            throw $this->createNotFoundException('Formation non trouvée ou accès refusé.');
        }
        $notifications = $notificationService->getNotificationsNonLues($user);
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        
        // Charger tous les utilisateurs pouvant être participants (hors RH et Responsable)
        $users = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.role NOT IN (:excludedRoles)')
            ->setParameter('excludedRoles', ['ROLE_RH', 'ROLE_RESPONSABLE'])
            ->getQuery()->getResult();
            
        // Charger tous les responsables pour la modification de formation
        $responsables = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', 'ROLE_RESPONSABLE')
            ->getQuery()->getResult();
        
        return $this->render('Responsable/formation_details.html.twig', [
            'formation' => $formation,
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'user' => $user,
            'users' => $users,
            'responsables' => $responsables
        ]);
    }

    #[Route('/get-salles', name: 'responsable_get_salles')]
    public function getSalles(EntityManagerInterface $em): JsonResponse
    {
        $salles = $em->getRepository(Salle::class)->findAll();
        $data = [];
        foreach ($salles as $salle) {
            $data[] = [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'capacite' => $salle->getCapacite()
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/session/check-salle-availability', name: 'responsable_check_salle_availability', methods: ['POST'])]
    public function checkSalleAvailability(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $formData = $request->request->all();
        $salleId = $formData['salle'] ?? null;
        $dateDebut = $formData['dateDebut'] ?? null;
        $dateFin = $formData['dateFin'] ?? null;
        $sessionId = $formData['sessionId'] ?? null;
        
        if (!$salleId || !$dateDebut || !$dateFin) {
            return new JsonResponse(['conflicts' => []], 400);
        }
        
        $salle = $em->getRepository(Salle::class)->find($salleId);
        if (!$salle) {
            return new JsonResponse(['conflicts' => []], 404);
        }
        
        $qb = $em->getRepository('App\Entity\Session')->createQueryBuilder('s')
            ->where('s.salle = :salle')
            ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
            ->setParameter('salle', $salle)
            ->setParameter('dateDebut', new \DateTime($dateDebut))
            ->setParameter('dateFin', new \DateTime($dateFin));
        
        if ($sessionId) {
            /** @var int $sessionIdInt */
            $sessionIdInt = (int)$sessionId;
            $qb->andWhere('s.id != :sessionId')
               ->setParameter('sessionId', $sessionIdInt);
        }
        
        $conflicts = $qb->getQuery()->getResult();
        
        $conflictData = [];
        foreach ($conflicts as $session) {
            $conflictData[] = [
                'id' => $session->getId(),
                'titre' => $session->getTitre(),
                'dateDebut' => $session->getDateDebut()->format('Y-m-d H:i'),
                'dateFin' => $session->getDateFin()->format('Y-m-d H:i')
            ];
        }
        
        return new JsonResponse(['conflicts' => $conflictData]);
    }

    #[Route('/formation/{id}/ajax-update', name: 'responsable_formation_ajax_update', methods: ['POST'])]
    public function ajaxUpdateFormation(int $id, Request $request, EntityManagerInterface $em, AuditLogService $auditLogService, NotificationService $notificationService): JsonResponse
    {
        $formation = $em->getRepository(Formation::class)->find($id);
        if (!$formation) {
            return new JsonResponse(['success' => false, 'message' => 'Formation non trouvée'], 404);
        }
        $user = $this->getUser();
        if (!$user || $formation->getResponsable() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        
        // Avant la mise à jour, stocker les anciennes valeurs
        $oldSujet = $formation->getSujet();
        $oldDateDebut = $formation->getDateDebut() ? $formation->getDateDebut()->format('Y-m-d') : '';
        $oldDuree = $formation->getDuree();
        $oldResponsable = $formation->getResponsable() ? $formation->getResponsable()->getEmail() : '';
        
        $data = json_decode($request->getContent(), true);
        $errors = [];
        
        // Validation simple
        if (empty($data['sujet'])) {
            $errors['sujet'] = 'Le sujet est requis.';
        }
        if (empty($data['dateDebut'])) {
            $errors['dateDebut'] = 'La date de début est requise.';
        }
        if (empty($data['duree']) || !is_numeric($data['duree']) || $data['duree'] < 2) {
            $errors['duree'] = 'La durée doit être un nombre supérieur ou égal à 2.';
        }
        if (empty($data['responsable'])) {
            $errors['responsable'] = 'Le responsable est requis.';
        }
        
        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 400);
        }
        
        // Vérifier que le nouveau responsable existe et est bien un responsable
        $newResponsable = $em->getRepository(User::class)->find($data['responsable']);
        if (!$newResponsable || !in_array('ROLE_RESPONSABLE', $newResponsable->getRoles())) {
            return new JsonResponse(['success' => false, 'errors' => ['responsable' => 'Le responsable sélectionné n\'est pas valide.']], 400);
        }
        
        // Mise à jour
        $formation->setSujet($data['sujet']);
        $formation->setDateDebut(new \DateTime($data['dateDebut']));
        $formation->setDuree((int)$data['duree']);
        $formation->setResponsable($newResponsable);
        
        $em->flush();
        
        // Audit log
        /** @var string $valeurAvant */
        $valeurAvant = sprintf('Sujet: %s, Date: %s, Durée: %s, Responsable: %s', $oldSujet, $oldDateDebut, $oldDuree, $oldResponsable);
        /** @var string $valeurApres */
        $valeurApres = sprintf('Sujet: %s, Date: %s, Durée: %s, Responsable: %s', $formation->getSujet(), $formation->getDateDebut()->format('Y-m-d'), $formation->getDuree(), $formation->getResponsable()->getEmail());
        $auditLogService->enregistrer($user, 'Modification formation', $valeurAvant, $valeurApres);
        $notificationService->notifierModificationFormation($formation, $user);
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/session/ajax-save', name: 'responsable_session_ajax_save', methods: ['POST'])]
    public function ajaxSaveSession(Request $request, EntityManagerInterface $em, AuditLogService $auditLogService, NotificationService $notificationService): JsonResponse
    {
        // Parser FormData au lieu de JSON pour gérer les fichiers
        $formationId = $request->request->get('formationId');
        $sessionId = $request->request->get('sessionId');
        $titre = $request->request->get('titre');
        $dateDebut = $request->request->get('dateDebut');
        $dateFin = $request->request->get('dateFin');
        $type = $request->request->get('type');
        $status = $request->request->get('status', 'créée');
        $salleId = $request->request->get('salleId');
        $emplacement = $request->request->get('emplacement');
        $participants = $request->request->get('participants', []);
        $fichier = $request->files->get('fichier');
        $supprimerFichier = $request->request->get('supprimer_fichier');
        
        $errors = [];
        
        if (empty($formationId)) {
            return new JsonResponse(['success' => false, 'message' => 'Formation manquante'], 400);
        }
        
        $formation = $em->getRepository(Formation::class)->find($formationId);
        if (!$formation) {
            return new JsonResponse(['success' => false, 'message' => 'Formation non trouvée'], 404);
        }
        
        // Vérifier que l'utilisateur est responsable de cette formation
        $user = $this->getUser();
        if (!$user || $formation->getResponsable() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        
        // Si sessionId fourni => modif, sinon création
        $session = null;
        if (!empty($sessionId)) {
            $session = $em->getRepository(Session::class)->find($sessionId);
            if (!$session || $session->getFormation() !== $formation) {
                return new JsonResponse(['success' => false, 'message' => 'Session non trouvée'], 404);
            }
        } else {
            $session = new Session();
            $session->setFormation($formation);
        }
        
        // Récupérer les participants AVANT modification
        $oldParticipants = [];
        if ($session->getId()) {
            $oldParticipants = array_map(function($i) { return $i->getUser()->getEmail(); }, $session->getInscriptions()->toArray());
        }
        
        // Champs obligatoires
        if (empty($titre)) $errors['titre'] = 'Le titre est requis.';
        if (empty($dateDebut)) $errors['dateDebut'] = 'La date de début est requise.';
        if (empty($dateFin)) $errors['dateFin'] = 'La date de fin est requise.';
        if (empty($type)) $errors['type'] = 'Le type est requis.';
        if ($type === 'interne' && empty($salleId)) $errors['salle'] = 'La salle est requise.';
        if ($type === 'externe' && empty($emplacement)) $errors['emplacement'] = 'La localisation est requise.';
        if (empty($participants) || !is_array($participants) || count($participants) === 0) $errors['participants'] = 'Au moins un participant est requis.';
        
        // Date cohérence
        if (!empty($dateDebut) && !empty($formation->getDateDebut()) && $dateDebut < $formation->getDateDebut()->format('Y-m-d\TH:i')) {
            $errors['dateDebut'] = 'La date de début de session doit être supérieure ou égale à la date de début de la formation (' . $formation->getDateDebut()->format('d/m/Y') . ')';
        }
        if (!empty($dateDebut) && !empty($dateFin) && $dateFin <= $dateDebut) {
            $errors['dateFin'] = 'La date de fin doit être supérieure à la date de début.';
        }
        
        // Vérification de disponibilité de salle (globale)
        if ($type === 'interne' && !empty($salleId) && !empty($dateDebut) && !empty($dateFin)) {
            /** @var int $sessionIdInt */
            $sessionIdInt = $sessionId ? (int)$sessionId : 0;
            
            $sessionsEnConflit = $em->getRepository(Session::class)->createQueryBuilder('s')
                ->where('s.salle = :salle')
                ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
                ->andWhere('s.id != :sessionId')
                ->setParameter('salle', $em->getRepository(Salle::class)->find($salleId))
                ->setParameter('dateDebut', new \DateTime($dateDebut))
                ->setParameter('dateFin', new \DateTime($dateFin))
                ->setParameter('sessionId', $sessionIdInt)
                ->getQuery()
                ->getResult();
            if (count($sessionsEnConflit) > 0) {
                $errors['salle'] = 'Salle déjà réservée à cet horaire.';
            }
        }
        
        // Vérification de conflit d'agenda pour les participants (détail)
        if (!empty($participants) && !empty($dateDebut) && !empty($dateFin)) {
            $participantsConflit = [];
            /** @var array<int|string> $participants */
            foreach ($participants as $userId) {
                /** @var int $userIdInt */
                $userIdInt = (int)$userId;
                /** @var int $sessionIdInt */
                $sessionIdInt = $sessionId ? (int)$sessionId : 0;
                
                $conflit = $em->getRepository(Inscription::class)->createQueryBuilder('i')
                    ->join('i.session', 's')
                    ->where('i.user = :user')
                    ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
                    ->andWhere('s.id != :sessionId')
                    ->setParameter('user', $userIdInt)
                    ->setParameter('dateDebut', new \DateTime($dateDebut))
                    ->setParameter('dateFin', new \DateTime($dateFin))
                    ->setParameter('sessionId', $sessionIdInt)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($conflit) {
                    $user = $em->getRepository(User::class)->find($userId);
                    if ($user) {
                        $participantsConflit[] = $user->getNom() . ' ' . $user->getPrenom() . ' (' . $user->getEmail() . ')';
                    }
                }
            }
            if (count($participantsConflit) > 0) {
                $errors['participants'] = 'Conflit d\'agenda pour : ' . implode(', ', $participantsConflit);
            }
        }
        
        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 400);
        }
        
        // Avant la mise à jour, stocker les anciennes valeurs si modification
        $oldData = [];
        if ($session->getId()) {
            $oldData = [
                'titre' => $session->getTitre(),
                'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
                'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
                'type' => $session->getType(),
                'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
                'emplacement' => $session->getEmplacement(),
                'participants' => array_map(function($i) { return $i->getUser()->getEmail(); }, $session->getInscriptions()->toArray()),
            ];
        }
        
        // Mise à jour des champs
        $isCreation = !$session->getId();
        $session->setTitre($titre);
        $session->setDateDebut(new \DateTime($dateDebut));
        $session->setDateFin(new \DateTime($dateFin));
        $session->setType($type);
        $session->setStatus($status);
        
        if ($type === 'interne') {
            $salle = $em->getRepository(Salle::class)->find($salleId);
            $session->setSalle($salle);
            $session->setEmplacement('');
        } else {
            $session->setSalle(null);
            $session->setEmplacement($emplacement);
        }
        
        // Gestion du fichier
        if ($fichier) {
            $fileName = uniqid() . '.' . $fichier->guessExtension();
            $fichier->move($this->getParameter('uploads_directory'), $fileName);
            $session->setFichier($fileName);
        } elseif ($supprimerFichier && $session->getFichier()) {
            // Supprimer le fichier existant
            $filePath = $this->getParameter('uploads_directory') . '/' . $session->getFichier();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $session->setFichier(null);
        }
        
        $em->persist($session);
        $em->flush();
        
        // Gérer les inscriptions (participants)
        $inscriptionRepo = $em->getRepository(Inscription::class);
        // Supprimer les anciennes inscriptions si modif
        foreach ($session->getInscriptions() as $insc) {
            $em->remove($insc);
        }
        $em->flush();
        
        // Ajouter les nouvelles
        $userRepo = $em->getRepository(User::class);
        $newParticipants = [];
        foreach ((array)$participants as $userId) {
            $participant = $userRepo->find($userId);
            if ($participant) {
                $insc = new Inscription();
                $insc->setUser($participant);
                $insc->setSession($session);
                $insc->setStatutParticipation('en attente');
                $em->persist($insc);
                $newParticipants[] = $participant->getEmail();
            }
        }
        $em->flush();
        
        // AuditLog ajout/suppression de participants
        $added = array_diff($newParticipants, $oldParticipants);
        $removed = array_diff($oldParticipants, $newParticipants);
        if (!empty($added)) {
            /** @var string $valeurApres */
            $valeurApres = json_encode(['participants_ajoutes' => array_values($added)]);
            $auditLogService->enregistrer(
                $this->getUser(),
                'Ajout participant(s) à la session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ')',
                null,
                $valeurApres
            );
        }
        if (!empty($removed)) {
            /** @var string $valeurAvant */
            $valeurAvant = json_encode(['participants_supprimes' => array_values($removed)]);
            $auditLogService->enregistrer(
                $this->getUser(),
                'Suppression participant(s) de la session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ')',
                $valeurAvant,
                null
            );
        }
        
        // Après la modification, stocker les nouvelles valeurs
        $newData = [
            'titre' => $session->getTitre(),
            'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
            'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
            'type' => $session->getType(),
            'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
            'emplacement' => $session->getEmplacement(),
            'participants' => $newParticipants,
        ];
        
        // Audit log complet si modification
        if (!empty($oldData)) {
            /** @var string $valeurAvant */
            $valeurAvant = json_encode($oldData);
            /** @var string $valeurApres */
            $valeurApres = json_encode($newData);
            $auditLogService->enregistrer(
                $this->getUser(),
                'Modification session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ')',
                $valeurAvant,
                $valeurApres
            );
        }
        
        $notificationService->notifierModificationSession($session, $this->getUser(), $isCreation ? 'ajout' : 'modification');
        
        return new JsonResponse(['success' => true, 'sessionId' => $session->getId()]);
    }

    #[Route('/session/ajax-delete', name: 'responsable_session_ajax_delete', methods: ['POST'])]
    public function ajaxDeleteSession(Request $request, EntityManagerInterface $em, AuditLogService $auditLogService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['sessionId'] ?? null;
        
        if (!$sessionId) {
            return new JsonResponse(['success' => false, 'message' => 'Session manquante'], 400);
        }
        
        $session = $em->getRepository(Session::class)->find($sessionId);
        if (!$session) {
            return new JsonResponse(['success' => false, 'message' => 'Session non trouvée'], 404);
        }
        
        // Vérifier que l'utilisateur est responsable de la formation
        $user = $this->getUser();
        if (!$user || $session->getFormation()->getResponsable() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        
        // Audit log avant suppression
        $formation = $session->getFormation();
        $oldData = [
            'titre' => $session->getTitre(),
            'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
            'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
            'type' => $session->getType(),
            'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
            'emplacement' => $session->getEmplacement(),
            'participants' => array_map(function($i) { return $i->getUser()->getEmail(); }, $session->getInscriptions()->toArray()),
        ];
        
        /** @var string $valeurAvant */
        $valeurAvant = json_encode($oldData);
        $auditLogService->enregistrer(
            $user,
            'Suppression session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ', Salle: ' . ($session->getSalle() ? $session->getSalle()->getNom() : 'Aucune') . ')',
            $valeurAvant,
            null
        );
        
        // Supprimer le fichier associé s'il existe
        if ($session->getFichier()) {
            $filePath = $this->getParameter('uploads_directory') . '/' . $session->getFichier();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Supprimer les inscriptions liées
        foreach ($session->getInscriptions() as $insc) {
            $em->remove($insc);
        }
        
        $em->remove($session);
        $em->flush();
        
        return new JsonResponse(['success' => true]);
    }
} 