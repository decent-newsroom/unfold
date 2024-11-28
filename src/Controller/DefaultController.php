<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{

    /**
     * @Route("/default")
     */
    #[\Symfony\Component\Routing\Attribute\Route('/', name: 'default')]
    public function index(): Response
    {
        return $this->render('default/home.html.twig');
    }
}
