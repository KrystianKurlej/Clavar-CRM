<?php

declare(strict_types=1);

class ReportRepository
{
    public function create(PDO $pdo, int $costPerHourCents, array $projectIds): int
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO reports(cost_per_hour_cents) VALUES(:cph)');
            $stmt->execute([':cph' => max(0, $costPerHourCents)]);
            $reportId = (int)$pdo->lastInsertId();
            if ($projectIds) {
                $ins = $pdo->prepare('INSERT OR IGNORE INTO report_projects(report_id, project_id) VALUES(:rid, :pid)');
                foreach ($projectIds as $pid) {
                    $ins->execute([':rid' => $reportId, ':pid' => (int)$pid]);
                }
            }
            $pdo->commit();
            return $reportId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function listAll(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT id, cost_per_hour_cents, created_at FROM reports ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['title'] = date('Y-m-d H:i', strtotime($r['created_at']));
            $r['url'] = '/reports/' . $r['id'];
        }
        unset($r);
        return $rows;
    }

    public function get(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, cost_per_hour_cents, created_at FROM reports WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) return null;
        $row['title'] = date('Y-m-d H:i', strtotime($row['created_at']));
        $row['url'] = '/reports/' . $row['id'];
        // projects list
        $stmt2 = $pdo->prepare('SELECT p.id, p.name FROM report_projects rp JOIN projects p ON p.id = rp.project_id WHERE rp.report_id = :id ORDER BY p.name');
        $stmt2->execute([':id' => $id]);
        $row['projects'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }
}
