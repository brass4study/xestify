<?php

declare(strict_types=1);

namespace Xestify\database\seeders;

use DateTimeImmutable;
use Xestify\database\seeders\support\AbstractGroupSeeder;
use Xestify\database\seeders\support\DateRangeGenerator;
use Xestify\database\seeders\support\FakeDataGenerator;
use Xestify\database\seeders\support\PersonDataGenerator;
use Xestify\database\seeders\support\RandomPrimitives;

/**
 * Seeds the 8 `plugin_entity_data` groups for STORY 10.6, in dependency
 * order: brands/manufacturers/distributors/ophthalmologists/clients
 * (independent) -> orders (-> distributors) -> invoices (-> orders) ->
 * sales (-> clients). Each group is "all or nothing": if it already has
 * rows, its existing ids are loaded instead of inserting, so dependent
 * groups keep working on a partial re-run.
 */
final class CoreEntitySeeder extends AbstractGroupSeeder
{
    private const BRANDS_COUNT = 30;
    private const MANUFACTURERS_COUNT = 15;
    private const DISTRIBUTORS_COUNT = 25;
    private const OPHTHALMOLOGISTS_COUNT = 100;
    private const CLIENTS_COUNT = 200;
    private const ORDERS_COUNT = 300;
    private const SALES_COUNT = 250;
    private const INVOICE_COVERAGE_PERCENT = 90;
    private const INVOICE_PAID_PERCENT = 55;

    private const ORDER_AMOUNT_MIN = 150.0;
    private const ORDER_AMOUNT_MAX = 6000.0;
    private const SALE_AMOUNT_MIN = 40.0;
    private const SALE_AMOUNT_MAX = 900.0;

    private const CLIENT_EMAIL_DOMAIN = 'demo.xestify.local';
    private const DISTRIBUTOR_EMAIL_DOMAIN = 'demo-distribuidores.xestify.local';
    private const OPHTHALMOLOGIST_EMAIL_DOMAIN = 'demo-clinicas.xestify.local';

    /** @var list<string> */
    public array $brandIds = [];

    /** @var list<string> */
    public array $manufacturerIds = [];

    /** @var list<string> */
    public array $distributorIds = [];

    /** @var list<string> */
    public array $ophthalmologistIds = [];

    /** @var list<string> */
    public array $clientIds = [];

    /** @var list<array{id: string, order_date: string, total_amount: float}> */
    private array $orders = [];

    public function run(DateTimeImmutable $now, DateTimeImmutable $rangeStart): void
    {
        $this->seedBrands();
        $this->seedManufacturers();
        $this->seedDistributors();
        $this->seedOphthalmologists();
        $this->seedClients();
        $this->seedOrders($now, $rangeStart);
        $this->seedInvoices($now);
        $this->seedSales($now, $rangeStart);
    }

    private function seedBrands(): void
    {
        $existing = $this->fetchExistingEntityIds('brands');
        if ($existing !== []) {
            $this->brandIds = $existing;
            $this->recordSummary('brands', 'skipped', count($existing));
            return;
        }

        $names = FakeDataGenerator::uniqueBrandNames(self::BRANDS_COUNT);
        $this->brandIds = $this->runInTransaction(function () use ($names): array {
            $stmt = $this->prepareEntityInsert();
            $ids = [];
            foreach ($names as $name) {
                $ids[] = $this->insertEntityRow($stmt, 'brands', ['name' => $name]);
            }
            return $ids;
        });
        $this->recordSummary('brands', 'seeded', count($this->brandIds));
    }

    private function seedManufacturers(): void
    {
        $existing = $this->fetchExistingEntityIds('manufacturers');
        if ($existing !== []) {
            $this->manufacturerIds = $existing;
            $this->recordSummary('manufacturers', 'skipped', count($existing));
            return;
        }

        $names = FakeDataGenerator::uniqueManufacturerNames(self::MANUFACTURERS_COUNT);
        $this->manufacturerIds = $this->runInTransaction(function () use ($names): array {
            $stmt = $this->prepareEntityInsert();
            $ids = [];
            foreach ($names as $name) {
                $ids[] = $this->insertEntityRow($stmt, 'manufacturers', ['name' => $name]);
            }
            return $ids;
        });
        $this->recordSummary('manufacturers', 'seeded', count($this->manufacturerIds));
    }

    private function seedDistributors(): void
    {
        $existing = $this->fetchExistingEntityIds('distributors');
        if ($existing !== []) {
            $this->distributorIds = $existing;
            $this->recordSummary('distributors', 'skipped', count($existing));
            return;
        }

        $this->distributorIds = $this->runInTransaction(function (): array {
            $stmt = $this->prepareEntityInsert();
            $usedEmails = [];
            $ids = [];
            for ($i = 0; $i < self::DISTRIBUTORS_COUNT; $i++) {
                $content = $this->buildDistributorContent($usedEmails);
                $ids[] = $this->insertEntityRow($stmt, 'distributors', $content);
            }
            return $ids;
        });
        $this->recordSummary('distributors', 'seeded', count($this->distributorIds));
    }

