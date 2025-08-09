<?php /** @var Auth $auth */ /** @var string|null $error */ ?>
<?php $config = require __DIR__ . '/../bootstrap/config.php'; ?>
<?php ob_start(); ?>
  <h1>Logowanie</h1>
  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/login">
    <input type="hidden" name="_csrf" value="<?= h($auth->csrfToken()) ?>">
    <label>
      E-mail
      <input name="email" type="email" required autocomplete="username">
    </label>
    <label>
      Hasło
      <input name="password" type="password" required autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Zaloguj</button>
  </form>
<?php $content = ob_get_clean(); ?>
<?php $title = 'Logowanie'; include __DIR__ . '/layout.php'; ?>
