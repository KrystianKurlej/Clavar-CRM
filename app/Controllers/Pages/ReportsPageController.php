<?php

declare(strict_types=1);

use Latte\Engine;

final class ReportsPageController
{
    public function __construct(private Auth $auth, private Engine $latte, private string $viewsDir) {}

    public function show(): void
    {
        if (!$this->auth->isLoggedIn()) { redirect('/login'); }

        // helper
        if (!function_exists('csrfToken')) {
            function csrfToken(): string { global $auth; return $auth->csrfToken(); }
        }

        $params = [
            'config' => require __DIR__ . '/../../../bootstrap/config.php',
            'me' => $this->auth->user(),
            'presenterPath' => '/reports',
        ];
        $this->latte->render($this->viewsDir . '/reports.latte', $params);
    }
}
