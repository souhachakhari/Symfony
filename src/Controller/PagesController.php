<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/pages', name: 'app_pages')]
    public function index(): Response
    {
       return $this->render('pages/index.html.twig', [
            'controller_name' => 'PagesController',
        ]);
    }
    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
    return $this->render('pages/about.html.twig', [
        'controller_name' => 'PagesController',
    ]);
}

#[Route('/connexion', name: 'app_connexion')]
public function connexion(): Response
{
return $this->render('pages/connexion.html.twig', [
    'controller_name' => 'PagesController',
]);
}
#[Route('/inscription', name: 'app_inscription')]
public function inscription(): Response
{
return $this->render('pages/inscription.html.twig', [
    'controller_name' => 'PagesController',
]);
}

}
