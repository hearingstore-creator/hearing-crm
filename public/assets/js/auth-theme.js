(function () {
    'use strict';

    // Show / hide password (works for any .au-toggle button)
    document.querySelectorAll('.au-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-target'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            }
            input.focus();
        });
    });

    // Submit: show spinner, block double submit (re-enabled if user navigates back)
    document.querySelectorAll('form[data-au-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.au-btn');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.setAttribute('data-label', btn.innerHTML);
            btn.innerHTML = '<span class="au-spin" aria-hidden="true"></span><span>' +
                (btn.getAttribute('data-loading') || 'Please wait…') + '</span>';
        });
    });
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('.au-btn[data-label]').forEach(function (btn) {
            btn.innerHTML = btn.getAttribute('data-label');
            btn.disabled = false;
        });
    });

    // Shake the card when the server returned errors
    var shell = document.querySelector('.au-shell');
    if (shell && document.querySelector('.au-alert--error')) {
        setTimeout(function () {
            shell.classList.add('au-shake');
            shell.addEventListener('animationend', function h(ev) {
                if (ev.animationName === 'au-shake') {
                    shell.classList.remove('au-shake');
                    shell.removeEventListener('animationend', h);
                }
            });
        }, 800);
    }

    // Focus the first empty field after the entrance animation (avoids the
    // early autofocus jump that made the first click feel ignored)
    var first = document.querySelector('[data-au-focus]');
    if (first) {
        setTimeout(function () {
            if (document.activeElement === document.body || document.activeElement === null) {
                first.focus({ preventScroll: true });
            }
        }, 650);
    }
})();
