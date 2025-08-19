<?php

namespace App\Controller\Admin;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Formation;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Session;
use App\Entity\Inscription;
use App\Entity\User;
use App\Entity\Notification;
use App\Entity\Salle;
use App\Service\AuditLogService;
use App\Service\SessionValidationService;
use App\Service\EmailService;
use App\Service\NotificationService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Repository\SessionRepository;

final class FormationController extends AbstractController
{
     #[Route('/nouvelleFormation', name: 'ajouter_formation')]
   public function ajouter(FormationRepository $formationRepository, NotificationService $notificationService, EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        $notifications = [];
        $countNonLues = 0;
        
        if ($user) {
            $notifications = $notificationService->getNotificationsNonLues($user);
            $countNonLues = $notificationService->countNotificationsNonLues($user);
        }
        
        return $this->render('admin/nouvelleFormation.html.twig', [
            'notifications' => $notifications,
            'countNonLues' => $countNonLues
        ]);
    }
    #[Route('/get-salles', name: 'get_salles')]
    public function getSalles(FormationRepository $formationRepository): JsonResponse
    {
        $salles = $formationRepository->getAllSalles();
        $data = [];

        foreach ($salles as $salle) {
            $data[] = [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'capacite' => $salle->getCapacite(),
            ];
        }

        return $this->json($data);
    }


    #[Route('/get-responsables', name: 'get_responsables')]
    public function getResponsables(FormationRepository $formationRepository): JsonResponse
    {
        $responsables = $formationRepository->getResponsablesByRole();

        $data = [];

        foreach ($responsables as $user) {
            $data[] = [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
            ];
        }

        return $this->json($data);
    }
    #[Route('/session/check-salle-availability', name: 'check_salle_availability', methods: ['POST'])]
    public function checkSalleAvailability(Request $request, FormationRepository $formationRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $salleId = $data['salleId'] ?? null;
        $dateDebut = $data['dateDebut'] ?? null;
        $dateFin = $data['dateFin'] ?? null;
        $sessionId = $data['sessionId'] ?? null;

        if (!$salleId || !$dateDebut || !$dateFin) {
            return new JsonResponse(['available' => false, 'message' => 'Données manquantes']);
        }

        $result = $formationRepository->checkSalleAvailability(
            $salleId,
            new \DateTime($dateDebut),
            new \DateTime($dateFin),
            $sessionId
        );

        return new JsonResponse($result);
    }

    #[Route('/check-user-conflicts', name: 'check_user_conflicts', methods: ['POST'])]
    public function checkUserConflicts(Request $request, FormationRepository $formationRepository): JsonResponse
    {
        $userIds = $request->request->all('userIds') ?: [];
        $dateDebut = $request->request->get('dateDebut');
        $dateFin = $request->request->get('dateFin');
        $sessionId = $request->request->get('sessionId', 0);

        if (empty($userIds) || !$dateDebut || !$dateFin) {
            return new JsonResponse(['conflicts' => [], 'message' => 'Données manquantes']);
        }

        $conflicts = $formationRepository->checkUserConflicts(
            $userIds,
            new \DateTime($dateDebut),
            new \DateTime($dateFin),
            $sessionId
        );
        
        return new JsonResponse([
            'conflicts' => $conflicts,
            'message' => empty($conflicts) ? 'Aucun conflit détecté' : 'Conflits détectés'
        ]);
    }



    #[Route('/admin/notifications', name: 'admin_notifications')]
    public function notifications(NotificationService $notificationService, EntityManagerInterface $em): Response
    {
    // Utiliser l'utilisateur réellement connecté
    $user = $this->getUser();
    
    if (!$user) {
        return new Response('Aucun utilisateur connecté');
    }
    
    $notifications = $notificationService->getNotificationsNonLues($user);
    $countNonLues = $notificationService->countNotificationsNonLues($user);
    
    return $this->render('admin/notifications.html.twig', [
        'notifications' => $notifications,
        'countNonLues' => $countNonLues
    ]);
}

    #[Route('/notification/marquer-lue/{id}', name: 'marquer_notification_lue')]
    public function marquerNotificationLue(int $id, NotificationService $notificationService, EntityManagerInterface $em): JsonResponse
    {
    $notification = $em->getRepository(Notification::class)->find($id);
    
    if (!$notification) {
        return new JsonResponse(['success' => false, 'message' => 'Notification non trouvée']);
    }
    
    $notificationService->marquerCommeLue($notification);
    
    return new JsonResponse(['success' => true]);
}

