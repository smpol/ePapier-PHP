<?php

namespace App\Controller;

use App\Entity\Countdown;
use App\Entity\EmailSettings;
use App\Entity\GoogleAccessToken;
use App\Entity\Location;
use App\Entity\SolarEdge;
use App\Entity\Spotify;
use App\Service\LayoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'settings')]
    public function settings(EntityManagerInterface $entityManager, LayoutService $componentService, LayoutConfigController $layoutConfigController): Response
    {
        $solarEdgeSettings = $entityManager->getRepository(SolarEdge::class)->findBy([], ['id' => 'DESC'], 1);
        $emailSettings = $entityManager->getRepository(EmailSettings::class)->findBy([], ['id' => 'DESC'], 1);
        $location = $entityManager->getRepository(Location::class)->find(1);
        $spotifySettings = $entityManager->getRepository(Spotify::class)->findBy([], ['id' => 'DESC'], 1);
        $googleSettings = $entityManager->getRepository(GoogleAccessToken::class)->findBy([], ['id' => 'DESC'], 1);
        $countDown = $entityManager->getRepository(Countdown::class)->findAll();

        // Pobieramy dostępne komponenty
        $availableComponents = $componentService->getAvailableComponents();

        $layoutResponse = $layoutConfigController->getLayout();
        $layout = json_decode($layoutResponse->getContent(), true);
        return $this->render('settings.html.twig', [
            'solarEdgeSettings' => $solarEdgeSettings,
            'emailSettings' => $emailSettings,
            'location' => $location,
            'spotifySettings' => $spotifySettings,
            'googleSettings' => $googleSettings,
            'availableComponents' => $availableComponents,
            'selectedComponents' => $layout,
            'countDown' => $countDown,
        ]);
    }

    #[Route('/save-component-order', name: 'save_component_order', methods: ['POST'])]
    public function saveComponentOrder(Request $request, LayoutService $componentService): Response
    {
        // Pobieramy komponenty z formularza
        $components = [
            $request->request->get('component1'),
            $request->request->get('component2'),
            $request->request->get('component3'),
            $request->request->get('component4'),
            $request->request->get('component5'),
            $request->request->get('component6'),
        ];

        // Sprawdzamy, czy są duplikaty
        if (count($components) !== count(array_unique($components))) {
            $this->addFlash('error', 'Nie możesz wybrać tego samego komponentu więcej niż raz.');
            return $this->redirectToRoute('settings');
        }

        // Zapisujemy nową kolejność komponentów w bazie danych lub pliku JSON
        // (Tutaj można zapisać komponenty w bazie danych lub pliku)

        $this->addFlash('success', 'Kolejność komponentów została zapisana.');
        return $this->redirectToRoute('settings');
    }
}
