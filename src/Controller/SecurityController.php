<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Entity\User;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastEmail = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \Exception('Logout is handled by Symfony.');
    }

    #[Route('/default-redirect', name: 'app_default_redirect')]
    public function defaultRedirect(): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $roles = $user->getRoles();
        
        if (in_array('ROLE_RH', $roles)) {
            return $this->redirectToRoute('liste_formations');
        }
        
        if (in_array('ROLE_RESPONSABLE', $roles)) {
            return $this->redirectToRoute('responsable_formations');
        }
        
        return $this->redirectToRoute('user_dashboard');
    }
}
