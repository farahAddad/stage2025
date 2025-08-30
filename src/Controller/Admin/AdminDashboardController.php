<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\AdminDashboardRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(AdminDashboardRepository $dashboardRepo): Response
    {
        $user = $this->getUser();
        $selectedResponsable = $_GET['responsable'] ?? null;

        // Récupérer toutes les statistiques globales
        $globalStats = $dashboardRepo->getGlobalStats();
        
        // Récupérer tous les responsables
        $responsables = $dashboardRepo->getAllResponsables();
        
        // Statistiques par responsable (si un responsable est sélectionné)
        $statsResponsable = null;
        if ($selectedResponsable) {
            $statsResponsable = $dashboardRepo->getStatsByResponsable($selectedResponsable);
        }
        
        // Récupérer les formations avec leur taux de participation
        $formationsAvecParticipation = $dashboardRepo->getFormationsWithParticipation();
        
        // Récupérer les statistiques mensuelles pour le graphique
        $monthlyStats = $dashboardRepo->getMonthlyStats();
        
        // Récupérer des statistiques supplémentaires pour les graphiques
        $additionalStats = $dashboardRepo->getAdditionalChartStats();
        
        // Récupérer les vraies statistiques de satisfaction (sans cache)
        $satisfactionStats = $dashboardRepo->getSatisfactionStats();

        return $this->render('admin/dashboard.html.twig', [
            'user' => $user,
            'totalFormations' => $globalStats['totalFormations'],
            'tauxParticipation' => $globalStats['tauxParticipation'],
            'totalInscriptions' => $globalStats['totalInscriptions'] ?? 0,
            'inscriptionsAcceptees' => $globalStats['inscriptionsAcceptees'] ?? 0,
            'tauxSatisfaction' => $globalStats['tauxSatisfaction'],
            'joursFormation' => $globalStats['joursFormation'],

            'formationsParResponsable' => $globalStats['formationsParResponsable'],
            'totalEvaluations' => $globalStats['totalEvaluations'],
            'responsables' => $responsables,
            'selectedResponsable' => $selectedResponsable,
            'statsResponsable' => $statsResponsable,
            'formationsAvecParticipation' => $formationsAvecParticipation,
            'monthlyStats' => $monthlyStats,
            'additionalStats' => $additionalStats,
            'satisfactionStats' => $satisfactionStats,
        ]);
    }

    #[Route('/admin/stats-dashboard/pdf', name: 'admin_dashboard_pdf')]
    public function exportPDF(AdminDashboardRepository $dashboardRepo): Response
    {
        $user = $this->getUser();
        
        // Récupérer toutes les statistiques
        $globalStats = $dashboardRepo->getGlobalStats();
        $monthlyStats = $dashboardRepo->getMonthlyStats();
        $additionalStats = $dashboardRepo->getAdditionalChartStats();
        $formationsAvecParticipation = $dashboardRepo->getFormationsWithParticipation();
        
        // Récupérer tous les responsables avec leurs statistiques
        $responsables = $dashboardRepo->getAllResponsables();
        $statsParResponsable = [];
        foreach ($responsables as $responsable) {
            $statsParResponsable[] = [
                'id' => $responsable['id'],
                'nom' => $responsable['nom'],
                'prenom' => $responsable['prenom'],
                'stats' => $dashboardRepo->getStatsByResponsable($responsable['id'])
            ];
        }
        
        // Calculs supplémentaires pour le PDF
        $calculsAvances = $this->calculerStatistiquesAvancees($globalStats, $monthlyStats);
        
        // Générer le HTML pour le PDF
        $html = $this->renderView('admin/dashboard_pdf.html.twig', [
            'globalStats' => $globalStats,
            'monthlyStats' => $monthlyStats,
            'additionalStats' => $additionalStats,
            'formationsAvecParticipation' => $formationsAvecParticipation,
            'calculsAvances' => $calculsAvances,
            'responsables' => $responsables,
            'statsParResponsable' => $statsParResponsable,
            'dateExport' => new \DateTime(),
            'user' => $user
        ]);
        
        // Configuration du PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('defaultMediaType', 'screen');
        $options->set('isFontSubsettingEnabled', true);
        
        // Générer le PDF avec Dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Générer le nom du fichier
        $filename = 'dashboard_formation_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Retourner le PDF
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }
    
    /**
     * Calculer des statistiques avancées pour le PDF
     */
    private function calculerStatistiquesAvancees(array $globalStats, array $monthlyStats): array
    {
        // Vérifier si monthlyStats n'est pas vide
        if (empty($monthlyStats)) {
            return [
                'totalFormations' => 0,
                'totalParticipants' => 0,
                'moyenneSatisfaction' => 0,
                'moisPlusActif' => ['month' => 'Aucun', 'formations' => 0],
                'croissanceFormations' => 0,
                'croissanceParticipants' => 0,
                'efficaciteFormation' => 0
            ];
        }
        
        // Calculs de tendances
        $totalFormations = array_sum(array_column($monthlyStats, 'formations'));
        $totalParticipants = array_sum(array_column($monthlyStats, 'participants'));
        $moyenneSatisfaction = array_sum(array_column($monthlyStats, 'satisfaction')) / count($monthlyStats);
        
        // Mois avec le plus d'activité
        $moisPlusActif = array_reduce($monthlyStats, function($carry, $item) {
            return ($carry === null || $item['formations'] > $carry['formations']) ? $item : $carry;
        });
        
        // S'assurer que moisPlusActif n'est pas null
        if ($moisPlusActif === null) {
            $moisPlusActif = ['month' => 'Aucun', 'formations' => 0, 'sessions' => 0, 'participants' => 0, 'inscriptionsAcceptees' => 0, 'moyenneSatisfaction' => 0];
        } else {
            // Enrichir les données du mois le plus actif
            $moisPlusActif['sessions'] = $moisPlusActif['sessions'] ?? 0;
            $moisPlusActif['participants'] = $moisPlusActif['participants'] ?? 0;
            $moisPlusActif['inscriptionsAcceptees'] = $moisPlusActif['inscriptionsAcceptees'] ?? 0;
            $moisPlusActif['moyenneSatisfaction'] = $moisPlusActif['satisfaction'] ?? 0;
        }
        
        // Calcul de croissance
        $premiersMois = array_slice($monthlyStats, 0, 6);
        $derniersMois = array_slice($monthlyStats, -6);
        $croissanceFormations = $this->calculerCroissance($premiersMois, $derniersMois, 'formations');
        $croissanceParticipants = $this->calculerCroissance($premiersMois, $derniersMois, 'participants');
        
        return [
            'totalFormations' => $totalFormations,
            'totalParticipants' => $totalParticipants,
            'moyenneSatisfaction' => round($moyenneSatisfaction, 2),
            'moisPlusActif' => $moisPlusActif,
            'croissanceFormations' => $croissanceFormations,
            'croissanceParticipants' => $croissanceParticipants,
            'efficaciteFormation' => $globalStats['totalUsers'] > 0 ? round(($totalFormations / $globalStats['totalUsers']) * 100, 2) : 0
        ];
    }
    
    /**
     * Calculer le pourcentage de croissance
     */
    private function calculerCroissance(array $premiersMois, array $derniersMois, string $type): float
    {
        $moyennePremiers = array_sum(array_column($premiersMois, $type)) / count($premiersMois);
        $moyenneDerniers = array_sum(array_column($derniersMois, $type)) / count($derniersMois);
        
        if ($moyennePremiers == 0) return 0;
        
        return round((($moyenneDerniers - $moyennePremiers) / $moyennePremiers) * 100, 2);
    }
}
