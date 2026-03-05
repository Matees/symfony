<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use App\Message\SmsNotification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class LuckyController extends AbstractController
{
    #[Route('/lucky/number')]
    public function number(MessageBusInterface $bus): Response
    {
        try {
            $bus->dispatch(new SmsNotification('test'));
        } catch (ExceptionInterface $e) {
            return $this->json($e->getMessage(), 500);
        }

        return $this->render('lucky/number.html.twig', ['number' => 10]);
    }
}
