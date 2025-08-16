<?php
namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    private $em;
    private $requestStack;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    /**
     * Enregistrer une action dans l'audit log
     */
    public function enregistrer(
        ?User $user, 
        string $action, 
        $valeurAvant = null, 
        $valeurApres = null
    ): void {
        $log = new AuditLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setHorodatage(new \DateTime());
        $log->setValeurAvant($valeurAvant);
        $log->setValeurApres($valeurApres);
        
        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * Enregistrer la modification d'une entité
     */
    public function enregistrerModification(
        ?User $user,
        string $entiteType,
        int $entiteId,
        $valeurAvant,
        $valeurApres,
        string $action = 'Modification'
    ): void {
        $actionDetail = sprintf('%s %s (ID: %d)', $action, $entiteType, $entiteId);
        $this->enregistrer($user, $actionDetail, $valeurAvant, $valeurApres);
    }

    /**
     * Enregistrer la création d'une entité
     */
    public function enregistrerCreation(
        ?User $user,
        string $entiteType,
        int $entiteId,
        $valeurApres,
        string $action = 'Création'
    ): void {
        $actionDetail = sprintf('%s %s (ID: %d)', $action, $entiteType, $entiteId);
        $this->enregistrer($user, $actionDetail, null, $valeurApres);
    }

    /**
     * Enregistrer la suppression d'une entité
     */
    public function enregistrerSuppression(
        ?User $user,
        string $entiteType,
        int $entiteId,
        $valeurAvant,
        string $action = 'Suppression'
    ): void {
        $actionDetail = sprintf('%s %s (ID: %d)', $action, $entiteType, $entiteId);
        $this->enregistrer($user, $actionDetail, $valeurAvant, null);
    }
} 