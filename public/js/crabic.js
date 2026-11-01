/**
 * Крабик: трекер поведения для форм MAWS.
 *
 * Пишет действия пачками примерно по три секунды на тот же адрес, с которого пришла страница.
 * Сервер держит три последние пачки в сессии, а на отправке формы получает ещё и сводку текущей
 * пачки — в скрытом поле crabic. Пустое поле сервер считает ботом, поэтому скрипт обязан
 * заполнить его до того, как форма уйдёт.
 *
 * Форма помечается атрибутом data-crabic, поле — обычный input name="crabic". Больше ничего
 * от разметки не требуется: картинку-метку скрипт ставит сам, а выключается она атрибутом
 * data-img="false" на его собственном теге.
 *
 * Ошибка в этом файле не должна мешать отправке: всё, что может упасть, обёрнуто, а поле
 * остаётся пустым — сервер откажет и покажет причину.
 */
(function () {
    'use strict';

    // Метка «скрипт дошёл»: картинка встаёт ровно туда, где стоит тег скрипта. Её нет, если
    // JavaScript не выполнился, — поэтому по ней видно, что страница проверяется, а не только
    // выглядит проверяемой. Стоит до поиска формы: метка принадлежит скрипту, а не форме.
    (function mark() {
        var tag = document.currentScript || document.querySelector('script[src$="/js/crabic.js"]');

        if (!tag) {
            return;
        }

        // Показ картинки: data-img="false" выключает, "true" и отсутствие атрибута — включают.
        // Страница без места под метку остаётся страницей под защитой: картинка — удобство,
        // а не условие проверки.
        var img = tag.getAttribute('data-img');

        if (img !== null && img.toLowerCase() === 'false') {
            return;
        }

        var host = tag.parentNode;
        var next = tag.nextSibling;

        // Тег в <head> — картинку туда класть нельзя, она там не отрисуется.
        if (host === document.head) {
            host = document.body;
            next = null;
        }

        if (!host) {
            return;
        }

        var crab = document.createElement('img');

        crab.src   = '/images/maws/crabic.png';
        crab.alt   = '';
        crab.title = 'Просто крабик, не обращайте внимания';

        host.insertBefore(crab, next);
    }());

    var form = document.querySelector('form[data-crabic]');

    if (!form) {
        return;
    }

    var field = form.querySelector('input[name="crabic"]');

    if (!field) {
        return;
    }

    var url = form.getAttribute('action') || window.location.href;

    // Своя дорожка на каждую загрузку страницы: две вкладки с формой не должны мешать друг другу,
    // а сервер начинает дорожку заново на этой отрисовке.
    var id = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);

    var PERIOD = 3000;   // как часто пачка уходит на сервер
    var SAMPLE = 40;     // как часто записывается точка, миллисекунды
    var LIMIT = 240;     // больше сервер не примет

    var t0 = now();
    var pts = [];
    var events = 0;
    var keys = 0;
    var distinct = {};
    var lastSample = -1;
    var closed = false;

    function now() {
        return window.performance && window.performance.now ? window.performance.now() : Date.now();
    }

    function sample(x, y, force) {
        var t = Math.round(now() - t0);

        if (pts.length >= LIMIT || (!force && t - lastSample < SAMPLE)) {
            return;
        }

        lastSample = t;
        pts.push([t, Math.round(x), Math.round(y)]);
    }

    document.addEventListener('mousemove', function (event) {
        events++;
        sample(event.clientX, event.clientY);
    }, {passive: true});

    // Клик, тап и колесо — тоже геометрия: это события с координатами, а клик ещё и единственное,
    // без которого форму не отправить. Выборка принудительная: фильтр по времени иначе съест
    // именно клик, потому что он идёт следом за движением к кнопке.
    function hit(event) {
        events++;
        sample(event.clientX, event.clientY, true);
    }

    document.addEventListener('click', hit, {passive: true});
    document.addEventListener('wheel', hit, {passive: true});

    document.addEventListener('touchstart', function (event) {
        var touch = event.touches && event.touches[0];

        if (!touch) {
            return;
        }

        events++;
        sample(touch.clientX, touch.clientY, true);
    }, {passive: true});

    document.addEventListener('touchmove', function (event) {
        var touch = event.touches && event.touches[0];

        if (!touch) {
            return;
        }

        events++;
        sample(touch.clientX, touch.clientY);
    }, {passive: true});

    document.addEventListener('keydown', function (event) {
        keys++;
        distinct[event.key || event.keyCode || '?'] = 1;
    }, true);

    // Пачка собирается и сразу начинается следующая: окно t0…t0+dt не должно накладываться,
    // иначе сервер увидит две пачки, прожитые одновременно.
    function take() {
        var batch = {
            id: id,
            t0: Math.round(t0),
            dt: Math.max(1, Math.round(now() - t0)),
            w: window.innerWidth,
            h: window.innerHeight,
            events: events,
            keys: keys,
            distinct: Object.keys(distinct).length,
            pts: pts
        };

        t0 = now();
        pts = [];
        events = 0;
        keys = 0;
        distinct = {};
        lastSample = -1;

        return batch;
    }

    function send() {
        if (closed) {
            return;
        }

        try {
            fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(take()),
                keepalive: true,
                credentials: 'same-origin'
            });
        } catch (error) {
            // Сеть отвалилась — пачка пропала. Кольцо на сервере останется короче, и это ничего
            // не ломает: приговор считается по сумме заявленного времени, а не по числу звеньев.
        }
    }

    var timer = window.setInterval(send, PERIOD);

    // Сводка уходит полем, а не запросом: к моменту отправки форма уже собирается браузером,
    // и единственный способ попасть в неё — записать значение до того, как это случится.
    form.addEventListener('submit', function () {
        closed = true;
        window.clearInterval(timer);

        try {
            field.value = JSON.stringify(take());
        } catch (error) {
            field.value = '';
        }
    }, true);
}());
