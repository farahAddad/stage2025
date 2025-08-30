<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Inscription;

#[Route('/participants', name: 'admin_participants')]
class ParticipantsController extends AbstractController
{
    #[Route('', name: '')]
    #[IsGranted('ROLE_RH')]
    public function index(EntityManagerInterface $em, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $user = $this->getUser();
        
        // Récupérer toutes les inscriptions avec les détails des sessions, formations et utilisateurs
        $inscriptions = $em->getRepository(Inscription::class)
            ->createQueryBuilder('i')
            ->select('i, s, f, u')
            ->join('i.session', 's')
            ->join('s.formation', 'f')
            ->join('i.user', 'u')
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
        // Pagination par formations (2 par page)
        $formationsParPage = 2;
        $page = max(1, (int)$request->query->get('page', 1));
        $formationIds = array_keys($participantsOrganises);
        $totalFormations = count($formationIds);
        $totalPages = (int) ceil($totalFormations / $formationsParPage);
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $formationsParPage;
        $idsPage = array_slice($formationIds, $offset, $formationsParPage, true);
        $participantsOrganisesPage = [];
        foreach ($idsPage as $fid) {
            $participantsOrganisesPage[$fid] = $participantsOrganises[$fid];
        }

        return $this->render('Admin/participants.html.twig', [
            'user' => $user,
            'participantsOrganises' => $participantsOrganisesPage,
            'pagination' => [
                'page' => $page,
                'perPage' => $formationsParPage,
                'totalFormations' => $totalFormations,
                'totalPages' => $totalPages,
            ],
        ]);
    }
}
