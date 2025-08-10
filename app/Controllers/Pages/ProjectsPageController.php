<?php

declare(strict_types=1);

use Latte\Engine;

final class ProjectsPageController
{
    public function __construct(private Auth $auth, private Engine $latte, private string $viewsDir) {}

    private function pdo(): PDO
    {
        $user = $this->auth->user();
        $pdo = DB::connect($user['db_path']);
        DB::ensureSchema($pdo);
        return $pdo;
    }

    public function show(): void
    {
        if (!$this->auth->isLoggedIn()) { redirect('/login'); }
        $pdo = $this->pdo();
        $repo = new ProjectRepository();
        $projects = $repo->listActive($pdo);
        foreach ($projects as &$p) {
            $secs = $repo->totalSeconds($pdo, (int)$p['id']);
            $p['total'] = $repo->formatHHMM($secs);
            $p['running'] = $repo->isRunning($pdo, (int)$p['id']);
        }
        unset($p);
        // helper
        if (!function_exists('csrfToken')) {
            function csrfToken(): string { global $auth; return $auth->csrfToken(); }
        }
    $params = [
            'config' => require __DIR__ . '/../../../bootstrap/config.php',
            'me' => $this->auth->user(),
            'presenterPath' => '/projects',
            'projects' => $projects,
        ];
    $this->latte->render($this->viewsDir . '/projects/main.latte', $params);
    }
}
