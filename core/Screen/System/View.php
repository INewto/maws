<?php

namespace Maws\Screen\System;

use Maws\Screen\System\Exception\TemplateMissing;
use Maws\System\Contract\{Effect, Fixture, Gives, Param, Purpose, Returns, Subject, Throws, Token};
use Maws\System\Language;

#[Subject(View::class, 'new', [])]
#[Fixture(files: ['tpl.php' => '<?php print $data["name"];', 'tpl/ru/a.php' => 'ru'])]
#[Purpose('Рендерер: подключает шаблон и возвращает то, что он вывел.')]
class View
{
    #[Purpose('Путь к шаблону языка: <каталог>/<язык>/<файл>; нет перевода — шаблон языка по умолчанию.')]
    #[Param('dir', 'string', 'каталог шаблонов, обычно __DIR__ . "/Templates"')]
    #[Param('file', 'string', 'имя файла внутри каталога языка')]
    #[Returns('string', 'путь к существующему шаблону')]
    #[Throws(TemplateMissing::class, 'шаблона нет ни в языке запроса, ни в языке по умолчанию')]
    #[Effect(Effect::FS_READ)]
    #[Gives([Token::SANDBOX . '/tpl', 'a.php'], Token::SANDBOX . '/tpl/ru/a.php')]
    public function template(string $dir, string $file = 'index.tpl.php'): string
    {
        return Language::file($dir, $file) ?? throw new TemplateMissing($dir . '/' . $file);
    }

    #[Purpose('Подключает шаблон с данными и возвращает его вывод строкой.')]
    #[Param('template', 'string', 'путь к шаблону; подключается как есть')]
    #[Param('data', 'array', 'данные, доступные шаблону как $data')]
    #[Returns('string', 'всё, что шаблон вывел')]
    #[Effect(Effect::FS_READ)]
    #[Gives([Token::SANDBOX . '/tpl.php', ['name' => 'Home']], 'Home')]
    public function render(string $template, array $data): string
    {
        $render = static function (string $__file, array $data): string {
            ob_start();
            require $__file;
            return ob_get_clean();
        };

        return $render($template, $data);
    }
}
