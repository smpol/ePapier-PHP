<?php

namespace App\Controller;

use App\Service\UpdateScreenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class LayoutConfigController extends AbstractController
{
    private $serializer;
    private $request;

    public function __construct(SerializerInterface $serializer, RequestStack $requestStack)
    {
        $this->serializer = $serializer;
        $this->request = $requestStack->getCurrentRequest();
    }

    public function getLayout(): Response
    {
        // Ścieżka do pliku JSON
        $filePath = $this->getParameter('kernel.project_dir') . '/public/layout_config.json';

        // Sprawdzamy, czy plik istnieje
        if (!file_exists($filePath)) {
            // Jeśli plik nie istnieje, tworzymy domyślną tablicę
            $defaultLayout = ['CurrentWeather', 'Forecast', 'Spotify', 'GoogleCalendar', 'Emails', 'SolarEdge'];

            // Serializacja domyślnej konfiguracji do JSON
            $jsonContent = json_encode($defaultLayout);

            // Zapis pliku na dysk
            file_put_contents($filePath, $jsonContent);

            $this->addFlash('success', 'Plik został automatycznie wygenerowany z domyślnymi wartościami.');
        } else {
            // Jeśli plik istnieje, wczytujemy go
            $jsonContent = file_get_contents($filePath);
            $defaultLayout = json_decode($jsonContent, true);
        }

        // Zwracamy tablicę jako JSON (można jej użyć w API)
        return $this->json($defaultLayout);
    }

    #[Route("/set-layout", name: 'set-layout')]
    public function setLayout()
    {
        // Pobieramy dane z formularza POST z użyciem $this->request
        $window1 = $this->request->request->get('component1');
        $window2 = $this->request->request->get('component2');
        $window3 = $this->request->request->get('component3');
        $window4 = $this->request->request->get('component4');
        $window5 = $this->request->request->get('component5');
        $window6 = $this->request->request->get('component6');

        // Walidacja danych (można dodać bardziej zaawansowane reguły)
        if (!$window1 || !$window2 || !$window3 || !$window4 || !$window5 || !$window6) {
            return $this->json(['error' => 'Wszystkie pola muszą być wypełnione!'], Response::HTTP_BAD_REQUEST);
        }

        // Tworzymy tablicę z wartościami okien
        $layout = [$window1, $window2, $window3, $window4, $window5, $window6];

        // Serializacja tablicy do JSON
        $jsonContent = json_encode($layout);

        // Ścieżka do pliku JSON
        $filePath = $this->getParameter('kernel.project_dir') . '/public/layout_config.json';

        // Zapisanie pliku na dysku
        file_put_contents($filePath, $jsonContent);

        $screen = new UpdateScreenService();
        $screen->updateScreen();

        return $this->redirectToRoute('settings', ['tab' => 'layout-settings']);
    }
}
