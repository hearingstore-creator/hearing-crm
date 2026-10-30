<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#2b0306">
    <title>Free Hearing Check – {{ $company }}</title>
    <meta name="description" content="Missing parts of words? Leave your number and a hearing specialist will call you back.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Hind+Siliguri:wght@400;500;600&display=swap" rel="stylesheet">
    @if ($turnstile)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <style>
        /* Red glass design: CSS-drawn red/black circles behind a smoked glass card (Hearing Store red). */
        :root {
            --ink: #fff; --muted: rgba(255, 255, 255, .74); --soft: rgba(255, 255, 255, .5);
            --field: rgba(0, 0, 0, .26); --edge: rgba(255, 255, 255, .2); --red: #ff3b47; --deep: #1a0204; --err: #ffb4b9;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'Poppins', 'Hind Siliguri', system-ui, sans-serif; color: var(--ink); -webkit-font-smoothing: antialiased;
            background: #2b0306; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 28px 16px 40px; overflow-x: hidden;
        }
        html[lang=bn] body { font-family: 'Hind Siliguri', 'Poppins', system-ui, sans-serif; }
        body.is-embed { padding: 12px 12px 26px; justify-content: flex-start; }

        .lf-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: linear-gradient(160deg, #120102 0%, #3d0509 45%, #7a0911 100%); }
        .lf-bg::before { content: ''; position: absolute; width: 120vmax; height: 120vmax; left: 50%; top: -78vmax; margin-left: -60vmax; border-radius: 50%;
            background: radial-gradient(circle at 50% 80%, #8d0a13 0%, #3a0408 35%, #070001 70%); box-shadow: 0 40px 120px rgba(0, 0, 0, .5); }
        .lf-bg::after { content: ''; position: absolute; width: 130vmax; height: 130vmax; left: 50%; bottom: -92vmax; margin-left: -60vmax; border-radius: 50%;
            background: radial-gradient(circle at 55% 20%, #ff3a45 0%, #e0161f 22%, #a50b14 48%, #5c050a 75%); box-shadow: 0 -30px 120px rgba(0, 0, 0, .45); }

        .lf-card {
            width: 100%; max-width: 400px; border-radius: 36px; padding: 14px 14px 24px; position: relative;
            background: linear-gradient(180deg, rgba(18, 6, 8, .62) 0%, rgba(30, 8, 11, .42) 45%, rgba(60, 10, 14, .3) 100%);
            -webkit-backdrop-filter: blur(30px) saturate(140%); backdrop-filter: blur(30px) saturate(140%);
            border: 1px solid rgba(255, 255, 255, .16);
            box-shadow: 0 40px 90px rgba(0, 0, 0, .45), inset 0 1px 0 rgba(255, 255, 255, .22), inset 0 -1px 0 rgba(255, 255, 255, .06);
            animation: lf-in .5s cubic-bezier(.2, .8, .2, 1);
        }
        body.is-embed .lf-card { max-width: none; animation: none; }
        @keyframes lf-in { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }

        .lf-top { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 8px 12px; }
        body.is-embed .lf-top { padding-right: 54px; }
        .lf-brand { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; letter-spacing: .2px; color: #fff; text-decoration: none; }
        .lf-brand .bars { display: flex; gap: 2px; align-items: center; height: 18px; }
        .lf-brand .bars i { width: 3px; border-radius: 2px; background: var(--red); }
        .lf-lang { display: flex; background: rgba(0, 0, 0, .3); border-radius: 999px; padding: 3px; border: 1px solid var(--edge); }
        .lf-lang button { border: 0; background: none; color: var(--muted); font: 600 12px 'Poppins', 'Hind Siliguri', sans-serif; padding: 6px 11px; border-radius: 999px; cursor: pointer; }
        .lf-lang button[aria-pressed=true] { background: #fff; color: var(--deep); }
        .lf-icon-btn {
            position: absolute; top: 18px; right: 18px; z-index: 2; width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center;
            border: 1px solid rgba(255, 255, 255, .25); background: rgba(0, 0, 0, .35); color: #fff; cursor: pointer; text-decoration: none;
        }
        body:not(.is-embed) .lf-icon-btn { position: static; width: 36px; height: 36px; }
        .lf-icon-btn svg { width: 18px; height: 18px; }

        /* hearing animation: clear sentence, then the high-pitched sounds fade out */
        .lf-scene { border-radius: 26px; padding: 20px 18px 16px; background: linear-gradient(150deg, rgba(255, 255, 255, .1), rgba(255, 255, 255, .03)); border: 1px solid var(--edge); }
        .lf-bubble { display: inline-block; background: #fff; color: var(--deep); border-radius: 22px 22px 22px 6px; padding: 11px 17px; font-size: 21px; font-weight: 600; box-shadow: 0 12px 28px rgba(0, 0, 0, .3); }
        .lf-bubble .who { display: block; font-size: 11px; font-weight: 600; color: #d3141f; letter-spacing: .6px; text-transform: uppercase; margin-bottom: 2px; }
        .lf-bubble .g { display: inline-block; white-space: pre; transition: opacity .9s ease, filter .9s ease, transform .9s ease; }
        .muffled .lf-bubble .g { opacity: .85; }
        .muffled .lf-bubble .g.lost { opacity: .06; filter: blur(4px); transform: translateY(2px); }
        .lf-eq { display: flex; align-items: flex-end; gap: 5px; height: 36px; margin: 16px 2px 4px; }
        .lf-eq i { flex: 1; border-radius: 4px; background: linear-gradient(180deg, #fff, #ff7a82); transition: opacity .9s ease; }
        .muffled .lf-eq i.hi { opacity: .18; }
        .lf-eq-labels { display: flex; justify-content: space-between; font-size: 10.5px; color: var(--muted); letter-spacing: .4px; }
        .lf-caption { margin: 10px 0 0; font-size: 14px; font-weight: 500; opacity: 0; transform: translateY(6px); transition: opacity .7s ease .3s, transform .7s ease .3s; }
        .muffled .lf-caption { opacity: 1; transform: none; }

        h1 { font-size: 30px; font-weight: 700; letter-spacing: -.6px; line-height: 1.1; margin: 22px 8px 6px; }
        .lf-sub { font-size: 14px; color: var(--muted); margin: 0 8px 16px; line-height: 1.45; }
        .lf-rule { height: 1px; background: rgba(255, 255, 255, .14); margin: 0 8px 16px; }
        form { padding: 0 8px; }
        .lf-field { margin-bottom: 14px; }
        .lf-label { display: block; font-size: 13px; color: var(--muted); margin: 0 0 7px 4px; }
        .lf-in {
            width: 100%; height: 52px; border-radius: 16px; border: 1px solid var(--edge); background: var(--field); color: #fff;
            font: 500 16px 'Poppins', 'Hind Siliguri', sans-serif; padding: 0 18px; outline: none; transition: border-color .15s, background .15s;
        }
        textarea.lf-in { height: 84px; padding: 14px 18px; resize: none; }
        .lf-in::placeholder { color: var(--soft); }
        .lf-in:focus { border-color: rgba(255, 255, 255, .7); background: rgba(0, 0, 0, .38); }
        .lf-field.has-error .lf-in { border-color: var(--err); }
        .lf-err { display: none; color: var(--err); font-size: 12.5px; margin: 6px 0 0 4px; }
        .lf-field.has-error .lf-err { display: block; }
        .lf-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .lf-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .lf-chip span { display: inline-block; border: 1px solid var(--edge); background: var(--field); color: #fff; border-radius: 999px; padding: 10px 15px;
            font-size: 14px; font-weight: 500; cursor: pointer; user-select: none; transition: background .2s, color .2s; }
        .lf-chip input:checked + span { background: #fff; color: var(--deep); border-color: transparent; }
        .lf-chip input:focus-visible + span { outline: 2px solid #fff; outline-offset: 2px; }
        .lf-note-link { background: none; border: 0; color: var(--muted); font: 500 14px 'Poppins', 'Hind Siliguri', sans-serif; cursor: pointer; padding: 4px; }
        .lf-note { display: none; }
        .show-note .lf-note { display: block; }
        .show-note .lf-note-link { display: none; }
        .lf-hp { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; overflow: hidden; }
        .lf-alert { display: none; margin: 0 0 14px; padding: 11px 14px; border-radius: 14px; font-size: 13.5px; background: rgba(0, 0, 0, .35); border: 1px solid rgba(255, 180, 185, .45); color: var(--err); }
        .lf-alert.show { display: block; }
        .cf-turnstile { margin: 4px 0 10px; }

        .lf-foot { display: flex; flex-direction: column; align-items: center; }
        .lf-submit {
            margin-top: 22px; border: 0; border-radius: 999px; background: #fff; color: var(--deep); font: 600 17px 'Poppins', 'Hind Siliguri', sans-serif;
            padding: 16px 40px; cursor: pointer; box-shadow: 0 16px 34px rgba(0, 0, 0, .4); display: inline-flex; align-items: center; gap: 10px;
            transition: transform .15s;
        }
        .lf-submit:hover { transform: translateY(-1px); }
        .lf-submit[disabled] { opacity: .75; cursor: wait; transform: none; }
        .lf-submit svg { width: 18px; height: 18px; fill: #d3141f; }
        .lf-privacy { margin: 12px 0 0; font-size: 12.5px; color: rgba(255, 255, 255, .8); }

        .lf-done { display: none; text-align: center; padding: 40px 18px 26px; }
        .sent .lf-done { display: block; animation: lf-in .4s ease; }
        .sent .lf-scene, .sent form, .sent h1, .sent .lf-sub, .sent .lf-rule, .sent .lf-foot { display: none; }
        .lf-tick { width: 84px; height: 84px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center;
            background: linear-gradient(145deg, #ff4650, #b80d17); box-shadow: 0 16px 34px rgba(220, 20, 30, .45), inset 0 1px 0 rgba(255, 255, 255, .4); }
        .lf-done h2 { font-size: 26px; margin: 0 0 8px; }
        .lf-done p { color: var(--muted); font-size: 14.5px; line-height: 1.5; margin: 0 auto; max-width: 320px; }
        .lf-done-btn { display: inline-block; margin-top: 22px; border: 1px solid var(--edge); background: rgba(0, 0, 0, .3); color: #fff; font: 600 14px 'Poppins', 'Hind Siliguri', sans-serif;
            padding: 11px 24px; border-radius: 999px; cursor: pointer; text-decoration: none; }

        @media (prefers-reduced-motion: reduce) { .lf-card, .lf-bubble .g, .lf-eq i, .lf-caption { animation: none !important; transition: none !important; } }
        @media (max-width: 420px) { h1 { font-size: 27px; } .lf-bubble { font-size: 19px; } }
        /* Short screens (laptops): tighter spacing so "Call me back" fits without scrolling */
        @media (max-height: 940px) {
            .lf-top { padding-bottom: 8px; }
            .lf-scene { padding: 14px 16px 12px; }
            .lf-bubble { font-size: 19px; padding: 9px 15px; }
            .lf-eq { height: 28px; margin-top: 12px; }
            .lf-caption { margin-top: 8px; font-size: 13.5px; }
            h1 { font-size: 26px; margin-top: 16px; }
            .lf-sub { margin-bottom: 12px; }
            .lf-rule { margin-bottom: 12px; }
            .lf-field { margin-bottom: 10px; }
            .lf-in { height: 48px; }
            .lf-chip span { padding: 8px 14px; }
            .lf-submit { margin-top: 14px; padding: 14px 36px; }
        }
    </style>
</head>
<body class="{{ $embedded ? 'is-embed' : '' }}">
<div class="lf-bg" aria-hidden="true"></div>

<main class="lf-card" id="lf-card">
    @if ($embedded)
        <button type="button" class="lf-icon-btn js-lf-close" id="lf-close" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    @endif
    <div class="lf-top">
        @unless ($embedded)
            <a href="https://hearingstore.com.bd" class="lf-icon-btn js-lf-back" id="lf-back" aria-label="Back to website"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></a>
        @endunless
        <span class="lf-brand"><span class="bars" aria-hidden="true"><i style="height:8px"></i><i style="height:14px"></i><i style="height:18px"></i><i style="height:11px"></i><i style="height:16px"></i><i style="height:7px"></i></span>{{ $company }}</span>
        <div class="lf-lang" role="group" aria-label="Language"><button type="button" data-lang="en" aria-pressed="true">EN</button><button type="button" data-lang="bn" aria-pressed="false">বাংলা</button></div>
    </div>

    <section class="lf-scene" id="lf-scene" aria-label="How hearing loss can sound">
        <div class="lf-bubble"><span class="who" data-t="who"></span><span id="lf-line"></span></div>
        <div class="lf-eq" id="lf-eq" aria-hidden="true"></div>
        <div class="lf-eq-labels" aria-hidden="true"><span data-t="low"></span><span data-t="high"></span></div>
        <p class="lf-caption" data-t="caption"></p>
    </section>

    <h1 data-t="title"></h1>
    <p class="lf-sub" data-t="sub"></p>
    <div class="lf-rule"></div>

    <form id="lf-form" novalidate>
        <div class="lf-alert" id="lf-alert" role="alert"></div>

        <div class="lf-field" data-field="name">
            <label class="lf-label" for="lf-name" data-t="name"></label>
            <input class="lf-in" type="text" id="lf-name" name="name" maxlength="191" autocomplete="name" required>
            <div class="lf-err"></div>
        </div>

        <div class="lf-field" data-field="phone">
            <label class="lf-label" for="lf-phone" data-t="phone"></label>
            <input class="lf-in" type="tel" id="lf-phone" name="phone" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required>
            <div class="lf-err"></div>
        </div>

        <div class="lf-field" data-field="inquiry_for">
            <span class="lf-label" data-t="about"></span>
            <div class="lf-chips">
                @foreach ($inquiries as $iq)
                    <label class="lf-chip"><input type="checkbox" name="inquiry_for[]" value="{{ $iq->value }}" id="lf-iq-{{ $iq->value }}" @checked(in_array($iq->value, $preset, true))><span data-t="iq_{{ $iq->value }}">{{ $iq->label() }}</span></label>
                @endforeach
            </div>
            <div class="lf-err"></div>
        </div>

        <button type="button" class="lf-note-link" id="lf-note-link" data-t="addNote"></button>
        <div class="lf-field lf-note" data-field="message">
            <label class="lf-label" for="lf-message" data-t="noteLabel"></label>
            <textarea class="lf-in" id="lf-message" name="message" maxlength="2000"></textarea>
            <div class="lf-err"></div>
        </div>

        {{-- Honeypot: real people never see or fill this --}}
        <div class="lf-hp" aria-hidden="true"><label for="lf-website">Website</label><input type="text" id="lf-website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="t" value="{{ $token }}">
        @if ($turnstile)<div class="cf-turnstile" data-sitekey="{{ $turnstile }}" data-theme="dark"></div>@endif

        <div class="lf-foot">
            <button type="submit" class="lf-submit" id="lf-submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.2 15.2 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1z"/></svg><span data-t="cta"></span></button>
            <p class="lf-privacy" data-t="private"></p>
        </div>
    </form>

    <section class="lf-done" id="lf-done" aria-live="polite">
        <div class="lf-tick"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h2 data-t="doneTitle"></h2>
        <p data-t="doneText"></p>
        @if ($embedded)<button type="button" class="lf-done-btn js-lf-close" id="lf-done-close" data-t="close"></button>
        @else<a href="https://hearingstore.com.bd" class="lf-done-btn js-lf-back" id="lf-done-back" data-t="back"></a>@endif
    </section>
</main>

<script>
(function () {
    var T = {
        en: {
            who: 'Your daughter', line: 'Dad, dinner is ready.', lost: [2, 5, 13, 18],
            low: 'LOW PITCH', high: 'HIGH PITCH', caption: 'Missing parts of words? A free check tells you why.',
            title: 'Free hearing check', sub: 'Leave your number. A hearing specialist will call you back.',
            name: 'Your name', namePh: 'e.g. Rahim Uddin', phone: 'Mobile number', about: 'What is it about?',
            iq_hearing_test: 'Hearing test', iq_hearing_aid: 'Hearing aid', iq_repair: 'Repair', iq_other: 'Other',
            addNote: '+ Add a note (optional)', noteLabel: 'Anything we should know?', notePh: 'e.g. My father struggles on phone calls',
            cta: 'Call me back', sending: 'Sending…', private: 'Your details stay private.',
            doneTitle: 'Thank you!', doneText: 'A specialist will call you within 15 minutes during office hours.', close: 'Close', back: 'Back to website',
            errName: 'Please enter your name.', errPhone: 'Please enter a valid mobile number (e.g. 017XXXXXXXX).',
            errNet: 'Network problem. Please check your connection and try again.', errAny: 'Something went wrong. Please try again.'
        },
        bn: {
            who: 'আপনার মেয়ে', line: 'আব্বু, খাবার রেডি।', lostChars: ['ব্ব', 'বা', 'ডি'],
            low: 'নিচু স্বর', high: 'উঁচু স্বর', caption: 'কথার কিছু অংশ শুনতে পান না? বিনামূল্যে পরীক্ষা করে দেখুন।',
            title: 'বিনামূল্যে শ্রবণ পরীক্ষা', sub: 'আপনার নম্বর দিন, আমাদের বিশেষজ্ঞ আপনাকে কল করবেন।',
            name: 'আপনার নাম', namePh: 'যেমন: রহিম উদ্দিন', phone: 'মোবাইল নম্বর', about: 'কী বিষয়ে?',
            iq_hearing_test: 'শ্রবণ পরীক্ষা', iq_hearing_aid: 'হিয়ারিং এইড', iq_repair: 'মেরামত', iq_other: 'অন্যান্য',
            addNote: '+ কিছু লিখতে চাইলে', noteLabel: 'আমাদের কিছু জানাতে চান?', notePh: 'যেমন: বাবা ফোনে কথা বুঝতে পারেন না',
            cta: 'আমাকে কল করুন', sending: 'পাঠানো হচ্ছে…', private: 'আপনার তথ্য গোপন থাকবে।',
            doneTitle: 'ধন্যবাদ!', doneText: 'অফিস সময়ে ১৫ মিনিটের মধ্যে আমাদের বিশেষজ্ঞ আপনাকে কল করবেন।', close: 'বন্ধ করুন', back: 'ওয়েবসাইটে ফিরে যান',
            errName: 'অনুগ্রহ করে আপনার নাম লিখুন।', errPhone: 'সঠিক মোবাইল নম্বর লিখুন (যেমন 017XXXXXXXX)।',
            errNet: 'নেটওয়ার্ক সমস্যা। আবার চেষ্টা করুন।', errAny: 'কিছু ভুল হয়েছে। আবার চেষ্টা করুন।'
        }
    };
    var form = document.getElementById('lf-form');
    var btn = document.getElementById('lf-submit');
    var alertBox = document.getElementById('lf-alert');
    var scene = document.getElementById('lf-scene');
    var qs = new URLSearchParams(location.search);
    var embedded = {{ $embedded ? 'true' : 'false' }};
    var post = function (type) { if (embedded && window.parent !== window) { window.parent.postMessage({ crmLeadForm: type }, '*'); } };
    var lang = qs.get('lang') === 'bn' ? 'bn' : 'en';
    try { lang = localStorage.getItem('crmlf-lang') || lang; } catch (e) {}

    document.querySelectorAll('.js-lf-close').forEach(function (b) { b.addEventListener('click', function () { post('close'); }); });
    // Direct link: "back" returns to the page the visitor came from (when it was another site), else the Hearing Store home page.
    var back = qs.get('ref') || (document.referrer && document.referrer.indexOf(location.host) === -1 ? document.referrer : '');
    if (back && /^https?:\/\//.test(back)) { document.querySelectorAll('.js-lf-back').forEach(function (a) { a.href = back; }); }

    // Equaliser: the right-hand (high-pitch) bars fade when the sound gets muffled
    var eq = document.getElementById('lf-eq');
    [16, 26, 34, 30, 36, 28, 24, 30, 22, 18].forEach(function (h, i) { var b = document.createElement('i'); b.style.height = h + 'px'; if (i >= 6) { b.className = 'hi'; } eq.appendChild(b); });

    function graphemes(text) {
        if (window.Intl && Intl.Segmenter) { return Array.from(new Intl.Segmenter(lang, { granularity: 'grapheme' }).segment(text), function (s) { return s.segment; }); }
        return Array.from(text);
    }
    function render() {
        var t = T[lang];
        document.documentElement.lang = lang;
        document.querySelectorAll('[data-t]').forEach(function (el) { if (t[el.dataset.t] !== undefined) { el.textContent = t[el.dataset.t]; } });
        document.getElementById('lf-name').placeholder = t.namePh;
        document.getElementById('lf-message').placeholder = t.notePh;
        document.querySelectorAll('.lf-lang button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.lang === lang ? 'true' : 'false'); });
        var line = document.getElementById('lf-line');
        line.textContent = '';
        graphemes(t.line).forEach(function (g, i) {
            var s = document.createElement('span');
            s.className = 'g' + ((t.lost ? t.lost.indexOf(i) !== -1 : t.lostChars.indexOf(g) !== -1) ? ' lost' : '');
            s.textContent = g;
            line.appendChild(s);
        });
    }
    document.querySelectorAll('.lf-lang button').forEach(function (b) {
        b.addEventListener('click', function () { lang = b.dataset.lang; try { localStorage.setItem('crmlf-lang', lang); } catch (e) {} render(); });
    });
    document.getElementById('lf-note-link').addEventListener('click', function () { form.classList.add('show-note'); document.getElementById('lf-message').focus(); });
    render();

    // Loop: clear speech, then the high sounds fade. Reduced motion: show the muffled state only.
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { scene.classList.add('muffled'); }
    else {
        var cycle = function () { scene.classList.remove('muffled'); setTimeout(function () { scene.classList.add('muffled'); }, 1600); };
        cycle(); setInterval(cycle, 6200);
    }

    function clearErrors() {
        alertBox.classList.remove('show');
        form.querySelectorAll('.lf-field.has-error').forEach(function (f) { f.classList.remove('has-error'); });
    }
    function showError(msg, errors) {
        var shown = false;
        Object.keys(errors || {}).forEach(function (k) {
            var f = form.querySelector('[data-field="' + k.split('.')[0] + '"]');
            if (f) { f.classList.add('has-error'); f.querySelector('.lf-err').textContent = errors[k][0]; shown = true; if (k === 'message') { form.classList.add('show-note'); } }
        });
        if (!shown) { alertBox.textContent = msg; alertBox.classList.add('show'); }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
        var t = T[lang];
        var name = document.getElementById('lf-name').value.trim(), phone = document.getElementById('lf-phone').value.replace(/[\s-]/g, '');
        var errs = {};
        if (name.length < 2) { errs.name = [t.errName]; }
        if (!/^(\+?88)?01[3-9]\d{8}$/.test(phone)) { errs.phone = [t.errPhone]; }
        if (Object.keys(errs).length) { showError('', errs); return; }

        var data = new FormData(form);
        ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { if (qs.get(k)) { data.append(k, qs.get(k)); } });
        data.append('page_url', qs.get('ref') || document.referrer || location.href);

        btn.disabled = true;
        btn.querySelector('span').textContent = t.sending;
        fetch(location.pathname, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, message: t.errAny }; }); })
            .then(function (res) {
                if (!res.ok) { throw res; }
                document.getElementById('lf-card').classList.add('sent');
                post('submitted');
            })
            .catch(function (res) {
                showError(res && res.message ? res.message : t.errNet, res && res.errors);
                btn.disabled = false;
                btn.querySelector('span').textContent = t.cta;
                if (window.turnstile) { window.turnstile.reset(); }
            });
    });
})();
</script>
</body>
</html>
