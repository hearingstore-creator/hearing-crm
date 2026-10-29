/*!
 * Hearing Store – Online Hearing Test (screening) v1.0
 * Plain JavaScript + Web Audio API, no dependencies.
 *
 * Embed:
 *   <link rel="stylesheet" href="/hearing-test/hearing-test.css">
 *   <div id="hearing-test"></div>
 *   <script src="/hearing-test/hearing-test.js" data-lang="en" data-phone="+8801XXXXXXXXX" defer></script>
 *
 * Screening only: browsers and headphones are not calibrated, so levels are approximate dB HL.
 */
(function () {
    'use strict';

    var script = document.currentScript || document.querySelector('script[src*="hearing-test.js"]');
    var ds = (script && script.dataset) || {};

    var CONFIG = {
        target: ds.target || '#hearing-test',
        lang: ds.lang === 'bn' ? 'bn' : 'en',
        phone: ds.phone || '',                 // tel: link, e.g. +8801XXXXXXXXX
        whatsapp: ds.whatsapp || '',           // digits only, e.g. 8801XXXXXXXXX
        bookingUrl: ds.bookingUrl || '',       // e.g. /book-appointment or "#crm-lead-form"
        leadUrl: ds.leadUrl || '',             // website route that forwards to the CRM (see README); empty = no form
        // Test order follows clinical practice: 1 kHz first, up through the highs, then 500 Hz.
        freqs: (ds.freqs || '1000,2000,4000,8000,500').split(',').map(Number).filter(Boolean),
        startLevel: 40,
        minLevel: -10,
        maxLevel: 85,
        maxTrialsPerFreq: 20,
        catchRate: 0.12
    };

    // Level model: the volume step sets CAL_LEVEL dB HL at 1 kHz to CAL_AMP of full scale.
    // CORR = typical headphone reference threshold offsets relative to 1 kHz (RETSPL shape, TDH-39).
    var CAL_LEVEL = 60;
    var CAL_AMP = 0.05;
    var CORR = { 250: 18.5, 500: 4.5, 1000: 0, 2000: 2, 3000: 2.5, 4000: 2.5, 6000: 8.5, 8000: 6 };
    var PTA_FREQS = [500, 1000, 2000, 4000];
    var CHART_FREQS = [250, 500, 1000, 2000, 4000, 8000];

    var TEXT = {
        en: {
            title: 'Online Hearing Test',
            subtitle: 'A free 5-minute hearing check',
            introLead: 'Find out in about 5 minutes if you should get your hearing professionally checked.',
            need1: 'Headphones or earphones (wired works best)',
            need2: 'A quiet room',
            need3: 'Phone silent mode OFF',
            disclaimer: 'This is a screening, not a medical diagnosis. Results are approximate. If your hearing changed suddenly, or you have ear pain, discharge or dizziness, see a doctor as soon as possible.',
            agree: 'I understand this is a screening test only',
            start: 'Start test',
            next: 'Next',
            back: 'Back',
            step: 'Step {n} of 4',
            lrTitle: 'Check your headphones',
            lrText: 'Put on your headphones. Tap Play, then tell us which side you heard the beep.',
            play: 'Play sound',
            playing: 'Playing…',
            left: 'Left',
            right: 'Right',
            cantTell: 'Both / not sure',
            lrWrong: 'That did not match. Check the headphones are on the correct ears (L on left, R on right), not in mono mode, and try again.',
            lrOk: 'Headphones look good!',
            volTitle: 'Set your volume',
            volText: 'Tap Play. Adjust your device volume until the beeps are clear and comfortable – about as loud as normal speech. Do not make it loud.',
            volKeep: 'After this step, do not change the volume until the test ends.',
            volCant: 'Can\'t hear anything? Turn your volume up, switch off silent mode, and check your headphones are connected.',
            volDone: 'Volume is set',
            testTitle: 'Testing your {ear} ear',
            earRight: 'right',
            earLeft: 'left',
            testText: 'You will hear soft beeps, some very quiet. Tap the button (or press Space) every time you hear one – even if it is very faint.',
            ready: 'I\'m ready',
            hear: 'I hear it',
            pause: 'Pause',
            resume: 'Resume',
            paused: 'Paused',
            listening: 'Listen carefully…',
            switchEar: 'Right ear done. Now the left ear.',
            qTitle: 'A few quick questions',
            q1: 'Do you often ask people to repeat themselves?',
            q2: 'Is it hard to follow conversations in noisy places?',
            q3: 'Do people say your TV or phone volume is too loud?',
            q4: 'Do you hear ringing or buzzing in your ears (tinnitus)?',
            q5: 'Do people seem to mumble when they speak?',
            yes: 'Yes',
            no: 'No',
            seeResults: 'See my results',
            resTitle: 'Your results',
            rightEar: 'Right ear',
            leftEar: 'Left ear',
            avg: 'Average: about {db} dB',
            band_normal: 'Normal',
            band_mild: 'Mild loss',
            band_moderate: 'Moderate loss',
            band_significant: 'Significant loss',
            sum_normal: 'Your hearing looks to be within the normal range. We recommend a hearing check once a year.',
            sum_mild: 'You may have a mild hearing loss. Soft voices and conversations in noise can be harder to follow. A professional hearing test is recommended.',
            sum_moderate: 'You may have a moderate hearing loss. This often affects everyday conversations. Please book a professional hearing test.',
            sum_significant: 'You may have a significant hearing loss. Please book a professional hearing test soon – help is available.',
            flagHigh: 'Some high-pitch sounds were harder to hear. This is often the first sign of hearing loss.',
            flagAsym: 'Your two ears gave quite different results. Uneven hearing should be checked by a professional.',
            flagQs: 'Your answers suggest you notice hearing difficulty in daily life.',
            flagUnreliable: 'Some responses were inconsistent, so these results may not be accurate. Try again in a quieter place, or visit us for a proper test.',
            chartTitle: 'Approximate audiogram',
            chartX: 'Pitch (Hz)',
            chartY: 'Hearing level (dB)',
            legendR: 'Right (O)',
            legendL: 'Left (X)',
            note: 'Online results are an estimate only. Only a test in a sound-treated room by a professional can confirm your hearing level.',
            ctaTitle: 'Book a free professional hearing test',
            call: 'Call us',
            whatsapp: 'WhatsApp',
            book: 'Book appointment',
            formName: 'Your name',
            formPhone: 'Mobile number',
            formConsent: 'Please contact me about my results',
            formSend: 'Request a call back',
            formSending: 'Sending…',
            formOk: 'Thank you! Our team will call you soon.',
            formErr: 'Something went wrong. Please call us instead.',
            formInvalid: 'Please enter your name and a valid mobile number.',
            print: 'Print / save results',
            retake: 'Take the test again',
            noAudio: 'Sorry, your browser does not support audio playback. Please try Chrome, Safari or Edge.',
            waMsg: 'Hi, I just took the online hearing test. Result: {r}. I would like to book a hearing test.'
        },
        bn: {
            title: 'অনলাইন হিয়ারিং টেস্ট',
            subtitle: '৫ মিনিটের বিনামূল্যে শ্রবণ পরীক্ষা',
            introLead: 'মাত্র ৫ মিনিটে জেনে নিন আপনার শ্রবণশক্তি পেশাদারভাবে পরীক্ষা করানো দরকার কিনা।',
            need1: 'হেডফোন বা ইয়ারফোন (তারযুক্ত হলে ভালো)',
            need2: 'একটি নিরিবিলি ঘর',
            need3: 'ফোনের সাইলেন্ট মোড বন্ধ রাখুন',
            disclaimer: 'এটি একটি প্রাথমিক স্ক্রিনিং, চিকিৎসা নির্ণয় নয়। ফলাফল আনুমানিক। হঠাৎ শ্রবণশক্তি কমে গেলে, অথবা কানে ব্যথা, পুঁজ বা মাথা ঘোরা থাকলে যত দ্রুত সম্ভব ডাক্তার দেখান।',
            agree: 'আমি বুঝেছি এটি শুধুমাত্র একটি স্ক্রিনিং টেস্ট',
            start: 'টেস্ট শুরু করুন',
            next: 'পরবর্তী',
            back: 'পেছনে',
            step: 'ধাপ {n} / ৪',
            lrTitle: 'হেডফোন পরীক্ষা করুন',
            lrText: 'হেডফোন পরে নিন। "শব্দ বাজান" চাপুন, তারপর বলুন কোন পাশে বিপ শুনলেন।',
            play: 'শব্দ বাজান',
            playing: 'বাজছে…',
            left: 'বাম',
            right: 'ডান',
            cantTell: 'দুই পাশে / নিশ্চিত নই',
            lrWrong: 'মিলল না। হেডফোন সঠিক কানে আছে কিনা দেখুন (L বাম কানে, R ডান কানে), মোনো মোড বন্ধ রাখুন, তারপর আবার চেষ্টা করুন।',
            lrOk: 'হেডফোন ঠিক আছে!',
            volTitle: 'ভলিউম ঠিক করুন',
            volText: '"শব্দ বাজান" চাপুন। ডিভাইসের ভলিউম এমনভাবে ঠিক করুন যাতে বিপ শব্দ পরিষ্কার ও আরামদায়ক শোনায় – স্বাভাবিক কথাবার্তার মতো। বেশি জোরে দেবেন না।',
            volKeep: 'এই ধাপের পর টেস্ট শেষ না হওয়া পর্যন্ত ভলিউম পরিবর্তন করবেন না।',
            volCant: 'কিছু শুনতে পাচ্ছেন না? ভলিউম বাড়ান, সাইলেন্ট মোড বন্ধ করুন এবং হেডফোন সংযুক্ত আছে কিনা দেখুন।',
            volDone: 'ভলিউম ঠিক আছে',
            testTitle: 'আপনার {ear} কান পরীক্ষা',
            earRight: 'ডান',
            earLeft: 'বাম',
            testText: 'আপনি মৃদু বিপ শব্দ শুনবেন, কিছু খুবই আস্তে। যখনই শুনবেন বাটনটি চাপুন (বা Space চাপুন) – খুব আস্তে শুনলেও।',
            ready: 'আমি প্রস্তুত',
            hear: 'শুনতে পাচ্ছি',
            pause: 'বিরতি',
            resume: 'আবার শুরু',
            paused: 'বিরতি চলছে',
            listening: 'মনোযোগ দিয়ে শুনুন…',
            switchEar: 'ডান কান শেষ। এবার বাম কান।',
            qTitle: 'কয়েকটি ছোট প্রশ্ন',
            q1: 'আপনি কি প্রায়ই অন্যদের কথা আবার বলতে বলেন?',
            q2: 'কোলাহলপূর্ণ জায়গায় কথাবার্তা বুঝতে কি কষ্ট হয়?',
            q3: 'অন্যরা কি বলে আপনার টিভি বা ফোনের ভলিউম বেশি?',
            q4: 'আপনি কি কানে ঝিঁঝিঁ বা ভোঁ ভোঁ শব্দ শোনেন (টিনিটাস)?',
            q5: 'মনে হয় কি মানুষ অস্পষ্টভাবে কথা বলে?',
            yes: 'হ্যাঁ',
            no: 'না',
            seeResults: 'ফলাফল দেখুন',
            resTitle: 'আপনার ফলাফল',
            rightEar: 'ডান কান',
            leftEar: 'বাম কান',
            avg: 'গড়: প্রায় {db} dB',
            band_normal: 'স্বাভাবিক',
            band_mild: 'সামান্য শ্রবণ হ্রাস',
            band_moderate: 'মাঝারি শ্রবণ হ্রাস',
            band_significant: 'উল্লেখযোগ্য শ্রবণ হ্রাস',
            sum_normal: 'আপনার শ্রবণশক্তি স্বাভাবিক সীমার মধ্যে আছে বলে মনে হচ্ছে। বছরে একবার শ্রবণ পরীক্ষা করানোর পরামর্শ দিই।',
            sum_mild: 'আপনার সামান্য শ্রবণ হ্রাস থাকতে পারে। মৃদু কণ্ঠস্বর ও কোলাহলে কথা বুঝতে অসুবিধা হতে পারে। একটি পেশাদার শ্রবণ পরীক্ষা করানোর পরামর্শ দেওয়া হচ্ছে।',
            sum_moderate: 'আপনার মাঝারি শ্রবণ হ্রাস থাকতে পারে, যা দৈনন্দিন কথাবার্তায় প্রভাব ফেলে। অনুগ্রহ করে একটি পেশাদার শ্রবণ পরীক্ষা বুক করুন।',
            sum_significant: 'আপনার উল্লেখযোগ্য শ্রবণ হ্রাস থাকতে পারে। অনুগ্রহ করে শীঘ্রই একটি পেশাদার শ্রবণ পরীক্ষা বুক করুন – সমাধান আছে।',
            flagHigh: 'কিছু উঁচু সুরের শব্দ শুনতে কষ্ট হয়েছে। এটি প্রায়ই শ্রবণ হ্রাসের প্রথম লক্ষণ।',
            flagAsym: 'আপনার দুই কানের ফলাফলে বেশ পার্থক্য আছে। অসমান শ্রবণশক্তি একজন বিশেষজ্ঞকে দিয়ে পরীক্ষা করানো উচিত।',
            flagQs: 'আপনার উত্তর থেকে মনে হচ্ছে দৈনন্দিন জীবনে আপনি শুনতে অসুবিধা অনুভব করেন।',
            flagUnreliable: 'কিছু উত্তর অসামঞ্জস্যপূর্ণ ছিল, তাই ফলাফল সঠিক নাও হতে পারে। নিরিবিলি জায়গায় আবার চেষ্টা করুন, অথবা সঠিক পরীক্ষার জন্য আমাদের কাছে আসুন।',
            chartTitle: 'আনুমানিক অডিওগ্রাম',
            chartX: 'সুর (Hz)',
            chartY: 'শ্রবণ মাত্রা (dB)',
            legendR: 'ডান (O)',
            legendL: 'বাম (X)',
            note: 'অনলাইন ফলাফল শুধুমাত্র একটি অনুমান। সাউন্ডপ্রুফ কক্ষে বিশেষজ্ঞের পরীক্ষাই আপনার সঠিক শ্রবণ মাত্রা নিশ্চিত করতে পারে।',
            ctaTitle: 'বিনামূল্যে পেশাদার শ্রবণ পরীক্ষা বুক করুন',
            call: 'কল করুন',
            whatsapp: 'হোয়াটসঅ্যাপ',
            book: 'অ্যাপয়েন্টমেন্ট নিন',
            formName: 'আপনার নাম',
            formPhone: 'মোবাইল নম্বর',
            formConsent: 'আমার ফলাফল নিয়ে আমার সাথে যোগাযোগ করুন',
            formSend: 'কল ব্যাক চাই',
            formSending: 'পাঠানো হচ্ছে…',
            formOk: 'ধন্যবাদ! আমাদের টিম শীঘ্রই আপনাকে কল করবে।',
            formErr: 'কিছু সমস্যা হয়েছে। অনুগ্রহ করে আমাদের কল করুন।',
            formInvalid: 'অনুগ্রহ করে আপনার নাম ও সঠিক মোবাইল নম্বর দিন।',
            print: 'ফলাফল প্রিন্ট / সেভ করুন',
            retake: 'আবার টেস্ট দিন',
            noAudio: 'দুঃখিত, আপনার ব্রাউজার অডিও সমর্থন করে না। Chrome, Safari বা Edge ব্যবহার করুন।',
            waMsg: 'হ্যালো, আমি অনলাইন হিয়ারিং টেস্ট দিয়েছি। ফলাফল: {r}। আমি একটি শ্রবণ পরীক্ষা বুক করতে চাই।'
        }
    };

    var LOGO_SVG = '<svg class="ht-logo" viewBox="0 0 64 48" aria-hidden="true">' +
        [[4, 18, 12], [13, 10, 28], [22, 4, 40], [31, 16, 16], [40, 0, 48], [49, 8, 32], [58, 18, 12]]
            .map(function (b) { return '<rect x="' + b[0] + '" y="' + b[1] + '" width="5" height="' + b[2] + '" rx="2.5"/>'; }).join('') +
        '</svg>';

    var root, lang = CONFIG.lang, state;

    function t(key, vars) {
        var s = (TEXT[lang] && TEXT[lang][key]) || TEXT.en[key] || key;
        if (vars) {
            Object.keys(vars).forEach(function (k) { s = s.replace('{' + k + '}', vars[k]); });
        }
        return s;
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
    function rand(a, b) { return a + Math.random() * (b - a); }

    /* ---------------- Audio engine ---------------- */

    var Audio = {
        ctx: null,
        init: function () {
            if (!this.ctx) {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) { return false; }
                this.ctx = new AC();
            }
            if (this.ctx.state === 'suspended') { this.ctx.resume(); }
            return true;
        },
        amp: function (freq, level) {
            var a = CAL_AMP * Math.pow(10, (level - CAL_LEVEL + (CORR[freq] || 0)) / 20);
            return Math.min(a, 1);
        },
        maxLevel: function (freq) {
            var max = CAL_LEVEL - (CORR[freq] || 0) + 20 * Math.log10(1 / CAL_AMP);
            return Math.min(CONFIG.maxLevel, Math.floor(max / 5) * 5);
        },
        // Three 225 ms pulses with 20 ms ramps; ear: 'L', 'R' or 'both'. Returns tone duration in ms.
        pulses: function (freq, ear, level, count) {
            var ctx = this.ctx, on = 0.225, period = 0.45, ramp = 0.02;
            count = count || 3;
            var t0 = ctx.currentTime + 0.05, a = this.amp(freq, level);
            var osc = ctx.createOscillator(), gain = ctx.createGain(), merger = ctx.createChannelMerger(2);
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, t0);
            for (var i = 0; i < count; i++) {
                var s = t0 + i * period;
                gain.gain.setValueAtTime(0, s);
                gain.gain.linearRampToValueAtTime(a, s + ramp);
                gain.gain.setValueAtTime(a, s + on - ramp);
                gain.gain.linearRampToValueAtTime(0, s + on);
            }
            osc.connect(gain);
            if (ear !== 'R') { gain.connect(merger, 0, 0); }
            if (ear !== 'L') { gain.connect(merger, 0, 1); }
            merger.connect(ctx.destination);
            var end = t0 + (count - 1) * period + on + 0.02;
            osc.start(t0);
            osc.stop(end);
            osc.onended = function () { osc.disconnect(); gain.disconnect(); merger.disconnect(); };
            return Math.round((end - ctx.currentTime) * 1000);
        }
    };

    /* ---------------- Rendering helpers ---------------- */

    function render(html) {
        root.innerHTML =
            '<div class="ht-card">' +
            '<div class="ht-head">' + LOGO_SVG +
            '<div class="ht-head-text"><div class="ht-title">' + t('title') + '</div><div class="ht-sub">' + t('subtitle') + '</div></div>' +
            '<div class="ht-lang">' +
            '<button type="button" data-lang="en" class="' + (lang === 'en' ? 'on' : '') + '">EN</button>' +
            '<button type="button" data-lang="bn" class="' + (lang === 'bn' ? 'on' : '') + '">বাংলা</button>' +
            '</div></div>' +
            '<div class="ht-body">' + html + '</div></div>';
        root.lang = lang;
        root.querySelectorAll('.ht-lang button').forEach(function (b) {
            b.addEventListener('click', function () {
                if (state.running) { return; } // don't re-render mid-test
                lang = b.getAttribute('data-lang');
                state.redraw();
            });
            b.disabled = !!state.running;
        });
    }

    function steps(n) {
        return '<div class="ht-steps"><div class="ht-steps-label">' + t('step', { n: lang === 'bn' ? bnDigits(n) : n }) + '</div>' +
            '<div class="ht-bar"><span style="width:' + (n * 25) + '%"></span></div></div>';
    }

    function bnDigits(s) {
        return String(s).replace(/\d/g, function (d) { return '০১২৩৪৫৬৭৮৯'[d]; });
    }

    function $(sel) { return root.querySelector(sel); }
    function on(sel, ev, fn) { var el = $(sel); if (el) { el.addEventListener(ev, fn); } }

    /* ---------------- Screens ---------------- */

    function screenIntro() {
        state.redraw = screenIntro;
        render(
            '<p class="ht-lead">' + t('introLead') + '</p>' +
            '<ul class="ht-needs"><li>🎧 ' + t('need1') + '</li><li>🤫 ' + t('need2') + '</li><li>🔔 ' + t('need3') + '</li></ul>' +
            '<div class="ht-disclaimer">' + t('disclaimer') + '</div>' +
            '<label class="ht-check"><input type="checkbox" id="ht-agree"> <span>' + t('agree') + '</span></label>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary" id="ht-start" disabled>' + t('start') + '</button></div>'
        );
        on('#ht-agree', 'change', function (e) { $('#ht-start').disabled = !e.target.checked; });
        on('#ht-start', 'click', function () {
            if (!Audio.init()) { render('<p class="ht-error">' + t('noAudio') + '</p>'); return; }
            state.startedAt = Date.now();
            screenLR();
        });
    }

    // Left/right check: two beeps, one per ear in random order; both must be identified.
    function screenLR(msg) {
        state.redraw = function () { screenLR(msg); };
        var seq = Math.random() < 0.5 ? ['L', 'R'] : ['R', 'L'];
        var idx = 0;
        render(steps(1) +
            '<h3>' + t('lrTitle') + '</h3><p>' + t('lrText') + '</p>' +
            (msg ? '<div class="ht-msg ' + (msg === 'lrOk' ? 'ok' : 'warn') + '">' + t(msg) + '</div>' : '') +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary" id="ht-play">🔊 ' + t('play') + ' (1/2)</button></div>' +
            '<div class="ht-choices" id="ht-lr" hidden>' +
            '<button type="button" class="ht-btn" data-ans="L">◀ ' + t('left') + '</button>' +
            '<button type="button" class="ht-btn" data-ans="both">' + t('cantTell') + '</button>' +
            '<button type="button" class="ht-btn" data-ans="R">' + t('right') + ' ▶</button></div>' +
            '<div class="ht-actions ht-split"><button type="button" class="ht-btn ht-ghost" id="ht-back">' + t('back') + '</button></div>'
        );
        on('#ht-back', 'click', screenIntro);
        on('#ht-play', 'click', function () {
            var btn = $('#ht-play');
            btn.disabled = true;
            btn.textContent = t('playing');
            Audio.init();
            var ms = Audio.pulses(1000, seq[idx], 45, 3);
            setTimeout(function () { $('#ht-lr').hidden = false; }, ms);
        });
        root.querySelectorAll('#ht-lr button').forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.getAttribute('data-ans') !== seq[idx]) { screenLR('lrWrong'); return; }
                idx++;
                if (idx >= seq.length) { screenVolume('lrOk'); return; }
                $('#ht-lr').hidden = true;
                var btn = $('#ht-play');
                btn.disabled = false;
                btn.textContent = '🔊 ' + t('play') + ' (2/2)';
            });
        });
    }

    function screenVolume(msg) {
        state.redraw = function () { screenVolume(); };
        var timer = null;
        render(steps(2) +
            (msg ? '<div class="ht-msg ok">' + t(msg) + '</div>' : '') +
            '<h3>' + t('volTitle') + '</h3><p>' + t('volText') + '</p>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary" id="ht-play">🔊 ' + t('play') + '</button></div>' +
            '<p class="ht-hint">' + t('volCant') + '</p>' +
            '<div class="ht-msg warn">' + t('volKeep') + '</div>' +
            '<div class="ht-actions ht-split"><button type="button" class="ht-btn ht-ghost" id="ht-back">' + t('back') + '</button>' +
            '<button type="button" class="ht-btn ht-primary" id="ht-done">' + t('volDone') + '</button></div>'
        );
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        on('#ht-play', 'click', function () {
            var btn = $('#ht-play');
            if (timer) { stop(); btn.textContent = '🔊 ' + t('play'); return; }
            Audio.init();
            Audio.pulses(1000, 'both', CAL_LEVEL, 3);
            timer = setInterval(function () { Audio.pulses(1000, 'both', CAL_LEVEL, 3); }, 2000);
            btn.textContent = '⏹ ' + t('playing');
        });
        on('#ht-back', 'click', function () { stop(); screenLR(); });
        on('#ht-done', 'click', function () { stop(); screenTestIntro('R'); });
    }

    function screenTestIntro(ear) {
        state.redraw = function () { screenTestIntro(ear); };
        render(steps(3) +
            (ear === 'L' ? '<div class="ht-msg ok">' + t('switchEar') + '</div>' : '') +
            '<h3>' + t('testTitle', { ear: t(ear === 'R' ? 'earRight' : 'earLeft') }) + '</h3>' +
            '<p>' + t('testText') + '</p>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary" id="ht-ready">' + t('ready') + '</button></div>'
        );
        on('#ht-ready', 'click', function () { runEar(ear); });
    }

    /* ---------------- Test procedure ---------------- */

    // Simplified Hughson-Westlake: down 10 after a response, up 5 after a miss;
    // threshold = lowest level heard on 2 ascending presentations.
    function runEar(ear) {
        state.running = true;
        var freqs = CONFIG.freqs.slice();
        if (ear === 'R') { freqs.push('retest'); }
        var total = CONFIG.freqs.length * 2 + 1;
        var done = ear === 'R' ? 0 : CONFIG.freqs.length + 1;

        state.redraw = function () {}; // language buttons disabled while running
        render(steps(3) +
            '<h3>' + t('testTitle', { ear: t(ear === 'R' ? 'earRight' : 'earLeft') }) + '</h3>' +
            '<div class="ht-ear ht-ear-' + ear + '">' + (ear === 'R' ? t('rightEar') : t('leftEar')) + '</div>' +
            '<div class="ht-bar ht-bar-test"><span id="ht-prog"></span></div>' +
            '<p class="ht-status" id="ht-status">' + t('listening') + '</p>' +
            '<button type="button" class="ht-hear" id="ht-hear">' + t('hear') + '</button>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-ghost" id="ht-pause">' + t('pause') + '</button></div>'
        );
        var prog = $('#ht-prog');
        var hearBtn = $('#ht-hear');
        function setProg() { prog.style.width = Math.round(done / total * 100) + '%'; }
        setProg();

        function press(e) {
            if (e) { e.preventDefault(); }
            if (state.paused) { return; }
            hearBtn.classList.remove('flash');
            void hearBtn.offsetWidth;
            hearBtn.classList.add('flash');
            if (state.window) { state.window.heard = true; } else { state.stray++; }
        }
        hearBtn.addEventListener('pointerdown', press);
        state.keyHandler = function (e) {
            if (e.code === 'Space' || e.key === ' ') { press(e); }
        };
        document.addEventListener('keydown', state.keyHandler);
        on('#ht-pause', 'click', function () {
            state.paused = !state.paused;
            $('#ht-pause').textContent = state.paused ? t('resume') : t('pause');
            $('#ht-status').textContent = state.paused ? t('paused') : t('listening');
            hearBtn.disabled = state.paused;
        });

        (async function () {
            for (var i = 0; i < freqs.length; i++) {
                var f = freqs[i];
                if (f === 'retest') {
                    var r = await threshold(1000, ear);
                    state.retest = { first: state.results.R[1000].db, second: r.db };
                    if (!r.noResp && r.db < state.results.R[1000].db) { state.results.R[1000] = r; }
                } else {
                    state.results[ear][f] = await threshold(f, ear);
                }
                done++;
                setProg();
            }
            document.removeEventListener('keydown', state.keyHandler);
            state.running = false;
            if (ear === 'R') { screenTestIntro('L'); } else { screenQuestions(); }
        })();
    }

    async function waitIfPaused() {
        while (state.paused) { await wait(200); }
    }

    async function trial(freq, ear, level, silent) {
        await waitIfPaused();
        await wait(rand(1200, 2800));
        await waitIfPaused();
        var w = { heard: false };
        state.window = w;
        var ms = silent ? 1100 : Audio.pulses(freq, ear, level, 3);
        await wait(ms + 1500); // response window covers Bluetooth latency
        state.window = null;
        return w.heard;
    }

    async function maybeCatch(freq, ear) {
        if (Math.random() < CONFIG.catchRate) {
            state.catchN++;
            if (await trial(freq, ear, 0, true)) { state.catchFalse++; }
        }
    }

    async function threshold(freq, ear) {
        var min = CONFIG.minLevel, max = Audio.maxLevel(freq);
        var level = Math.min(CONFIG.startLevel, max);

        // Familiarisation: rise in 10 dB steps until heard.
        var maxMisses = 0;
        while (!(await trial(freq, ear, level))) {
            if (level >= max) {
                if (++maxMisses >= 2) { return { db: max, noResp: true }; }
            }
            level = Math.min(level + 10, max);
        }

        var asc = {}, lastHeard = true, minHits = 0, trials = 0;
        level = Math.max(level - 10, min);
        maxMisses = 0;
        while (trials++ < CONFIG.maxTrialsPerFreq) {
            await maybeCatch(freq, ear);
            var ascending = !lastHeard;
            var heard = await trial(freq, ear, level);
            if (ascending) {
                var a = asc[level] || (asc[level] = { n: 0, h: 0 });
                a.n++;
                if (heard) { a.h++; }
                if (a.h >= 2) { return { db: level, noResp: false }; }
            }
            if (heard) {
                if (level === min && ++minHits >= 2) { return { db: min, noResp: false }; }
                lastHeard = true;
                level = Math.max(level - 10, min);
            } else {
                lastHeard = false;
                if (level >= max) {
                    if (++maxMisses >= 2) { return { db: max, noResp: true }; }
                } else {
                    level = Math.min(level + 5, max);
                }
            }
        }
        // Trial limit reached: best ascending response, else the current level.
        var best = Object.keys(asc).map(Number).filter(function (l) { return asc[l].h > 0; }).sort(function (x, y) { return x - y; })[0];
        return { db: best !== undefined ? best : level, noResp: false, uncertain: true };
    }

    /* ---------------- Questionnaire ---------------- */

    function screenQuestions() {
        state.redraw = screenQuestions;
        var qs = ['q1', 'q2', 'q3', 'q4', 'q5'];
        render(steps(4) + '<h3>' + t('qTitle') + '</h3>' +
            qs.map(function (q) {
                var v = state.answers[q];
                return '<div class="ht-q"><div class="ht-q-text">' + t(q) + '</div><div class="ht-yn">' +
                    '<button type="button" data-q="' + q + '" data-v="1" class="' + (v === 1 ? 'on' : '') + '">' + t('yes') + '</button>' +
                    '<button type="button" data-q="' + q + '" data-v="0" class="' + (v === 0 ? 'on' : '') + '">' + t('no') + '</button>' +
                    '</div></div>';
            }).join('') +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary" id="ht-res">' + t('seeResults') + '</button></div>'
        );
        root.querySelectorAll('.ht-yn button').forEach(function (b) {
            b.addEventListener('click', function () {
                state.answers[b.getAttribute('data-q')] = Number(b.getAttribute('data-v'));
                b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
            });
        });
        on('#ht-res', 'click', function () {
            state.summary = score();
            screenResults();
            root.dispatchEvent(new CustomEvent('hearingtest:complete', { bubbles: true, detail: state.summary }));
        });
    }

    /* ---------------- Scoring ---------------- */

    function band(pta) {
        if (pta < 20) { return 'normal'; }
        if (pta < 35) { return 'mild'; }
        if (pta < 50) { return 'moderate'; }
        return 'significant';
    }
    var BAND_RANK = { normal: 0, mild: 1, moderate: 2, significant: 3 };

    // Bands follow the WHO 2021 grades on the 4-frequency pure-tone average (PTA).
    function score() {
        var ears = {};
        ['R', 'L'].forEach(function (e) {
            var res = state.results[e];
            var vals = PTA_FREQS.filter(function (f) { return res[f]; }).map(function (f) { return res[f].noResp ? res[f].db + 5 : res[f].db; });
            var pta = vals.length ? Math.round(vals.reduce(function (s, v) { return s + v; }, 0) / vals.length) : 0;
            var high = [4000, 8000].some(function (f) { return res[f] && res[f].db >= 35; });
            ears[e] = { pta: pta, band: band(pta), high: high, thresholds: res };
        });
        var worst = BAND_RANK[ears.R.band] >= BAND_RANK[ears.L.band] ? ears.R.band : ears.L.band;
        var yes = Object.keys(state.answers).filter(function (k) { return state.answers[k] === 1; }).length;
        var flags = [];
        if (worst === 'normal' && (ears.R.high || ears.L.high)) { flags.push('flagHigh'); }
        if (Math.abs(ears.R.pta - ears.L.pta) >= 15) { flags.push('flagAsym'); }
        if (yes >= 2) { flags.push('flagQs'); }
        var unreliable = (state.catchN >= 2 && state.catchFalse / state.catchN > 0.34) ||
            (state.retest && Math.abs(state.retest.first - state.retest.second) > 10) ||
            state.stray > 12;
        if (unreliable) { flags.push('flagUnreliable'); }
        return {
            overall: worst,
            right: { pta: ears.R.pta, band: ears.R.band, thresholds: flat(ears.R.thresholds) },
            left: { pta: ears.L.pta, band: ears.L.band, thresholds: flat(ears.L.thresholds) },
            flags: flags,
            questionnaireYes: yes,
            reliable: !unreliable,
            durationSeconds: Math.round((Date.now() - state.startedAt) / 1000),
            lang: lang
        };
    }

    function flat(res) {
        var o = {};
        Object.keys(res).forEach(function (f) { o[f] = res[f].noResp ? '>' + res[f].db : res[f].db; });
        return o;
    }

    // One-line English summary, used for WhatsApp and the CRM lead note.
    function summaryText(s) {
        return 'Online hearing test: overall ' + s.overall.toUpperCase() +
            ' | Right ' + s.right.band + ' (PTA ~' + s.right.pta + ' dB)' +
            ' | Left ' + s.left.band + ' (PTA ~' + s.left.pta + ' dB)' +
            (s.flags.length ? ' | Flags: ' + s.flags.map(function (f) { return f.replace('flag', ''); }).join(', ') : '') +
            ' | Questionnaire yes: ' + s.questionnaireYes + '/5' +
            ' | R ' + JSON.stringify(s.right.thresholds) + ' L ' + JSON.stringify(s.left.thresholds);
    }

    /* ---------------- Results ---------------- */

    function screenResults() {
        state.redraw = screenResults;
        var s = state.summary;
        function earCard(key, e) {
            return '<div class="ht-earres ht-b-' + e.band + '"><div class="ht-earres-name">' + t(key) + '</div>' +
                '<div class="ht-earres-band">' + t('band_' + e.band) + '</div>' +
                '<div class="ht-earres-avg">' + t('avg', { db: e.pta }) + '</div></div>';
        }
        var resultLine = t('band_' + s.overall);
        var wa = CONFIG.whatsapp ? 'https://wa.me/' + CONFIG.whatsapp.replace(/\D/g, '') + '?text=' + encodeURIComponent(t('waMsg', { r: resultLine })) : '';

        render(
            '<h3>' + t('resTitle') + '</h3>' +
            '<div class="ht-summary ht-b-' + s.overall + '">' + t('sum_' + s.overall) + '</div>' +
            '<div class="ht-ears">' + earCard('rightEar', s.right) + earCard('leftEar', s.left) + '</div>' +
            (s.flags.length ? '<ul class="ht-flags">' + s.flags.map(function (f) { return '<li>' + t(f) + '</li>'; }).join('') + '</ul>' : '') +
            '<div class="ht-chart"><div class="ht-chart-title">' + t('chartTitle') + '</div>' + audiogram() + '</div>' +
            '<p class="ht-note">' + t('note') + '</p>' +
            '<div class="ht-cta"><div class="ht-cta-title">' + t('ctaTitle') + '</div><div class="ht-cta-btns">' +
            (CONFIG.bookingUrl ? '<a class="ht-btn ht-primary" href="' + esc(CONFIG.bookingUrl) + '">' + t('book') + '</a>' : '') +
            (CONFIG.phone ? '<a class="ht-btn" href="tel:' + esc(CONFIG.phone) + '">📞 ' + t('call') + '</a>' : '') +
            (wa ? '<a class="ht-btn ht-wa" href="' + esc(wa) + '" target="_blank" rel="noopener">' + t('whatsapp') + '</a>' : '') +
            '</div>' + (CONFIG.leadUrl ? leadForm() : '') + '</div>' +
            '<div class="ht-actions ht-split ht-noprint"><button type="button" class="ht-btn ht-ghost" id="ht-print">' + t('print') + '</button>' +
            '<button type="button" class="ht-btn ht-ghost" id="ht-again">' + t('retake') + '</button></div>'
        );
        on('#ht-print', 'click', function () { window.print(); });
        on('#ht-again', 'click', function () { reset(); screenIntro(); });
        if (CONFIG.leadUrl) { bindLeadForm(); }
    }

    function audiogram() {
        var W = 360, H = 260, L = 44, R = 14, T = 14, B = 34;
        var minDb = -10, maxDb = 100;
        function x(f) { return L + CHART_FREQS.indexOf(f) * (W - L - R) / (CHART_FREQS.length - 1); }
        function y(db) { return T + (db - minDb) * (H - T - B) / (maxDb - minDb); }
        var g = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="ht-svg" role="img" aria-label="' + t('chartTitle') + '">';
        g += '<rect x="' + L + '" y="' + y(-10) + '" width="' + (W - L - R) + '" height="' + (y(20) - y(-10)) + '" class="ht-zone"/>';
        for (var d = minDb; d <= maxDb; d += 10) {
            g += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + y(d) + '" y2="' + y(d) + '" class="ht-grid"/>' +
                '<text x="' + (L - 6) + '" y="' + (y(d) + 4) + '" class="ht-axis" text-anchor="end">' + d + '</text>';
        }
        CHART_FREQS.forEach(function (f) {
            g += '<line x1="' + x(f) + '" x2="' + x(f) + '" y1="' + T + '" y2="' + (H - B) + '" class="ht-grid"/>' +
                '<text x="' + x(f) + '" y="' + (H - B + 16) + '" class="ht-axis" text-anchor="middle">' + (f >= 1000 ? (f / 1000) + 'k' : f) + '</text>';
        });
        g += '<text x="' + ((W + L) / 2) + '" y="' + (H - 2) + '" class="ht-axis" text-anchor="middle">' + t('chartX') + '</text>';
        g += '<text x="10" y="' + ((H - B + T) / 2) + '" class="ht-axis" text-anchor="middle" transform="rotate(-90 10 ' + ((H - B + T) / 2) + ')">' + t('chartY') + '</text>';

        [['R', 'ht-r'], ['L', 'ht-l']].forEach(function (p) {
            var res = state.results[p[0]];
            var pts = CHART_FREQS.filter(function (f) { return res[f]; }).map(function (f) { return [x(f), y(res[f].db), res[f].noResp]; });
            if (pts.length > 1) {
                g += '<polyline class="ht-line ' + p[1] + '" points="' + pts.map(function (q) { return q[0] + ',' + q[1]; }).join(' ') + '"/>';
            }
            pts.forEach(function (q) {
                if (p[0] === 'R') {
                    g += '<circle cx="' + q[0] + '" cy="' + q[1] + '" r="6" class="ht-mark ' + p[1] + '"/>';
                } else {
                    g += '<path d="M' + (q[0] - 5) + ' ' + (q[1] - 5) + 'l10 10M' + (q[0] + 5) + ' ' + (q[1] - 5) + 'l-10 10" class="ht-mark ' + p[1] + '"/>';
                }
                if (q[2]) { // no response at max level: downward arrow
                    g += '<path d="M' + q[0] + ' ' + (q[1] + 7) + 'v12m-4 -5l4 5l4 -5" class="ht-mark ' + p[1] + '"/>';
                }
            });
        });
        g += '</svg>';
        return g + '<div class="ht-legend"><span class="ht-r">● ' + t('legendR') + '</span><span class="ht-l">✕ ' + t('legendL') + '</span></div>';
    }

    /* ---------------- Lead form (optional) ---------------- */

    function leadForm() {
        return '<form class="ht-form" id="ht-form" novalidate>' +
            '<input type="text" name="name" placeholder="' + t('formName') + '" autocomplete="name" maxlength="100" required>' +
            '<input type="tel" name="phone" placeholder="' + t('formPhone') + ' (01XXXXXXXXX)" autocomplete="tel" maxlength="20" required>' +
            '<input type="text" name="website" class="ht-hp" tabindex="-1" autocomplete="off" aria-hidden="true">' +
            '<label class="ht-check"><input type="checkbox" name="consent" checked> <span>' + t('formConsent') + '</span></label>' +
            '<button type="submit" class="ht-btn ht-primary">' + t('formSend') + '</button>' +
            '<div class="ht-form-msg" id="ht-form-msg" role="status"></div></form>';
    }

    function bindLeadForm() {
        var form = $('#ht-form'), msg = $('#ht-form-msg');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name = form.name.value.trim();
            var phone = form.phone.value.replace(/[\s-]/g, '');
            if (name.length < 2 || !/^(\+?88)?01[3-9]\d{8}$/.test(phone)) {
                msg.className = 'ht-form-msg err';
                msg.textContent = t('formInvalid');
                return;
            }
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            btn.textContent = t('formSending');
            var params = new URLSearchParams(location.search);
            var body = {
                name: name,
                phone: phone,
                website: form.website.value,
                consent: form.consent.checked,
                elapsed_seconds: state.summary.durationSeconds,
                page_url: location.href,
                referrer_url: document.referrer || null,
                summary: summaryText(state.summary),
                result: state.summary
            };
            ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'].forEach(function (k) {
                if (params.get(k)) { body[k] = params.get(k); }
            });
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch(CONFIG.leadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    csrf ? { 'X-CSRF-TOKEN': csrf.getAttribute('content') } : {}),
                body: JSON.stringify(body)
            }).then(function (r) {
                if (!r.ok) { throw new Error(r.status); }
                form.innerHTML = '<div class="ht-form-msg ok">' + t('formOk') + '</div>';
                root.dispatchEvent(new CustomEvent('hearingtest:lead', { bubbles: true, detail: state.summary }));
            }).catch(function () {
                btn.disabled = false;
                btn.textContent = t('formSend');
                msg.className = 'ht-form-msg err';
                msg.textContent = t('formErr');
            });
        });
    }

    /* ---------------- Boot ---------------- */

    function reset() {
        if (state && state.keyHandler) { document.removeEventListener('keydown', state.keyHandler); }
        state = {
            results: { R: {}, L: {} }, answers: {}, catchN: 0, catchFalse: 0, stray: 0,
            retest: null, window: null, paused: false, running: false, startedAt: Date.now(), redraw: function () {}
        };
    }

    function boot() {
        root = document.querySelector(CONFIG.target);
        if (!root) { return; }
        root.classList.add('ht-root');
        reset();
        screenIntro();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