    #[Route('/notifications/marquer-toutes-lues', name: 'marquer_toutes_notifications_lues')]
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

    #[Route('/get-users-cibles', name: 'get_users_cibles')]
    public function getUsersCibles(FormationRepository $formationRepository): JsonResponse
    {
        $users = $formationRepository->getUsersCibles();

        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(), 
            ];
        }

        return $this->json($data);
    }

#[Route('/formation/save', name: 'save_formation', methods: ['POST'])]
    public function saveFormation(Request $request, EntityManagerInterface $em, FormationRepository $formationRepository, AuditLogService $auditLogService, ValidatorInterface $validator, SessionValidationService $sessionValidationService, EmailService $emailService, NotificationService $notificationService)
    {
        try {
        $sujet = $request->request->get('sujet', '');
        $dateDebut = $request->request->get('dateDebut');
        $duree = $request->request->get('duree');
        $responsableId = $request->request->get('responsable');
        $sessionsJson = $request->request->get('sessions');
        $sessions = json_decode($sessionsJson, true);

        $formation = new Formation();
        $formation->setSujet($sujet);
        if ($dateDebut) {
            $formation->setDateDebut(new \DateTime($dateDebut));
        }
        $formation->setDuree($duree);
        $responsable = $formationRepository->getEntityManager()->getRepository(User::class)->find($responsableId);
        $formation->setResponsable($responsable);

        // Validation
        $errorsList = $validator->validate($formation);
        if (count($errorsList) > 0) {
            $errors = [];
            foreach ($errorsList as $error) {
                $property = $error->getPropertyPath();
                $errors[$property] = $error->getMessage();
            }
            return new JsonResponse([
                'success' => false,
                'errors' => $errors,
            ], 400);
        }

        $em->persist($formation);

        // Validation des sessions avant sauvegarde
        $sessionValidationErrors = $sessionValidationService->validateSessions($sessions, $formation);
        if (!empty($sessionValidationErrors)) {
            // Formater les erreurs pour l'affichage côté client
            $formattedErrors = [];
            foreach ($sessionValidationErrors as $sessionKey => $errors) {
                if (is_array($errors)) {
                    // Simplifier : envoyer toutes les erreurs comme une seule chaîne
                    $formattedErrors[$sessionKey] = implode(', ', $errors);
                } else {
                    $formattedErrors[$sessionKey] = $errors;
                }
            }
            return new JsonResponse([
                'success' => false,
                'errors' => $formattedErrors,
                'message' => 'Erreurs de validation des sessions'
            ], 400);
        }

        $em->flush(); // flush AVANT l'auditlog pour avoir l'id
        // Audit : création formation
        $user = $this->getUser();
        if (!$user || (!in_array('ROLE_RH', $user->getRoles()) && !in_array('ROLE_RESPONSABLE', $user->getRoles()))) {
            throw new \Exception('Créateur de formation non authentifié. Seuls les RH et Responsables peuvent créer des formations.');
        }
        $em->persist($formation);
        $em->flush(); // flush AVANT l'auditlog pour avoir l'id
        // Audit : création formation
        $auditLogService->enregistrer(
            $user,
            'Création formation',
            null,
            json_encode(['formation' => $formation->getId(), 'sujet' => $formation->getSujet()])
        );

        foreach ($sessions as $i => $sessionData) {
            if (!isset($sessionData['titre']) || empty($sessionData['titre'])) {
                throw new \Exception('Titre manquant ou vide dans sessionData : ' . json_encode($sessionData));
            }
            if (!isset($sessionData['type']) || empty($sessionData['type'])) {
                throw new \Exception('Type manquant ou vide dans sessionData : ' . json_encode($sessionData));
            }
            if (!isset($sessionData['dateDebut']) || empty($sessionData['dateDebut'])) {
                throw new \Exception('Date de début manquante ou vide dans sessionData : ' . json_encode($sessionData));
            }
            if (!isset($sessionData['dateFin']) || empty($sessionData['dateFin'])) {
                throw new \Exception('Date de fin manquante ou vide dans sessionData : ' . json_encode($sessionData));
            }
            $session = new Session();

            // Si $sessionData['car'][0]['titre'] existe :
            $titre = $sessionData['titre'] ?? ($sessionData['car'][0]['titre'] ?? null);
            if (empty($titre)) {
                throw new \Exception("Le titre de session est vide ou manquant.");
            }
            $session->setTitre($titre);

            // Récupération du type
            $type = $sessionData['type'] ?? ($sessionData['type'] ?? null) ?? ($sessionData['car'][0]['type'] ?? null);
            if (empty($type)) {
                throw new \Exception("Le type de session est vide ou manquant.");
            }
            $session->setType($type);


            $session->setDateDebut(new \DateTime($sessionData['dateDebut']));
            $session->setDateFin(new \DateTime($sessionData['dateFin']));
            $session->setType(isset($sessionData['type']) ? $sessionData['type'] : '');
            $session->setEmplacement(isset($sessionData['emplacement']) ? $sessionData['emplacement'] : '');
            $session->setFormation($formation);
            $session->setStatus('créée');

            // Fichier pour la session
            if (!empty($sessionData['fichier'])) {
                $fichier = $request->files->get($sessionData['fichier']);
                if ($fichier) {
                    $nomFichier = uniqid().'.'.$fichier->guessExtension();
                    $fichier->move($this->getParameter('dossier_upload'), $nomFichier);
                    $session->setFichier('/uploads/'.$nomFichier);
                }
            }

            // Salle par ID
            if (!empty($sessionData['salle'])) {
                $salle = $formationRepository->getEntityManager()->getRepository(Salle::class)->find($sessionData['salle']);
                $session->setSalle($salle);
            }
            // Emplacement
            if (!empty($sessionData['emplacement'])) {
                $session->setEmplacement($sessionData['emplacement']);
            }
            $em->persist($session); // D'abord persister la session pour avoir un ID
            $em->flush(); // Pour obtenir un ID
            // AuditLog réservation salle pour session (création)
            if ($session->getSalle()) {
                $auditLogService->enregistrer(
                    $this->getUser(),
                    'Réservation salle pour session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ', Salle: ' . $session->getSalle()->getNom() . ')',
                    null,
                    json_encode(['salle' => $session->getSalle()->getNom()])
                );
            }
            
            // Users ciblés - Créer des inscriptions
            if (isset($sessionData['users']) && is_array($sessionData['users']) && count($sessionData['users']) > 0) {
                $users = $formationRepository->getEntityManager()->getRepository(User::class)->findBy(['id' => $sessionData['users']]);
                foreach ($users as $user) {
                    $inscription = new Inscription();
                    $inscription->setUser($user);
                    $inscription->setSession($session);
                    $inscription->setStatutParticipation('en attente');
                    $em->persist($inscription);
                }
            }

            $em->flush();
        }

        $em->flush();

        // Envoyer les emails ET notifications
        try {
            // Email au responsable
            $emailService->sendFormationNotificationToResponsable($formation);
            
            // Pas de notification interne au créateur - il sait qu'il a créé la formation
            $createur = $this->getUser();
            
            // Notification interne au responsable (seulement si différent du créateur)
            $responsable = $formation->getResponsable();
            if ($responsable && $createur && $responsable instanceof User && $createur instanceof User && $responsable->getId() !== $createur->getId()) {
                $notificationService->notifierResponsableFormation($formation);
            } else if ($responsable && !$createur) {
                // Si pas de créateur identifié, notifier le responsable
                $notificationService->notifierResponsableFormation($formation);
            } else if ($responsable && $createur && $responsable instanceof User && $createur instanceof User && $responsable->getId() === $createur->getId()) {
                // Si le responsable est le même que le créateur, pas de notification supplémentaire
                // (le créateur a déjà reçu sa notification)
            }
            
            // Récupérer toutes les inscriptions créées
            $allInscriptions = [];
            foreach ($sessions as $sessionData) {
                if (isset($sessionData['users']) && is_array($sessionData['users']) && count($sessionData['users']) > 0) {
                    // Trouver la session correspondante
                    $session = $em->getRepository(Session::class)->findOneBy([
                        'formation' => $formation,
                        'titre' => $sessionData['titre']
                    ], ['id' => 'DESC']);
                    
                    if ($session) {
                        // Récupérer les inscriptions pour cette session
                        $inscriptions = $em->getRepository(Inscription::class)->findBy(['session' => $session]);
                        $allInscriptions = array_merge($allInscriptions, $inscriptions);
                        
                        // Notifications internes aux participants
                        foreach ($inscriptions as $inscription) {
                            // Exclure le créateur de la formation
                            $participant = $inscription->getUser();
                            if ($createur && $participant && $participant === $createur) {
                                continue;
                            }
                            if ($participant) {
                                $notificationService->notifierParticipantInscription(
                                    $participant,
                                    $formation,
                                    $session
                                );
                            }
                        }
                    }
                }
            }
            
            // Email aux participants (exclure le créateur)
            if (!empty($allInscriptions)) {
                $createur = $this->getUser();
                $filteredInscriptions = [];
                foreach ($allInscriptions as $inscription) {
                    $participant = $inscription->getUser();
                    if ($createur && $participant && $participant === $createur) {
                        continue;
                    }
                    $filteredInscriptions[] = $inscription;
                }
                if (!empty($filteredInscriptions)) {
                    $emailService->sendFormationNotificationToParticipants($formation, $filteredInscriptions);
                }
            }
        } catch (\Throwable $e) {
            // Log l'erreur mais continuer
        }

        // Déterminer l'URL de redirection selon le rôle
        $user = $this->getUser();
        if ($user && in_array('ROLE_RH', $user->getRoles())) {
            $redirectUrl = $this->generateUrl('liste_formations');
        } else if ($user && in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            $redirectUrl = $this->generateUrl('responsable_formations');
        } else {
            $redirectUrl = '/';
        }
        return new JsonResponse(['success' => true, 'redirect' => $redirectUrl]);
    } catch (\Throwable $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
    }

    #[Route('/formations', name: 'liste_formations')]
    public function index(FormationRepository $formationRepository, NotificationService $notificationService, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_RH', $user->getRoles())) {
            return $this->redirectToRoute('app_login');
        }
        $formations = $formationRepository->findAll();
        $notifications = $notificationService->getNotificationsNonLues($user);
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        return $this->render('admin/formations.html.twig', [
            'formations' => $formations,
            'notifications' => $notifications,
            'countNonLues' => $countNonLues
        ]);
    }

    #[Route('/formation/{id}', name: 'admin_formation_details')]
    public function adminFormationDetails(int $id, FormationRepository $formationRepository, NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée.');
        }
        
        $notifications = $notificationService->getNotificationsNonLues($user);
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        
        // Charger tous les utilisateurs pouvant être participants (hors RH et Responsable)
        $users = $formationRepository->getUsersForFormation($id);
        $responsables = $formationRepository->getResponsablesForFormation($id);
        
        return $this->render('admin/formation_details.html.twig', [
            'formation' => $formation,
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'user' => $user,
            'users' => $users,
            'responsables' => $responsables
        ]);
    }

    #[Route('/formation/{id}/ajax-update', name: 'admin_formation_ajax_update', methods: ['POST'])]
    public function ajaxUpdateFormation(int $id, Request $request, EntityManagerInterface $em, AuditLogService $auditLogService, NotificationService $notificationService): JsonResponse
    {
        $formation = $em->getRepository('App\\Entity\\Formation')->find($id);
        if (!$formation) {
            return new JsonResponse(['success' => false, 'message' => 'Formation non trouvée'], 404);
        }
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
        } else {
            $responsable = $em->getRepository('App\\Entity\\User')->find($data['responsable']);
            if (!$responsable || $responsable->getRole() !== 'ROLE_RESPONSABLE') {
                $errors['responsable'] = 'Responsable invalide.';
            }
        }
        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 400);
        }
        // Avant la mise à jour, stocker les anciennes valeurs
        $oldData = [
            'sujet' => $formation->getSujet(),
            'dateDebut' => $formation->getDateDebut() ? $formation->getDateDebut()->format('Y-m-d') : '',
            'duree' => $formation->getDuree(),
            'responsable' => $formation->getResponsable() ? $formation->getResponsable()->getNom().' '.$formation->getResponsable()->getPrenom() : '',
        ];
        // Mise à jour
        $formation->setSujet($data['sujet']);
        $formation->setDateDebut(new \DateTime($data['dateDebut']));
        $formation->setDuree((int)$data['duree']);
        $formation->setResponsable($responsable);
        $em->flush();
        // Après la mise à jour, stocker les nouvelles valeurs
        $newData = [
            'sujet' => $formation->getSujet(),
            'dateDebut' => $formation->getDateDebut() ? $formation->getDateDebut()->format('Y-m-d') : '',
            'duree' => $formation->getDuree(),
            'responsable' => $formation->getResponsable() ? $formation->getResponsable()->getNom().' '.$formation->getResponsable()->getPrenom() : '',
        ];
        // Audit log
        $user = $this->getUser();
        $auditLogService->enregistrer($user, 'Modification formation', json_encode($oldData), json_encode($newData));
        $notificationService->notifierModificationFormation($formation, $user);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/session/ajax-save', name: 'admin_session_ajax_save', methods: ['POST'])]
    public function ajaxSaveSession(Request $request, EntityManagerInterface $em, AuditLogService $auditLogService, NotificationService $notificationService): JsonResponse
    {
    // Récupérer les données FormData
    $formationId = $request->request->get('formationId');
    $sessionId = $request->request->get('sessionId');
    $titre = $request->request->get('titre');
    $dateDebut = $request->request->get('dateDebut');
    $dateFin = $request->request->get('dateFin');
    $type = $request->request->get('type');
    $status = $request->request->get('status');
    $salleId = $request->request->get('salleId');
    $emplacement = $request->request->get('emplacement');
    $participants = $request->request->all('participants') ?? [];
    $fichier = $request->files->get('fichier');
    $supprimerFichier = $request->request->get('supprimer_fichier');
    
    $errors = [];
    if (empty($formationId)) {
        return new JsonResponse(['success' => false, 'message' => 'Formation manquante'], 400);
    }
    $formation = $em->getRepository('App\\Entity\\Formation')->find($formationId);
    if (!$formation) {
        return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
    }
    // Si sessionId fourni => modif, sinon création
    $session = null;
    $oldData = [];
    if (!empty($sessionId)) {
        $session = $em->getRepository('App\\Entity\\Session')->find($sessionId);
        if (!$session || $session->getFormation() !== $formation) {
            return new JsonResponse(['success' => false, 'message' => 'Session non trouvée'], 404);
        }
        // Stocker les anciennes valeurs
        $oldData = [
            'titre' => $session->getTitre(),
            'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
            'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
            'type' => $session->getType(),
            'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
            'emplacement' => $session->getEmplacement(),
            'participants' => array_map(function($i) { return $i->getUser()->getEmail(); }, $session->getInscriptions()->toArray()),
        ];
    } else {
        $session = new \App\Entity\Session();
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
    if (empty($status)) $errors['status'] = 'Le statut est requis.';
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
    // Vérification disponibilité salle (si interne)
    if ($type === 'interne' && !empty($salleId) && !empty($dateDebut) && !empty($dateFin)) {
        $salle = $em->getRepository('App\\Entity\\Salle')->find($salleId);
        if ($salle) {
            $sessionsEnConflit = $em->getRepository('App\\Entity\\Session')->createQueryBuilder('s')
                ->where('s.salle = :salle')
                ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
                ->andWhere('s.id != :sessionId')
                ->setParameter('salle', $salle)
                ->setParameter('dateDebut', new \DateTime($dateDebut))
                ->setParameter('dateFin', new \DateTime($dateFin))
                ->setParameter('sessionId', $session->getId() ?: 0)
                ->getQuery()
                ->getResult();
            if (count($sessionsEnConflit) > 0) {
                $titresConflit = array_map(function($s) { return $s->getTitre(); }, $sessionsEnConflit);
                $errors['salle'] = 'Salle déjà réservée à cet horaire (Sessions : ' . implode(', ', $titresConflit) . ')';
            }
        }
    }
    // Vérification conflits participants
    if (!empty($participants) && !empty($dateDebut) && !empty($dateFin)) {
        $participantsConflit = [];
        foreach ($participants as $userId) {
            $conflit = $em->getRepository('App\\Entity\\Inscription')->createQueryBuilder('i')
                ->join('i.session', 's')
                ->where('i.user = :user')
                ->andWhere('s.dateFin > :dateDebut AND s.dateDebut < :dateFin')
                ->andWhere('s.id != :sessionId')
                ->setParameter('user', $userId)
                ->setParameter('dateDebut', new \DateTime($dateDebut))
                ->setParameter('dateFin', new \DateTime($dateFin))
                ->setParameter('sessionId', $session->getId() ?: 0)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if ($conflit) {
                $user = $em->getRepository('App\\Entity\User')->find($userId);
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
    // Mise à jour des champs
    $isCreation = !$session->getId();
    $session->setTitre($titre);
    $session->setDateDebut(new \DateTime($dateDebut));
    $session->setDateFin(new \DateTime($dateFin));
    $session->setType($type);
    $session->setStatus($status);
    
    // Gestion du fichier
    if ($supprimerFichier) {
        // Supprimer le fichier existant
        if ($session->getFichier()) {
            $cheminFichier = $this->getParameter('kernel.project_dir') . '/public' . $session->getFichier();
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }
        }
        $session->setFichier(null);
    } elseif ($fichier) {
        // Supprimer l'ancien fichier s'il existe
        if ($session->getFichier()) {
            $cheminFichier = $this->getParameter('kernel.project_dir') . '/public' . $session->getFichier();
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }
        }
        // Upload du nouveau fichier
        $nomFichier = uniqid() . '.' . $fichier->guessExtension();
        $fichier->move($this->getParameter('dossier_upload'), $nomFichier);
        $session->setFichier('/uploads/' . $nomFichier);
    }
    
    if ($type === 'interne') {
        $salle = $em->getRepository('App\\Entity\\Salle')->find($salleId);
        $session->setSalle($salle);
        $session->setEmplacement('');
    } else {
        $session->setSalle(null);
        $session->setEmplacement($emplacement);
    }
    $em->persist($session);
    $em->flush();
    // Supprimer les anciennes inscriptions si modif
    foreach ($session->getInscriptions() as $insc) {
        $em->remove($insc);
    }
    $em->flush();
    // Ajouter les nouvelles
    $userRepo = $em->getRepository('App\\Entity\\User');
    $newParticipants = [];
    foreach ($participants as $userId) {
        $participant = $userRepo->find($userId);
        if ($participant) {
            $insc = new \App\Entity\Inscription();
            $insc->setUser($participant);
            $insc->setSession($session);
            $insc->setStatutParticipation('en attente');
            $em->persist($insc);
            $newParticipants[] = $participant->getEmail();
        }
    }
    $em->flush();
    // Après flush des inscriptions, notifier
    $notificationService->notifierModificationSession($session, $this->getUser(), $isCreation ? 'ajout' : 'modification');
    
    // Audit log pour la session
    if ($isCreation) {
        // Audit log pour création de session
        $newData = [
            'titre' => $session->getTitre(),
            'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
            'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
            'type' => $session->getType(),
            'status' => $session->getStatus(),
            'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
            'emplacement' => $session->getEmplacement(),
            'participants' => $newParticipants,
            'fichier' => $session->getFichier()
        ];
        $auditLogService->enregistrer(
            $this->getUser(),
            'Création session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ')',
            null,
            json_encode($newData)
        );
    } else {
        // Audit log pour modification de session
        $newData = [
            'titre' => $session->getTitre(),
            'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
            'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
            'type' => $session->getType(),
            'status' => $session->getStatus(),
            'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
            'emplacement' => $session->getEmplacement(),
            'participants' => $newParticipants,
            'fichier' => $session->getFichier()
        ];
        $auditLogService->enregistrer(
            $this->getUser(),
            'Modification session (Formation: ' . $formation->getSujet() . ', Session: ' . $session->getTitre() . ')',
            json_encode($oldData),
            json_encode($newData)
        );
    }
    
    return new JsonResponse(['success' => true, 'sessionId' => $session->getId()]);
}

    #[Route('/session/ajax-delete', name: 'admin_session_ajax_delete', methods: ['POST'])]
    public function ajaxDeleteSession(Request $request, EntityManagerInterface $em, AuditLogService $auditLogService): JsonResponse
    {
    $data = json_decode($request->getContent(), true);
    $sessionId = $data['sessionId'] ?? null;
    if (!$sessionId) {
        return new JsonResponse(['success' => false, 'message' => 'Session manquante'], 400);
    }
    $session = $em->getRepository('App\\Entity\\Session')->find($sessionId);
    if (!$session) {
        return new JsonResponse(['success' => false, 'message' => 'Session non trouvée'], 404);
    }
    
    // Stocker les données avant suppression pour audit log
    $oldData = [
        'titre' => $session->getTitre(),
        'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
        'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
        'type' => $session->getType(),
        'status' => $session->getStatus(),
        'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
        'emplacement' => $session->getEmplacement(),
        'participants' => array_map(function($i) { return $i->getUser()->getEmail(); }, $session->getInscriptions()->toArray()),
        'fichier' => $session->getFichier(),
        'formation' => $session->getFormation() ? $session->getFormation()->getSujet() : null
    ];
    
    // Audit log avant suppression
    $auditLogService->enregistrer(
        $this->getUser(),
        'Suppression session (Formation: ' . ($session->getFormation() ? $session->getFormation()->getSujet() : '') . ', Session: ' . $session->getTitre() . ')',
        json_encode($oldData),
        null
    );
    
    // Supprimer les inscriptions liées
    foreach ($session->getInscriptions() as $insc) {
        $em->remove($insc);
    }
    $em->remove($session);
    $em->flush();
    return new JsonResponse(['success' => true]);
}

    #[Route('/session/get-session-status', name: 'get_session_status', methods: ['POST'])]
    public function getSessionStatus(Request $request, SessionRepository $sessionRepository): JsonResponse
    {
        $sessionId = $request->request->get('sessionId');
        if (!$sessionId) {
            return new JsonResponse(['success' => false, 'message' => 'ID de session manquant']);
        }

        $session = $sessionRepository->find($sessionId);
        if (!$session) {
            return new JsonResponse(['success' => false, 'message' => 'Session non trouvée']);
        }

        return new JsonResponse([
            'success' => true,
            'status' => $session->getStatus(),
            'dateDebut' => $session->getDateDebut() ? $session->getDateDebut()->format('Y-m-d H:i') : null,
            'dateFin' => $session->getDateFin() ? $session->getDateFin()->format('Y-m-d H:i') : null,
            'type' => $session->getType(),
            'salle' => $session->getSalle() ? $session->getSalle()->getNom() : null,
            'emplacement' => $session->getEmplacement(),
            'fichier' => $session->getFichier() ? $session->getFichier() : null,
            'participants' => array_map(function($i) { return $i->getUser()->getEmail(); }, $session->getInscriptions()->toArray())
        ]);
    }

    #[Route('/admin/sessions-status', name: 'admin_sessions_status', methods: ['GET'])]
    public function getSessionsStatus(SessionRepository $sessionRepository): JsonResponse
    {
        $now = new \DateTime();
        
        // Récupérer toutes les sessions avec statut "créée"
        $sessionsACheck = $sessionRepository->findBy(['status' => 'créée']);
        
        // Récupérer toutes les sessions avec statut "en cours"
        $sessionsEnCours = $sessionRepository->findBy(['status' => 'en cours']);
        
        $resultat = [
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'sessions_verifiees' => count($sessionsACheck) + count($sessionsEnCours),
            'sessions_a_demarrer' => [],
            'sessions_a_terminer' => [],
            'details' => []
        ];
        
        // Analyser les sessions qui doivent commencer
        foreach ($sessionsACheck as $session) {
            $dateDebut = $session->getDateDebut();
            $doitCommencer = $dateDebut <= $now;
            
            $sessionInfo = [
                'id' => $session->getId(),
                'titre' => $session->getTitre(),
                'status_actuel' => $session->getStatus(),
                'date_debut' => $dateDebut->format('Y-m-d H:i:s'),
                'doit_changer' => $doitCommencer,
                'nouveau_status' => $doitCommencer ? 'en cours' : null,
                'raison' => $doitCommencer ? 'Date de début atteinte' : 'Date de début pas encore atteinte'
            ];
            
            $resultat['sessions_a_demarrer'][] = $sessionInfo;
            
            if ($doitCommencer) {
                $resultat['details'][] = "Session '{$session->getTitre()}' doit passer de 'créée' à 'en cours'";
            }
        }
        
        // Analyser les sessions qui doivent se terminer
        foreach ($sessionsEnCours as $session) {
            $dateFin = $session->getDateFin();
            $doitTerminer = $dateFin <= $now;
            
            $sessionInfo = [
                'id' => $session->getId(),
                'titre' => $session->getTitre(),
                'status_actuel' => $session->getStatus(),
                'date_fin' => $dateFin->format('Y-m-d H:i:s'),
                'doit_changer' => $doitTerminer,
                'nouveau_status' => $doitTerminer ? 'terminé' : null,
                'raison' => $doitTerminer ? 'Date de fin atteinte' : 'Date de fin pas encore atteinte'
            ];
            
            $resultat['sessions_a_terminer'][] = $sessionInfo;
            
            if ($doitTerminer) {
                $resultat['details'][] = "Session '{$session->getTitre()}' doit passer de 'en cours' à 'terminé'";
            }
        }
        
        return $this->json($resultat);
    }

    #[Route('/admin/execute-scheduler', name: 'admin_execute_scheduler', methods: ['POST'])]
    public function executeScheduler(SessionRepository $sessionRepository, EntityManagerInterface $em, NotificationService $notificationService): JsonResponse
    {
        $now = new \DateTime();
        $sessionsModifiees = 0;
        $notificationsEnvoyees = 0;
        $details = [];
        
        // Récupérer toutes les sessions avec statut "créée"
        $sessionsACheck = $sessionRepository->findBy(['status' => 'créée']);
        
        // Récupérer toutes les sessions avec statut "en cours"
        $sessionsEnCours = $sessionRepository->findBy(['status' => 'en cours']);
        
        // Traiter les sessions qui doivent commencer
        foreach ($sessionsACheck as $session) {
            $dateDebut = $session->getDateDebut();
            if ($dateDebut <= $now) {
                $ancienStatus = $session->getStatus();
                $session->setStatus('en cours');
                $em->persist($session);
                $sessionsModifiees++;
                $details[] = "Session '{$session->getTitre()}' (ID: {$session->getId()}) : '{$ancienStatus}' → 'en cours'";
            }
        }
        
        // Traiter les sessions qui doivent se terminer
        foreach ($sessionsEnCours as $session) {
            $dateFin = $session->getDateFin();
            if ($dateFin <= $now) {
                $ancienStatus = $session->getStatus();
                $session->setStatus('terminé');
                $em->persist($session);
                $sessionsModifiees++;
                $details[] = "Session '{$session->getTitre()}' (ID: {$session->getId()}) : '{$ancienStatus}' → 'terminé'";
                
                // ENVOYER NOTIFICATIONS aux participants acceptés
                $notifications = $this->envoyerNotificationsSessionTerminee($session, $em, $notificationService);
                $notificationsEnvoyees += $notifications;
            }
        }
        
        // Sauvegarder les changements
        if ($sessionsModifiees > 0) {
            $em->flush();
        }
        
        return $this->json([
            'success' => true,
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'sessions_modifiees' => $sessionsModifiees,
            'notifications_envoyees' => $notificationsEnvoyees,
            'details' => $details,
            'message' => $sessionsModifiees > 0 ? 
                "{$sessionsModifiees} session(s) modifiée(s) et {$notificationsEnvoyees} notification(s) envoyée(s)" : 
                "Aucune session à modifier pour le moment"
        ]);
    }
    
    /**
     * Envoie des notifications aux participants acceptés quand une session se termine
     */
    private function envoyerNotificationsSessionTerminee($session, EntityManagerInterface $em, NotificationService $notificationService): int
    {
        $notificationsEnvoyees = 0;
        
        // Récupérer les inscriptions avec statut "accepté" pour cette session
        $inscriptionsAcceptees = $em->getRepository('App\Entity\Inscription')
            ->createQueryBuilder('i')
            ->where('i.session = :session')
            ->andWhere('i.statutParticipation = :statut')
            ->setParameter('session', $session)
            ->setParameter('statut', 'accepté')
            ->getQuery()
            ->getResult();
        
        foreach ($inscriptionsAcceptees as $inscription) {
            $user = $inscription->getUser();
            
            // Utiliser le NotificationService pour créer la notification et mettre à jour evaluationsEnvoyee
            $formation = $session->getFormation();
            $formationTitre = $formation ? $formation->getSujet() : 'Formation inconnue';
            
            $notificationService->notifierFinSession($user, $session, $formationTitre);
            $notificationsEnvoyees++;
        }
        
        return $notificationsEnvoyees;
    }
}
