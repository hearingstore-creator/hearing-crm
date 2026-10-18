<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Book a Free Consultation – {{ $company }}</title>
    <meta name="description" content="Tell us how we can help with your hearing. Our specialist will call you back shortly.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @if ($turnstile)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <style>
        :root {
            /* Green = calm, positive; Hearing Store red (#dd3333) is kept as a thin brand accent. */
            --brand: #059669; --brand-2: #10b981; --accent: #14b8a6; --site-red: #dd3333;
            --ring: rgba(5, 150, 105, .14);
            --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --bg: #ecfdf5; --card: #ffffff;
            --ok: #10b981; --err: #dc2626;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'Inter', 'Hind Siliguri', system-ui, sans-serif; color: var(--text); -webkit-font-smoothing: antialiased;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 14px;
            background: radial-gradient(1200px 600px at 10% -10%, rgba(16, 185, 129, .20), transparent 60%),
                        radial-gradient(900px 500px at 110% 110%, rgba(20, 184, 166, .18), transparent 60%), var(--bg);
        }
        body.is-embed { background: transparent; padding: 0; align-items: stretch; min-height: 0; }
        .lf-card {
            width: 100%; max-width: 480px; background: var(--card); border-radius: 22px; overflow: hidden;
            box-shadow: 0 30px 70px -20px rgba(15, 23, 42, .35), 0 0 0 1px rgba(15, 23, 42, .04);
            animation: lf-in .45s cubic-bezier(.2, .8, .2, 1);
        }
        body.is-embed .lf-card { max-width: none; border-radius: 0; box-shadow: none; animation: none; min-height: 100vh; }
        @keyframes lf-in { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }

        .lf-brand { position: relative; display: flex; align-items: center; justify-content: center; padding: 16px 56px 14px; background: #fff; }
        .lf-brand img { display: block; height: 34px; width: auto; max-width: 100%; }
        .lf-brand::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: linear-gradient(90deg, var(--site-red), #f59e0b 35%, var(--brand-2) 70%, var(--accent)); }
        .lf-close { position: absolute; top: 50%; right: 14px; transform: translateY(-50%); z-index: 2; width: 36px; height: 36px; border-radius: 50%; border: 0; cursor: pointer; color: #475569; background: #f1f5f9; font-size: 16px; display: grid; place-items: center; text-decoration: none; transition: background .15s, color .15s, transform .2s; }
        .lf-close:hover { background: #fee2e2; color: var(--site-red); transform: translateY(-50%) rotate(90deg); }
        .lf-close:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

        .lf-head { position: relative; padding: 22px 26px 22px; color: #fff; background: linear-gradient(135deg, #047857 0%, var(--brand) 40%, var(--brand-2) 75%, var(--accent) 120%); overflow: hidden; }
        .lf-head::before, .lf-head::after { content: ''; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .1); }
        .lf-head::before { width: 220px; height: 220px; right: -70px; top: -90px; }
        .lf-head::after { width: 120px; height: 120px; right: 60px; bottom: -70px; background: rgba(255, 255, 255, .12); }
        .lf-badge { position: relative; display: inline-flex; align-items: center; gap: 7px; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; background: rgba(255, 255, 255, .18); padding: 5px 11px; border-radius: 999px; backdrop-filter: blur(6px); }
        .lf-badge i { color: #fde68a; }
        .lf-head h1 { position: relative; margin: 12px 0 6px; font-size: 23px; line-height: 1.25; font-weight: 800; letter-spacing: -.01em; }
        .lf-head p { position: relative; margin: 0; font-size: 14px; opacity: .95; line-height: 1.5; }
        .lf-head .bn { font-family: 'Hind Siliguri', sans-serif; display: block; font-size: 13.5px; opacity: .9; margin-top: 2px; }
        .lf-trust { position: relative; display: flex; gap: 14px; flex-wrap: wrap; margin-top: 14px; font-size: 12.5px; font-weight: 600; }
        .lf-trust span { display: inline-flex; align-items: center; gap: 6px; opacity: .97; }

        .lf-body { padding: 22px 26px 24px; }
        .lf-field { margin-bottom: 14px; }
        .lf-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .lf-label em { font-style: normal; color: var(--muted); font-weight: 500; }
        .lf-label .req { color: var(--err); }
        .lf-input { position: relative; }
        .lf-input i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; pointer-events: none; transition: color .15s; }
        .lf-input input, .lf-input textarea {
            width: 100%; font: inherit; font-size: 15px; color: var(--text); background: #f8fafc;
            border: 1.5px solid var(--line); border-radius: 12px; padding: 12px 14px 12px 40px; outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .lf-input textarea { padding-left: 14px; resize: vertical; min-height: 78px; }
        .lf-input input:focus, .lf-input textarea:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 4px var(--ring); }
        .lf-input input:focus + i, .lf-input:focus-within i { color: var(--brand); }
        .lf-field.has-error input, .lf-field.has-error textarea { border-color: var(--err); box-shadow: 0 0 0 4px rgba(239, 68, 68, .1); }
        .lf-err { display: none; color: var(--err); font-size: 12.5px; margin-top: 5px; }
        .lf-field.has-error .lf-err { display: block; }

        .lf-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .lf-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .lf-chip span {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 13px; border-radius: 999px; cursor: pointer; user-select: none;
            font-size: 13px; font-weight: 600; color: #334155; background: #f1f5f9; border: 1.5px solid transparent; transition: all .15s;
        }
        .lf-chip span:hover { background: #d1fae5; color: var(--brand); }
        .lf-chip input:checked + span { color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 6px 16px -6px rgba(5, 150, 105, .6); }
        .lf-chip input:focus-visible + span { outline: 2px solid var(--brand); outline-offset: 2px; }
        .lf-chip input:checked + span::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 10px; }

        .lf-hp { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; overflow: hidden; }
        .lf-submit {
            width: 100%; margin-top: 6px; border: 0; cursor: pointer; font: inherit; font-size: 15.5px; font-weight: 700; color: #fff;
            padding: 14px 18px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            background: linear-gradient(135deg, var(--brand), var(--brand-2)); background-size: 160% 160%;
            box-shadow: 0 14px 28px -12px rgba(5, 150, 105, .7); transition: transform .15s, box-shadow .15s, background-position .4s;
        }
        .lf-submit:hover { transform: translateY(-1px); background-position: 100% 0; box-shadow: 0 18px 32px -12px rgba(5, 150, 105, .8); }
        .lf-submit:active { transform: translateY(0); }
        .lf-submit[disabled] { opacity: .75; cursor: wait; transform: none; }
        .lf-alert { display: none; margin-bottom: 14px; padding: 11px 13px; border-radius: 12px; font-size: 13.5px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .lf-alert.show { display: flex; gap: 8px; align-items: flex-start; }
        .lf-privacy { margin: 12px 0 0; text-align: center; font-size: 12px; color: var(--muted); }
        .lf-privacy i { color: var(--ok); }
        .cf-turnstile { margin: 4px 0 10px; }

        .lf-done { display: none; padding: 40px 28px 44px; text-align: center; }
        .lf-done.show { display: block; animation: lf-in .4s ease; }
        .lf-tick { width: 84px; height: 84px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 36px;
            background: linear-gradient(135deg, var(--brand), var(--accent)); box-shadow: 0 16px 36px -10px rgba(16, 185, 129, .6); animation: lf-pop .5s cubic-bezier(.2, 1.6, .4, 1); }
        @keyframes lf-pop { from { transform: scale(.3); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .lf-done h2 { margin: 0 0 8px; font-size: 22px; font-weight: 800; }
        .lf-done p { margin: 0 auto; max-width: 320px; color: var(--muted); font-size: 14.5px; line-height: 1.55; }
        .lf-done .bn { font-family: 'Hind Siliguri', sans-serif; display: block; margin-top: 6px; }
        .lf-done-close { margin-top: 22px; border: 1.5px solid var(--line); background: transparent; color: var(--text); font: inherit; font-size: 14px; font-weight: 600; padding: 10px 22px; border-radius: 12px; cursor: pointer; transition: border-color .15s, color .15s; }
        .lf-done-close:hover { border-color: var(--brand); color: var(--brand); }
        .lf-foot { padding: 12px; text-align: center; font-size: 11.5px; color: #94a3b8; border-top: 1px solid #f1f5f9; }

        /* Dark mode only on the stand-alone page; the website popup always matches the light site. */
        @media (prefers-color-scheme: dark) {
            body:not(.is-embed) { --bg: #06140f; --text: #e2e8f0; --muted: #94a3b8; --line: #1f3a31; --card: #0d1f19; }
            body:not(.is-embed) .lf-input input, body:not(.is-embed) .lf-input textarea { background: #0a1914; }
            body:not(.is-embed) .lf-input input:focus, body:not(.is-embed) .lf-input textarea:focus { background: #071410; }
            body:not(.is-embed) .lf-chip span { background: #143028; color: #cbd5e1; }
            body:not(.is-embed) .lf-chip span:hover { background: #1b4034; color: #a7f3d0; }
            body:not(.is-embed) .lf-alert { background: rgba(239, 68, 68, .1); border-color: rgba(239, 68, 68, .3); color: #fca5a5; }
            body:not(.is-embed) .lf-foot { border-top-color: #143028; color: #64748b; }
        }
        @media (max-width: 420px) { .lf-head, .lf-body { padding-left: 18px; padding-right: 18px; } .lf-head h1 { font-size: 21px; } }
    </style>
</head>
<body class="{{ $embedded ? 'is-embed' : '' }}">
<main class="lf-card" id="lf-card">
    <div class="lf-brand">
        <img src="{{ asset('assets/img/hearing-store-logo.png') }}" alt="Hearing Store" width="260" height="56" id="lf-logo">
        @if ($embedded)<button type="button" class="lf-close js-lf-close" id="lf-close" aria-label="Close"><i class="fas fa-times"></i></button>
        @else<a href="https://hearingstore.com.bd" class="lf-close js-lf-back" id="lf-back" aria-label="Back to website"><i class="fas fa-arrow-left"></i></a>@endif
    </div>
    <header class="lf-head">
        <span class="lf-badge"><i class="fas fa-star"></i> 100% free · no obligation</span>
        <h1>Get a free hearing consultation</h1>
        <p>Leave your number and our hearing specialist will call you back.
            <span class="bn">আপনার নম্বর দিন, আমাদের বিশেষজ্ঞ আপনাকে কল করবেন।</span></p>
        <div class="lf-trust">
            <span><i class="fas fa-phone-volume"></i> Quick call-back</span>
            <span><i class="fas fa-user-doctor"></i> Certified experts</span>
            <span><i class="fas fa-shield-heart"></i> Trusted care</span>
        </div>
    </header>

    <form class="lf-body" id="lf-form" novalidate>
        <div class="lf-alert" id="lf-alert" role="alert"><i class="fas fa-circle-exclamation" style="margin-top: 2px;"></i><span></span></div>

        <div class="lf-field" data-field="name">
            <label class="lf-label" for="lf-name">Your name <span class="req">*</span></label>
            <div class="lf-input"><input type="text" id="lf-name" name="name" maxlength="191" autocomplete="name" placeholder="e.g. Rahim Uddin" required><i class="fas fa-user"></i></div>
            <div class="lf-err"></div>
        </div>

        <div class="lf-field" data-field="phone">
            <label class="lf-label" for="lf-phone">Mobile number <span class="req">*</span></label>
            <div class="lf-input"><input type="tel" id="lf-phone" name="phone" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required><i class="fas fa-mobile-screen"></i></div>
            <div class="lf-err"></div>
        </div>

        <div class="lf-field" data-field="inquiry_for">
            <span class="lf-label">I'm interested in <em>(choose any)</em></span>
            <div class="lf-chips">
                @foreach ($inquiries as $iq)
                    <label class="lf-chip"><input type="checkbox" name="inquiry_for[]" value="{{ $iq->value }}" id="lf-iq-{{ $iq->value }}" @checked(in_array($iq->value, $preset, true))><span>{{ $iq->label() }}</span></label>
                @endforeach
            </div>
            <div class="lf-err"></div>
        </div>

        <div class="lf-field" data-field="email">
            <label class="lf-label" for="lf-email">Email <em>(optional)</em></label>
            <div class="lf-input"><input type="email" id="lf-email" name="email" maxlength="191" autocomplete="email" placeholder="you@example.com"><i class="fas fa-envelope"></i></div>
            <div class="lf-err"></div>
        </div>

        <div class="lf-field" data-field="message">
            <label class="lf-label" for="lf-message">Anything we should know? <em>(optional)</em></label>
            <div class="lf-input"><textarea id="lf-message" name="message" maxlength="2000" rows="3" placeholder="e.g. My father has trouble hearing on phone calls"></textarea></div>
            <div class="lf-err"></div>
        </div>

        {{-- Honeypot: real people never see or fill this --}}
        <div class="lf-hp" aria-hidden="true"><label for="lf-website">Website</label><input type="text" id="lf-website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="t" value="{{ $token }}">
        @if ($turnstile)<div class="cf-turnstile" data-sitekey="{{ $turnstile }}"></div>@endif

        <button type="submit" class="lf-submit" id="lf-submit"><i class="fas fa-phone"></i> <span>Request a call-back</span></button>
        <p class="lf-privacy"><i class="fas fa-lock"></i> Your details stay private. We never share them.</p>
    </form>

    <section class="lf-done" id="lf-done" aria-live="polite">
        <div class="lf-tick"><i class="fas fa-check"></i></div>
        <h2>Thank you!</h2>
        <p>We've received your request. Our specialist will call you shortly.
            <span class="bn">ধন্যবাদ! আমাদের বিশেষজ্ঞ শীঘ্রই আপনাকে কল করবেন।</span></p>
        @if ($embedded)<button type="button" class="lf-done-close js-lf-close" id="lf-done-close">Close</button>
        @else<a href="https://hearingstore.com.bd" class="lf-done-close js-lf-back" id="lf-done-back" style="display: inline-block; text-decoration: none;"><i class="fas fa-arrow-left" style="margin-right: 6px;"></i>Back to website</a>@endif
    </section>
    <div class="lf-foot">Hearing Store · Reliable hearing care in Bangladesh</div>
</main>

<script>
(function () {
    var form = document.getElementById('lf-form');
    var btn = document.getElementById('lf-submit');
    var alertBox = document.getElementById('lf-alert');
    var qs = new URLSearchParams(location.search);
    var embedded = {{ $embedded ? 'true' : 'false' }};
    var post = function (type) { if (embedded && window.parent !== window) { window.parent.postMessage({ crmLeadForm: type }, '*'); } };

    document.querySelectorAll('.js-lf-close').forEach(function (b) { b.addEventListener('click', function () { post('close'); }); });
    // Direct link: "back" returns to the page the visitor came from (when it was another site), else the Hearing Store home page.
    var back = qs.get('ref') || (document.referrer && document.referrer.indexOf(location.host) === -1 ? document.referrer : '');
    if (back && /^https?:\/\//.test(back)) { document.querySelectorAll('.js-lf-back').forEach(function (a) { a.href = back; }); }

    function clearErrors() {
        alertBox.classList.remove('show');
        form.querySelectorAll('.lf-field.has-error').forEach(function (f) { f.classList.remove('has-error'); });
    }
    function showError(msg, errors) {
        var shown = false;
        Object.keys(errors || {}).forEach(function (k) {
            var f = form.querySelector('[data-field="' + k.split('.')[0] + '"]');
            if (f) { f.classList.add('has-error'); f.querySelector('.lf-err').textContent = errors[k][0]; shown = true; }
        });
        if (!shown) { alertBox.querySelector('span').textContent = msg; alertBox.classList.add('show'); }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
        var name = document.getElementById('lf-name').value.trim(), phone = document.getElementById('lf-phone').value.replace(/[\s-]/g, '');
        var errs = {};
        if (name.length < 2) { errs.name = ['Please enter your name.']; }
        if (!/^(\+?88)?01[3-9]\d{8}$/.test(phone)) { errs.phone = ['Please enter a valid mobile number (e.g. 017XXXXXXXX).']; }
        if (Object.keys(errs).length) { showError('', errs); return; }

        var data = new FormData(form);
        ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { if (qs.get(k)) { data.append(k, qs.get(k)); } });
        data.append('page_url', qs.get('ref') || document.referrer || location.href);

        btn.disabled = true;
        btn.querySelector('span').textContent = 'Sending…';
        fetch(location.pathname, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Something went wrong. Please try again.' }; }); })
            .then(function (res) {
                if (!res.ok) { throw res; }
                form.style.display = 'none';
                document.getElementById('lf-done').classList.add('show');
                post('submitted');
            })
            .catch(function (res) {
                showError(res && res.message ? res.message : 'Network problem. Please check your connection and try again.', res && res.errors);
                btn.disabled = false;
                btn.querySelector('span').textContent = 'Request a call-back';
                if (window.turnstile) { window.turnstile.reset(); }
            });
    });
})();
</script>
</body>
</html>
