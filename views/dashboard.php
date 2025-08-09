<?php /** @var Auth $auth */ ?>
<?php $config = require __DIR__ . '/../bootstrap/config.php'; ?>
<?php ob_start(); ?>
  <h1>Witaj!</h1>
  <p>Jesteś zalogowany jako <strong><?= h($auth->user()['email'] ?? '') ?></strong>.</p>
  <p>Tu wyląduje dashboard i routing do modułów (projekty, czas, raporty). Na razie placeholder.</p>
<?php $content = ob_get_clean(); ?>
<?php $title = 'Dashboard'; include __DIR__ . '/layout.php'; ?>
