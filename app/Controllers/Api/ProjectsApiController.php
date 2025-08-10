<?php

declare(strict_types=1);

final class ProjectsApiController
{
    public function __construct(private Auth $auth) {}

    private function pdo(): PDO
    {
        $user = $this->auth->user();
        $pdo = DB::connect($user['db_path']);
        DB::ensureSchema($pdo);
        return $pdo;
    }

    public function list(): void
    {
        if (!$this->auth->isLoggedIn()) { json(['ok' => false, 'error' => 'Unauthorized'], 401); }
        $pdo = $this->pdo();
        $repo = new ProjectRepository();
        $rows = $repo->listActive($pdo);
        foreach ($rows as &$r) {
            $r['running'] = $repo->isRunning($pdo, (int)$r['id']);
            $secs = $repo->totalSeconds($pdo, (int)$r['id']);
            $r['total'] = $repo->formatHHMM($secs);
        }
        unset($r);
        json(['ok' => true, 'projects' => $rows]);
    }

    public function create(): void
    {
        if (!$this->auth->isLoggedIn()) { json(['ok' => false, 'error' => 'Unauthorized'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $_POST['_csrf'] = $input['_csrf'] ?? '';
        $this->auth->checkCsrf();
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') { json(['ok' => false, 'error' => 'Name required'], 422); }
        $id = (new ProjectRepository())->create($this->pdo(), $name);
        json(['ok' => true, 'id' => $id]);
    }

    public function update(int $id): void
    {
        if (!$this->auth->isLoggedIn()) { json(['ok' => false, 'error' => 'Unauthorized'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $_POST['_csrf'] = $input['_csrf'] ?? '';
        $this->auth->checkCsrf();
        $fields = [];
        $params = [':id' => $id];
        if (array_key_exists('name', $input)) { $fields[] = 'name = :name'; $params[':name'] = trim((string)$input['name']); }
        if (array_key_exists('archived', $input)) { $fields[] = 'archived = :archived'; $params[':archived'] = (int)!!$input['archived']; }
        if (!$fields) { json(['ok' => false, 'error' => 'No changes'], 400); }
        $pdo = $this->pdo();
        $sql = 'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json(['ok' => true]);
    }

    public function delete(int $id): void
    {
        if (!$this->auth->isLoggedIn()) { json(['ok' => false, 'error' => 'Unauthorized'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $_POST['_csrf'] = $input['_csrf'] ?? '';
        $this->auth->checkCsrf();
        (new ProjectRepository())->delete($this->pdo(), $id);
        json(['ok' => true]);
    }
}