    private function seedOphthalmologists(): void
    {
        $existing = $this->fetchExistingEntityIds('ophthalmologists');
        if ($existing !== []) {
            $this->ophthalmologistIds = $existing;
            $this->recordSummary('ophthalmologists', 'skipped', count($existing));
            return;
        }

        $this->ophthalmologistIds = $this->runInTransaction(function (): array {
            $stmt = $this->prepareEntityInsert();
            $usedSurnames = [];
            $usedEmails = [];
            $ids = [];
            for ($i = 0; $i < self::OPHTHALMOLOGISTS_COUNT; $i++) {
                $content = $this->buildOphthalmologistContent($usedSurnames, $usedEmails);
                $ids[] = $this->insertEntityRow($stmt, 'ophthalmologists', $content);
            }
            return $ids;
        });
        $this->recordSummary('ophthalmologists', 'seeded', count($this->ophthalmologistIds));
    }

    private function seedClients(): void
    {
        $existing = $this->fetchExistingEntityIds('clients');
        if ($existing !== []) {
            $this->clientIds = $existing;
            $this->recordSummary('clients', 'skipped', count($existing));
            return;
        }

        $this->clientIds = $this->runInTransaction(function (): array {
            $stmt = $this->prepareEntityInsert();
            $usedSurnames = [];
            $usedEmails = [];
            $ids = [];
            for ($i = 0; $i < self::CLIENTS_COUNT; $i++) {
                $content = $this->buildClientContent($usedSurnames, $usedEmails);
                $ids[] = $this->insertEntityRow($stmt, 'clients', $content);
            }
            return $ids;
        });
        $this->recordSummary('clients', 'seeded', count($this->clientIds));
    }

    private function seedOrders(DateTimeImmutable $now, DateTimeImmutable $rangeStart): void
    {
        $existing = $this->fetchExistingOrders();
        if ($existing !== []) {
            $this->orders = $existing;
            $this->recordSummary('orders', 'skipped', count($existing));
            return;
        }

        $this->orders = $this->runInTransaction(function () use ($now, $rangeStart): array {
            $stmt = $this->prepareEntityInsert();
            $rows = [];
            for ($i = 0; $i < self::ORDERS_COUNT; $i++) {
                $orderDate = DateRangeGenerator::dateBetween($rangeStart, $now);
                $totalAmount = RandomPrimitives::floatBetween(self::ORDER_AMOUNT_MIN, self::ORDER_AMOUNT_MAX);
                $content = [
                    'order_date' => $orderDate,
                    'status' => FakeDataGenerator::orderStatus(),
                    'total_amount' => $totalAmount,
                    'notes' => FakeDataGenerator::noteFor('order'),
                    'id_distributor' => RandomPrimitives::pick($this->distributorIds),
                ];
                $id = $this->insertEntityRow($stmt, 'orders', $content);
                $rows[] = ['id' => $id, 'order_date' => $orderDate, 'total_amount' => $totalAmount];
            }
            return $rows;
        });
        $this->recordSummary('orders', 'seeded', count($this->orders));
    }

    private function seedInvoices(DateTimeImmutable $now): void
    {
        $existingInvoices = $this->fetchExistingEntityIds('invoices');
        if ($existingInvoices !== []) {
            $this->recordSummary('invoices', 'skipped', count($existingInvoices));
            return;
        }

        $invoiceCount = (int) round(count($this->orders) * self::INVOICE_COVERAGE_PERCENT / 100);
        $ordersToInvoice = $this->orders;
        shuffle($ordersToInvoice);
        $ordersToInvoice = array_slice($ordersToInvoice, 0, $invoiceCount);

        $inserted = $this->runInTransaction(function () use ($ordersToInvoice, $now): int {
            $stmt = $this->prepareEntityInsert();
            $sequence = 0;
            foreach ($ordersToInvoice as $order) {
                $sequence++;
                $content = $this->buildInvoiceContent($order, $sequence, $now);
                $this->insertEntityRow($stmt, 'invoices', $content);
            }
            return $sequence;
        });
        $this->recordSummary('invoices', 'seeded', $inserted);
    }

    private function seedSales(DateTimeImmutable $now, DateTimeImmutable $rangeStart): void
    {
        $existing = $this->fetchExistingEntityIds('sales');
        if ($existing !== []) {
            $this->recordSummary('sales', 'skipped', count($existing));
            return;
        }

        $inserted = $this->runInTransaction(function () use ($now, $rangeStart): int {
            $stmt = $this->prepareEntityInsert();
            for ($i = 0; $i < self::SALES_COUNT; $i++) {
                $content = [
                    'order_date' => DateRangeGenerator::dateBetween($rangeStart, $now),
                    'status' => FakeDataGenerator::orderStatus(),
                    'total_amount' => RandomPrimitives::floatBetween(self::SALE_AMOUNT_MIN, self::SALE_AMOUNT_MAX),
                    'notes' => FakeDataGenerator::noteFor('sale'),
                    'id_client' => RandomPrimitives::pick($this->clientIds),
                ];
                $this->insertEntityRow($stmt, 'sales', $content);
            }
            return self::SALES_COUNT;
        });
        $this->recordSummary('sales', 'seeded', $inserted);
    }

