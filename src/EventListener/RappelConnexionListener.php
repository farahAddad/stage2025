<?php

namespace App\EventListener;

use App\Service\RappelAutomatiqueService;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RappelConnexionListener implements EventSubscriberInterface
{
    private RappelAutomatiqueService $rappelService;

    public function __construct(RappelAutomatiqueService $rappelService)
    {
        $this->rappelService = $rappelService;
    }

    /**
     * Définir les événements auxquels ce listener s'abonne
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }

    /**
     * Méthode appelée à chaque requête HTTP
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Ne s'exécuter que pour les requêtes principales (pas les sous-requêtes)
        if (!$event->isMainRequest()) {
            return;
        }

        // Ne s'exécuter que pour les requêtes GET (éviter les appels multiples sur POST/PUT/DELETE)
        $request = $event->getRequest();
        if ($request->getMethod() !== 'GET') {
            return;
        }

        // Éviter les requêtes pour les assets (CSS, JS, images)
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/app-assets/') || 
            str_starts_with($path, '/assets/') || 
            str_starts_with($path, '/uploads/') ||
            str_starts_with($path, '/_wdt/') ||
            str_starts_with($path, '/_profiler/')) {
            return;
        }

        // Vérifier et envoyer automatiquement les rappels J-1 (une seule fois par jour)
        try {
            $this->rappelService->verifierEtEnvoyerRappelsJ1();
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas interrompre la requête
            error_log("Erreur dans RappelConnexionListener: " . $e->getMessage());
        }
    }
} 