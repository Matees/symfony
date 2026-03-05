<?php

namespace App\MessageHandler;

use App\Message\SmsNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SmsNotificationHandler
{

    public function __construct(
        private LoggerInterface $logger
    ) {
    }
    public function __invoke(SmsNotification $message)
    {
        $this->logger->info('fafafa', ['message' => $message]);
    }
}

