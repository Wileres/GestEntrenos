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
})();
