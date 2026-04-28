<?php

namespace App\Service;

use App\DTO\TransferRequest;
use App\Entity\Transfer;
use App\Repository\AccountRepository;
use App\Repository\TransferRepository;
use Doctrine\ORM\EntityManagerInterface;

class TransferService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccountRepository $accountRepo,
        private TransferRepository $transferRepo
    ) {}

    public function transfer(TransferRequest $dto): void
    {
        $conn = $this->em->getConnection();

        // 🔁 Retry mechanism (3 attempts)
        for ($i = 0; $i < 3; $i++) {
            try {
                $conn->beginTransaction();

                // ⏱️ Lock timeout
                $conn->executeQuery('SET innodb_lock_wait_timeout = 5');

                // 🔁 Idempotency check
                if ($this->transferRepo->findOneBy(['referenceId' => $dto->referenceId])) {
                    return;
                }

                $source = $this->accountRepo->findForUpdate($dto->sourceAccountId);
                $destination = $this->accountRepo->findForUpdate($dto->destinationAccountId);

                if (!$source || !$destination) {
                    throw new \DomainException('Account not found');
                }

                if ($source->getId() === $destination->getId()) {
                    throw new \DomainException('Invalid transfer');
                }

                if (bccomp($source->getBalance(), (string)$dto->amount, 2) < 0) {
                    throw new \DomainException('Insufficient funds');
                }

                // 💰 Apply transfer
                $source->debit((string)$dto->amount);
                $destination->credit((string)$dto->amount);

                $transfer = new Transfer(
                    $source,
                    $destination,
                    (string)$dto->amount,
                    $dto->referenceId
                );

                $transfer->setStatus(Transfer::STATUS_SUCCESS);

                $this->em->persist($transfer);
                $this->em->flush();

                $conn->commit();
                return;
            } catch (\Doctrine\DBAL\Exception\LockWaitTimeoutException $e) {
                $conn->rollBack();

                if ($i === 2) {
                    throw new \RuntimeException('System busy, retry later');
                }

                usleep(100000); // 100ms retry delay
            } catch (\Throwable $e) {
                $conn->rollBack();
                throw $e;
            }
        }
    }
}
