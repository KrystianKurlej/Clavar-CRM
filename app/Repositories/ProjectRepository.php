<?php

declare(strict_types=1);

class ProjectRepository
{
    public function listActive(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, name, archived, created_at FROM projects WHERE archived = 0 ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(PDO $pdo, string $name): int
    {
        $stmt = $pdo->prepare('INSERT INTO projects(name) VALUES(:name)');
        $stmt->execute([':name' => $name]);
        return (int)$pdo->lastInsertId();
    }

    public function archive(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('UPDATE projects SET archived = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function startTimer(PDO $pdo, int $projectId): void
    {
        // ensure no active timer exists for this project
        $active = $pdo->prepare('SELECT id FROM project_time_entries WHERE project_id = :pid AND stopped_at IS NULL LIMIT 1');
        $active->execute([':pid' => $projectId]);
        if ($active->fetchColumn()) {
            return; // idempotent start
        }
        $stmt = $pdo->prepare('INSERT INTO project_time_entries(project_id) VALUES(:pid)');
        $stmt->execute([':pid' => $projectId]);
    }

    public function stopTimer(PDO $pdo, int $projectId): void
    {
        // stop the latest active timer
        $stmt = $pdo->prepare('UPDATE project_time_entries SET stopped_at = CURRENT_TIMESTAMP WHERE project_id = :pid AND stopped_at IS NULL');
        $stmt->execute([':pid' => $projectId]);
    }

    public function totalSeconds(PDO $pdo, int $projectId): int
    {
        // sum closed intervals
        $sqlClosed = 'SELECT COALESCE(SUM(strftime("%s", stopped_at) - strftime("%s", started_at)), 0) AS secs FROM project_time_entries WHERE project_id = :pid AND stopped_at IS NOT NULL';
        $stmt = $pdo->prepare($sqlClosed);
        $stmt->execute([':pid' => $projectId]);
        $closed = (int)$stmt->fetchColumn();

        // add active interval if exists
        $sqlOpen = 'SELECT started_at FROM project_time_entries WHERE project_id = :pid AND stopped_at IS NULL ORDER BY id DESC LIMIT 1';
        $stmt2 = $pdo->prepare($sqlOpen);
        $stmt2->execute([':pid' => $projectId]);
        $started = $stmt2->fetchColumn();
        if ($started) {
            $stmt3 = $pdo->query('SELECT strftime("%s", "now")');
            $now = (int)$stmt3->fetchColumn();
            $stmt4 = $pdo->prepare('SELECT strftime("%s", :started)');
            $stmt4->execute([':started' => $started]);
            $st = (int)$stmt4->fetchColumn();
            $closed += max(0, $now - $st);
        }
    // add base_seconds from projects
    $stmt5 = $pdo->prepare('SELECT base_seconds FROM projects WHERE id = :pid');
    $stmt5->execute([':pid' => $projectId]);
    $base = (int)$stmt5->fetchColumn();
    return $closed + $base;
    }

    public function formatHHMM(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%02dh %02dmin', $hours, $minutes);
    }

    public function isRunning(PDO $pdo, int $projectId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM project_time_entries WHERE project_id = :pid AND stopped_at IS NULL LIMIT 1');
        $stmt->execute([':pid' => $projectId]);
        return (bool)$stmt->fetchColumn();
    }

    public function resetAndSetBaseTime(PDO $pdo, int $projectId, int $baseSeconds): void
    {
        // wipe all time entries
        $del = $pdo->prepare('DELETE FROM project_time_entries WHERE project_id = :pid');
        $del->execute([':pid' => $projectId]);
        // set base time
        $upd = $pdo->prepare('UPDATE projects SET base_seconds = :bs WHERE id = :pid');
        $upd->execute([':bs' => max(0, $baseSeconds), ':pid' => $projectId]);
    }

    public function updateName(PDO $pdo, int $projectId, string $name): void
    {
        $stmt = $pdo->prepare('UPDATE projects SET name = :name WHERE id = :pid');
        $stmt->execute([':name' => $name, ':pid' => $projectId]);
    }
}
