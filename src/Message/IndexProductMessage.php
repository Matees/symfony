<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
class IndexProductMessage
{
    public function __construct(
        private readonly int $productId,
    ) {
    }

    public function getProductId(): int
    {
        return $this->productId;
    }
}
