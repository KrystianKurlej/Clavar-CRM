<?php

declare(strict_types=1);

final class ProjectsController
{
    public function __construct(
        private Auth $auth,
        private array $config,
    ) {}

    private function pdo(): PDO
    {
        $user = $this->auth->user();
        $pdo = DB::connect($user['db_path']);
        DB::ensureSchema($pdo);
        return $pdo;
    }

    public function handle(): void
    {
        if (!$this->auth->isLoggedIn()) {
            json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        $this->auth->checkCsrf();

        $action = (string)($_POST['action'] ?? '');
        $repo = new ProjectRepository();

        try {
            switch ($action) {
                case 'create_project':
                    $name = trim((string)($_POST['name'] ?? ''));
                    if ($name === '') {
                        json(['status' => 'error', 'message' => 'Nazwa wymagana'], 422);
                    }
                    $id = $repo->create($this->pdo(), $name);
                    json(['status' => 'success', 'id' => $id]);
                    break;
                case 'start_project':
                    $pid = (int)($_POST['project_id'] ?? 0);
                    if ($pid <= 0) { json(['status' => 'error', 'message' => 'project_id wymagane'], 422); }
                    $pdo = $this->pdo();
                    $repo->startTimer($pdo, $pid);
                    $secs = $repo->totalSeconds($pdo, $pid);
                    json(['status' => 'success', 'total' => $repo->formatHHMM($secs), 'running' => true]);
                    break;
                case 'stop_project':
                    $pid = (int)($_POST['project_id'] ?? 0);
                    if ($pid <= 0) { json(['status' => 'error', 'message' => 'project_id wymagane'], 422); }
                    $pdo = $this->pdo();
                    $repo->stopTimer($pdo, $pid);
                    $secs = $repo->totalSeconds($pdo, $pid);
                    json(['status' => 'success', 'total' => $repo->formatHHMM($secs), 'running' => false]);
                    break;
                case 'archive_project':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $repo->archive($this->pdo(), $id);
                    json(['status' => 'success']);
                    break;
                case 'delete_project':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $repo->delete($this->pdo(), $id);
                    json(['status' => 'success']);
                    break;
                default:
                    json(['status' => 'error', 'message' => 'Unknown action'], 400);
            }
        } catch (Throwable $e) {
            error_log('ax_projects error: ' . $e->getMessage());
            json(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
}
