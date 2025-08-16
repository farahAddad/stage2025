<?php

namespace App\Command;

use App\Service\SessionTestService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:session-test',
    description: 'Test des sessions et notifications',
)]
class SessionTestCommand extends Command
{
    public function __construct(
        private SessionTestService $sessionTestService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('list', 'l', null, 'Lister toutes les sessions')
            ->addOption('test', 't', InputOption::VALUE_REQUIRED, 'Tester le changement de statut d\'une session (ID)')
            ->addOption('fin', 'f', InputOption::VALUE_REQUIRED, 'Tester la fin d\'une session (ID)')
            ->addOption('reset', 'r', InputOption::VALUE_REQUIRED, 'Remettre une session au statut "créée" (ID)')
            ->setHelp('Cette commande permet de tester les sessions et les notifications');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧪 Test des Sessions et Notifications');

        // Option --list
        if ($input->getOption('list')) {
            return $this->listerSessions($io);
        }

        // Option --test
        if ($input->getOption('test')) {
            $sessionId = (int) $input->getOption('test');
            return $this->testerSession($io, $sessionId);
        }

        // Option --fin
        if ($input->getOption('fin')) {
            $sessionId = (int) $input->getOption('fin');
            return $this->testerFinSession($io, $sessionId);
        }

        // Option --reset
        if ($input->getOption('reset')) {
            $sessionId = (int) $input->getOption('reset');
            return $this->resetSession($io, $sessionId);
        }

        // Aucune option spécifiée, afficher l'aide
        $io->info('Utilisez --help pour voir les options disponibles');
        $io->text('Options disponibles:');
        $io->text('  --list (-l) : Lister toutes les sessions');
        $io->text('  --test (-t) ID : Tester le changement de statut d\'une session');
        $io->text('  --fin (-f) ID : Tester la fin d\'une session');
        $io->text('  --reset (-r) ID : Remettre une session au statut "créée"');

        return Command::SUCCESS;
    }

    private function listerSessions(SymfonyStyle $io): int
    {
        $io->section('📋 Liste des Sessions');
        
        try {
            $sessions = $this->sessionTestService->listerSessions();
            
            if (empty($sessions)) {
                $io->info('Aucune session trouvée dans la base de données.');
                return Command::SUCCESS;
            }

            $io->table(
                ['ID', 'Titre', 'Statut', 'Date Début', 'Date Fin', 'Formation', 'Participants'],
                array_map(function($session) {
                    return [
                        $session['id'],
                        $session['titre'],
                        $session['statut'],
                        $session['date_debut'],
                        $session['date_fin'],
                        $session['formation'],
                        $session['participants']
                    ];
                }, $sessions)
            );

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la récupération des sessions: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function testerSession(SymfonyStyle $io, int $sessionId): int
    {
        $io->section(sprintf('🧪 Test de la Session ID: %d', $sessionId));
        
        try {
            $resultat = $this->sessionTestService->testerChangementStatut($sessionId);
            
            if ($resultat['success']) {
                $io->success($resultat['message']);
                
                // Afficher les détails
                if (!empty($resultat['details'])) {
                    $io->section('📊 Détails de la session:');
                    $session = $resultat['details']['session'];
                    $io->text(sprintf('Titre: %s', $session['titre']));
                    $io->text(sprintf('Formation: %s', $session['formation']));
                    $io->text(sprintf('Date début: %s', $session['date_debut']));
                    $io->text(sprintf('Date fin: %s', $session['date_fin']));
                    
                    if (isset($resultat['details']['changement'])) {
                        $io->section('🔄 Changement de statut:');
                        $changement = $resultat['details']['changement'];
                        $io->text(sprintf('Ancien statut: %s', $changement['ancien_statut']));
                        $io->text(sprintf('Nouveau statut: %s', $changement['nouveau_statut']));
                        $io->text(sprintf('Timestamp: %s', $changement['timestamp']));
                    }
                    
                    if (isset($resultat['details']['notifications'])) {
                        $notifications = $resultat['details']['notifications'];
                        $io->section(sprintf('📢 Notifications (%d envoyées):', $notifications['total_envoyees']));
                        
                        if (!empty($notifications['participants_notifies'])) {
                            foreach ($notifications['participants_notifies'] as $participant) {
                                $io->text(sprintf('• %s %s (%s)', 
                                    $participant['prenom'], 
                                    $participant['nom'], 
                                    $participant['email']
                                ));
                            }
                        }
                        
                        if (!empty($notifications['erreurs'])) {
                            $io->section('⚠️ Erreurs:');
                            foreach ($notifications['erreurs'] as $erreur) {
                                $io->error($erreur);
                            }
                        }
                    }
                }
                
            } else {
                $io->error($resultat['message']);
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors du test: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function testerFinSession(SymfonyStyle $io, int $sessionId): int
    {
        $io->section(sprintf('🧪 Test de la Fin de la Session ID: %d', $sessionId));
        
        try {
            $resultat = $this->sessionTestService->testerFinSession($sessionId);
            
            if ($resultat['success']) {
                $io->success($resultat['message']);
                
                // Afficher les détails
                if (!empty($resultat['details'])) {
                    $io->section('📊 Détails de la session:');
                    $session = $resultat['details']['session'];
                    $io->text(sprintf('Titre: %s', $session['titre']));
                    $io->text(sprintf('Formation: %s', $session['formation']));
                    $io->text(sprintf('Date début: %s', $session['date_debut']));
                    $io->text(sprintf('Date fin: %s', $session['date_fin']));
                    
                    if (isset($resultat['details']['changement'])) {
                        $io->section('🔄 Changement de statut:');
                        $changement = $resultat['details']['changement'];
                        $io->text(sprintf('Ancien statut: %s', $changement['ancien_statut']));
                        $io->text(sprintf('Nouveau statut: %s', $changement['nouveau_statut']));
                        $io->text(sprintf('Timestamp: %s', $changement['timestamp']));
                    }
                    
                    if (isset($resultat['details']['notifications'])) {
                        $notifications = $resultat['details']['notifications'];
                        $io->section(sprintf('📢 Notifications (%d envoyées):', $notifications['total_envoyees']));
                        
                        if (!empty($notifications['participants_notifies'])) {
                            foreach ($notifications['participants_notifies'] as $participant) {
                                $io->text(sprintf('• %s %s (%s)', 
                                    $participant['prenom'], 
                                    $participant['nom'], 
                                    $participant['email']
                                ));
                            }
                        }
                        
                        if (!empty($notifications['erreurs'])) {
                            $io->section('⚠️ Erreurs:');
                            foreach ($notifications['erreurs'] as $erreur) {
                                $io->error($erreur);
                            }
                        }
                    }
                }
                
            } else {
                $io->error($resultat['message']);
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors du test: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function resetSession(SymfonyStyle $io, int $sessionId): int
    {
        $io->section(sprintf('🔄 Reset de la Session ID: %d', $sessionId));
        
        try {
            $success = $this->sessionTestService->remettreSessionCreee($sessionId);
            
            if ($success) {
                $io->success(sprintf('Session ID %d remise au statut "créée" avec succès', $sessionId));
            } else {
                $io->error(sprintf('Impossible de remettre la session ID %d au statut "créée"', $sessionId));
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors du reset: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
