<?php

declare(strict_types=1);

final class RecordsController
{
    public function __construct(private Auth $auth) {}

    private function pdo(): PDO
    {
        $user = $this->auth->user();
        $pdo = DB::connect($user['db_path']);
        DB::ensureSchema($pdo);
        return $pdo;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') { return null; }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt) { return null; }
        $errors = DateTime::getLastErrors();
        if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) { return null; }
        return $dt->format('Y-m-d');
    }

    private function parseCents(string $raw): int
    {
        $raw = trim($raw);
        $raw = preg_replace('/\s+/u', '', $raw) ?? '';
        if ($raw === '') { return 0; }
        if (strpos($raw, ',') !== false && strpos($raw, '.') === false) {
            $raw = str_replace(',', '.', $raw);
        }
        $val = (float)$raw;
        return (int)round($val * 100);
    }

    public function handle(): void
    {
        if (!$this->auth->isLoggedIn()) {
            json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        $this->auth->checkCsrf();

        $action = (string)($_POST['action'] ?? '');
        $repo = new RecordRepository();

        try {
            switch ($action) {
                case 'create_record':
                    $date = $this->parseDate((string)($_POST['sale_date'] ?? ''));
                    $desc = trim((string)($_POST['description'] ?? ''));
                    $amountCents = $this->parseCents((string)($_POST['net_amount'] ?? ''));
                    if (!$date) { json(['status' => 'error', 'message' => 'Data wymagana'], 422); }
                    if ($desc === '') { json(['status' => 'error', 'message' => 'Opis wymagany'], 422); }
                    $id = $repo->create($this->pdo(), [
                        'sale_date' => $date,
                        'description' => $desc,
                        'net_amount_cents' => $amountCents,
                        'payment_method' => trim((string)($_POST['payment_method'] ?? '')),
                        'document_no' => trim((string)($_POST['document_no'] ?? '')),
                        'notes' => trim((string)($_POST['notes'] ?? '')),
                    ]);
                    json(['status' => 'success', 'id' => $id]);
                    break;
                case 'edit_record':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $date = $this->parseDate((string)($_POST['sale_date'] ?? ''));
                    $desc = trim((string)($_POST['description'] ?? ''));
                    $amountCents = $this->parseCents((string)($_POST['net_amount'] ?? ''));
                    if (!$date) { json(['status' => 'error', 'message' => 'Data wymagana'], 422); }
                    if ($desc === '') { json(['status' => 'error', 'message' => 'Opis wymagany'], 422); }
                    $repo->update($this->pdo(), $id, [
                        'sale_date' => $date,
                        'description' => $desc,
                        'net_amount_cents' => $amountCents,
                        'payment_method' => trim((string)($_POST['payment_method'] ?? '')),
                        'document_no' => trim((string)($_POST['document_no'] ?? '')),
                        'notes' => trim((string)($_POST['notes'] ?? '')),
                    ]);
                    json(['status' => 'success']);
                    break;
                case 'delete_record':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) { json(['status' => 'error', 'message' => 'ID wymagane'], 422); }
                    $repo->delete($this->pdo(), $id);
                    json(['status' => 'success']);
                    break;
                case 'update_limits':
                    $year = (int)($_POST['year'] ?? 0);
                    if ($year <= 0) { json(['status' => 'error', 'message' => 'Rok wymagany'], 422); }
                    $pdo = $this->pdo();
                    for ($q = 1; $q <= 4; $q++) {
                        $raw = (string)($_POST['limit_q' . $q] ?? '');
                        $repo->upsertLimit($pdo, $year, $q, $this->parseCents($raw));
                    }
                    json(['status' => 'success']);
                    break;
                default:
                    json(['status' => 'error', 'message' => 'Unknown action'], 400);
            }
        } catch (Throwable $e) {
            error_log('ax_records error: ' . $e->getMessage());
            json(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
}
