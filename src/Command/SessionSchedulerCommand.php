<?php

namespace App\Command;

use App\Service\SessionSchedulerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:session-scheduler',
    description: 'Vérifie et met à jour le statut des sessions toutes les 40 secondes',
)]
class SessionSchedulerCommand extends Command
{
    public function __construct(
        private SessionSchedulerService $sessionSchedulerService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Cette commande vérifie toutes les sessions et met à jour leur statut selon la date de début');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🕐 Scheduler des Sessions - Vérification des statuts');

        try {
            // Utiliser le service pour vérifier et mettre à jour les sessions
            $resultat = $this->sessionSchedulerService->verifierEtMettreAJourSessions();
            
            // Afficher les résultats
            $io->info(sprintf('📊 Sessions vérifiées: %d', $resultat['sessions_verifiees']));
            
            if ($resultat['sessions_modifiees'] > 0) {
                $io->success(sprintf(
                    '✅ %d session(s) mise(s) à jour avec succès !',
                    $resultat['sessions_modifiees']
                ));
                
                // Afficher le nombre de notifications envoyées
                if ($resultat['notifications_envoyees'] > 0) {
                    $io->success(sprintf(
                        '📢 %d notification(s) envoyée(s) aux participants',
                        $resultat['notifications_envoyees']
                    ));
                }
                
                // Afficher les détails des modifications
                $io->section('📋 Détails des modifications:');
                
                // Grouper par type de changement
                $changementsDebut = array_filter($resultat['details'], fn($d) => $d['type_changement'] === 'debut');
                $changementsFin = array_filter($resultat['details'], fn($d) => $d['type_changement'] === 'fin');
                
                if (!empty($changementsDebut)) {
                    $io->section('🟢 Sessions qui commencent:');
                    foreach ($changementsDebut as $detail) {
                        $io->text(sprintf(
                            '🔄 Session "%s" (ID: %d) - %s → %s (Date début: %s) - %d notifications',
                            $detail['titre'],
                            $detail['session_id'],
                            $detail['ancien_statut'],
                            $detail['nouveau_statut'],
                            $detail['date_debut'],
                            $detail['notifications_envoyees']
                        ));
                    }
                }
                
                if (!empty($changementsFin)) {
                    $io->section('🔴 Sessions qui se terminent:');
                    foreach ($changementsFin as $detail) {
                        $io->text(sprintf(
                            '🔄 Session "%s" (ID: %d) - %s → %s (Date fin: %s) - %d notifications',
                            $detail['titre'],
                            $detail['session_id'],
                            $detail['ancien_statut'],
                            $detail['nouveau_statut'],
                            $detail['date_fin'],
                            $detail['notifications_envoyees']
                        ));
                    }
                }
            } else {
                $io->info('ℹ️ Aucune session nécessite de changement de statut pour le moment.');
            }
            
            // Afficher les erreurs s'il y en a
            if (!empty($resultat['erreurs'])) {
                $io->section('⚠️ Erreurs rencontrées:');
                foreach ($resultat['erreurs'] as $erreur) {
                    $io->error($erreur);
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('❌ Erreur lors de la vérification des sessions: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
