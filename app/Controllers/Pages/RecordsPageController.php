<?php

declare(strict_types=1);

use Latte\Engine;

final class RecordsPageController
{
    public function __construct(private Auth $auth, private Engine $latte, private string $viewsDir) {}

    private function pdo(): PDO
    {
        $user = $this->auth->user();
        $pdo = DB::connect($user['db_path']);
        DB::ensureSchema($pdo);
        return $pdo;
    }

    private function quarterFromDate(string $date): int
    {
        $month = (int)date('n', strtotime($date));
        return (int)ceil($month / 3);
    }

    private function quarterLabel(int $year, int $quarter): string
    {
        return 'Q' . $quarter . ' ' . $year;
    }

    private function quarterStart(int $year, int $quarter): DateTimeImmutable
    {
        $month = ($quarter - 1) * 3 + 1;
        return new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    }

    private function quarterEnd(int $year, int $quarter): DateTimeImmutable
    {
        $start = $this->quarterStart($year, $quarter);
        $next = $quarter === 4
            ? new DateTimeImmutable(sprintf('%04d-01-01', $year + 1))
            : new DateTimeImmutable(sprintf('%04d-%02d-01', $year, ($quarter * 3) + 1));
        return $next->modify('-1 day');
    }

    public function show(): void
    {
        if (!$this->auth->isLoggedIn()) { redirect('/login'); }
        $pdo = $this->pdo();

        // helper
        if (!function_exists('csrfToken')) {
            function csrfToken(): string { global $auth; return $auth->csrfToken(); }
        }

        $repo = new RecordRepository();
        $allYears = $repo->availableYears($pdo);
        $currentYear = (int)date('Y');
        if (!in_array($currentYear, $allYears, true)) {
            $allYears[] = $currentYear;
        }
        rsort($allYears);

        $requestedYear = (int)($_GET['year'] ?? 0);
        $selectedYear = $requestedYear > 0 ? $requestedYear : ($allYears[0] ?? $currentYear);
        if (!in_array($selectedYear, $allYears, true)) {
            $allYears[] = $selectedYear;
            rsort($allYears);
        }

        $records = $repo->listByYear($pdo, $selectedYear);
        $limits = $repo->getLimits($pdo, $selectedYear);

        $quarterTotals = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($records as &$r) {
            $year = (int)date('Y', strtotime($r['sale_date']));
            $q = $this->quarterFromDate($r['sale_date']);
            $r['quarter_label'] = $this->quarterLabel($year, $q);
            $quarterTotals[$q] += (int)$r['net_amount_cents'];
        }
        unset($r);

        $summaryRows = [];
        $sumTotals = 0;
        $sumLimits = 0;
        for ($q = 1; $q <= 4; $q++) {
            $total = $quarterTotals[$q] ?? 0;
            $limit = $limits[$q] ?? 0;
            $remaining = max(0, $limit - $total);
            $percent = $limit > 0 ? ($total / $limit * 100) : 0.0;
            if ($limit <= 0) {
                $status = 'BRAK LIMITU';
            } elseif ($total >= $limit) {
                $status = 'PRZEKROCZONY';
            } elseif ($total >= ($limit * 0.8)) {
                $status = 'BLISKO LIMITU';
            } else {
                $status = 'OK';
            }
            $summaryRows[] = [
                'quarter' => $this->quarterLabel($selectedYear, $q),
                'total_cents' => $total,
                'limit_cents' => $limit,
                'remaining_cents' => $remaining,
                'percent' => number_format($percent, 1, ',', ' '),
                'status' => $status,
            ];
            $sumTotals += $total;
            $sumLimits += $limit;
        }
        $summaryTotal = [
            'total_cents' => $sumTotals,
            'limit_cents' => $sumLimits,
            'remaining_cents' => max(0, $sumLimits - $sumTotals),
            'percent' => $sumLimits > 0 ? number_format($sumTotals / $sumLimits * 100, 1, ',', ' ') : '0,0',
            'status' => $sumLimits > 0 && $sumTotals >= $sumLimits ? 'PRZEKROCZONY' : 'BEZPIECZNIE',
        ];

        $today = new DateTimeImmutable('today');
        $isCurrentYear = $selectedYear === (int)$today->format('Y');
        $currentQuarter = $isCurrentYear ? (int)ceil(((int)$today->format('n')) / 3) : 4;
        $quarterStart = $this->quarterStart($selectedYear, $currentQuarter);
        $quarterEnd = $this->quarterEnd($selectedYear, $currentQuarter);

        $ytdTotal = 0;
        $ytdCostOfIncome = 0;
        $yearStart = new DateTimeImmutable(sprintf('%04d-01-01', $selectedYear));
        $yearEnd = new DateTimeImmutable(sprintf('%04d-12-31', $selectedYear));
        $dashboardCutoff = $isCurrentYear ? $today : $yearEnd;

        foreach ($records as $r) {
            $date = new DateTimeImmutable($r['sale_date']);
            $amount = (int)$r['net_amount_cents'];
            $cost = (int)($r['cost_of_income_cents'] ?? 0);
            if ($date >= $yearStart && $date <= $dashboardCutoff) {
                $ytdTotal += $amount;
                $ytdCostOfIncome += $cost;
            }
        }

        $diffToEnd = $dashboardCutoff->diff($quarterEnd);
        $daysToEndQuarter = $diffToEnd->invert ? 0 : (int)$diffToEnd->days;

        $dashboard = [
            'current_quarter' => $this->quarterLabel($selectedYear, $currentQuarter),
            'ytd_total_cents' => $ytdTotal,
            'ytd_kup_cents' => $ytdCostOfIncome,
            'quarter_end_label' => $quarterEnd->format('d.m'),
            'days_to_end_quarter' => $daysToEndQuarter,
        ];

        $params = [
            'config' => require __DIR__ . '/../../../bootstrap/config.php',
            'me' => $this->auth->user(),
            'presenterPath' => '/record',
            'records' => $records,
            'limits' => $limits,
            'summaryRows' => $summaryRows,
            'summaryTotal' => $summaryTotal,
            'selectedYear' => $selectedYear,
            'yearTabs' => $allYears,
            'suggestedNewYear' => max($allYears) + 1,
            'dashboard' => $dashboard,
        ];
        $this->latte->render($this->viewsDir . '/records/main.latte', $params);
    }
}
