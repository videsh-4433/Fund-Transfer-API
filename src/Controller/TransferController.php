<?php

namespace App\Controller;

use App\DTO\TransferRequest;
use App\Service\TransferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class TransferController extends AbstractController
{
    #[Route('/api/transfers', methods: ['POST'])]
    public function transfer(
        Request $request,
        TransferService $service,
        ValidatorInterface $validator,
        RateLimiterFactory $transferApiLimiter
    ): JsonResponse {

        $limiter = $transferApiLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Too many requests'], 429);
        }
        $data = json_decode($request->getContent(), true);

        $dto = new TransferRequest();
        $dto->sourceAccountId = $data['sourceAccountId'] ?? 0;
        $dto->destinationAccountId = $data['destinationAccountId'] ?? 0;
        $dto->amount = $data['amount'] ?? 0;
        $dto->referenceId = $data['referenceId'] ?? '';

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 400);
        }

        try {
            $service->transfer($dto);
            return $this->json(['status' => 'success']);

        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Internal Server Error'], 500);
        }
    }
}