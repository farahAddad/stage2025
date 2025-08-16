<?php

namespace App\Controller;

use App\Repository\CalendarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
class CalendarController extends AbstractController
{
    #[Route('/', name: 'app_calendar_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request, CalendarRepository $calendarRepository): Response
    {
        // Utiliser le mois et l'année actuels par défaut
        $month = $request->query->get('month', (int)date('n'));
        $year = $request->query->get('year', (int)date('Y'));
        
        // Vérifier si on a cliqué sur "Aujourd'hui"
        $showToday = $request->query->get('today', false);
        
        // Récupérer et organiser toutes les données du calendrier
        $calendarData = $calendarRepository->organizeCalendarData($month, $year);
        
        return $this->render('calendar/calendrier.html.twig', [
            'calendarData' => $calendarData,
            'currentMonth' => $month,
            'currentYear' => $year,
            'monthName' => $this->getMonthName($month),
            'today' => new \DateTime(),
            'showToday' => $showToday,
        ]);
    }
    

    
    private function getMonthName($month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        return $months[$month] ?? 'Mois inconnu';
    }
    

}