    /**
     * @param array<string, bool> $usedEmails
     * @return array<string, mixed>
     */
    private function buildDistributorContent(array &$usedEmails): array
    {
        $name = FakeDataGenerator::companyName();
        [$town, $county, $postalPrefix] = FakeDataGenerator::townRecord();

        return [
            'name' => $name,
            'legal_name' => FakeDataGenerator::legalName($name),
            'identity_document_number' => PersonDataGenerator::randomDni(),
            'address' => FakeDataGenerator::streetAddress(),
            'town' => $town,
            'county' => $county,
            'postal_code' => FakeDataGenerator::postalCode($postalPrefix),
            'phone' => FakeDataGenerator::landlinePhone(),
            'phone_orders' => FakeDataGenerator::landlinePhone(),
            'fax' => FakeDataGenerator::faxNumber(),
            'web' => 'https://www.' . FakeDataGenerator::webDomain($name),
            'mail' => PersonDataGenerator::uniqueEmailFromCompany($name, self::DISTRIBUTOR_EMAIL_DOMAIN, $usedEmails),
            'client_code' => FakeDataGenerator::clientCode(),
            'contact' => PersonDataGenerator::firstName() . ' ' . PersonDataGenerator::singleSurname(),
            'phone_mobile' => FakeDataGenerator::mobilePhone(),
            'notes' => FakeDataGenerator::noteFor('distributor'),
        ];
    }

    /**
     * @param array<string, bool> $usedSurnames
     * @param array<string, bool> $usedEmails
     * @return array<string, mixed>
     */
    private function buildOphthalmologistContent(array &$usedSurnames, array &$usedEmails): array
    {
        $name = PersonDataGenerator::firstName();
        $surnames = PersonDataGenerator::uniqueSurnames($usedSurnames);
        [$town, $county, $postalPrefix] = FakeDataGenerator::townRecord();

        return [
            'name' => $name,
            'type' => FakeDataGenerator::ophthalmologistType(),
            'license_number' => FakeDataGenerator::ophthalmologistNumero(),
            'surnames' => $surnames,
            'identity_document_number' => PersonDataGenerator::randomDni(),
            'address' => FakeDataGenerator::streetAddress(),
            'town' => $town,
            'county' => $county,
            'postal_code' => FakeDataGenerator::postalCode($postalPrefix),
            'mail' => PersonDataGenerator::uniqueEmail($name, $surnames, self::OPHTHALMOLOGIST_EMAIL_DOMAIN, $usedEmails),
            'phone' => FakeDataGenerator::landlinePhone(),
            'phone_mobile' => FakeDataGenerator::mobilePhone(),
            'notes' => FakeDataGenerator::noteFor('ophthalmologist'),
        ];
    }

    /**
     * @param array<string, bool> $usedSurnames
     * @param array<string, bool> $usedEmails
     * @return array<string, mixed>
     */
    private function buildClientContent(array &$usedSurnames, array &$usedEmails): array
    {
        $name = PersonDataGenerator::firstName();
        $surnames = PersonDataGenerator::uniqueSurnames($usedSurnames);
        [$town, $county, $postalPrefix] = FakeDataGenerator::townRecord();

        return [
            'name' => $name,
            'surnames' => $surnames,
            'identity_document_number' => PersonDataGenerator::randomDni(),
            'address' => FakeDataGenerator::streetAddress(),
            'town' => $town,
            'county' => $county,
            'postal_code' => FakeDataGenerator::postalCode($postalPrefix),
            'mail' => PersonDataGenerator::uniqueEmail($name, $surnames, self::CLIENT_EMAIL_DOMAIN, $usedEmails),
            'phone' => FakeDataGenerator::landlinePhone(),
            'phone_mobile' => FakeDataGenerator::mobilePhone(),
            'notes' => FakeDataGenerator::noteFor('client'),
        ];
    }

    /**
     * @param array{id: string, order_date: string, total_amount: float} $order
     * @return array<string, mixed>
     */
    private function buildInvoiceContent(array $order, int $sequence, DateTimeImmutable $now): array
    {
        $orderDate = new DateTimeImmutable($order['order_date']);

        return [
            'invoice_number' => sprintf('F-%06d', $sequence),
            'issue_date' => DateRangeGenerator::dateBetween($orderDate, $now),
            'amount' => $order['total_amount'],
            'payment_status' => RandomPrimitives::boolWithProbability(self::INVOICE_PAID_PERCENT) ? 'pagada' : 'pendiente',
            'id_order' => $order['id'],
        ];
    }

    /**
     * @return list<array{id: string, order_date: string, total_amount: float}>
     */
    private function fetchExistingOrders(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id,
                    content->>'order_date' AS order_date,
                    (content->>'total_amount')::numeric AS total_amount
               FROM plugin_entity_data
              WHERE entity_slug = 'orders' AND deleted_at IS NULL
              ORDER BY created_at ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (string) $row['id'],
                'order_date' => (string) $row['order_date'],
                'total_amount' => (float) $row['total_amount'],
            ];
        }

        return $result;
    }
}
