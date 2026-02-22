<?php

namespace App\Controller;

use App\Entity\Layout;
use App\Service\UpdateScreenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LayoutConfigController extends AbstractController
{
    private $request;

    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
    }

    public function getLayout(EntityManagerInterface $entityManager): Response
    {
        $data = $entityManager->getRepository(Layout::class)->findAll();

        if (!$data) {
            $defaultLayout = ['CurrentWeather', 'Forecast', 'Spotify', 'GoogleCalendar', 'AirQuality'];
            foreach ($defaultLayout as $component) {
                $layout = new Layout(); // Tworzymy nowĂ„â€¦ instancjĂ„â„˘ dla kaÄąÄ˝dego komponentu
                $layout->setLayout($component, null);
                $entityManager->persist($layout);
            }
            $entityManager->flush();
        }

        $layout = $entityManager->getRepository(Layout::class)->findBy([], ['id' => 'ASC']);
        $layoutMainArray = [];
        $layoutReplecmentArray = [];
        foreach ($layout as $item) {
            $layoutMainArray[] = $item->getMain();
            $layoutReplecmentArray[] = $item->getReplacement();
        }

        // Defensive limit: current screen layout is 5 slots max even if DB has stale extra rows.
        $layoutMainArray = \array_slice($layoutMainArray, 0, 5);
        $layoutReplecmentArray = \array_slice($layoutReplecmentArray, 0, 5);

        return $this->json(['layout' => $layoutMainArray, 'replacment' => $layoutReplecmentArray]);
    }

    #[Route('/set-layout', name: 'set-layout')]
    public function setLayout(EntityManagerInterface $entityManager)
    {
        // Pobieramy dane z formularza POST z uÄąÄ˝yciem $this->request
        $window1 = $this->request->request->get('component1');
        $window2 = $this->request->request->get('component2');
        $window3 = $this->request->request->get('component3');
        $window4 = $this->request->request->get('component4');
        $window5 = $this->request->request->get('component5');

        $replacment1 = $this->request->request->get('replacement1');
        $replacment2 = $this->request->request->get('replacement2');
        $replacment3 = $this->request->request->get('replacement3');
        $replacment4 = $this->request->request->get('replacement4');
        $replacment5 = $this->request->request->get('replacement5');

        // Walidacja danych (moÄąÄ˝na dodaĂ„â€ˇ bardziej zaawansowane reguÄąâ€šy)
        if (!$window1 || !$window2 || !$window3 || !$window4 || !$window5) {
            return $this->json(['error' => 'Wszystkie pola muszĂ„â€¦ byĂ„â€ˇ wypeÄąâ€šnione!'], Response::HTTP_BAD_REQUEST);
        }

        // Tworzymy tablicĂ„â„˘ z wartoÄąâ€şciami okien
        $layoutNew = [$window1, $window2, $window3, $window4, $window5];
        $replacmentNew = [$replacment1, $replacment2, $replacment3, $replacment4, $replacment5];

        // Usuwamy wszystko z tabeli
        $layoutRepository = $entityManager->getRepository(Layout::class);
        $allLayouts = $layoutRepository->findAll();
        foreach ($allLayouts as $layout) {
            $entityManager->remove($layout);
        }
        $entityManager->flush();

        // Zapisujemy nowe wartoÄąâ€şci
        for ($i = 0; $i < count($layoutNew); ++$i) {
            $layoutEntity = new Layout();
            $layoutEntity->setLayout($layoutNew[$i], $replacmentNew[$i]);
            $entityManager->persist($layoutEntity);
        }
        $entityManager->flush();

        $screen = new UpdateScreenService();
        $screen->updateScreen();

        return $this->redirectToRoute('settings', ['tab' => 'layout-settings']);
    }
}
