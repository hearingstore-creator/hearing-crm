<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#f8f6f9">
    <title>Free Hearing Check – {{ $company }}</title>
    <meta name="description" content="Missing parts of words? Leave your number and a hearing specialist will call you back.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Hind+Siliguri:wght@400;500;600&display=swap" rel="stylesheet">
    @if ($turnstile)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <style>
        /* Soft light design: CSS-drawn pink / light blue / coral glow behind a frosted white card (Hearing Store red accents). */
        :root {
            --ink: #1d1416; --muted: #3f3236; --soft: #75656a; --line: #a08d92;
            --field: rgba(255, 255, 255, .86); --edge: rgba(60, 20, 26, .14); --red: #d3141f; --red-dark: #a80f18; --err: #9a0b15;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'Poppins', 'Hind Siliguri', system-ui, sans-serif; color: var(--ink); -webkit-font-smoothing: antialiased;
            background: #f8f6f9; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 20px 16px; overflow-x: hidden;
        }
        html[lang=bn] body { font-family: 'Hind Siliguri', 'Poppins', system-ui, sans-serif; }
        body.is-embed { padding: 10px; justify-content: flex-start; }

        .lf-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden;
            background: radial-gradient(ellipse 70% 60% at 50% 45%, #f3e9ef 0%, #f8f6f9 70%), #f8f6f9; }
        /* portrait composition (like the brand artwork), centred so wide screens keep its shape; edges feathered */
        .lf-bg::before { content: ''; position: absolute; top: 0; bottom: 0; left: 50%; width: min(100vw, 90vh); transform: translateX(-50%);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 18%, #000 82%, transparent); mask-image: linear-gradient(90deg, transparent, #000 18%, #000 82%, transparent);
            background:
                radial-gradient(ellipse 44% 24% at 46% 78%, rgba(242, 78, 86, 1) 0%, rgba(243, 96, 102, .6) 45%, rgba(243, 96, 102, 0) 100%),
                radial-gradient(ellipse 50% 30% at 38% 60%, rgba(214, 118, 136, .55) 0%, rgba(214, 118, 136, 0) 100%),
                radial-gradient(ellipse 36% 42% at 68% 30%, rgba(160, 186, 216, 1) 0%, rgba(188, 212, 232, .75) 45%, rgba(188, 212, 232, 0) 100%),
                radial-gradient(ellipse 62% 52% at 28% 28%, rgba(247, 178, 192, 1) 0%, rgba(249, 192, 202, .7) 50%, rgba(249, 192, 202, 0) 100%),
                radial-gradient(ellipse 70% 60% at 50% 55%, rgba(222, 214, 234, .8) 0%, rgba(222, 214, 234, 0) 100%); }

        .lf-card {
            width: 100%; max-width: 400px; border-radius: 32px; padding: 12px 14px 16px; position: relative;
            background: linear-gradient(180deg, rgba(255, 255, 255, .5) 0%, rgba(255, 255, 255, .34) 55%, rgba(255, 255, 255, .42) 100%);
            -webkit-backdrop-filter: blur(30px) saturate(130%); backdrop-filter: blur(30px) saturate(130%);
            border: 1px solid rgba(255, 255, 255, .75);
            box-shadow: 0 30px 70px rgba(120, 40, 55, .14), inset 0 1px 0 rgba(255, 255, 255, .9);
            animation: lf-in .5s cubic-bezier(.2, .8, .2, 1);
        }
        body.is-embed .lf-card { max-width: none; animation: none; }
        @keyframes lf-in { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }

        .lf-top { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 4px 4px 10px; }
        body.is-embed .lf-top { padding-right: 50px; }
        .lf-brand { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; letter-spacing: .2px; color: var(--ink); text-decoration: none; }
        .lf-brand .bars { display: flex; gap: 2px; align-items: center; height: 18px; }
        .lf-brand .bars i { width: 3px; border-radius: 2px; background: var(--red); }
        .lf-lang { display: flex; background: rgba(255, 255, 255, .8); border-radius: 999px; padding: 3px; border: 1px solid var(--line); }
        .lf-lang button { border: 0; background: none; color: var(--muted); font: 600 12px 'Poppins', 'Hind Siliguri', sans-serif; padding: 5px 11px; border-radius: 999px; cursor: pointer; }
        .lf-lang button[aria-pressed=true] { background: var(--red); color: #fff; }
        .lf-lang button:focus-visible { outline: 2px solid var(--ink); outline-offset: 1px; }
        .lf-icon-btn {
            position: absolute; top: 14px; right: 14px; z-index: 2; width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center;
            border: 1px solid var(--line); background: #fff; color: var(--ink); cursor: pointer; text-decoration: none; box-shadow: 0 4px 12px rgba(80, 20, 30, .1);
        }
        .lf-icon-btn:hover { border-color: var(--ink); }
        body:not(.is-embed) .lf-icon-btn { position: static; width: 34px; height: 34px; }
        .lf-icon-btn svg { width: 18px; height: 18px; }

        /* Scene: daughter calls Dad (watching TV). Soft voice: the words break up and he doesn't react.
           Loud voice: the meter jumps, the words come through clearly and he turns round. Only transform/opacity animate. */
        .lf-scene { border-radius: 24px; padding: 8px 10px 9px; background: linear-gradient(150deg, rgba(255, 255, 255, .8), rgba(255, 255, 255, .5)); border: 1px solid var(--edge); }
        .lf-stage { position: relative; container-type: inline-size; }
        .lf-art { display: block; width: 100%; height: auto; overflow: visible; }
        .lf-art * { transform-box: fill-box; }
        .lf-bubble {
            position: absolute; left: 19%; top: 1%; max-width: 64%; background: #fff; color: var(--ink); border-radius: 16px 16px 16px 4px;
            padding: 6px 12px; font-size: 15px; font-size: clamp(11px, 4.4cqw, 15px); font-weight: 600; line-height: 1.3; white-space: nowrap; box-shadow: 0 8px 20px rgba(120, 40, 55, .14);
            transform-origin: 0 100%; transition: transform .45s cubic-bezier(.3, 1.6, .5, 1);
        }
        .lf-bubble::after { content: ''; position: absolute; left: 4px; bottom: -7px; border: 7px solid transparent; border-top-color: #fff; border-left-width: 0; }
        .lf-bubble .g { display: inline-block; white-space: pre; transition: opacity .5s ease, filter .5s ease, transform .5s ease; }
        .lf-bubble .g.lost { opacity: .07; filter: blur(3px); transform: translateY(2px); }
        .loud .lf-bubble { transform: scale(1.07); }
        .loud .lf-bubble .g.lost { opacity: 1; filter: none; transform: none; }
        /* loudness meter beside the daughter */
        .lf-meter { position: absolute; left: 1.5%; bottom: 4%; width: 9px; height: 52%; border-radius: 3px; background: rgba(60, 20, 26, .12);
            -webkit-mask: repeating-linear-gradient(0deg, #000 0 5px, transparent 5px 7px); mask: repeating-linear-gradient(0deg, #000 0 5px, transparent 5px 7px); }
        .lf-meter i { position: absolute; inset: 0; background: linear-gradient(0deg, #f7a8ae, #ee4651 55%, #a80f18); transform-origin: 50% 100%; transform: scaleY(.25);
            animation: lf-vol-soft .9s ease-in-out infinite; }
        .loud .lf-meter i { animation-name: lf-vol-loud; }
        @keyframes lf-vol-soft { 0%, 100% { transform: scaleY(.16); } 30% { transform: scaleY(.34); } 55% { transform: scaleY(.22); } 80% { transform: scaleY(.3); } }
        @keyframes lf-vol-loud { 0%, 100% { transform: scaleY(.78); } 30% { transform: scaleY(1); } 55% { transform: scaleY(.86); } 80% { transform: scaleY(.97); } }
        /* daughter */
        .g-arm { transform-origin: 50% 5%; transition: transform .4s ease; }
        .loud .g-arm { transform: rotate(-150deg); }
        .g-mouth { transform-origin: center; transition: transform .3s; }
        .loud .g-mouth { transform: scale(1.7, 2.6); }
        .g-girl { transform-origin: 50% 100%; transition: transform .4s ease; }
        .loud .g-girl { transform: rotate(4deg); }
        /* sound waves: soft ones die out halfway, loud ones reach Dad */
        .wv path { fill: none; stroke-linecap: round; opacity: 0; }
        .wv-soft path { stroke: #b9a3a9; stroke-width: 1.6; animation: lf-wave-soft 1.5s ease-out infinite; }
        .wv-loud path { stroke: var(--red); stroke-width: 2.6; animation: lf-wave-loud 1.2s ease-in infinite; animation-play-state: paused; }
        .wv path:nth-child(2) { animation-delay: .4s; } .wv path:nth-child(3) { animation-delay: .8s; }
        .wv-loud { opacity: 0; } .loud .wv-soft { opacity: 0; } .loud .wv-loud { opacity: 1; } .loud .wv-loud path { animation-play-state: running; }
        @keyframes lf-wave-soft { 0% { opacity: 0; transform: translateX(0); } 20% { opacity: .9; } 100% { opacity: 0; transform: translateX(60px); } }
        @keyframes lf-wave-loud { 0% { opacity: 0; transform: translateX(0) scale(.8); } 15% { opacity: 1; } 85% { opacity: 1; } 100% { opacity: 0; transform: translateX(150px) scale(1.25); } }
        /* dad */
        .d-head { transform-origin: 50% 55%; transition: transform .5s cubic-bezier(.4, 1.4, .5, 1); }
        .loud .d-head { transform: scaleX(-1); }
        .d-smile, .d-ring { opacity: 0; transition: opacity .4s; } .loud .d-smile { opacity: 1; } .loud .d-flat { opacity: 0; }
        .loud .d-ring { opacity: 1; animation: lf-ping 1.2s ease-out infinite; transform-origin: center; }
        .d-q { transform-origin: 50% 100%; animation: lf-bob 1.6s ease-in-out infinite; transition: opacity .3s; } .loud .d-q { opacity: 0; }
        @keyframes lf-ping { 0% { transform: scale(.75); opacity: .45; } 100% { transform: scale(1.35); opacity: 0; } }
        @keyframes lf-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
        .tv-flick { animation: lf-tv 2.6s steps(1) infinite; }
        @keyframes lf-tv { 0% { opacity: .1; } 25% { opacity: .35; } 45% { opacity: .05; } 70% { opacity: .25; } }
        .lf-caption { display: grid; margin: 4px 2px 0; font-size: 12.5px; font-weight: 500; line-height: 1.35; color: var(--muted); }
        .lf-caption span { grid-area: 1 / 1; transition: opacity .4s; }
        .lf-caption .c-loud { opacity: 0; color: var(--ink); } .loud .lf-caption .c-loud { opacity: 1; } .loud .lf-caption .c-soft { opacity: 0; }
        .lf-caption b { color: var(--red-dark); font-weight: 600; }

        h1 { font-size: 24px; font-weight: 700; letter-spacing: -.4px; line-height: 1.15; margin: 12px 6px 2px; }
        .lf-sub { font-size: 13px; color: var(--muted); margin: 0 6px 10px; line-height: 1.4; }
        form { padding: 0 6px; }
        .lf-field { margin-bottom: 9px; min-width: 0; }
        .lf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 330px) { .lf-row { grid-template-columns: 1fr; gap: 0; } }
        .lf-label { display: block; font-size: 12.5px; font-weight: 500; color: var(--muted); margin: 0 0 5px 4px; }
        .lf-in {
            width: 100%; height: 46px; border-radius: 14px; border: 1px solid var(--line); background: var(--field); color: var(--ink);
            font: 500 15px 'Poppins', 'Hind Siliguri', sans-serif; padding: 0 14px; outline: none; transition: border-color .15s, background .15s, box-shadow .15s;
        }
        textarea.lf-in { height: 70px; padding: 11px 14px; resize: none; }
        .lf-in::placeholder { color: var(--soft); }
        .lf-in:focus { border-color: var(--red); background: #fff; box-shadow: 0 0 0 3px rgba(211, 20, 31, .14); }
        .lf-field.has-error .lf-in { border-color: var(--err); }
        .lf-err { display: none; color: var(--err); font-size: 12px; font-weight: 500; margin: 4px 0 0 4px; }
        .lf-field.has-error .lf-err { display: block; }
        .lf-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .lf-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .lf-chip span { display: inline-block; border: 1px solid var(--line); background: var(--field); color: var(--ink); border-radius: 999px; padding: 6px 11px;
            font-size: 13px; font-weight: 500; cursor: pointer; user-select: none; transition: background .2s, color .2s, border-color .2s; }
        .lf-chip span:hover { border-color: var(--ink); }
        .lf-chip input:checked + span { background: var(--red); color: #fff; border-color: var(--red); }
        .lf-chip input:focus-visible + span { outline: 2px solid var(--ink); outline-offset: 2px; }
        .lf-note-link { background: none; border: 0; color: #8f0c14; font: 500 13px 'Poppins', 'Hind Siliguri', sans-serif; cursor: pointer; padding: 2px 4px; margin-bottom: 6px; }
        .lf-note { display: none; }
        .show-note .lf-note { display: block; }
        .show-note .lf-note-link { display: none; }
        .lf-consent { display: flex; gap: 9px; align-items: flex-start; font-size: 12px; line-height: 1.4; color: var(--muted); cursor: pointer; margin: 0 4px; }
        .lf-consent input { width: 17px; height: 17px; margin: 1px 0 0; flex: none; accent-color: var(--red); }
        .lf-consent em { font-style: normal; color: var(--muted); }
        .lf-hp { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; overflow: hidden; }
        .lf-alert { display: none; margin: 0 0 10px; padding: 9px 12px; border-radius: 12px; font-size: 13px; background: #fff1f2; border: 1px solid rgba(154, 11, 21, .35); color: var(--err); }
        .lf-alert.show { display: block; }
        .cf-turnstile { margin: 4px 0 8px; }

        .lf-foot { display: flex; flex-direction: column; align-items: center; }
        .lf-submit {
            margin-top: 10px; border: 0; border-radius: 999px; background: linear-gradient(135deg, #e01b27, var(--red-dark)); color: #fff; font: 600 16px 'Poppins', 'Hind Siliguri', sans-serif;
            padding: 13px 38px; cursor: pointer; box-shadow: 0 12px 26px rgba(211, 20, 31, .3), inset 0 1px 0 rgba(255, 255, 255, .25); display: inline-flex; align-items: center; gap: 10px;
            transition: transform .15s, box-shadow .15s;
        }
        .lf-submit:hover { transform: translateY(-1px); box-shadow: 0 16px 30px rgba(211, 20, 31, .36), inset 0 1px 0 rgba(255, 255, 255, .25); }
        .lf-submit:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; }
        .lf-submit[disabled] { opacity: .75; cursor: wait; transform: none; }
        .lf-submit svg { width: 18px; height: 18px; fill: #fff; }
        .lf-privacy { margin: 8px 0 0; font-size: 12px; color: var(--muted); }

        /* Thank-you: a specialist with a headset, a tick, a ringing phone and the three steps of what happens next */
        .lf-done { display: none; text-align: center; padding: 18px 10px 12px; }
        .sent .lf-done { display: block; animation: lf-in .4s ease; }
        .sent .lf-scene, .sent form, .sent h1, .sent .lf-sub { display: none; }
        .lf-agent { position: relative; width: 132px; height: 132px; margin: 4px auto 14px; }
        .lf-agent::before, .lf-agent::after { content: ''; position: absolute; inset: 6px; border-radius: 50%; border: 2px solid rgba(211, 20, 31, .35); animation: lf-call 2.4s ease-out infinite; }
        .lf-agent::after { animation-delay: 1.2s; }
        @keyframes lf-call { 0% { transform: scale(.85); opacity: 1; } 100% { transform: scale(1.35); opacity: 0; } }
        .lf-agent svg { position: relative; width: 100%; height: 100%; }
        .lf-agent .a-in { transform-box: fill-box; transform-origin: 50% 100%; animation: lf-rise .6s cubic-bezier(.2, .9, .3, 1.2) .1s both; }
        .lf-agent .a-tick-c { transform-box: fill-box; transform-origin: center; animation: lf-pop .45s cubic-bezier(.3, 1.6, .5, 1) .55s both; }
        .lf-agent .a-tick { stroke-dasharray: 20; stroke-dashoffset: 20; animation: lf-draw .35s ease-out .85s forwards; }
        .lf-agent .a-phone { transform-box: fill-box; transform-origin: center; animation: lf-pop .45s cubic-bezier(.3, 1.6, .5, 1) .3s both, lf-shake 1.2s ease-in-out 1.2s infinite; }
        @keyframes lf-rise { from { opacity: 0; transform: translateY(12px) scale(.9); } to { opacity: 1; transform: none; } }
        @keyframes lf-pop { from { transform: scale(0); } to { transform: scale(1); } }
        @keyframes lf-draw { to { stroke-dashoffset: 0; } }
        @keyframes lf-shake { 0%, 50%, 100% { transform: rotate(0); } 10%, 30% { transform: rotate(-14deg); } 20%, 40% { transform: rotate(14deg); } }
        .lf-done h2 { font-size: 23px; margin: 0 0 6px; letter-spacing: -.3px; }
        .lf-done p { color: var(--muted); font-size: 14px; line-height: 1.5; margin: 0 auto; max-width: 320px; }
        .lf-done p b { color: var(--ink); white-space: nowrap; }
        .lf-steps { list-style: none; padding: 0; margin: 16px auto 4px; max-width: 300px; text-align: left; }
        .lf-steps li { position: relative; display: flex; align-items: center; gap: 12px; padding: 7px 0; font-size: 13.5px; font-weight: 500; color: var(--muted);
            opacity: 0; animation: lf-rise .4s ease forwards; }
        .lf-steps li:nth-child(1) { animation-delay: 1s; } .lf-steps li:nth-child(2) { animation-delay: 1.3s; } .lf-steps li:nth-child(3) { animation-delay: 1.6s; }
        .lf-steps li:not(:last-child)::after { content: ''; position: absolute; left: 11px; top: 31px; height: 12px; border-left: 2px dotted var(--line); }
        .lf-steps i { flex: none; width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; border: 2px solid var(--line); background: #fff; }
        .lf-steps .ok { color: var(--ink); } .lf-steps .ok i { background: var(--red); border-color: var(--red); }
        .lf-steps .ok i::before { content: ''; width: 9px; height: 5px; border: solid #fff; border-width: 0 0 2px 2px; transform: translateY(-1px) rotate(-45deg); }
        .lf-steps .now { color: var(--ink); } .lf-steps .now i { border-color: var(--red); }
        .lf-steps .now i::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--red); animation: lf-pulse 1.2s ease-in-out infinite; }
        @keyframes lf-pulse { 50% { transform: scale(.5); opacity: .5; } }
        .lf-done-btn { display: inline-block; margin-top: 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); font: 600 14px 'Poppins', 'Hind Siliguri', sans-serif;
            padding: 10px 24px; border-radius: 999px; cursor: pointer; text-decoration: none; }

        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition: none !important; } }
        /* Short screens: shrink the scene first so the whole form still fits */
        @media (max-height: 700px) {
            .lf-stage { max-width: 300px; margin: 0 auto; }
            h1 { font-size: 22px; margin-top: 8px; }
            .lf-sub { margin-bottom: 8px; }
            .lf-in { height: 42px; }
            .lf-field { margin-bottom: 7px; }
            .lf-submit { padding: 11px 34px; margin-top: 6px; }
        }
        @media (max-height: 660px) { .lf-stage { max-width: 250px; } .lf-sub { display: none; } h1 { margin-bottom: 8px; } }
        @media (max-height: 580px) { .lf-stage { max-width: 210px; } .lf-caption { display: none; } }
    </style>
</head>
@php($needsConsent = \App\Support\Region::needsConsent())
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
        <div class="lf-lang" role="group" aria-label="Language"><button type="button" data-lang="en" aria-pressed="false">EN</button><button type="button" data-lang="bn" aria-pressed="true">বাংলা</button></div>
    </div>

    <section class="lf-scene" id="lf-scene" aria-label="How hearing loss can sound">
        <div class="lf-stage">
            <svg class="lf-art" viewBox="0 0 360 130" aria-hidden="true">
                <defs><linearGradient id="lf-tv" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#cfe0f2"/><stop offset="1" stop-color="#8fb0d6"/></linearGradient></defs>
                <ellipse cx="46" cy="125" rx="20" ry="3" fill="#3c141a" opacity=".1"/><ellipse cx="250" cy="125" rx="64" ry="4" fill="#3c141a" opacity=".1"/>
                {{-- TV --}}
                <path d="M300 62L258 46v58l42-8z" fill="#cfe0f2" opacity=".45"/>
                <rect x="298" y="104" width="58" height="20" rx="3" fill="#d9a3af"/><rect x="322" y="96" width="10" height="9" fill="#3a3540"/>
                <rect x="300" y="56" width="54" height="42" rx="5" fill="#2f2b36"/><rect x="304" y="60" width="46" height="34" rx="3" fill="url(#lf-tv)"/>
                <path d="M310 88l9-12 7 8 5-5 13 9z" fill="#fff" opacity=".55"/><circle cx="340" cy="68" r="3.5" fill="#fff" opacity=".7"/>
                <rect class="tv-flick" x="304" y="60" width="46" height="34" rx="3" fill="#fff"/>
                {{-- armchair --}}
                <rect x="198" y="58" width="20" height="64" rx="9" fill="#e7a9b6"/><rect x="206" y="82" width="58" height="12" rx="6" fill="#dc96a5"/>
                <rect x="198" y="98" width="90" height="18" rx="7" fill="#efbcc6"/><rect x="204" y="114" width="5" height="10" rx="1" fill="#b77a88"/><rect x="278" y="114" width="5" height="10" rx="1" fill="#b77a88"/>
                {{-- dad --}}
                <rect x="236" y="91" width="42" height="13" rx="6" fill="#4b5068"/><rect x="268" y="95" width="12" height="26" rx="5" fill="#4b5068"/>
                <path d="M266 119h19a3.5 3.5 0 010 7h-19z" fill="#2f2b36"/>
                <rect x="214" y="66" width="32" height="34" rx="12" fill="#7b9cc4"/>
                <rect x="232" y="79" width="28" height="9" rx="4.5" fill="#6d8db5"/><rect x="257" y="77" width="10" height="5" rx="2" fill="#2f2b36"/><circle cx="259" cy="84" r="4.5" fill="#c98d68"/>
                <rect x="227" y="58" width="10" height="11" rx="2" fill="#c98d68"/>
                <circle class="d-ring" cx="232" cy="50" r="19" fill="#f2545c" opacity="0"/>
                <g class="d-head">
                    <circle cx="232" cy="50" r="12.5" fill="#d9a27e"/>
                    <path d="M219.6 51a12.5 12.5 0 0118-11.6q-5 1-6.5 5-4.5-1-7 3-2 4-1 8-2.5-1.5-3.5-4.4z" fill="#b9b1b5"/>
                    <circle cx="229" cy="52" r="2.6" fill="#c98d68"/>
                    <circle cx="239" cy="48.5" r="3.6" fill="none" stroke="#2f2b36" stroke-width="1.2"/><circle cx="239.5" cy="48.5" r="1.3" fill="#2f2b36"/>
                    <path d="M243.5 48.5h1.4M244 51l1.8 2.6-1.8.6" fill="none" stroke="#a8745a" stroke-width="1.2" stroke-linecap="round"/>
                    <path d="M238.5 56.4q3-1.4 5.6 0" fill="none" stroke="#8d8589" stroke-width="2" stroke-linecap="round"/>
                    <path class="d-flat" d="M239.5 58.6h3.5" stroke="#7a3b35" stroke-width="1.3" stroke-linecap="round"/>
                    <path class="d-smile" d="M238.6 58.2q2.4 2.4 4.8 0" fill="none" stroke="#7a3b35" stroke-width="1.4" stroke-linecap="round"/>
                </g>
                <text class="d-q" x="250" y="40" font-size="15" font-weight="700" fill="#75656a" font-family="Poppins, sans-serif">?</text>
                {{-- sound waves --}}
                <g transform="translate(64 62)"><g class="wv wv-soft"><path d="M0-5q3 5 0 10"/><path d="M0-5q3 5 0 10"/><path d="M0-5q3 5 0 10"/></g>
                    <g class="wv wv-loud"><path d="M0-7q4 7 0 14"/><path d="M0-7q4 7 0 14"/><path d="M0-7q4 7 0 14"/></g></g>
                {{-- daughter --}}
                <g class="g-girl">
                    <rect x="38" y="103" width="6" height="21" rx="3" fill="#5a3d4a"/><rect x="47" y="103" width="6" height="21" rx="3" fill="#5a3d4a"/>
                    <rect x="35" y="77" width="6" height="22" rx="3" fill="#d9434d"/>
                    <path d="M36 75q10-6 20 0l6 33q-17 5-33 0z" fill="#f2545c"/>
                    <path d="M37 75q8 10 20 2l2 4q-12 9-23-2z" fill="#ffd1d6"/>
                    <rect x="42" y="67" width="6" height="9" fill="#c98d68"/>
                    <path d="M37 57q-9 5-5 18 4-5 7-11z" fill="#2e2226"/>
                    <circle cx="46" cy="59" r="10.5" fill="#d9a27e"/>
                    <path d="M35.6 59a10.5 10.5 0 0120.6-3.4q-8-3.6-13.6 1.6-2 6-6.4 8.4z" fill="#2e2226"/>
                    <circle cx="51" cy="58.5" r="1.3" fill="#2e2226"/><path d="M49.2 55.6l3-.6" stroke="#2e2226" stroke-width=".9" stroke-linecap="round"/>
                    <ellipse class="g-mouth" cx="54.2" cy="64" rx="1.5" ry=".9" fill="#8a2a30"/>
                    <g class="g-arm"><rect x="50" y="77" width="6" height="21" rx="3" fill="#f2545c"/><circle cx="53" cy="99" r="3.2" fill="#d9a27e"/></g>
                </g>
            </svg>
            <div class="lf-meter" aria-hidden="true"><i></i></div>
            <div class="lf-bubble"><span id="lf-line"></span></div>
        </div>
        <p class="lf-caption"><span class="c-soft" data-t="capSoft"></span><span class="c-loud" data-t="capLoud"></span></p>
    </section>

    <h1 data-t="title"></h1>
    <p class="lf-sub" data-t="sub"></p>

    <form id="lf-form" novalidate>
        <div class="lf-alert" id="lf-alert" role="alert"></div>

        <div class="lf-row">
            <div class="lf-field" data-field="name">
                <label class="lf-label" for="lf-name" data-t="name"></label>
                <input class="lf-in" type="text" id="lf-name" name="name" maxlength="191" autocomplete="name" required>
                <div class="lf-err"></div>
            </div>

            <div class="lf-field" data-field="phone">
                <label class="lf-label" for="lf-phone" data-t="phone"></label>
                <input class="lf-in" type="tel" id="lf-phone" name="phone" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="{{ \App\Support\Region::isBangladesh() ? '01XXXXXXXXX' : '+' . \App\Support\Region::phonePrefix() . ' …' }}" required>
                <div class="lf-err"></div>
            </div>
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

        <div class="lf-field" data-field="consent">
            <label class="lf-consent"><input type="checkbox" name="consent" value="1" id="lf-consent" @if ($needsConsent) required @endif><span><span data-t="consent"></span>@unless ($needsConsent) <em data-t="optional"></em>@endunless</span></label>
            <div class="lf-err"></div>
        </div>

        {{-- Honeypot: real people never see or fill this --}}
        <div class="lf-hp" aria-hidden="true"><label for="lf-website">Website</label><input type="text" id="lf-website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="t" value="{{ $token }}">
        @if ($turnstile)<div class="cf-turnstile" data-sitekey="{{ $turnstile }}" data-theme="light"></div>@endif

        <div class="lf-foot">
            <button type="submit" class="lf-submit" id="lf-submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.2 15.2 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1z"/></svg><span data-t="cta"></span></button>
            <p class="lf-privacy" data-t="private"></p>
        </div>
    </form>

    <section class="lf-done" id="lf-done" aria-live="polite">
        <div class="lf-agent" aria-hidden="true">
            <svg viewBox="0 0 132 132">
                <circle cx="66" cy="66" r="54" fill="#fff"/><circle cx="66" cy="66" r="54" fill="#f7d4da" opacity=".55"/>
                <g class="a-in">
                    <path d="M30 112q4-26 36-26t36 26a54 54 0 01-72 0z" fill="#7b9cc4"/>
                    <path d="M58 86l8 10 8-10z" fill="#fff"/>
                    <rect x="60" y="72" width="12" height="14" rx="3" fill="#c98d68"/>
                    <circle cx="66" cy="58" r="17" fill="#d9a27e"/>
                    <path d="M49 57q0-19 17-19t17 19q-4-9-12-10-6 6-22 10z" fill="#2e2226"/>
                    <circle cx="60" cy="60" r="1.7" fill="#2e2226"/><circle cx="72" cy="60" r="1.7" fill="#2e2226"/>
                    <path d="M61 67q5 4 10 0" fill="none" stroke="#7a3b35" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M47 59a19 19 0 0138 0" fill="none" stroke="#2f2b36" stroke-width="3.2"/>
                    <rect x="43" y="55" width="7" height="12" rx="3.5" fill="#2f2b36"/><rect x="82" y="55" width="7" height="12" rx="3.5" fill="#2f2b36"/>
                    <path d="M47 66q2 9 13 9" fill="none" stroke="#2f2b36" stroke-width="2.2" stroke-linecap="round"/><circle cx="61" cy="75" r="2.6" fill="#d3141f"/>
                </g>
                <g class="a-phone"><circle cx="26" cy="30" r="15" fill="#fff" stroke="#f0c5cb" stroke-width="1.5"/>
                    <path d="M20.5 27.2a9 9 0 004 4l1.4-1.3a.6.6 0 01.6-.15 6.8 6.8 0 002.1.33.6.6 0 01.6.6V33a.6.6 0 01-.6.6A10 10 0 0118.2 23.2a.6.6 0 01.6-.6h2.2a.6.6 0 01.6.6c0 .75.12 1.45.33 2.1a.6.6 0 01-.15.6z" fill="#d3141f"/></g>
                <g class="a-tick-c"><circle cx="104" cy="102" r="15" fill="#d3141f" stroke="#fff" stroke-width="3"/>
                    <path class="a-tick" d="M97.5 102.5l4.5 4.5 8.5-9" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></g>
            </svg>
        </div>
        <h2 id="lf-done-title"></h2>
        <p id="lf-done-text"></p>
        <ol class="lf-steps">
            <li class="ok"><i></i><span data-t="step1"></span></li>
            <li class="now"><i></i><span data-t="step2"></span></li>
            <li><i></i><span data-t="step3"></span></li>
        </ol>
        @if ($embedded)<button type="button" class="lf-done-btn js-lf-close" id="lf-done-close" data-t="close"></button>
        @else<a href="https://hearingstore.com.bd" class="lf-done-btn js-lf-back" id="lf-done-back" data-t="back"></a>@endif
    </section>
</main>

<script>
(function () {
    var BD = {{ \App\Support\Region::isBangladesh() ? 'true' : 'false' }}, NEEDS_CONSENT = {{ $needsConsent ? 'true' : 'false' }};
    var T = {
        en: {
            line: 'Dad, dinner is ready.', lost: [2, 5, 13, 18],
            capSoft: 'Normal voice: Dad misses the words.', capLoud: 'Only hears when she shouts? <b>A free check tells you why.</b>',
            title: 'Free hearing check', sub: 'Leave your number. A hearing specialist will call you back.',
            name: 'Your name', namePh: 'e.g. Rahim Uddin', phone: 'Mobile number', about: 'What is it about?',
            iq_hearing_test: 'Hearing test', iq_hearing_aid: 'Hearing aid', iq_repair: 'Repair', iq_other: 'Other',
            addNote: '+ Add a note (optional)', noteLabel: 'Anything we should know?', notePh: 'e.g. My father struggles on phone calls',
            cta: 'Call me back', sending: 'Sending…', private: 'Your details stay private.',
            doneTitle: 'Thank you{name}!', doneText: 'One of our hearing specialists will call you shortly{phone}.',
            step1: 'Request received', step2: 'A specialist is assigned to you', step3: 'We call you, within 15 minutes in office hours',
            close: 'Close', back: 'Back to website',
            consent: 'I agree to receive messages about hearing care and offers. I can say STOP at any time.', optional: '(optional)',
            errName: 'Please enter your name.', errPhone: BD ? 'Please enter a valid mobile number (e.g. 017XXXXXXXX).' : 'Please enter a valid mobile number with the country code.',
            errConsent: 'Please tick that you agree to be contacted.',
            errNet: 'Network problem. Please check your connection and try again.', errAny: 'Something went wrong. Please try again.'
        },
        bn: {
            line: 'আব্বু, খাবার রেডি।', lostChars: ['ব্ব', 'বা', 'ডি'],
            capSoft: 'স্বাভাবিক স্বরে বাবা কথা বুঝতে পারেন না।', capLoud: 'জোরে না বললে শোনেন না? <b>বিনামূল্যে পরীক্ষা করে দেখুন।</b>',
            title: 'বিনামূল্যে শ্রবণ পরীক্ষা', sub: 'আপনার নম্বর দিন, আমাদের বিশেষজ্ঞ আপনাকে কল করবেন।',
            name: 'আপনার নাম', namePh: 'যেমন: রহিম উদ্দিন', phone: 'মোবাইল নম্বর', about: 'কী বিষয়ে?',
            iq_hearing_test: 'শ্রবণ পরীক্ষা', iq_hearing_aid: 'হিয়ারিং এইড', iq_repair: 'মেরামত', iq_other: 'অন্যান্য',
            addNote: '+ কিছু লিখতে চাইলে', noteLabel: 'আমাদের কিছু জানাতে চান?', notePh: 'যেমন: বাবা ফোনে কথা বুঝতে পারেন না',
            cta: 'আমাকে কল করুন', sending: 'পাঠানো হচ্ছে…', private: 'আপনার তথ্য গোপন থাকবে।',
            doneTitle: 'ধন্যবাদ{name}!', doneText: 'আমাদের একজন শ্রবণ বিশেষজ্ঞ শীঘ্রই{phone} আপনাকে কল করবেন।',
            step1: 'আপনার অনুরোধ পেয়েছি', step2: 'একজন বিশেষজ্ঞ দায়িত্ব নিয়েছেন', step3: 'অফিস সময়ে ১৫ মিনিটের মধ্যে কল',
            close: 'বন্ধ করুন', back: 'ওয়েবসাইটে ফিরে যান',
            consent: 'শ্রবণ সেবা ও অফারের বিষয়ে বার্তা পেতে আমি সম্মত। যেকোনো সময় STOP লিখে বন্ধ করতে পারব।', optional: '(ঐচ্ছিক)',
            errName: 'অনুগ্রহ করে আপনার নাম লিখুন।', errPhone: BD ? 'সঠিক মোবাইল নম্বর লিখুন (যেমন 017XXXXXXXX)।' : 'দেশের কোডসহ সঠিক মোবাইল নম্বর লিখুন।',
            errConsent: 'যোগাযোগের জন্য সম্মতির ঘরে টিক দিন।',
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
    var lang = qs.get('lang') === 'en' ? 'en' : 'bn';   // Bangla first; ?lang=en or the visitor's own choice switches
    var sentName = '', sentPhone = '';
    try { lang = localStorage.getItem('crmlf-lang') || lang; } catch (e) {}

    document.querySelectorAll('.js-lf-close').forEach(function (b) { b.addEventListener('click', function () { post('close'); }); });
    // Direct link: "back" returns to the page the visitor came from (when it was another site), else the Hearing Store home page.
    var back = qs.get('ref') || (document.referrer && document.referrer.indexOf(location.host) === -1 ? document.referrer : '');
    if (back && /^https?:\/\//.test(back)) { document.querySelectorAll('.js-lf-back').forEach(function (a) { a.href = back; }); }

    function graphemes(text) {
        if (window.Intl && Intl.Segmenter) { return Array.from(new Intl.Segmenter(lang, { granularity: 'grapheme' }).segment(text), function (s) { return s.segment; }); }
        return Array.from(text);
    }
    // Thank-you text with the visitor's first name and number (set as text, never as HTML)
    function fillDone() {
        var t = T[lang], title = document.getElementById('lf-done-title'), text = document.getElementById('lf-done-text');
        var first = sentName.split(/\s+/)[0];
        title.textContent = t.doneTitle.replace('{name}', first ? ', ' + first : '');
        var parts = t.doneText.split('{phone}');
        text.textContent = parts[0];
        if (sentPhone) { text.appendChild(document.createTextNode(lang === 'bn' ? ' ' : ' on ')); var b = document.createElement('b'); b.textContent = sentPhone; text.appendChild(b); if (lang === 'bn') { text.appendChild(document.createTextNode(' নম্বরে')); } }
        text.appendChild(document.createTextNode(parts[1] || ''));
    }
    function render() {
        var t = T[lang];
        document.documentElement.lang = lang;
        document.querySelectorAll('[data-t]').forEach(function (el) { if (t[el.dataset.t] !== undefined) { el.textContent = t[el.dataset.t]; } });
        // captions carry a bold part: our own fixed strings above, so innerHTML is safe here
        document.querySelectorAll('.lf-caption [data-t]').forEach(function (el) { el.innerHTML = t[el.dataset.t]; });
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
        fillDone();
    }
    document.querySelectorAll('.lf-lang button').forEach(function (b) {
        b.addEventListener('click', function () { lang = b.dataset.lang; try { localStorage.setItem('crmlf-lang', lang); } catch (e) {} render(); });
    });
    document.getElementById('lf-note-link').addEventListener('click', function () { form.classList.add('show-note'); document.getElementById('lf-message').focus(); });
    render();

    // Loop: she speaks softly (Dad misses it), then loudly (he hears and turns). Reduced motion: show the soft state only.
    if (!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) {
        var cycle = function () { scene.classList.remove('loud'); setTimeout(function () { scene.classList.add('loud'); }, 3000); };
        cycle(); setInterval(cycle, 6800);
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
        if (BD ? !/^(\+?88)?01[3-9]\d{8}$/.test(phone) : !/^\+?\d{8,15}$/.test(phone)) { errs.phone = [t.errPhone]; }
        if (NEEDS_CONSENT && !document.getElementById('lf-consent').checked) { errs.consent = [t.errConsent]; }
        if (Object.keys(errs).length) { showError('', errs); return; }

        var data = new FormData(form);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'].forEach(function (k) { if (qs.get(k)) { data.append(k, qs.get(k)); } });
        if (qs.get('c') && !qs.get('utm_campaign')) {   // campaign QR code / printed link
            data.append('utm_campaign', qs.get('c'));
            if (!qs.get('utm_source')) { data.append('utm_source', 'qr'); data.append('utm_medium', 'offline'); }
        }
        data.append('page_url', qs.get('ref') || document.referrer || location.href);
        data.append('landing_page', qs.get('lp') || qs.get('ref') || location.href);
        if (qs.get('rf') || (document.referrer && document.referrer.indexOf(location.host) === -1)) { data.append('referrer_url', qs.get('rf') || document.referrer); }

        btn.disabled = true;
        btn.querySelector('span').textContent = t.sending;
        fetch(location.pathname, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, message: t.errAny }; }); })
            .then(function (res) {
                if (!res.ok) { throw res; }
                sentName = name; sentPhone = phone;
                fillDone();
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
