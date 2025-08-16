<?php
namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use App\Repository\EvaluationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

class StatsDashboardController extends AbstractController
{
    #[Route('/admin/stats-dashboard', name: 'stats_dashboard')]
    public function index(FormationRepository $formationRepo, UserRepository $userRepo, EvaluationRepository $evalRepo): Response
    {
        // Nombre de formations
        $nbFormations = $formationRepo->count([]);

        // Taux de participation (exemple simplifié)
        $totalUsers = $userRepo->count([]);
        $totalParticipants = $formationRepo->createQueryBuilder('f')
            ->select('COUNT(DISTINCT u.id)')
            ->leftJoin('f.sessions', 's')
            ->leftJoin('s.inscriptions', 'i')
            ->leftJoin('i.user', 'u')
            ->getQuery()->getSingleScalarResult();
        $tauxParticipation = $totalUsers > 0 ? round(($totalParticipants / $totalUsers) * 100, 2) : 0;

        // Taux de satisfaction moyen (exemple simplifié)
        $totalEvaluations = $evalRepo->count([]);
        $sommeSatisfaction = $evalRepo->createQueryBuilder('e')
            ->select('SUM(e.noteGlobale)')
            ->getQuery()->getSingleScalarResult();
        $tauxSatisfaction = $totalEvaluations > 0 ? round($sommeSatisfaction / $totalEvaluations, 2) : 0;

        // Heures de formation par utilisateur
        $totalHeures = $formationRepo->createQueryBuilder('f')
            ->select('SUM(f.duree)')
            ->getQuery()->getSingleScalarResult() ?? 0;
        $heuresParUtilisateur = $totalUsers > 0 ? round($totalHeures / $totalUsers, 2) : 0;

        return $this->render('admin/stats_dashboard.html.twig', [
            'stats' => [
                'nbFormations' => $nbFormations,
                'tauxParticipation' => $tauxParticipation,
                'tauxSatisfaction' => $tauxSatisfaction,
                'heuresParUtilisateur' => $heuresParUtilisateur,
            ]
        ]);
    }

    #[Route('/admin/stats-dashboard/pdf', name: 'stats_dashboard_pdf')]
    public function exportPdf(FormationRepository $formationRepo, UserRepository $userRepo, EvaluationRepository $evalRepo): Response
    {
        // Récupère les stats comme dans index()
        $nbFormations = $formationRepo->count([]);
        $totalUsers = $userRepo->count([]);
        $totalParticipants = $formationRepo->createQueryBuilder('f')
            ->select('COUNT(DISTINCT u.id)')
            ->leftJoin('f.sessions', 's')
            ->leftJoin('s.inscriptions', 'i')
            ->leftJoin('i.user', 'u')
            ->getQuery()->getSingleScalarResult();
        $tauxParticipation = $totalUsers > 0 ? round(($totalParticipants / $totalUsers) * 100, 2) : 0;
        $totalEvaluations = $evalRepo->count([]);
        $sommeSatisfaction = $evalRepo->createQueryBuilder('e')
            ->select('SUM(e.noteGlobale)')
            ->getQuery()->getSingleScalarResult();
        $tauxSatisfaction = $totalEvaluations > 0 ? round($sommeSatisfaction / $totalEvaluations, 2) : 0;
        $totalHeures = $formationRepo->createQueryBuilder('f')
            ->select('SUM(f.duree)')
            ->getQuery()->getSingleScalarResult() ?? 0;
        $heuresParUtilisateur = $totalUsers > 0 ? round($totalHeures / $totalUsers, 2) : 0;

        $html = $this->renderView('admin/stats_dashboard_pdf.html.twig', [
            'stats' => [
                'nbFormations' => $nbFormations,
                'tauxParticipation' => $tauxParticipation,
                'tauxSatisfaction' => $tauxSatisfaction,
                'heuresParUtilisateur' => $heuresParUtilisateur,
            ]
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->stream('stats_dashboard.pdf', ["Attachment" => true]),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }
} 