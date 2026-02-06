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
    $reportRepo = class_exists('ReportRepository') ? new ReportRepository() : null;

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
                    json(['status' => 'success', 'archived' => 1]);
                    break;
                case 'unarchive_project':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $repo->unarchive($this->pdo(), $id);
                    json(['status' => 'success', 'archived' => 0]);
                    break;
                case 'delete_project':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $repo->delete($this->pdo(), $id);
                    json(['status' => 'success']);
                    break;
                case 'create_report':
                    if (!$reportRepo) { json(['status' => 'error', 'message' => 'Server missing reports module'], 500); }
                    $costRaw = trim((string)($_POST['cost_per_hour'] ?? ''));
                    // Accept formats like "120", "120,50", "120.50", with spaces
                    $costRaw = str_replace([' ', ' '], '', $costRaw);
                    if (strpos($costRaw, ',') !== false && strpos($costRaw, '.') === false) {
                        $costRaw = str_replace(',', '.', $costRaw);
                    }
                    $costPerHour = $costRaw !== '' ? (float)$costRaw : 0.0;
                    // projects[] from multipart
                    $projectIds = $_POST['projects'] ?? [];
                    if (!is_array($projectIds)) { $projectIds = [$projectIds]; }
                    $pdo = $this->pdo();
                    $reportId = $reportRepo->create($pdo, (int)round($costPerHour * 100), array_map('intval', $projectIds));
                    json(['status' => 'success', 'id' => $reportId, 'url' => '/reports/' . $reportId]);
                    break;
                case 'edit_project':
                    $pid = (int)($_POST['id'] ?? 0);
                    if ($pid <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $name = trim((string)($_POST['name'] ?? ''));
                    $hours = (int)($_POST['hours'] ?? 0);
                    $minutes = (int)($_POST['minutes'] ?? 0);
                    $baseSeconds = max(0, $hours) * 3600 + max(0, min(59, $minutes)) * 60;
                    $pdo = $this->pdo();
                    $repo->resetAndSetBaseTime($pdo, $pid, $baseSeconds);
                    if ($name !== '') { $repo->updateName($pdo, $pid, $name); }
                    $secs = $repo->totalSeconds($pdo, $pid);
                    $running = $repo->isRunning($pdo, $pid);
                    json(['status' => 'success', 'total' => $repo->formatHHMM($secs), 'running' => $running]);
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
