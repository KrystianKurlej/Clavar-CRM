<?php /** @var Auth $auth */ ?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title ?? 'Simple CRM') ?></title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: #f7f7f8; color: #222; }
    header, footer { background: #fff; border-bottom: 1px solid #eee; }
    header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; }
    main { max-width: 720px; margin: 24px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    a { color: #2563eb; text-decoration: none; }
    .btn { display: inline-block; padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; background: #f3f4f6; }
    form { display: grid; gap: 12px; }
    input, select, textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
    .error { background: #fee2e2; color: #991b1b; padding: 8px 12px; border-radius: 6px; border: 1px solid #fecaca; }
  </style>
</head>
<body>
  <header>
    <div><strong><?= h($config['app_name'] ?? 'Simple CRM') ?></strong></div>
    <nav>
      <?php if (isset($auth) && $auth->isLoggedIn()): ?>
        <form method="post" action="/logout" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= h($auth->csrfToken()) ?>">
          <button class="btn" type="submit">Wyloguj (<?= h($auth->user()['email'] ?? '') ?>)</button>
        </form>
      <?php endif; ?>
    </nav>
  </header>
  <main>
    <?php if (!empty($content)) echo $content; ?>
  </main>
  <footer style="text-align:center; padding: 16px; color:#666">&copy; <?= date('Y') ?> Simple CRM</footer>
</body>
</html>
