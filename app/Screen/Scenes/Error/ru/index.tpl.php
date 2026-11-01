<?php

// $data — причина сбоя из index.php. В DEV она помогает разработчику, посетителю не показывается.
$detail = DEV && is_string($data) && $data !== '' ? $data : null;
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Maws\System\Language::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — внутренняя ошибка</title>
</head>
<body>
    <main>
        <h1>500</h1>
        <p>На сервере что-то сломалось. Попробуйте обновить страницу позже.</p>
        <p><a href="/">На главную</a></p>
<?php if ($detail !== null): ?>
        <pre><?= htmlspecialchars($detail) ?></pre>
<?php endif; ?>
    </main>
</body>
</html>
