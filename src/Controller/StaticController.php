<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StaticController extends AbstractController
{
    #[Route('/about')]
    public function about(): Response
    {
        return $this->render('static/about.html.twig');
    }

    #[Route('/roadmap')]
    public function roadmap(): Response
    {
        return $this->render('static/roadmap.html.twig');
    }

    #[Route('/pricing')]
    public function pricing(): Response
    {
        return $this->render('static/pricing.html.twig');
    }

    #[Route('/tos')]
    public function tos(): Response
    {
        return $this->render('static/tos.html.twig');
    }
}
