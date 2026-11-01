<?php
/**
 * Сцена Default. $data — разметка места, $meta — шапка страницы из раздела:
 * плоский список «имя колонки → значение». Пустые значения печатаются как есть —
 * что показать, а что пропустить, решает шаблон, а не ядро.
 */
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Maws\System\Language::current()) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($meta['title'] ?? '') ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description'] ?? '') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($meta['keywords'] ?? '') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta['og_title'] ?? '') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta['og_description'] ?? '') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($meta['og_url'] ?? '') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($meta['og_type'] ?? '') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($meta['og_site_name'] ?? '') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($meta['og_image'] ?? '') ?>">
    <meta property="og:image:width" content="<?= htmlspecialchars($meta['og_image_width'] ?? '') ?>">
    <meta property="og:image:height" content="<?= htmlspecialchars($meta['og_image_height'] ?? '') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($meta['og_image_alt'] ?? '') ?>">
</head>
<body>
<?= $data ?>
</body>
</html>