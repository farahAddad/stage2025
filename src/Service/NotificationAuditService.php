<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NotificationAuditService
{
    private $em;
    private $requestStack;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    /**
     * Enregistrer l'envoi d'une notification dans l'audit log
     */
    public function enregistrerNotification(
        ?User $user,
        string $typeNotification,
        string $destinataire,
        string $titre,
        string $message,
        ?string $lien = null,
        ?int $formationId = null,
        ?int $sessionId = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        
        $log = new AuditLog();
        $log->setUser($user);
        $log->setAction('Notification envoyée');
        $log->setHorodatage(new \DateTime());
        $log->setValeurAvant(null);
        
        // Enregistrer les détails de la notification
        $valeurApres = [
            'type_notification' => $typeNotification,
            'destinataire' => $destinataire,
            'titre' => $titre,
            'message' => $message,
            'lien' => $lien,
            'formation_id' => $formationId,
            'session_id' => $sessionId
        ];
        
        $log->setValeurApres(json_encode($valeurApres));
        
        $this->em->persist($log);
        $this->em->flush();
    }
}
