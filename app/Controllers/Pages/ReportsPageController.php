<?php

declare(strict_types=1);

use Latte\Engine;

final class ReportsPageController
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

        // helper
        if (!function_exists('csrfToken')) {
            function csrfToken(): string { global $auth; return $auth->csrfToken(); }
        }

        // prepare projects list similar to /projects
        $repo = new ProjectRepository();
        $projects = $repo->listActive($pdo);
        foreach ($projects as &$p) {
            $secs = $repo->totalSeconds($pdo, (int)$p['id']);
            $p['total'] = $repo->formatHHMM($secs);
            $p['running'] = $repo->isRunning($pdo, (int)$p['id']);
        }
        unset($p);

        // reports list
        $reportRepo = class_exists('ReportRepository') ? new ReportRepository() : null;
        $reports = $reportRepo ? $reportRepo->listAll($pdo) : [];

    $params = [
            'config' => require __DIR__ . '/../../../bootstrap/config.php',
            'me' => $this->auth->user(),
            'presenterPath' => '/reports',
            'projects' => $projects,
            'reports' => $reports,
        ];
    $this->latte->render($this->viewsDir . '/reports/main.latte', $params);
    }

    public function showDetail(int $id): void
    {
        if (!$this->auth->isLoggedIn()) { redirect('/login'); }
        $pdo = $this->pdo();
        $reportRepo = class_exists('ReportRepository') ? new ReportRepository() : null;
        if (!$reportRepo) { http_response_code(500); echo 'Reports module missing'; return; }
        $report = $reportRepo->get($pdo, $id);
        if (!$report) { http_response_code(404); echo 'Report not found'; return; }

    $params = [
            'config' => require __DIR__ . '/../../../bootstrap/config.php',
            'me' => $this->auth->user(),
            'presenterPath' => '/reports/' . $id,
            'report' => $report,
        ];
    $this->latte->render($this->viewsDir . '/reports/show.latte', $params);
    }
}
