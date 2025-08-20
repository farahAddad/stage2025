<?php

namespace App\Controller\Admin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SalleRepository;
use App\Entity\Salle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AuditLogService;

 class SalleController extends AbstractController
{
    #[Route('/admin/salles', name: 'listeSalles')]
  public function index(SalleRepository $salleRepository): Response
    {
        $salles = $salleRepository->findAll();

        return $this->render('admin/salles.html.twig', [
            'salles' => $salles,
        ]);
    }
    
#[Route('/admin/salles/add', name: 'add_salle', methods: ['POST'])]
public function addAjax(Request $request, EntityManagerInterface $em, AuditLogService $auditLogService): JsonResponse
{
    $nom = $request->request->get('nom');
    $capacite = $request->request->get('capacite');

    if (!$nom || !$capacite) {
        return new JsonResponse(['error' => 'Données invalides'], 400);
    }

    $salle = new Salle();
    $salle->setNom($nom);
    $salle->setCapacite((int)$capacite);
$salle->setDisponible(true);
    $em->persist($salle);
    $em->flush();

    // Audit : création salle
    $auditLogService->enregistrer(
        $this->getUser(),
        'Création salle',
        null,
        json_encode(['id' => $salle->getId(), 'nom' => $salle->getNom(), 'capacite' => $salle->getCapacite()])
    );

    return new JsonResponse([
        'id' => $salle->getId(),
        'nom' => $salle->getNom(),
        'capacite' => $salle->getCapacite()
    ]);
}

 #[Route('/admin/salles/edit', name: 'edit_salle', methods: ['POST'])]
    public function editAjax(Request $request, EntityManagerInterface $em, SalleRepository $salleRepository, AuditLogService $auditLogService): JsonResponse
    {
        $id = $request->request->get('id');
        $nom = $request->request->get('nom');
        $capacite = $request->request->get('capacite');

        if (!$id || !$nom || !$capacite) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        $salle = $salleRepository->find($id);

        if (!$salle) {
            return new JsonResponse(['success' => false, 'message' => 'Salle non trouvée'], 404);
        }

        $oldData = [];
        $newData = [];

        if ($salle->getNom() !== $nom) {
            $oldData['nom'] = $salle->getNom();
            $newData['nom'] = $nom;
        }
        if ($salle->getCapacite() != (int)$capacite) {
            $oldData['capacite'] = $salle->getCapacite();
            $newData['capacite'] = (int)$capacite;
        }

        $salle->setNom($nom);
        $salle->setCapacite((int)$capacite);
        $em->flush();

        // Audit uniquement si au moins un champ a changé
        if (!empty($oldData)) {
            $auditLogService->enregistrer(
                $this->getUser(),
                'Modification salle',
                json_encode($oldData),
                json_encode($newData)
            );
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/admin/salles/delete', name: 'delete_salle', methods: ['POST'])]
    public function deleteAjax(Request $request, EntityManagerInterface $em, SalleRepository $salleRepository, AuditLogService $auditLogService): JsonResponse
    {
        $id = $request->request->get('id');

        if (!$id) {
            return new JsonResponse(['success' => false, 'message' => 'ID manquant'], 400);
        }

        $salle = $salleRepository->find($id);

        if (!$salle) {
            return new JsonResponse(['success' => false, 'message' => 'Salle non trouvée'], 404);
        }

        // Vérifier s'il y a des sessions qui utilisent cette salle
        $sessions = $em->getRepository('App\\Entity\\Session')->findBy(['salle' => $salle]);
        
        if (!empty($sessions)) {
            $sessionTitles = array_map(function($session) {
                return $session->getTitre();
            }, $sessions);
            
            $message = sprintf(
                'Impossible de supprimer la salle "%s". Elle est utilisée par %d session(s) : %s',
                $salle->getNom(),
                count($sessions),
                implode(', ', array_slice($sessionTitles, 0, 3)) . (count($sessionTitles) > 3 ? '...' : '')
            );
            
            return new JsonResponse(['success' => false, 'message' => $message], 409);
        }

        // Audit log avant suppression
        $auditLogService->enregistrer(
            $this->getUser(),
            'Suppression salle',
            json_encode(['id' => $salle->getId(), 'nom' => $salle->getNom(), 'capacite' => $salle->getCapacite()]),
            null
        );

        $em->remove($salle);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}