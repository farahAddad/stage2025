<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Entity\Notification;
use App\Service\NotificationService;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/', name: 'user_dashboard')]
    public function index(NotificationService $notificationService, EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur n'est pas RH ou Responsable
        if (in_array('ROLE_RH', $user->getRoles()) || in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        $notifications = $notificationService->getNotificationsNonLues($user);
        $countNonLues = $notificationService->countNotificationsNonLues($user);
        
        // Récupérer les inscriptions de l'utilisateur
        $inscriptions = $em->getRepository('App\Entity\Inscription')->findBy(['user' => $user]);
        
        // Statistiques
        $totalFormations = count($inscriptions);
        $formationsEnCours = 0;
        $formationsTerminees = 0;
        $sessionsAValider = 0;
        
        foreach ($inscriptions as $inscription) {
            $session = $inscription->getSession();
            if ($session) {
                $status = $session->getStatus();
                $statutParticipation = $inscription->getStatutParticipation();
                
                if ($status == 'en cours') {
                    $formationsEnCours++;
                } elseif ($status == 'terminé') {
                    $formationsTerminees++;
                }
                
                // Compter les sessions à valider (statut "en attente" et session "créée")
                if ($statutParticipation == 'en attente' && $status == 'créée') {
                    $sessionsAValider++;
                }
            }
        }
        
        return $this->render('User/sessions.html.twig', [
            'user' => $user,
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'inscriptions' => $inscriptions,
            'totalFormations' => $totalFormations,
            'formationsEnCours' => $formationsEnCours,
            'formationsTerminees' => $formationsTerminees,
            'sessionsAValider' => $sessionsAValider
        ]);
    }

    #[Route('/notifications', name: 'user_notifications')]
    public function notifications(NotificationService $notificationService, EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur n'est pas RH ou Responsable
        if (in_array('ROLE_RH', $user->getRoles()) || in_array('ROLE_RESPONSABLE', $user->getRoles())) {
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
        
        return $this->render('User/notifications.html.twig', [
            'notifications' => $notifications,
            'countNonLues' => $countNonLues,
            'user' => $user
        ]);
    }

    #[Route('/notification/marquer-lue/{id}', name: 'user_marquer_notification_lue', methods: ['POST'])]
    public function marquerNotificationLue(int $id, NotificationService $notificationService, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();
            
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Utilisateur non connecté']);
            }
            
            $notification = $em->getRepository(Notification::class)->find($id);
            
            if (!$notification) {
                return new JsonResponse(['success' => false, 'message' => 'Notification non trouvée']);
            }
            
            // Vérifier que l'utilisateur connecté est bien le destinataire de la notification
            $destinataire = $notification->getDestinataire();
            if (!$destinataire) {
                return new JsonResponse(['success' => false, 'message' => 'Notification invalide']);
            }
            
            // Vérifier que le destinataire est bien l'utilisateur connecté
            if ($destinataire !== $user) {
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

    #[Route('/notifications/marquer-toutes-lues', name: 'user_marquer_toutes_notifications_lues', methods: ['POST'])]
    public function marquerToutesNotificationsLues(NotificationService $notificationService, EntityManagerInterface $em): JsonResponse
    {
        try {
            // Utiliser l'utilisateur réellement connecté
            $user = $this->getUser();
            
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Utilisateur non connecté']);
            }
            
            $notificationService->marquerToutesCommeLues($user);
            
            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            // Log l'erreur
            error_log('Erreur dans marquerToutesNotificationsLues: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Erreur interne: ' . $e->getMessage()]);
        }
    }

    #[Route('/formations', name: 'user_formations')]
    public function formations(EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur n'est pas RH ou Responsable
        if (in_array('ROLE_RH', $user->getRoles()) || in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        // Récupérer les inscriptions de l'utilisateur
        $inscriptions = $em->getRepository('App\Entity\Inscription')->findBy(['user' => $user]);
        
        return $this->render('User/formations.html.twig', [
            'inscriptions' => $inscriptions,
            'user' => $user
        ]);
    }


    #[Route('/inscription/{id}/details', name: 'user_inscription_details')]
    public function inscriptionDetails(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Récupérer l'inscription
        $inscription = $em->getRepository('App\Entity\Inscription')->find($id);
        
        if (!$inscription || $inscription->getUser() !== $user) {
            throw $this->createNotFoundException('Inscription non trouvée');
        }
        
        // Vérifier si le statut doit être mis à jour automatiquement
        $session = $inscription->getSession();
        $sessionStatus = $session->getStatus();
        $inscriptionStatus = $inscription->getStatutParticipation();
        
        // Si la session n'est plus "créée" et que l'inscription n'est pas validée, marquer comme "absence de validation"
        if (in_array($sessionStatus, ['en cours', 'terminée', 'annulée']) && 
            ($inscriptionStatus == 'en attente' || !$inscriptionStatus)) {
            $inscription->setStatutParticipation('absence de validation');
            $em->persist($inscription);
            $em->flush();
        }
        
        return $this->render('User/inscription_details.html.twig', [
            'inscription' => $inscription,
            'user' => $user
        ]);
    }

    #[Route('/inscription/{id}/accepter', name: 'user_accepter_inscription', methods: ['POST'])]
    public function accepterInscription(int $id, EntityManagerInterface $em, AuditLogService $auditLogService): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non connecté']);
        }
        
        $inscription = $em->getRepository('App\Entity\Inscription')->find($id);
        
        if (!$inscription || $inscription->getUser() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Inscription non trouvée']);
        }
        
        // Mettre à jour l'inscription
        $inscription->setStatutParticipation('accepté');
        $inscription->setDateValidation(new \DateTime());
        
        $em->persist($inscription);
        $em->flush();
        
        // Marquer que le calendrier doit être mis à jour
        $this->addFlash('success', 'Participation acceptée avec succès ! Le calendrier sera mis à jour automatiquement.');
        
        // Créer un audit log pour l'acceptation
        $session = $inscription->getSession();
        $formation = $session->getFormation();
        
        $auditLogService->enregistrer(
            $user,
            'Acceptation inscription formation',
            null,
            json_encode([
                'formation_id' => $formation->getId(),
                'formation_sujet' => $formation->getSujet(),
                'session_id' => $session->getId(),
                'session_titre' => $session->getTitre(),
                'inscription_id' => $inscription->getId(),
                'date_validation' => $inscription->getDateValidation()->format('d/m/Y H:i:s')
            ])
        );
        
        return new JsonResponse(['success' => true, 'message' => 'Inscription acceptée avec succès']);
    }

    #[Route('/inscription/{id}/refuser', name: 'user_refuser_inscription', methods: ['POST'])]
    public function refuserInscription(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non connecté']);
        }
        
        $inscription = $em->getRepository('App\Entity\Inscription')->find($id);
        
        if (!$inscription || $inscription->getUser() !== $user) {
            return new JsonResponse(['success' => false, 'message' => 'Inscription non trouvée']);
        }
        
        // Mettre à jour l'inscription
        $inscription->setStatutParticipation('refusé');
        
        $em->persist($inscription);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Inscription refusée']);
    }

    #[Route('/profile', name: 'user_profile')]
    public function profile(EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur n'est pas RH ou Responsable
        if (in_array('ROLE_RH', $user->getRoles()) || in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        return $this->render('User/profile.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/evaluations', name: 'user_evaluations')]
    public function evaluations(Request $request, EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur n'est pas RH ou Responsable
        if (in_array('ROLE_RH', $user->getRoles()) || in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        // Récupérer le numéro de page depuis la requête
        $page = $request->query->getInt('page', 1);
        $limit = 10; // 10 évaluations par page
        $offset = ($page - 1) * $limit;
        
        // Récupérer le total des évaluations
        $totalEvaluations = $em->getRepository('App\Entity\Evaluation')
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Récupérer les évaluations paginées
        $evaluations = $em->getRepository('App\Entity\Evaluation')
            ->createQueryBuilder('e')
            ->leftJoin('e.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        // Calculer le nombre total de pages
        $totalPages = ceil($totalEvaluations / $limit);
        
        return $this->render('User/evaluations.html.twig', [
            'user' => $user,
            'evaluations' => $evaluations,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalEvaluations' => $totalEvaluations
        ]);
    }

    #[Route('/historique', name: 'user_historique')]
    public function historique(EntityManagerInterface $em): Response
    {
        // Utiliser l'utilisateur réellement connecté
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur n'est pas RH ou Responsable
        if (in_array('ROLE_RH', $user->getRoles()) || in_array('ROLE_RESPONSABLE', $user->getRoles())) {
            return $this->redirectToRoute('liste_formations');
        }
        
        // Récupérer toutes les inscriptions de l'utilisateur triées par date
        $inscriptions = $em->getRepository('App\Entity\Inscription')
            ->createQueryBuilder('i')
            ->leftJoin('i.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Récupérer les notifications de l'utilisateur
        $notifications = $em->getRepository('App\Entity\Notification')
            ->createQueryBuilder('n')
            ->where('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Récupérer les évaluations de l'utilisateur
        $evaluations = $em->getRepository('App\Entity\Evaluation')
            ->createQueryBuilder('e')
            ->leftJoin('e.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('User/historique.html.twig', [
            'user' => $user,
            'inscriptions' => $inscriptions,
            'notifications' => $notifications,
            'evaluations' => $evaluations
        ]);
    }
}
