<?php

use Maws\System\Exception\MawsException;
use Maws\System\Exception\MawsMisuse;
use Maws\System\Exception\ResponseSent;
use Maws\System\Log\Log;
use Maws\System\Session\Session;
use Maws\Man\Man;
use Maws\Screen\Screen;
use Maws\World\World;

require __DIR__  . '/../app/config.php';
require __DIR__  . '/../vendor/autoload.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");

// HSTS только по https: по http он бессмыслен, а браузер запомнил бы «только https» для домена,
// который ещё не умеет https, и закрыл бы к нему доступ. includeSubDomains не ставим: поддомены без
// TLS — решение сайта, а не фреймворка, и отменить это решение браузер не даст.
if (Session::overHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}

error_reporting(E_ALL);
ini_set('display_errors', DEV ? '1' : '0');

$screen = new Screen();

$url = $_GET['way'] ?? '';

try {
    Session::open();

    $world = new World();
    $man   = new Man($world, $screen);
    $man->body->head->getWorkspace($url);
    $man->body->legs->walk($man->body->head->currentPlace->way());

    $data = $man->body->head->initWorkspace();
    $man->body->hands->useScreenInterface($man->body->head->currentPlace->way(), 'showPage', $data);
} catch (ResponseSent $e) {
} catch (MawsException $e) {

    http_response_code($e->status());

    $text = DEV ? $e->getMessage() : null;

    if (isset($man)) {
        $man->body->hands->useScreenErrorInterface($e->status(), $text);
    } else {
        $screen->screenErrorInterface($e->status(), $text);
    }
} catch (MawsMisuse $e) {

    http_response_code(500);
    $text = DEV ? $e->getMessage() : null;
    Log::write($e, LOGS_FILE);

    if (isset($man)) {
        $man->body->hands->useScreenErrorInterface(500, $text);
    } else {
        $screen->screenErrorInterface(500, $text);
    }
} catch (Throwable $e) {

    http_response_code(500);
    $text = DEV ? $e->getMessage() : null;
    Log::write($e, LOGS_FILE);

    if (isset($man)) {
        $man->body->hands->useScreenErrorInterface(500, $text);
    } else {
        $screen->screenErrorInterface(500, $text);
    }
}
