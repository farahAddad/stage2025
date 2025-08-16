<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Entity\Formation;
use App\Entity\Session;
use App\Entity\User;
use App\Entity\Inscription;

class EmailService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Envoyer un email de notification de création de formation au responsable
     */
    public function sendFormationNotificationToResponsable(Formation $formation): void
    {
        $responsable = $formation->getResponsable();
        if (!$responsable) {
            return;
        }

        $email = (new Email())
            ->from(new Address('noreply@formation.com', 'Système de Formation'))
            ->to(new Address($responsable->getEmail(), $responsable->getNom() . ' ' . $responsable->getPrenom()))
            ->subject('Nouvelle formation créée : ' . $formation->getSujet())
            ->html($this->generateResponsableEmailContent($formation));

        $this->mailer->send($email);
    }

    /**
     * Envoyer un email de notification aux participants
     */
    public function sendFormationNotificationToParticipants(Formation $formation, array $inscriptions): void
    {
        foreach ($inscriptions as $inscription) {
            $user = $inscription->getUser();
            $session = $inscription->getSession();
            
            if (!$user || !$session) {
                continue;
            }

            $email = (new Email())
                ->from(new Address('noreply@formation.com', 'Système de Formation'))
                ->to(new Address($user->getEmail(), $user->getNom() . ' ' . $user->getPrenom()))
                ->subject('Inscription à la formation : ' . $formation->getSujet())
                ->html($this->generateParticipantEmailContent($formation, $session, $user));

            $this->mailer->send($email);
        }
    }

    /**
     * Générer le contenu de l'email pour le responsable
     */
    private function generateResponsableEmailContent(Formation $formation): string
    {
        $responsable = $formation->getResponsable();
        $sessions = $formation->getSessions();
        $sessionsHtml = '';
        
        foreach ($sessions as $session) {
            $sessionsHtml .= '
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">' . $session->getTitre() . '</td>
                <td style="padding: 10px; border: 1px solid #ddd;">' . $session->getDateDebut()->format('d/m/Y H:i') . '</td>
                <td style="padding: 10px; border: 1px solid #ddd;">' . $session->getDateFin()->format('d/m/Y H:i') . '</td>
                <td style="padding: 10px; border: 1px solid #ddd;">' . $session->getType() . '</td>
                <td style="padding: 10px; border: 1px solid #ddd;">' . ($session->getSalle() ? $session->getSalle()->getNom() : $session->getEmplacement()) . '</td>
            </tr>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Nouvelle formation créée</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
                    🎓 Nouvelle formation créée
                </h2>
                
                <p>Bonjour <strong>' . $responsable->getPrenom() . ' ' . $responsable->getNom() . '</strong>,</p>
                
                <p>Une nouvelle formation a été créée et vous avez été désigné(e) comme responsable.</p>
                
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="color: #2c3e50; margin-top: 0;">📋 Détails de la formation</h3>
                    <p><strong>Sujet :</strong> ' . $formation->getSujet() . '</p>
                    <p><strong>Date de début :</strong> ' . $formation->getDateDebut()->format('d/m/Y') . '</p>
                    <p><strong>Durée :</strong> ' . $formation->getDuree() . ' jour(s)</p>
                </div>
                
                <h3 style="color: #2c3e50;">📅 Sessions programmées</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead>
                        <tr style="background-color: #3498db; color: white;">
                            <th style="padding: 10px; border: 1px solid #ddd;">Titre</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Début</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Fin</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Type</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Lieu</th>
                        </tr>
                    </thead>
                    <tbody>' . $sessionsHtml . '
                    </tbody>
                </table>
                
                <div style="background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0;"><strong>✅ Action requise :</strong> Veuillez valider cette formation dans votre espace administrateur.</p>
                </div>
                
                <p>Cordialement,<br>
                <strong>Équipe Formation</strong></p>
            </div>
        </body>
        </html>';
    }

    /**
     * Générer le contenu de l'email pour les participants
     */
    private function generateParticipantEmailContent(Formation $formation, Session $session, User $user): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Inscription à la formation</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
                    🎓 Inscription à la formation
                </h2>
                
                <p>Bonjour <strong>' . $user->getPrenom() . ' ' . $user->getNom() . '</strong>,</p>
                
                <p>Vous avez été inscrit(e) à une nouvelle formation.</p>
                
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="color: #2c3e50; margin-top: 0;">📋 Détails de la formation</h3>
                    <p><strong>Formation :</strong> ' . $formation->getSujet() . '</p>
                    <p><strong>Responsable :</strong> ' . $formation->getResponsable()->getPrenom() . ' ' . $formation->getResponsable()->getNom() . '</p>
                </div>
                
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="color: #856404; margin-top: 0;">📅 Session à laquelle vous êtes inscrit(e)</h3>
                    <p><strong>Titre :</strong> ' . $session->getTitre() . '</p>
                    <p><strong>Date de début :</strong> ' . $session->getDateDebut()->format('d/m/Y H:i') . '</p>
                    <p><strong>Date de fin :</strong> ' . $session->getDateFin()->format('d/m/Y H:i') . '</p>
                    <p><strong>Type :</strong> ' . $session->getType() . '</p>
                    <p><strong>Lieu :</strong> ' . ($session->getSalle() ? $session->getSalle()->getNom() : $session->getEmplacement()) . '</p>
                </div>
                
                <div style="background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0;"><strong>✅ Action requise :</strong> Veuillez confirmer votre participation en vous connectant à votre espace utilisateur.</p>
                </div>
                
                <p>Cordialement,<br>
                <strong>Équipe Formation</strong></p>
            </div>
        </body>
        </html>';
    }
} 