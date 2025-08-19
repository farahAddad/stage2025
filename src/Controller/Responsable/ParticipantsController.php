<?php

namespace App\Controller\Responsable;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Inscription;

#[Route('/responsable/participants', name: 'responsable_participants')]
class ParticipantsController extends AbstractController
{
    #[Route('', name: '')]
    #[IsGranted('ROLE_RESPONSABLE')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        // Récupérer uniquement les inscriptions pour les formations du responsable connecté
        $inscriptions = $em->getRepository(Inscription::class)
            ->createQueryBuilder('i')
            ->select('i, s, f, u')
            ->join('i.session', 's')
            ->join('s.formation', 'f')
            ->join('i.user', 'u')
            ->where('f.responsable = :responsable')
            ->setParameter('responsable', $user)
            ->orderBy('f.dateDebut', 'DESC')
            ->addOrderBy('s.dateDebut', 'DESC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Organiser les données par formation et session
        $participantsOrganises = [];
        foreach ($inscriptions as $inscription) {
            $formation = $inscription->getSession()->getFormation();
            $session = $inscription->getSession();
            $user = $inscription->getUser();
            
            $formationKey = $formation->getId();
            $sessionKey = $session->getId();
            
            if (!isset($participantsOrganises[$formationKey])) {
                $participantsOrganises[$formationKey] = [
                    'formation' => $formation,
                    'sessions' => []
                ];
            }
            
            if (!isset($participantsOrganises[$formationKey]['sessions'][$sessionKey])) {
                $participantsOrganises[$formationKey]['sessions'][$sessionKey] = [
                    'session' => $session,
                    'participants' => []
                ];
            }
            
            $participantsOrganises[$formationKey]['sessions'][$sessionKey]['participants'][] = [
                'user' => $user,
                'inscription' => $inscription
            ];
        }
        
        return $this->render('Responsable/participants.html.twig', [
            'user' => $user,
            'participantsOrganises' => $participantsOrganises
        ]);
    }
}
