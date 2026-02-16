<?php

declare(strict_types=1);

final class RecordRepository
{
    public function listAll(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, sale_date, description, net_amount_cents, payment_method, document_no, notes, created_at FROM sales_records ORDER BY sale_date DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO sales_records(sale_date, description, net_amount_cents, payment_method, document_no, notes)
             VALUES(:sale_date, :description, :net_amount_cents, :payment_method, :document_no, :notes)'
        );
        $stmt->execute([
            ':sale_date' => $data['sale_date'],
            ':description' => $data['description'],
            ':net_amount_cents' => $data['net_amount_cents'],
            ':payment_method' => $data['payment_method'] ?? null,
            ':document_no' => $data['document_no'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE sales_records
             SET sale_date = :sale_date,
                 description = :description,
                 net_amount_cents = :net_amount_cents,
                 payment_method = :payment_method,
                 document_no = :document_no,
                 notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            ':sale_date' => $data['sale_date'],
            ':description' => $data['description'],
            ':net_amount_cents' => $data['net_amount_cents'],
            ':payment_method' => $data['payment_method'] ?? null,
            ':document_no' => $data['document_no'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':id' => $id,
        ]);
    }

    public function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM sales_records WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function getLimits(PDO $pdo, int $year): array
    {
        $stmt = $pdo->prepare('SELECT quarter, limit_cents FROM record_limits WHERE year = :year');
        $stmt->execute([':year' => $year]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $limits = [];
        foreach ($rows as $row) {
            $limits[(int)$row['quarter']] = (int)$row['limit_cents'];
        }
        return $limits;
    }

    public function upsertLimit(PDO $pdo, int $year, int $quarter, int $limitCents): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO record_limits(year, quarter, limit_cents)
             VALUES(:year, :quarter, :limit_cents)
             ON CONFLICT(year, quarter) DO UPDATE SET limit_cents = excluded.limit_cents'
        );
        $stmt->execute([
            ':year' => $year,
            ':quarter' => $quarter,
            ':limit_cents' => max(0, $limitCents),
        ]);
    }

    public function maxYear(PDO $pdo): ?int
    {
        $stmt = $pdo->query('SELECT MAX(CAST(strftime("%Y", sale_date) AS INTEGER)) FROM sales_records');
        $year = $stmt->fetchColumn();
        return $year ? (int)$year : null;
    }
}
