<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            
            // Vérifier si l'utilisateur existe déjà
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            
            if ($existingUser) {
                return $this->render('security/register.html.twig', [
                    'error' => 'Un utilisateur avec cet email existe déjà.'
                ]);
            }
            
            // Récupérer le rôle sélectionné
            $role = $request->request->get('role');
            $customRole = $request->request->get('customRole');
            
            // Traiter le rôle
            if ($role === 'custom') {
                if (empty($customRole)) {
                    return $this->render('security/register.html.twig', [
                        'error' => 'Veuillez saisir un rôle personnalisé.'
                    ]);
                }
                // Ajouter automatiquement ROLE_ devant si ce n'est pas déjà fait
                $finalRole = str_starts_with($customRole, 'ROLE_') ? $customRole : 'ROLE_' . strtoupper($customRole);
            } else {
                $finalRole = $role;
            }
            
            // Vérifier que le rôle est valide
            $validRoles = ['ROLE_RH', 'ROLE_RESPONSABLE'];
            if (empty($finalRole)) {
                return $this->render('security/register.html.twig', [
                    'error' => 'Veuillez sélectionner un rôle ou en saisir un personnalisé.'
                ]);
            }
            
            // Créer un nouvel utilisateur
            $user = new User();
            $user->setEmail($email);
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setRole($finalRole);
            
            // Hasher le mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            
            // Sauvegarder l'utilisateur
            $entityManager->persist($user);
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('security/register.html.twig');
    }
}
