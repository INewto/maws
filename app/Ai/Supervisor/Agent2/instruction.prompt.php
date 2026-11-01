<?php

$prompt = <<< END
<instruction>
Скажи bye один раз.
Для выполнения используй этот инструмент:

{$toolsListXml}

Инструменты нужно вызывать строго по одному за вызов
</instruction>
END;
