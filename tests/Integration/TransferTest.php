<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Account;
use App\Entity\Transfer;

class TransferTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        // ✅ Create the client once and store it
        $this->client = static::createClient();

        $this->em = $this->client->getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Database cleanup
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            $connection->executeStatement($platform->getTruncateTableSQL($table));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createAccounts(): array
    {
        $a1 = new Account();
        $a1->credit('1000.00');

        $a2 = new Account();
        $a2->credit('500.00');

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->flush();

        return [$a1, $a2];
    }

    public function testSuccessfulTransfer(): void
    {
        [$a1, $a2] = $this->createAccounts();

        $this->client->request('POST', '/api/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'sourceAccountId'      => $a1->getId(),
            'destinationAccountId' => $a2->getId(),
            'amount'               => 100.00,
            'referenceId'          => 'TXN-SUCCESS-123'
        ]));

        $this->assertResponseIsSuccessful();

        // 1. Verify the Transfer record exists in the DB
        $transfer = $this->em->getRepository(Transfer::class)
            ->findOneBy(['referenceId' => 'TXN-SUCCESS-123']);

        $this->assertNotNull($transfer, 'Transfer record was not created in the database.');
        $this->assertEquals(100.00, (float) $transfer->getAmount());

        // 2. Refresh accounts from DB to check balances
        $this->em->refresh($a1);
        $this->em->refresh($a2);

        $this->assertEquals('900.00', $a1->getBalance(), 'Source account balance did not decrease.');
        $this->assertEquals('600.00', $a2->getBalance(), 'Destination account balance did not increase.');
    }

    public function testInsufficientBalance(): void
    {
        [$a1, $a2] = $this->createAccounts();

        $this->client->request('POST', '/api/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'sourceAccountId' => $a1->getId(),
            'destinationAccountId' => $a2->getId(),
            'amount' => 999999,
            'referenceId' => 'TXN-2'
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDuplicateReferenceId(): void
    {
        [$a1, $a2] = $this->createAccounts();

        $payload = [
            'sourceAccountId' => $a1->getId(),
            'destinationAccountId' => $a2->getId(),
            'amount' => 50,
            'referenceId' => 'TXN-DUP'
        ];

        // First request
        $this->client->request('POST', '/api/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($payload));

        // Second request (same reference)
        $this->client->request('POST', '/api/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($payload));

        $transfers = $this->em->getRepository(Transfer::class)
            ->findBy(['referenceId' => 'TXN-DUP']);

        $this->assertCount(1, $transfers);
    }

    public function testInvalidSameAccount(): void
    {
        [$a1] = $this->createAccounts();

        $this->client->request('POST', '/api/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'sourceAccountId' => $a1->getId(),
            'destinationAccountId' => $a1->getId(),
            'amount' => 10,
            'referenceId' => 'TXN-INVALID'
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
