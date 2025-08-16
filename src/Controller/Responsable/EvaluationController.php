<?php

namespace App\Controller\Responsable;

use App\Entity\Evaluation;
use App\Repository\EvaluationRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/responsable/evaluations')]
#[IsGranted('ROLE_RESPONSABLE')]
class EvaluationController extends AbstractController
{
    public function __construct(
        private EvaluationRepository $evaluationRepository,
        private SessionRepository $sessionRepository
    ) {}

    /**
     * Liste de toutes les évaluations
     */
    #[Route('/', name: 'responsable_evaluations_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        
        $evaluations = $this->evaluationRepository->findAllWithDetails($page, $limit);
        $totalEvaluations = $this->evaluationRepository->count([]);
        $totalPages = ceil($totalEvaluations / $limit);
        
        // Statistiques globales
        $moyenneGlobale = $this->evaluationRepository->getMoyenneGlobale();
        $moyenneClarte = $this->evaluationRepository->getMoyenneClarte();
        $moyennePertinence = $this->evaluationRepository->getMoyennePertinence();
        
        return $this->render('responsable/evaluations.html.twig', [
            'evaluations' => $evaluations,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalEvaluations' => $totalEvaluations,
            'moyenneGlobale' => $moyenneGlobale,
            'moyenneClarte' => $moyenneClarte,
            'moyennePertinence' => $moyennePertinence
        ]);
    }

    /**
     * Détails d'une évaluation spécifique
     */
    #[Route('/{id}', name: 'responsable_evaluation_show', methods: ['GET'])]
    public function show(Evaluation $evaluation): Response
    {
        return $this->render('responsable/evaluations/show.html.twig', [
            'evaluation' => $evaluation,
            'session' => $evaluation->getSession(),
            'formation' => $evaluation->getSession()->getFormation(),
            'user' => $evaluation->getUser()
        ]);
    }

    /**
     * Statistiques des évaluations
     */
    #[Route('/statistiques', name: 'responsable_evaluations_stats', methods: ['GET'])]
    public function statistiques(): Response
    {
        $moyenneGlobale = $this->evaluationRepository->getMoyenneGlobale();
        $moyenneClarte = $this->evaluationRepository->getMoyenneClarte();
        $moyennePertinence = $this->evaluationRepository->getMoyennePertinence();
        $repartitionNotes = $this->evaluationRepository->getRepartitionNotes();
        
        return $this->render('responsable/evaluations/statistiques.html.twig', [
            'moyenneGlobale' => $moyenneGlobale,
            'moyenneClarte' => $moyenneClarte,
            'moyennePertinence' => $moyennePertinence,
            'repartitionNotes' => $repartitionNotes
        ]);
    }

    /**
     * Export CSV des évaluations
     */
    #[Route('/export/csv', name: 'responsable_evaluations_export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $evaluations = $this->evaluationRepository->findAllWithDetails(1, 1000); // Toutes les évaluations
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="evaluations_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, [
            'ID',
            'Formation',
            'Session',
            'Participant',
            'Email',
            'Note Globale',
            'Clarté',
            'Pertinence',
            'Suggestions'
        ]);
        
        // Données
        foreach ($evaluations as $evaluation) {
            $session = $evaluation->getSession();
            $formation = $session->getFormation();
            $user = $evaluation->getUser();
            
            fputcsv($output, [
                $evaluation->getId(),
                $formation ? $formation->getSujet() : 'N/A',
                $session->getTitre(),
                $user ? $user->getPrenom() . ' ' . $user->getNom() : 'N/A',
                $user ? $user->getEmail() : 'N/A',
                $evaluation->getNoteGlobale(),
                $evaluation->getClarte(),
                $evaluation->getPertinence(),
                $evaluation->getSuggestion()
            ]);
        }
        
        fclose($output);
        return $response;
    }
}
