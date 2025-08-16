<?php

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuditLogController extends AbstractController
{
    #[Route('/admin/audit-logs', name: 'admin_audit_logs')]
    public function index(Request $request, AuditLogRepository $auditLogRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20; // Nombre d'audit logs par page
        
        // Récupérer les paramètres de filtrage
        $user = $request->query->get('user');
        $action = $request->query->get('action');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        
        // Appliquer les filtres
        $auditLogs = $auditLogRepository->findByFilters(
            $user, 
            $action, 
            $dateFrom, 
            $dateTo, 
            $page, 
            $limit
        );
        
        // Calculer le nombre total avec filtres
        $totalAuditLogs = $auditLogRepository->countByFilters(
            $user, 
            $action, 
            $dateFrom, 
            $dateTo
        );
        $totalPages = ceil($totalAuditLogs / $limit);
        
        // Récupérer les actions et utilisateurs pour les filtres
        $actions = $auditLogRepository->getActions();
        $users = $auditLogRepository->getUsers();
        
        return $this->render('admin/audit_logs.html.twig', [
            'audit_logs' => $auditLogs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_audit_logs' => $totalAuditLogs,
            'actions' => $actions,
            'users' => $users,
            'filters' => [
                'user' => $user,
                'action' => $action,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }

    #[Route('/admin/audit-logs/{id}', name: 'admin_audit_log_detail')]
    public function detail(AuditLog $auditLog): Response
    {
        return $this->render('admin/audit_log_detail.html.twig', [
            'audit_log' => $auditLog
        ]);
    }

    #[Route('/admin/audit-logs/export/csv', name: 'admin_audit_logs_export_csv')]
    public function exportCsv(Request $request, AuditLogRepository $auditLogRepository): Response
    {
        // Récupérer les paramètres de filtrage
        $user = $request->query->get('user');
        $action = $request->query->get('action');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        
        // Récupérer tous les logs avec filtres (sans pagination)
        $auditLogs = $auditLogRepository->findByFilters(
            $user, 
            $action, 
            $dateFrom, 
            $dateTo, 
            1, 
            10000 // Limite très élevée pour récupérer tout
        );
        
        // Créer le contenu CSV
        $csvContent = "ID,Date,Utilisateur,Action,Valeur Avant,Valeur Après\n";
        
        foreach ($auditLogs as $log) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $log->getId(),
                $log->getHorodatage()->format('Y-m-d H:i:s'),
                $log->getUser() ? $log->getUser()->getEmail() : 'N/A',
                $log->getAction(),
                $log->getValeurAvant() ?? 'N/A',
                $log->getValeurApres() ?? 'N/A'
            );
        }
        
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }
} 