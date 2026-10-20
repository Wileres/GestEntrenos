/* Entrenaments JSPM - millores progressives amb JavaScript nativo. */
(function () {
    'use strict';
    document.documentElement.classList.add('js');

    // Menú mòbil
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('menu-principal');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Tanca els menús desplegables en fer clic a fora o prémer Escape
    document.addEventListener('click', function (ev) {
        document.querySelectorAll('details.nav-menu[open]').forEach(function (d) {
            if (!d.contains(ev.target)) { d.removeAttribute('open'); }
        });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            document.querySelectorAll('details.nav-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
        }
    });

    // Confirmació d'accions destructives amb <dialog>
    var dialog = document.getElementById('confirm-dialog');
    var pending = null;
    if (dialog && typeof dialog.showModal === 'function') {
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!form.hasAttribute || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') { return; }
            ev.preventDefault();
            pending = { form: form, submitter: ev.submitter || null };
            document.getElementById('confirm-text').textContent = form.getAttribute('data-confirm');
            dialog.showModal();
        }, true);
        dialog.addEventListener('close', function () {
            if (dialog.returnValue === 'ok' && pending) {
                pending.form.dataset.confirmed = '1';
                if (pending.form.requestSubmit) { pending.form.requestSubmit(pending.submitter); } else { pending.form.submit(); }
            }
            pending = null;
            dialog.returnValue = '';
        });
    } else {
        document.addEventListener('submit', function (ev) {
            var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) { ev.preventDefault(); }
        }, true);
    }

    // Indicador de càrrega en enviar formularis
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (ev.defaultPrevented || !form.hasAttribute || !form.hasAttribute('data-loading')) { return; }
        var btn = ev.submitter;
        if (btn && btn.classList) {
            // Es deshabilita després de l'enviament perquè el valor del botó (name/value) arribi al servidor
            setTimeout(function () { btn.classList.add('is-loading'); btn.disabled = true; }, 0);
        }
        // Evita doble enviament
        if (form.dataset.sending === '1') { ev.preventDefault(); return; }
        form.dataset.sending = '1';
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.disabled = false; });
        document.querySelectorAll('[data-sending]').forEach(function (f) { delete f.dataset.sending; });
    });
    // ---- PWA: Service Worker ----
    var baseMeta = document.querySelector('meta[name="app-base"]');
    var base = baseMeta ? baseMeta.getAttribute('content') : '';
    var secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if ('serviceWorker' in navigator && secure) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(base + '/service-worker.js', { scope: base + '/' }).catch(function () { /* sense PWA, l'aplicació funciona igual */ });
        });
    }

    // ---- PWA: botó "Instal·la l'aplicació" ----
    var installButtons = document.querySelectorAll('[data-install]');
    var deferredPrompt = null;
    var standalone = (window.matchMedia && (window.matchMedia('(display-mode: standalone)').matches || window.matchMedia('(display-mode: fullscreen)').matches))
        || window.navigator.standalone === true;
    var ua = navigator.userAgent || '';
    var isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function setInstallVisible(show) {
        installButtons.forEach(function (b) { b.hidden = !show; });
    }
    if (installButtons.length && !standalone) {
        if (isIos) { setInstallVisible(true); }
        window.addEventListener('beforeinstallprompt', function (ev) {
            ev.preventDefault();
            deferredPrompt = ev;
            setInstallVisible(true);
        });
        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            setInstallVisible(false);
        });
        installButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (deferredPrompt) {
                    var p = deferredPrompt;
                    deferredPrompt = null;
                    p.prompt();
                    if (p.userChoice) {
                        p.userChoice.then(function (c) { if (c.outcome === 'accepted') { setInstallVisible(false); } else if (!isIos) { setInstallVisible(false); } });
                    }
                } else if (isIos) {
                    var d = document.getElementById('ios-install-dialog');
                    if (d && typeof d.showModal === 'function') { d.showModal(); }
                }
            });
        });
    }

    // ---- Notificacions al navegador (Web Push) ----
    var pushBox = document.querySelector('[data-push]');
    if (pushBox) {
        var toggle2 = pushBox.querySelector('[data-push-toggle]');
        var statusEl = pushBox.querySelector('[data-push-status]');
        var unsupported = pushBox.querySelector('[data-push-unsupported]');
        var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && secure;
        if (!supported) {
            unsupported.hidden = false;
            statusEl.hidden = true;
        } else {
            var b64 = function (s) {
                var pad = '='.repeat((4 - s.length % 4) % 4);
                var raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
                var out = new Uint8Array(raw.length);
                for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
                return out;
            };
            var post = function (url, body) {
                return fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': pushBox.dataset.csrf },
                    body: JSON.stringify(body)
                });
            };
            var refresh = function () {
                return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
                    toggle2.hidden = false;
                    if (Notification.permission === 'denied') {
                        statusEl.textContent = 'Les notificacions estan bloquejades en aquest navegador. Activa-les als permisos del lloc.';
                        toggle2.hidden = true;
                    } else if (sub) {
                        statusEl.textContent = 'Activades en aquest dispositiu.';
                        toggle2.textContent = 'Desactiva les notificacions en aquest dispositiu';
                    } else {
                        statusEl.textContent = 'No activades en aquest dispositiu.';
                        toggle2.textContent = 'Activa les notificacions en aquest dispositiu';
                    }
                    return sub;
                });
            };
            refresh();
            toggle2.addEventListener('click', function () {
                toggle2.disabled = true;
                navigator.serviceWorker.ready.then(function (reg) {
                    return reg.pushManager.getSubscription().then(function (sub) {
                        if (sub) {
                            return post(pushBox.dataset.unsubscribeUrl, { endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); });
                        }
                        return Notification.requestPermission().then(function (perm) {
                            if (perm !== 'granted') { return null; }
                            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(pushBox.dataset.vapidKey) }).then(function (s) {
                                return post(pushBox.dataset.subscribeUrl, s.toJSON()).then(function (res) {
                                    if (!res.ok) { return s.unsubscribe().then(function () { statusEl.textContent = 'No s\'ha pogut activar en aquest navegador.'; }); }
                                });
                            });
                        });
                    });
                }).catch(function () { statusEl.textContent = 'No s\'ha pogut canviar l\'estat de les notificacions.'; })
                  .then(function () { toggle2.disabled = false; return refresh(); });
            });
        }
    }

})();
