<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TransferRequest
{
    #[Assert\NotBlank]
    public int $sourceAccountId;

    #[Assert\NotBlank]
    public int $destinationAccountId;

    #[Assert\Positive]
    public float $amount;

    #[Assert\NotBlank]
    public string $referenceId;
}