<?php

namespace App\Controller\User;

use App\Entity\Session;
use App\Entity\Evaluation;
use App\Repository\SessionRepository;
use App\Repository\EvaluationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/sessions')]

class SessionEvaluationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionRepository $sessionRepository,
        private EvaluationRepository $evaluationRepository
    ) {}

    /**
     * Interface d'évaluation d'une session terminée
     */
    #[Route('/{id}/evaluation', name: 'user_session_evaluation', methods: ['GET', 'POST'])]
    public function evaluation(Request $request, Session $session): Response
    {
        // Vérifier que l'utilisateur est inscrit à cette session
        $user = $this->getUser();
        $inscription = $session->getInscriptions()->filter(fn($i) => $i->getUser() === $user)->first();
        
        if (!$inscription) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas inscrit à cette session.');
        }

        // Vérifier que la session est terminée
        if ($session->getStatus() !== 'terminé') {
            $this->addFlash('warning', 'Cette session n\'est pas encore terminée.');
            return $this->redirectToRoute('user_sessions');
        }

        // Vérifier si l'utilisateur a déjà évalué cette session
        $evaluationExistante = $this->evaluationRepository->findOneBy([
            'user' => $user,
            'session' => $session
        ]);

        if ($evaluationExistante) {
            $this->addFlash('info', 'Vous avez déjà évalué cette session.');
            return $this->redirectToRoute('user_session_evaluation_show', ['id' => $session->getId()]);
        }

        if ($request->isMethod('POST')) {
            return $this->traiterEvaluation($request, $session, $user);
        }

        return $this->render('user/session_evaluation.html.twig', [
            'session' => $session,
            'formation' => $session->getFormation(),
            'evaluation' => null
        ]);
    }

    /**
     * Afficher une évaluation existante
     */
    #[Route('/{id}/evaluation/show', name: 'user_session_evaluation_show', methods: ['GET'])]
    public function showEvaluation(Session $session): Response
    {
        $user = $this->getUser();
        $evaluation = $this->evaluationRepository->findOneBy([
            'user' => $user,
            'session' => $session
        ]);

        if (!$evaluation) {
            $this->addFlash('warning', 'Aucune évaluation trouvée pour cette session.');
            return $this->redirectToRoute('user_sessions');
        }

        return $this->render('user/session_evaluation_show.html.twig', [
            'session' => $session,
            'formation' => $session->getFormation(),
            'evaluation' => $evaluation
        ]);
    }

    /**
     * Télécharger le certificat de participation
     */
    #[Route('/{id}/certificat', name: 'user_session_certificat', methods: ['GET'])]
    public function certificat(Session $session): Response
    {
        $user = $this->getUser();
        $inscription = $session->getInscriptions()->filter(fn($i) => $i->getUser() === $user)->first();
        
        if (!$inscription) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas inscrit à cette session.');
        }

        if ($session->getStatus() !== 'terminé') {
            $this->addFlash('warning', 'Cette session n\'est pas encore terminée.');
            return $this->redirectToRoute('user_sessions');
        }

        // Ici vous pouvez générer un PDF de certificat
        // Pour l'instant, on affiche juste une page de confirmation
        return $this->render('user/session_certificat.html.twig', [
            'session' => $session,
            'formation' => $session->getFormation(),
            'user' => $user
        ]);
    }

    /**
     * Interface de feedback détaillé
     */
    #[Route('/{id}/feedback', name: 'user_session_feedback', methods: ['GET', 'POST'])]
    public function feedback(Request $request, Session $session): Response
    {
        $user = $this->getUser();
        $inscription = $session->getInscriptions()->filter(fn($i) => $i->getUser() === $user)->first();
        
        if (!$inscription) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas inscrit à cette session.');
        }

        if ($session->getStatus() !== 'terminé') {
            $this->addFlash('warning', 'Cette session n\'est pas encore terminée.');
            return $this->redirectToRoute('user_sessions');
        }

        if ($request->isMethod('POST')) {
            return $this->traiterFeedback($request, $session, $user);
        }

        return $this->render('user/session_feedback.html.twig', [
            'session' => $session,
            'formation' => $session->getFormation()
        ]);
    }

    /**
     * Traiter la soumission de l'évaluation
     */
    private function traiterEvaluation(Request $request, Session $session, $user): Response
    {
        $noteGlobale = (int) $request->request->get('noteGlobale');
        $clarte = (int) $request->request->get('clarte');
        $pertinence = (int) $request->request->get('pertinence');
        $suggestion = $request->request->get('suggestion', '');

        // Validation des notes (1-5)
        if ($noteGlobale < 1 || $noteGlobale > 5) {
            $this->addFlash('error', 'La note globale doit être comprise entre 1 et 5.');
            return $this->redirectToRoute('user_session_evaluation', ['id' => $session->getId()]);
        }

        if ($clarte < 1 || $clarte > 5) {
            $this->addFlash('error', 'La note de clarté doit être comprise entre 1 et 5.');
            return $this->redirectToRoute('user_session_evaluation', ['id' => $session->getId()]);
        }

        if ($pertinence < 1 || $pertinence > 5) {
            $this->addFlash('error', 'La note de pertinence doit être comprise entre 1 et 5.');
            return $this->redirectToRoute('user_session_evaluation', ['id' => $session->getId()]);
        }

        $evaluation = new Evaluation();
        $evaluation->setUser($user);
        $evaluation->setSession($session);
        $evaluation->setNoteGlobale($noteGlobale);
        $evaluation->setClarte($clarte);
        $evaluation->setPertinence($pertinence);
        $evaluation->setSuggestion($suggestion);

        $this->entityManager->persist($evaluation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre évaluation post-formation a été enregistrée avec succès !');
        return $this->redirectToRoute('user_session_evaluation_show', ['id' => $session->getId()]);
    }

    /**
     * Traiter le feedback détaillé
     */
    private function traiterFeedback(Request $request, Session $session, $user): Response
    {
        $feedback = $request->request->get('feedback', '');
        $suggestions = $request->request->get('suggestions', '');

        if (empty($feedback)) {
            $this->addFlash('error', 'Le feedback ne peut pas être vide.');
            return $this->redirectToRoute('user_session_feedback', ['id' => $session->getId()]);
        }

        // Ici vous pouvez sauvegarder le feedback dans une entité dédiée
        // Pour l'instant, on affiche juste un message de succès
        $this->addFlash('success', 'Votre feedback a été enregistré avec succès !');
        return $this->redirectToRoute('user_sessions');
    }
}
