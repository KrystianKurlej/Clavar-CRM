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
}
