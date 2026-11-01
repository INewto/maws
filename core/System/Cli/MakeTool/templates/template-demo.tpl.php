<?php

// $data — строки из таблицы инструмента: их прочитал Repository и передал шаблону.
?>
<h1>Инструмент {{Tool}}</h1>

<ul>
<?php foreach ($data as $row): ?>
    <li><?= htmlspecialchars((string) $row['id']) ?> — <?= htmlspecialchars((string) $row['name']) ?></li>
<?php endforeach; ?>
</ul>
