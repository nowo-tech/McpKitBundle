<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo home page: how to connect an MCP client to this demo.
 */
final class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('demo/home.html.twig');
    }
}
