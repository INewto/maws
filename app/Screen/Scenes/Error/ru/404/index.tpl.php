<?php

// $data — причина сбоя из index.php. В DEV она помогает разработчику, посетителю не показывается.
$detail = DEV && is_string($data) && $data !== '' ? $data : null;
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Maws\System\Language::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — страница не найдена</title>
</head>
<body>
    <main>
        <h1>404</h1>
        <p>Страница не найдена: такого адреса на сайте нет.</p>
        <p><a href="/">На главную</a></p>
<?php if ($detail !== null): ?>
        <p><small><?= htmlspecialchars($detail) ?></small></p>
<?php endif; ?>
    </main>
</body>
</html>
