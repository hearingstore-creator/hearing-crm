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
        brand: ds.brand || 'Hearing Store',    // shown on the printed report
        bookingUrl: ds.bookingUrl || '',       // e.g. /book-appointment or "#crm-lead-form"
        leadUrl: ds.leadUrl || '',             // website route that forwards to the CRM (see README); empty = no form
        // Test order follows clinical practice: 1 kHz first, up through the highs, then 500 Hz.
        // Same four pitches per ear as ReSound's test; add 8000 with data-freqs if wanted.
        freqs: (ds.freqs || '1000,2000,4000,500').split(',').map(Number).filter(Boolean),
        retest: ds.retest === 'on',             // repeat 1 kHz on the first ear as a reliability check
        // 'warble' = ±5% frequency wobble at 5 Hz (clinical warble tone, easier to notice); 'pure' = sine
        toneType: ds.toneType === 'pure' ? 'pure' : 'warble',
        startLevel: 40,
        minLevel: -10,
        maxLevel: 85,
        maxTrialsPerFreq: 20,
        catchRate: 0.12,
        // 'adjust' = visitor slides each tone up until barely audible (default, easiest);
        // 'classic' = tap-when-you-hear-it with a Hughson-Westlake staircase
        toneMethod: ds.toneMethod === 'classic' ? 'classic' : 'adjust',
        // 'quick' = tones, then the noise test offered on the results (default);
        // 'full' = tones + noise test in one go; 'tones' = tones only; 'din' = noise test only
        mode: ['quick', 'full', 'tones', 'din'].indexOf(ds.mode) >= 0 ? ds.mode : 'quick',
        audioBase: ds.audioBase || (script && script.src ? new URL('digits/', script.src).href : 'digits/'),
        din: {
            // 14 triplets with a 4 dB fast start ≈ 1 minute; SRT spread ±1.1 dB vs ±0.7 dB for the classic 24.
            trials: ds.dinTrials ? Math.max(8, Math.min(30, Number(ds.dinTrials))) : 14,
            fastStep: 4,
            startSnr: 0,
            step: 2,
            maxSnr: 10,
            minSnr: -24,
            // Two-syllable digits are left out (English "seven", Bangla "shunno"), as in standard DIN tests.
            digits: { en: [0, 1, 2, 3, 4, 5, 6, 8, 9], bn: [1, 2, 3, 4, 5, 6, 7, 8, 9] },
            // PROVISIONAL cut-offs (dB SNR). Re-norm whenever the recordings change – see README.
            good: ds.dinGood !== undefined ? Number(ds.dinGood) : -9,
            poor: ds.dinPoor !== undefined ? Number(ds.dinPoor) : -6
        }
    };

    // Level model: the volume step sets CAL_LEVEL dB HL at 1 kHz to CAL_AMP of full scale.
    // CORR = typical headphone reference threshold offsets relative to 1 kHz (RETSPL shape, TDH-39).
    var CAL_LEVEL = 60;
    var CAL_AMP = 0.05;
    var CORR = { 250: 18.5, 500: 4.5, 1000: 0, 2000: 2, 3000: 2.5, 4000: 2.5, 6000: 8.5, 8000: 6 };
    var PTA_FREQS = [500, 1000, 2000, 4000];
    var CHART_FREQS = CONFIG.freqs.slice().sort(function (a, b) { return a - b; });

    var TEXT = {
        en: {
            title: 'Online Hearing Test',
            subtitle: 'A free 5-minute hearing check',
            introTitle: 'Check your hearing in 5 minutes',
            introLead: 'A quick screening that tells you whether you should get your hearing professionally checked.',
            chipTime: 'About 5 min',
            chipFree: 'Free',
            chipPrivate: 'Private',
            needsTitle: 'Before you start',
            need1: 'Headphones or earphones (wired works best)',
            need2: 'A quiet room',
            need3: 'Phone silent mode OFF',
            disclaimer: 'This is a screening, not a medical diagnosis. Results are approximate. If your hearing changed suddenly, or you have ear pain, discharge or dizziness, see a doctor as soon as possible.',
            agree: 'I understand this is a screening test only',
            start: 'Start test',
            next: 'Next',
            back: 'Back',
            step: 'Step {n} of {m}',
            volTextDin: 'Tap Play. You will hear numbers with background noise. Adjust your device volume until the voice is clear and comfortable – like normal conversation.',
            dinTitle: 'Numbers in noise',
            dinText: 'You will hear 3 numbers spoken with background noise. Type the numbers you heard, then tap OK. The noise gets louder as you do well – that is normal. If you are not sure, take a guess.',
            dinStart: 'Start',
            dinLoading: 'Loading sounds…',
            dinLoadErr: 'The sounds could not be loaded. You can skip this part.',
            skip: 'Skip',
            dinListen: 'Listen…',
            dinType: 'Type the 3 numbers you heard',
            ok: 'OK',
            dinTrial: 'Round {n} of {m}',
            toneCount: 'Sound {n} of {m}',
            dinResTitle: 'Hearing in noise',
            din_good: 'Good',
            din_borderline: 'Borderline',
            din_poor: 'Poor',
            dinScore: 'Speech-in-noise score: {v} dB SNR (lower is better)',
            flagDin: 'You found it harder than expected to follow speech in background noise. This is often the first everyday sign of hearing loss.',
            sumdin_good: 'You understood speech in noise well. Your hearing for everyday conversation seems fine. We recommend a hearing check once a year.',
            sumdin_borderline: 'You found speech in noise somewhat hard to follow. A professional hearing test is recommended.',
            sumdin_poor: 'You found speech in noise hard to follow, which suggests a hearing loss. Please book a professional hearing test.',
            lrTitle: 'Check your headphones',
            lrText: 'Put on your headphones. Tap Play, then tell us which side you heard the beep.',
            play: 'Play sound',
            playing: 'Playing…',
            left: 'Left',
            right: 'Right',
            cantTell: 'Both / not sure',
            lrWrong: 'That did not match. Check the headphones are on the correct ears (L on left, R on right), not in mono mode, and try again.',
            lrOk: 'Headphones look good!',
            volTitle: 'Turn your volume to maximum',
            volText: 'Set your phone or computer volume to maximum. The test sounds start very quietly, so this is safe. Tap Play to check you can hear the beep.',
            volTitleDin: 'Set your volume',
            stSetup: 'Setup',
            stR: 'Right ear',
            stL: 'Left ear',
            stDin: 'Noise',
            stQs: 'Questions',
            stRes: 'Results',
            testTextAdj: 'You will hear a steady tone in one ear. It starts very quiet. Press + (or drag the slider) until you can barely hear it, then tap Continue. If you already hear it, just tap Continue.',
            adjTitle: 'Press <b>+</b> until you can <b>barely hear</b> the tone',
            adjHint: 'Go up one step at a time. Stop at the first step where you can just hear the tone.',
            adjCant: 'I can\'t hear it at all',
            cont: 'Continue',
            louder: 'Louder',
            softer: 'Softer',
            offerTitle: 'Check how you hear in noise',
            offerText: 'An optional 1-minute test: pick out spoken numbers in background noise. It shows how well you follow conversation in busy places.',
            offerBtn: 'Start the noise test',
            volKeep: 'After this step, do not change the volume until the test ends.',
            volCant: 'Can\'t hear anything? Turn your volume up, switch off silent mode, and check your headphones are connected.',
            volDone: 'Volume is set',
            testTitle: 'Testing your {ear} ear',
            earRight: 'right',
            earLeft: 'left',
            testTextClassic: 'You will hear soft beeps, some very quiet. Tap the button (or press Space) every time you hear one – even if it is very faint.',
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
            textSize: 'Larger text',
            pitchLow: 'Low pitch',
            pitchMid: 'Middle pitch',
            pitchHigh: 'High pitch',
            pitchVHigh: 'Very high pitch',
            showChart: 'Show detailed chart',
            hideChart: 'Hide detailed chart',
            printTitle: 'Print or save your results',
            printHelp: 'Add your name and age to the report if you like. Both are optional and stay on this device.',
            printName: 'Name (optional)',
            printAge: 'Age (optional)',
            printAgeErr: 'Enter an age between 1 and 120, or leave it empty.',
            printGo: 'Print report',
            printTip: 'To save a PDF, choose "Save as PDF" in the print window.',
            cancel: 'Cancel',
            rpTitle: 'Hearing screening report',
            rpNotClinical: 'Not a clinical test',
            rpNotClinicalText: 'This online screening is not a medical diagnosis and does not replace a hearing test by a qualified hearing care professional. Results are approximate and depend on the device, headphones and room used.',
            rpName: 'Name',
            rpAge: 'Age',
            rpDate: 'Date',
            rpTest: 'Test',
            rpTestTone: 'Tone screening, {n} pitches per ear',
            rpTestDin: ' + hearing in noise',
            rpOverall: 'Overall result',
            rpEar: 'Ear',
            rpResult: 'Result',
            rpAvg: 'Average',
            rpNotes: 'Notes',
            rpNext: 'Recommended next step',
            rpNextNormal: 'Have your hearing checked once a year, or sooner if you notice any change.',
            rpNextBook: 'Book a full hearing test with a hearing care professional and bring this report.',
            rpContact: 'Contact',
            rpYears: '{n} years',
            noAudio: 'Sorry, your browser does not support audio playback. Please try Chrome, Safari or Edge.',
            waMsg: 'Hi, I just took the online hearing test. Result: {r}. I would like to book a hearing test.'
        },
        bn: {
            title: 'অনলাইন হিয়ারিং টেস্ট',
            subtitle: '৫ মিনিটের বিনামূল্যে শ্রবণ পরীক্ষা',
            introTitle: '৫ মিনিটে আপনার শ্রবণশক্তি যাচাই করুন',
            introLead: 'একটি দ্রুত স্ক্রিনিং, যা জানাবে আপনার শ্রবণশক্তি পেশাদারভাবে পরীক্ষা করানো দরকার কিনা।',
            chipTime: 'প্রায় ৫ মিনিট',
            chipFree: 'বিনামূল্যে',
            chipPrivate: 'গোপনীয়',
            needsTitle: 'শুরু করার আগে',
            need1: 'হেডফোন বা ইয়ারফোন (তারযুক্ত হলে ভালো)',
            need2: 'একটি নিরিবিলি ঘর',
            need3: 'ফোনের সাইলেন্ট মোড বন্ধ রাখুন',
            disclaimer: 'এটি একটি প্রাথমিক স্ক্রিনিং, চিকিৎসা নির্ণয় নয়। ফলাফল আনুমানিক। হঠাৎ শ্রবণশক্তি কমে গেলে, অথবা কানে ব্যথা, পুঁজ বা মাথা ঘোরা থাকলে যত দ্রুত সম্ভব ডাক্তার দেখান।',
            agree: 'আমি বুঝেছি এটি শুধুমাত্র একটি স্ক্রিনিং টেস্ট',
            start: 'টেস্ট শুরু করুন',
            next: 'পরবর্তী',
            back: 'পেছনে',
            step: 'ধাপ {n} / {m}',
            volTextDin: '"শব্দ বাজান" চাপুন। আপনি পেছনের শব্দের সাথে কিছু সংখ্যা শুনবেন। ডিভাইসের ভলিউম এমনভাবে ঠিক করুন যাতে কণ্ঠস্বর পরিষ্কার ও আরামদায়ক শোনায় – স্বাভাবিক কথাবার্তার মতো।',
            dinTitle: 'কোলাহলে সংখ্যা শোনা',
            dinText: 'আপনি পেছনের শব্দের মধ্যে ৩টি সংখ্যা শুনবেন। যে সংখ্যাগুলো শুনলেন তা লিখে OK চাপুন। আপনি ভালো করলে পেছনের শব্দ বাড়বে – এটি স্বাভাবিক। নিশ্চিত না হলে অনুমান করে দিন।',
            dinStart: 'শুরু করুন',
            dinLoading: 'শব্দ লোড হচ্ছে…',
            dinLoadErr: 'শব্দ লোড করা যায়নি। আপনি এই অংশটি বাদ দিতে পারেন।',
            skip: 'বাদ দিন',
            dinListen: 'শুনুন…',
            dinType: 'যে ৩টি সংখ্যা শুনলেন তা লিখুন',
            ok: 'OK',
            dinTrial: 'রাউন্ড {n} / {m}',
            toneCount: 'শব্দ {n} / {m}',
            dinResTitle: 'কোলাহলে শোনা',
            din_good: 'ভালো',
            din_borderline: 'সীমারেখায়',
            din_poor: 'দুর্বল',
            dinScore: 'কোলাহলে কথা বোঝার স্কোর: {v} dB SNR (কম হলে ভালো)',
            flagDin: 'পেছনের শব্দের মধ্যে কথা বুঝতে আপনার প্রত্যাশার চেয়ে বেশি কষ্ট হয়েছে। এটি প্রায়ই দৈনন্দিন জীবনে শ্রবণ হ্রাসের প্রথম লক্ষণ।',
            sumdin_good: 'কোলাহলের মধ্যেও আপনি কথা ভালোভাবে বুঝেছেন। দৈনন্দিন কথাবার্তায় আপনার শ্রবণশক্তি ঠিক আছে বলে মনে হচ্ছে। বছরে একবার শ্রবণ পরীক্ষা করানোর পরামর্শ দিই।',
            sumdin_borderline: 'কোলাহলের মধ্যে কথা বুঝতে আপনার কিছুটা কষ্ট হয়েছে। একটি পেশাদার শ্রবণ পরীক্ষা করানোর পরামর্শ দেওয়া হচ্ছে।',
            sumdin_poor: 'কোলাহলের মধ্যে কথা বুঝতে আপনার বেশ কষ্ট হয়েছে, যা শ্রবণ হ্রাসের ইঙ্গিত দেয়। অনুগ্রহ করে একটি পেশাদার শ্রবণ পরীক্ষা বুক করুন।',
            lrTitle: 'হেডফোন পরীক্ষা করুন',
            lrText: 'হেডফোন পরে নিন। "শব্দ বাজান" চাপুন, তারপর বলুন কোন পাশে বিপ শুনলেন।',
            play: 'শব্দ বাজান',
            playing: 'বাজছে…',
            left: 'বাম',
            right: 'ডান',
            cantTell: 'দুই পাশে / নিশ্চিত নই',
            lrWrong: 'মিলল না। হেডফোন সঠিক কানে আছে কিনা দেখুন (L বাম কানে, R ডান কানে), মোনো মোড বন্ধ রাখুন, তারপর আবার চেষ্টা করুন।',
            lrOk: 'হেডফোন ঠিক আছে!',
            volTitle: 'ভলিউম সর্বোচ্চ করুন',
            volText: 'আপনার ফোন বা কম্পিউটারের ভলিউম সর্বোচ্চ করুন। টেস্টের শব্দ খুব আস্তে শুরু হয়, তাই এটি নিরাপদ। বিপ শুনতে পাচ্ছেন কিনা দেখতে "শব্দ বাজান" চাপুন।',
            volTitleDin: 'ভলিউম ঠিক করুন',
            stSetup: 'প্রস্তুতি',
            stR: 'ডান কান',
            stL: 'বাম কান',
            stDin: 'কোলাহল',
            stQs: 'প্রশ্ন',
            stRes: 'ফলাফল',
            testTextAdj: 'আপনি এক কানে একটানা একটি শব্দ শুনবেন। এটি খুব আস্তে শুরু হবে। + চাপুন (বা স্লাইডার টানুন) যতক্ষণ না খুব আস্তে শুনতে পান, তারপর "এগিয়ে যান" চাপুন। শুরুতেই শুনতে পেলে সরাসরি "এগিয়ে যান" চাপুন।',
            adjTitle: '<b>+</b> চাপুন যতক্ষণ না শব্দটি <b>খুব আস্তে শুনতে</b> পান',
            adjHint: 'এক ধাপ করে বাড়ান। যে ধাপে প্রথম হালকা শব্দ শুনবেন সেখানেই থামুন।',
            adjCant: 'আমি একদমই শুনতে পাচ্ছি না',
            cont: 'এগিয়ে যান',
            louder: 'জোরে',
            softer: 'আস্তে',
            offerTitle: 'কোলাহলে কেমন শোনেন যাচাই করুন',
            offerText: 'ঐচ্ছিক ১ মিনিটের টেস্ট: পেছনের শব্দের মধ্যে বলা সংখ্যা চিনে নিন। এতে বোঝা যায় ভিড়ের জায়গায় কথাবার্তা কতটা বুঝতে পারেন।',
            offerBtn: 'কোলাহল টেস্ট শুরু করুন',
            volKeep: 'এই ধাপের পর টেস্ট শেষ না হওয়া পর্যন্ত ভলিউম পরিবর্তন করবেন না।',
            volCant: 'কিছু শুনতে পাচ্ছেন না? ভলিউম বাড়ান, সাইলেন্ট মোড বন্ধ করুন এবং হেডফোন সংযুক্ত আছে কিনা দেখুন।',
            volDone: 'ভলিউম ঠিক আছে',
            testTitle: 'আপনার {ear} কান পরীক্ষা',
            earRight: 'ডান',
            earLeft: 'বাম',
            testTextClassic: 'আপনি মৃদু বিপ শব্দ শুনবেন, কিছু খুবই আস্তে। যখনই শুনবেন বাটনটি চাপুন (বা Space চাপুন) – খুব আস্তে শুনলেও।',
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
            textSize: 'বড় লেখা',
            pitchLow: 'নিচু সুর',
            pitchMid: 'মাঝারি সুর',
            pitchHigh: 'উঁচু সুর',
            pitchVHigh: 'খুব উঁচু সুর',
            showChart: 'বিস্তারিত চার্ট দেখুন',
            hideChart: 'বিস্তারিত চার্ট লুকান',
            printTitle: 'আপনার ফলাফল প্রিন্ট বা সেভ করুন',
            printHelp: 'চাইলে রিপোর্টে আপনার নাম ও বয়স যোগ করুন। দুটোই ঐচ্ছিক এবং শুধু এই ডিভাইসে থাকে।',
            printName: 'নাম (ঐচ্ছিক)',
            printAge: 'বয়স (ঐচ্ছিক)',
            printAgeErr: 'বয়স ১ থেকে ১২০-এর মধ্যে দিন, অথবা খালি রাখুন।',
            printGo: 'রিপোর্ট প্রিন্ট করুন',
            printTip: 'PDF হিসেবে সেভ করতে প্রিন্ট উইন্ডোতে "Save as PDF" বেছে নিন।',
            cancel: 'বাতিল',
            rpTitle: 'শ্রবণ স্ক্রিনিং রিপোর্ট',
            rpNotClinical: 'এটি ক্লিনিক্যাল টেস্ট নয়',
            rpNotClinicalText: 'এই অনলাইন স্ক্রিনিং কোনো চিকিৎসা নির্ণয় নয় এবং যোগ্য শ্রবণ বিশেষজ্ঞের পরীক্ষার বিকল্প নয়। ফলাফল আনুমানিক এবং ব্যবহৃত ডিভাইস, হেডফোন ও পরিবেশের ওপর নির্ভর করে।',
            rpName: 'নাম',
            rpAge: 'বয়স',
            rpDate: 'তারিখ',
            rpTest: 'টেস্ট',
            rpTestTone: 'টোন স্ক্রিনিং, প্রতি কানে {n}টি সুর',
            rpTestDin: ' + কোলাহলে শোনা',
            rpOverall: 'সামগ্রিক ফলাফল',
            rpEar: 'কান',
            rpResult: 'ফলাফল',
            rpAvg: 'গড়',
            rpNotes: 'লক্ষণীয়',
            rpNext: 'পরবর্তী করণীয়',
            rpNextNormal: 'বছরে একবার, অথবা কোনো পরিবর্তন টের পেলে আগেই, শ্রবণ পরীক্ষা করান।',
            rpNextBook: 'একজন শ্রবণ বিশেষজ্ঞের কাছে পূর্ণাঙ্গ শ্রবণ পরীক্ষা বুক করুন এবং এই রিপোর্টটি সঙ্গে আনুন।',
            rpContact: 'যোগাযোগ',
            rpYears: '{n} বছর',
            noAudio: 'দুঃখিত, আপনার ব্রাউজার অডিও সমর্থন করে না। Chrome, Safari বা Edge ব্যবহার করুন।',
            waMsg: 'হ্যালো, আমি অনলাইন হিয়ারিং টেস্ট দিয়েছি। ফলাফল: {r}। আমি একটি শ্রবণ পরীক্ষা বুক করতে চাই।'
        }
    };

    var LOGO_SVG = '<svg class="ht-logo" viewBox="0 0 64 48" aria-hidden="true">' +
        [[4, 18, 12], [13, 10, 28], [22, 4, 40], [31, 16, 16], [40, 0, 48], [49, 8, 32], [58, 18, 12]]
            .map(function (b) { return '<rect x="' + b[0] + '" y="' + b[1] + '" width="5" height="' + b[2] + '" rx="2.5"/>'; }).join('') +
        '</svg>';

    // Line icons (Lucide, ISC licence), 24px grid, stroke = currentColor.
    var ICONS = {
        ear: '<path d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/><path d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>',
        headphones: '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
        home: '<path d="M3 10l9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        bellOff: '<path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"/><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="m2 2 20 20"/>',
        volume: '<path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>',
        stop: '<rect x="6" y="6" width="12" height="12" rx="2"/>',
        pause: '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
        play: '<path d="M7 4v16l13-8z"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        checkCircle: '<circle cx="12" cy="12" r="10"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        gift: '<path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
        left: '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        right: '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        minus: '<path d="M5 12h14"/>',
        plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
        del: '<path d="M10 5a2 2 0 0 0-1.34.52l-6.33 5.74a1 1 0 0 0 0 1.48l6.33 5.74A2 2 0 0 0 10 19h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/><path d="m12 9 6 6"/><path d="m18 9-6 6"/>',
        phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        chat: '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        calendar: '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        printer: '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
        redo: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        wave: '<path d="M2 10v3"/><path d="M6 6v11"/><path d="M10 3v18"/><path d="M14 8v7"/><path d="M18 5v13"/><path d="M22 10v3"/>',
        textSize: '<path d="M4 7V5h10v2"/><path d="M9 5v14"/><path d="M7 19h4"/><path d="M14 13v-1h7v1"/><path d="M17.5 12v7"/><path d="M16 19h3"/>',
        chevron: '<path d="m6 9 6 6 6-6"/>',
        user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        list: '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="m3 6 1 1 2-2"/><path d="m3 12 1 1 2-2"/><path d="m3 18 1 1 2-2"/>'
    };

    function ic(name, cls) {
        return '<svg class="ht-ic' + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    function msgBox(kind, text, id) {
        return '<div class="ht-msg ' + kind + '"' + (id ? ' id="' + id + '"' : '') + '>' + ic(kind === 'ok' ? 'checkCircle' : kind === 'warn' ? 'alert' : 'info') + '<span>' + text + '</span></div>';
    }

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

    // Warble tone: a 5 Hz sine LFO swings the carrier ±5%. Started before the carrier, returns the LFO (or null).
    function warble(ctx, osc, freq) {
        if (CONFIG.toneType !== 'warble') { return null; }
        var lfo = ctx.createOscillator(), depth = ctx.createGain();
        lfo.frequency.value = 5;
        depth.gain.value = freq * 0.05;
        lfo.connect(depth);
        depth.connect(osc.frequency);
        lfo.start();
        lfo.onended = function () { lfo.disconnect(); depth.disconnect(); };
        return lfo;
    }

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
        // Continuous tone whose level follows the slider immediately (15 ms smoothing, no clicks).
        hold: function (freq, ear, level) {
            var ctx = this.ctx, self = this;
            var osc = ctx.createOscillator(), gain = ctx.createGain(), merger = ctx.createChannelMerger(2);
            var cur = level, muted = false;
            osc.type = 'sine';
            osc.frequency.value = freq;
            var lfo = warble(ctx, osc, freq);
            gain.gain.setValueAtTime(0, ctx.currentTime);
            osc.connect(gain);
            if (ear !== 'R') { gain.connect(merger, 0, 0); }
            if (ear !== 'L') { gain.connect(merger, 0, 1); }
            merger.connect(ctx.destination);
            osc.start();
            function apply() { gain.gain.setTargetAtTime(muted ? 0 : self.amp(freq, cur), ctx.currentTime, 0.015); }
            apply();
            return {
                set: function (l) { cur = l; apply(); },
                mute: function (m) { muted = m; apply(); },
                stop: function () {
                    gain.gain.setTargetAtTime(0, ctx.currentTime, 0.01);
                    if (lfo) { lfo.stop(ctx.currentTime + 0.08); }
                    osc.stop(ctx.currentTime + 0.08);
                    osc.onended = function () { osc.disconnect(); gain.disconnect(); merger.disconnect(); };
                }
            };
        },
        // Three 225 ms pulses with 20 ms ramps; ear: 'L', 'R' or 'both'. Returns tone duration in ms.
        pulses: function (freq, ear, level, count) {
            var ctx = this.ctx, on = 0.225, period = 0.45, ramp = 0.02;
            count = count || 3;
            var t0 = ctx.currentTime + 0.05, a = this.amp(freq, level);
            var osc = ctx.createOscillator(), gain = ctx.createGain(), merger = ctx.createChannelMerger(2);
            osc.type = 'sine';
            osc.frequency.value = freq;
            var lfo = warble(ctx, osc, freq);
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
            if (lfo) { lfo.stop(end); }
            osc.onended = function () { osc.disconnect(); gain.disconnect(); merger.disconnect(); };
            return Math.round((end - ctx.currentTime) * 1000);
        }
    };

    /* ---------------- Rendering helpers ---------------- */

    function render(html) {
        root.innerHTML =
            '<div class="ht-ambient" aria-hidden="true"><i></i><i></i><i></i></div>' +
            '<div class="ht-card">' +
            '<div class="ht-head">' + LOGO_SVG +
            '<div class="ht-head-text"><div class="ht-title">' + t('title') + '</div><div class="ht-sub">' + t('subtitle') + '</div></div>' +
            '<button type="button" class="ht-size' + (state.large ? ' on' : '') + '" id="ht-size" aria-pressed="' + !!state.large + '" aria-label="' + t('textSize') + '" title="' + t('textSize') + '">' + ic('textSize') + '</button>' +
            '<div class="ht-lang">' +
            '<button type="button" data-lang="en" class="' + (lang === 'en' ? 'on' : '') + '">EN</button>' +
            '<button type="button" data-lang="bn" class="' + (lang === 'bn' ? 'on' : '') + '">বাংলা</button>' +
            '</div></div>' +
            '<div class="ht-body">' + html + '</div></div>';
        root.lang = lang;
        root.classList.toggle('ht-large', !!state.large);
        on('#ht-size', 'click', function () {
            state.large = !state.large;
            root.classList.toggle('ht-large', state.large);
            this.classList.toggle('on', state.large);
            this.setAttribute('aria-pressed', String(state.large));
            try { localStorage.setItem('ht-large', state.large ? '1' : '0'); } catch (e) { /* storage blocked */ }
        });
        root.querySelectorAll('.ht-lang button').forEach(function (b) {
            b.addEventListener('click', function () {
                if (state.running) { return; } // don't re-render mid-test
                lang = b.getAttribute('data-lang');
                state.redraw();
            });
            b.disabled = !!state.running;
        });
    }

    function flowSteps() {
        var s = ['setup'], m = CONFIG.mode;
        if (m !== 'din') { s.push('R', 'L'); }
        if (m === 'full' || m === 'din') { s.push('din'); }
        s.push('qs');
        if (m === 'quick' && state.dinOptional) { s.push('din'); } // offered after the results
        s.push('results');
        return s;
    }
    var STEP_LABEL = { setup: 'stSetup', R: 'stR', L: 'stL', din: 'stDin', qs: 'stQs', results: 'stRes' };

    function pitchName(f) {
        return t(f <= 500 ? 'pitchLow' : f <= 1000 ? 'pitchMid' : f <= 2000 ? 'pitchHigh' : 'pitchVHigh');
    }

    function num(n) { return lang === 'bn' ? bnDigits(n) : String(n); }

    // Step tracker: done steps get a tick, the current one is highlighted.
    function steps(id) {
        if (id === 'lr' || id === 'vol') { id = 'setup'; }
        var list = flowSteps(), cur = list.indexOf(id);
        return '<ol class="ht-stepper" aria-label="' + t('step', { n: num(cur + 1), m: num(list.length) }) + '">' +
            list.map(function (s, i) {
                var cls = i < cur || (s === 'results' && s === id) ? 'done' : i === cur ? 'on' : '';
                return '<li class="' + cls + '"' + (i === cur ? ' aria-current="step"' : '') + '><span class="ht-dot">' +
                    (cls === 'done' ? ic('check') : num(i + 1)) + '</span><span class="ht-lbl">' + t(STEP_LABEL[s]) + '</span></li>';
            }).join('') + '</ol>';
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
            '<div class="ht-hero"><div class="ht-hero-ic">' + ic('ear') + '</div>' +
            '<h2 class="ht-h2">' + t('introTitle') + '</h2>' +
            '<p class="ht-lead">' + t('introLead') + '</p>' +
            '<div class="ht-chips"><span>' + ic('clock') + t('chipTime') + '</span><span>' + ic('gift') + t('chipFree') + '</span><span>' + ic('lock') + t('chipPrivate') + '</span></div></div>' +
            '<div class="ht-panel"><div class="ht-eyebrow">' + t('needsTitle') + '</div>' +
            '<ul class="ht-needs"><li>' + ic('headphones') + '<span>' + t('need1') + '</span></li><li>' + ic('home') + '<span>' + t('need2') + '</span></li><li>' + ic('bellOff') + '<span>' + t('need3') + '</span></li></ul></div>' +
            '<div class="ht-disclaimer">' + ic('shield') + '<span>' + t('disclaimer') + '</span></div>' +
            '<label class="ht-check"><input type="checkbox" id="ht-agree"> <span>' + t('agree') + '</span></label>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary ht-wide" id="ht-start" disabled>' + t('start') + ic('right') + '</button></div>'
        );
        on('#ht-agree', 'change', function (e) { $('#ht-start').disabled = !e.target.checked; });
        on('#ht-start', 'click', function () {
            if (!Audio.init()) { render(msgBox('warn', t('noAudio'))); return; }
            state.startedAt = Date.now();
            screenLR();
        });
    }

    // Left/right check: two beeps, one per ear in random order; both must be identified.
    function screenLR(msg) {
        state.redraw = function () { screenLR(msg); };
        var seq = Math.random() < 0.5 ? ['L', 'R'] : ['R', 'L'];
        var idx = 0;
        render(steps('lr') +
            '<h3>' + t('lrTitle') + '</h3><p>' + t('lrText') + '</p>' +
            (msg ? msgBox(msg === 'lrOk' ? 'ok' : 'warn', t(msg)) : '') +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary" id="ht-play">' + ic('volume') + t('play') + ' (1/2)</button></div>' +
            '<div class="ht-choices" id="ht-lr" hidden>' +
            '<button type="button" class="ht-btn ht-choice" data-ans="L">' + ic('left') + t('left') + '</button>' +
            '<button type="button" class="ht-btn ht-choice" data-ans="both">' + t('cantTell') + '</button>' +
            '<button type="button" class="ht-btn ht-choice" data-ans="R">' + t('right') + ic('right') + '</button></div>' +
            '<div class="ht-actions ht-split"><button type="button" class="ht-btn ht-ghost" id="ht-back">' + ic('left') + t('back') + '</button></div>'
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
                btn.innerHTML = ic('volume') + t('play') + ' (2/2)';
            });
        });
    }

    function screenVolume(msg) {
        state.redraw = function () { screenVolume(); };
        var timer = null, dinOnly = CONFIG.mode === 'din';
        render(steps('vol') +
            (msg ? msgBox('ok', t(msg)) : '') +
            '<h3>' + t(dinOnly ? 'volTitleDin' : 'volTitle') + '</h3><p>' + t(dinOnly ? 'volTextDin' : 'volText') + '</p>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-glass ht-play" id="ht-play">' + ic('volume') + t('play') + '</button></div>' +
            '<p class="ht-hint ht-center">' + t('volCant') + '</p>' +
            msgBox('warn', t('volKeep')) +
            '<div class="ht-actions ht-split"><button type="button" class="ht-btn ht-ghost" id="ht-back">' + ic('left') + t('back') + '</button>' +
            '<button type="button" class="ht-btn ht-primary" id="ht-done">' + t('volDone') + ic('right') + '</button></div>'
        );
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        on('#ht-play', 'click', function () {
            var btn = $('#ht-play');
            if (timer) { stop(); btn.innerHTML = ic('volume') + t('play'); btn.classList.remove('on'); return; }
            Audio.init();
            btn.innerHTML = ic('stop') + t('playing');
            btn.classList.add('on');
            if (dinOnly) {
                // Speech sample in light noise, so the volume is set on the voice the test uses.
                var sample = function () { Din.play(Din.triplet(), 10); };
                Din.load(lang).then(function () {
                    if (btn.isConnected && !timer) { sample(); timer = setInterval(sample, 4500); }
                }).catch(function () { btn.textContent = t('dinLoadErr'); });
                return;
            }
            Audio.pulses(1000, 'both', 40, 3);
            timer = setInterval(function () { Audio.pulses(1000, 'both', 40, 3); }, 2000);
        });
        on('#ht-back', 'click', function () { stop(); screenLR(); });
        on('#ht-done', 'click', function () { stop(); if (dinOnly) { screenDinIntro(); } else { screenTestIntro('R'); } });
    }

    function screenTestIntro(ear) {
        state.redraw = function () { screenTestIntro(ear); };
        render(steps(ear) +
            (ear === 'L' ? msgBox('ok', t('switchEar')) : '') +
            earAnim(ear) +
            '<h3 class="ht-center">' + t('testTitle', { ear: t(ear === 'R' ? 'earRight' : 'earLeft') }) + '</h3>' +
            '<p class="ht-center">' + t(CONFIG.toneMethod === 'adjust' ? 'testTextAdj' : 'testTextClassic') + '</p>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-primary ht-wide" id="ht-ready">' + t('ready') + ic('right') + '</button></div>'
        );
        on('#ht-ready', 'click', function () { if (CONFIG.toneMethod === 'adjust') { runEarAdjust(ear); } else { runEar(ear); } });
    }

    // Ear outline draws itself, then sound bars ripple out from it (mirrored for the left ear).
    function earAnim(ear) {
        var hs = [10, 22, 34, 20, 42, 28, 16, 30, 12];
        return '<svg class="ht-earanim ht-earanim-' + ear + '" viewBox="0 0 132 80" aria-hidden="true">' +
            '<g transform="translate(6 6) scale(2.8)" class="ht-earanim-ear"><path pathLength="1" d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/><path pathLength="1" d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/></g>' +
            hs.map(function (h, i) {
                return '<rect class="ht-wbar" x="' + (72 + i * 6.4) + '" y="' + (40 - h / 2) + '" width="2.6" height="' + h + '" rx="1.3" style="--i:' + i + '"/>';
            }).join('') + '</svg>';
    }

    /* ---------------- Test procedure ---------------- */

    // Simplified Hughson-Westlake: down 10 after a response, up 5 after a miss;
    // threshold = lowest level heard on 2 ascending presentations.
    function runEar(ear) {
        state.running = true;
        var freqs = CONFIG.freqs.slice();
        if (ear === 'R' && CONFIG.retest) { freqs.push('retest'); }
        var total = CONFIG.freqs.length * 2 + (CONFIG.retest ? 1 : 0);
        var done = ear === 'R' ? 0 : total - CONFIG.freqs.length;

        state.redraw = function () {}; // language buttons disabled while running
        render(steps(ear) +
            '<h3>' + t('testTitle', { ear: t(ear === 'R' ? 'earRight' : 'earLeft') }) + '</h3>' +
            '<div class="ht-ear ht-ear-' + ear + '">' + ic('ear') + (ear === 'R' ? t('rightEar') : t('leftEar')) + '</div>' +
            '<div class="ht-count" id="ht-count" aria-live="polite"></div>' +
            '<div class="ht-bar ht-bar-test"><span id="ht-prog"></span></div>' +
            '<p class="ht-status" id="ht-status">' + t('listening') + '</p>' +
            '<button type="button" class="ht-hear" id="ht-hear">' + ic('ear') + '<span>' + t('hear') + '</span></button>' +
            '<div class="ht-actions"><button type="button" class="ht-btn ht-ghost" id="ht-pause">' + ic('pause') + t('pause') + '</button></div>'
        );
        var prog = $('#ht-prog');
        var hearBtn = $('#ht-hear');
        var count = $('#ht-count');
        // Counts pitches across both ears; the number of beeps per pitch varies, so beeps are not counted.
        function setProg() {
            prog.style.width = Math.round(done / total * 100) + '%';
            count.textContent = t('toneCount', { n: num(Math.min(done + 1, total)), m: num(total) });
        }
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
            $('#ht-pause').innerHTML = state.paused ? ic('play') + t('resume') : ic('pause') + t('pause');
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
            afterEar(ear);
        })();
    }

    function afterEar(ear) {
        if (ear === 'R') { screenTestIntro('L'); } else if (CONFIG.mode === 'full') { screenDinIntro(); } else { screenQuestions(); }
    }

    // Method of adjustment (ascending): each tone starts below audibility and the visitor
    // raises it in 5 dB steps until it is barely audible. Faster and easier than tap-to-respond.
    function runEarAdjust(ear) {
        var freqs = CONFIG.freqs.slice();
        if (ear === 'R' && CONFIG.retest) { freqs.push('retest'); }
        var total = CONFIG.freqs.length * 2 + (CONFIG.retest ? 1 : 0);
        var done = ear === 'R' ? 0 : total - CONFIG.freqs.length;
        var i = 0;
        state.running = true;
        state.redraw = function () {};
        (function next() {
            if (i >= freqs.length) { state.running = false; afterEar(ear); return; }
            var f = freqs[i];
            // Back redoes the previous sound of this ear.
            var back = i > 0 ? function () { i--; done--; next(); } : null;
            adjustTone(ear, f === 'retest' ? 1000 : f, done + 1, total, back, function (r) {
                if (f === 'retest') {
                    state.retest = { first: state.results.R[1000].db, second: r.db };
                    if (!r.noResp && r.db < state.results.R[1000].db) { state.results.R[1000] = r; }
                } else {
                    state.results[ear][f] = r;
                }
                i++;
                done++;
                next();
            });
        })();
    }

    function adjustTone(ear, freq, n, total, back, cb) {
        var min = CONFIG.minLevel, max = Audio.maxLevel(freq), top = Math.round((max - min) / 5), idx = 0;
        render(steps(ear) +
            '<div class="ht-count">' + t('toneCount', { n: num(n), m: num(total) }) + '</div>' +
            '<h3 class="ht-adj-title">' + t('adjTitle') + '</h3>' +
            '<div class="ht-adj-label"><span class="ht-ear ht-ear-' + ear + '">' + ic('ear') + t(ear === 'R' ? 'rightEar' : 'leftEar') + '</span><span class="ht-freq">' + pitchName(freq) + ' <small>' + num(freq) + ' Hz</small></span>' +
            '<span class="ht-live" id="ht-live" aria-hidden="true"><i></i><i></i><i></i><i></i></span></div>' +
            '<div class="ht-adj">' +
            '<div class="ht-roundwrap"><button type="button" class="ht-round" id="ht-minus" aria-label="' + t('softer') + '">' + ic('minus') + '</button><span>' + t('softer') + '</span></div>' +
            '<div class="ht-adj-track"><output id="ht-val" class="ht-adj-val">' + num(1) + '</output>' +
            '<input type="range" id="ht-range" min="0" max="' + top + '" step="1" value="0" aria-label="' + t('louder') + '"></div>' +
            '<div class="ht-roundwrap"><button type="button" class="ht-round" id="ht-plus" aria-label="' + t('louder') + '">' + ic('plus') + '</button><span>' + t('louder') + '</span></div>' +
            '</div>' +
            '<p class="ht-hint ht-center">' + t('adjHint') + '</p>' +
            '<div class="ht-actions ht-split">' +
            (back ? '<button type="button" class="ht-btn ht-ghost" id="ht-prev">' + ic('left') + t('back') + '</button>' : '') +
            '<button type="button" class="ht-btn ht-ghost" id="ht-cant" hidden>' + t('adjCant') + '</button>' +
            '<button type="button" class="ht-btn ht-primary" id="ht-cont">' + t('cont') + ic('right') + '</button></div>'
        );
        var range = $('#ht-range'), val = $('#ht-val'), live = $('#ht-live');
        var tone = Audio.hold(freq, ear, min);
        function set(v) {
            var prev = idx;
            idx = Math.max(0, Math.min(top, v));
            tone.set(min + idx * 5);
            range.value = idx;
            val.textContent = num(idx + 1);
            range.style.setProperty('--pct', (idx / top * 100) + '%');
            live.style.setProperty('--lv', (0.25 + 0.75 * idx / top).toFixed(2));
            if (idx !== prev) { val.classList.remove('pop'); void val.offsetWidth; val.classList.add('pop'); }
            $('#ht-cant').hidden = idx < top;
        }
        set(0);
        // Silence the tone while the page is in the background.
        function onVis() { tone.mute(document.hidden); }
        document.addEventListener('visibilitychange', onVis);
        function finish(noResp) {
            tone.stop();
            document.removeEventListener('visibilitychange', onVis);
            cb({ db: noResp ? max : min + idx * 5, noResp: noResp });
        }
        range.addEventListener('input', function () { set(Number(range.value)); });
        on('#ht-minus', 'click', function () { set(idx - 1); });
        on('#ht-plus', 'click', function () { set(idx + 1); });
        on('#ht-cant', 'click', function () { finish(true); });
        on('#ht-cont', 'click', function () { finish(false); });
        on('#ht-prev', 'click', function () {
            tone.stop();
            document.removeEventListener('visibilitychange', onVis);
            back();
        });
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

    /* ---------------- Digits-in-noise (DIN) ---------------- */

    // Iterative radix-2 FFT, in place; inverse when inv is true (unscaled).
    function fft(re, im, inv) {
        var n = re.length, i, j, k, tmp;
        for (i = 1, j = 0; i < n; i++) {
            var bit = n >> 1;
            for (; j & bit; bit >>= 1) { j ^= bit; }
            j ^= bit;
            if (i < j) {
                tmp = re[i]; re[i] = re[j]; re[j] = tmp;
                tmp = im[i]; im[i] = im[j]; im[j] = tmp;
            }
        }
        for (var len = 2; len <= n; len <<= 1) {
            var ang = 2 * Math.PI / len * (inv ? 1 : -1), wr = Math.cos(ang), wi = Math.sin(ang), half = len >> 1;
            for (i = 0; i < n; i += len) {
                var cr = 1, ci = 0;
                for (k = 0; k < half; k++) {
                    var a = i + k, b = a + half;
                    var tr = re[b] * cr - im[b] * ci, ti = re[b] * ci + im[b] * cr;
                    re[b] = re[a] - tr; im[b] = im[a] - ti;
                    re[a] += tr; im[a] += ti;
                    tmp = cr * wr - ci * wi; ci = cr * wi + ci * wr; cr = tmp;
                }
            }
        }
    }

    function rms(arr) {
        var s = 0;
        for (var i = 0; i < arr.length; i++) { s += arr[i] * arr[i]; }
        return Math.sqrt(s / arr.length) || 1;
    }

    // Cuts silence below -40 dB re peak (10 ms windows), keeping 30 ms of padding.
    function trimSilence(x, sr) {
        var peak = 0, i, k;
        for (i = 0; i < x.length; i++) { peak = Math.max(peak, Math.abs(x[i])); }
        var win = Math.max(1, Math.round(sr * 0.01)), thr = peak * 0.01, first = -1, last = -1;
        for (i = 0; i < x.length; i += win) {
            var m = 0;
            for (k = i; k < Math.min(i + win, x.length); k++) { m = Math.max(m, Math.abs(x[k])); }
            if (m > thr) { if (first < 0) { first = i; } last = Math.min(i + win, x.length); }
        }
        if (first < 0) { return x; }
        var pad = Math.round(sr * 0.03);
        return x.subarray(Math.max(0, first - pad), Math.min(x.length, last + pad));
    }

    var Din = {
        cache: {},       // lang -> Promise<{ digits: {d: AudioBuffer}, noise: AudioBuffer }>
        set: null,
        DIN_RMS: 0.04,   // combined speech + noise RMS; constant so loudness does not jump between triplets

        load: function (l) {
            if (this.cache[l]) { return this.cache[l]; }
            Audio.init();
            var ctx = Audio.ctx, ds = CONFIG.din.digits[l];
            var p = Promise.all(ds.map(function (d) {
                // MP3 first, then WAV (the digit recorder tool saves WAV).
                var base = CONFIG.audioBase + l + '/' + d;
                return fetch(base + '.mp3')
                    .then(function (r) { return r.ok ? r : fetch(base + '.wav'); })
                    .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.arrayBuffer(); })
                    .then(function (buf) { return new Promise(function (res, rej) { ctx.decodeAudioData(buf, res, rej); }); });
            })).then(function (bufs) {
                // Trim leading/trailing silence, then equalise every digit to RMS 1 so the SNR is exact for each one.
                var digits = {}, all = [];
                bufs.forEach(function (b, i) {
                    var src = trimSilence(b.getChannelData(0), b.sampleRate), g = 1 / rms(src);
                    var out = ctx.createBuffer(1, src.length, b.sampleRate), dst = out.getChannelData(0);
                    for (var k = 0; k < src.length; k++) { dst[k] = src[k] * g; }
                    digits[ds[i]] = out;
                    all.push(dst);
                });
                return { digits: digits, noise: Din.speechNoise(all, ctx) };
            });
            p.catch(function () { delete Din.cache[l]; });
            this.cache[l] = p;
            return p;
        },

        // Stationary noise with the long-term spectrum of the digit recordings (random-phase method).
        // It loops seamlessly because the inverse FFT is circular.
        speechNoise: function (signals, ctx) {
            var SEG = 4096, psd = new Float64Array(SEG / 2 + 1), segs = 0, win = new Float64Array(SEG), i, k;
            for (i = 0; i < SEG; i++) { win[i] = 0.5 - 0.5 * Math.cos(2 * Math.PI * i / (SEG - 1)); }
            var total = 0;
            signals.forEach(function (s) { total += s.length; });
            var cat = new Float64Array(Math.max(total, SEG)), off = 0;
            signals.forEach(function (s) { cat.set(s, off); off += s.length; });
            for (var start = 0; start + SEG <= cat.length; start += SEG / 2) {
                var re = new Float64Array(SEG), im = new Float64Array(SEG);
                for (i = 0; i < SEG; i++) { re[i] = cat[start + i] * win[i]; }
                fft(re, im, false);
                for (k = 0; k <= SEG / 2; k++) { psd[k] += re[k] * re[k] + im[k] * im[k]; }
                segs++;
            }
            var N = 1 << 18, nre = new Float64Array(N), nim = new Float64Array(N);
            for (k = 1; k < N / 2; k++) {
                var pos = k * SEG / N, lo = Math.floor(pos), fr = pos - lo;
                var mag = Math.sqrt(((psd[lo] * (1 - fr) + psd[Math.min(lo + 1, SEG / 2)] * fr) / Math.max(segs, 1)));
                var ph = Math.random() * 2 * Math.PI;
                nre[k] = mag * Math.cos(ph); nim[k] = mag * Math.sin(ph);
                nre[N - k] = nre[k]; nim[N - k] = -nim[k];
            }
            fft(nre, nim, true);
            var buf = ctx.createBuffer(1, N, ctx.sampleRate), d = buf.getChannelData(0), g = 1 / rms(nre);
            for (i = 0; i < N; i++) { d[i] = nre[i] * g; }
            return buf;
        },

        triplet: function () {
            var pool = CONFIG.din.digits[this.lang || lang].slice(), out = [];
            for (var i = 0; i < 3; i++) { out.push(pool.splice(Math.floor(Math.random() * pool.length), 1)[0]); }
            return out;
        },

        // Plays three digits in gated noise at the given SNR (both ears). Returns duration in ms.
        play: function (digits, snr) {
            var ctx = Audio.ctx, set = this.set;
            if (!set) { return 0; }
            var r = Math.pow(10, snr / 20), gn = this.DIN_RMS / Math.sqrt(1 + r * r), gs = gn * r;
            var t0 = ctx.currentTime + 0.1, lead = 0.5, gap = 0.3, ramp = 0.1, at = t0 + lead;
            var srcs = [];
            digits.forEach(function (d) {
                var s = ctx.createBufferSource(), g = ctx.createGain();
                s.buffer = set.digits[d];
                g.gain.value = gs;
                s.connect(g); g.connect(ctx.destination);
                s.start(at);
                at += s.buffer.duration + gap;
                srcs.push([s, g]);
            });
            var end = at - gap + lead;
            var n = ctx.createBufferSource(), ng = ctx.createGain();
            n.buffer = set.noise;
            n.loop = true;
            ng.gain.setValueAtTime(0, t0);
            ng.gain.linearRampToValueAtTime(gn, t0 + ramp);
            ng.gain.setValueAtTime(gn, end - ramp);
            ng.gain.linearRampToValueAtTime(0, end);
            n.connect(ng); ng.connect(ctx.destination);
            n.start(t0, Math.random() * set.noise.duration);
            n.stop(end + 0.05);
            n.onended = function () {
                n.disconnect(); ng.disconnect();
                srcs.forEach(function (p) { p[0].disconnect(); p[1].disconnect(); });
            };
            return Math.round((end - ctx.currentTime) * 1000);
        }
    };

    function screenDinIntro() {
        state.redraw = screenDinIntro;
        render(steps('din') +
            '<div class="ht-ear-hero">' + ic('wave') + '</div>' +
            '<h3 class="ht-center">' + t('dinTitle') + '</h3><p class="ht-center">' + t('dinText') + '</p>' +
            '<div class="ht-msg warn" id="ht-din-msg" hidden>' + ic('alert') + '<span></span></div>' +
            '<div class="ht-actions ht-split"><button type="button" class="ht-btn ht-ghost" id="ht-skip">' + t('skip') + '</button>' +
            '<button type="button" class="ht-btn ht-primary" id="ht-din-start">' + t('dinStart') + ic('right') + '</button></div>'
        );
        on('#ht-skip', 'click', function () {
            state.din = null;
            if (state.dinOptional) { state.dinOptional = false; screenResults(); } else { screenQuestions(); }
        });
        on('#ht-din-start', 'click', function () {
            var btn = $('#ht-din-start'), msg = $('#ht-din-msg');
            btn.disabled = true;
            btn.textContent = t('dinLoading');
            Din.load(lang).then(function (set) {
                Din.set = set;
                Din.lang = lang;
                runDin();
            }).catch(function () {
                msg.hidden = false;
                msg.querySelector('span').textContent = t('dinLoadErr');
                btn.disabled = false;
                btn.innerHTML = t('dinStart') + ic('right');
            });
        });
    }

    // Adaptive 1-up/1-down on whole-triplet correctness (after Smits et al. 2004):
    // first triplet repeats 4 dB easier until correct; 4 dB steps down until the first miss,
    // then 2 dB steps. SRT = mean SNR of the levels after the approach phase, plus the next one
    // (classic 24-triplet run: triplets 5-25).
    function runDin() {
        var cfg = CONFIG.din;
        state.running = true;
        state.redraw = function () {};
        render(steps('din') +
            '<h3>' + t('dinTitle') + '</h3>' +
            '<div class="ht-count" id="ht-count" aria-live="polite"></div>' +
            '<div class="ht-bar ht-bar-test"><span id="ht-prog"></span></div>' +
            '<p class="ht-status" id="ht-status">' + t('dinListen') + '</p>' +
            '<div class="ht-slots" id="ht-slots"><span></span><span></span><span></span></div>' +
            '<div class="ht-keypad" id="ht-keypad">' +
            [1, 2, 3, 4, 5, 6, 7, 8, 9].map(function (d) { return '<button type="button" data-d="' + d + '">' + num(d) + '</button>'; }).join('') +
            '<button type="button" data-d="back" aria-label="Delete">' + ic('del') + '</button>' +
            '<button type="button" data-d="0">' + num(0) + '</button>' +
            '<button type="button" data-d="ok" class="ht-key-ok" aria-label="' + t('ok') + '">' + ic('check') + '</button>' +
            '</div>'
        );
        var prog = $('#ht-prog'), status = $('#ht-status'), slots = root.querySelectorAll('#ht-slots span');
        var keys = root.querySelectorAll('#ht-keypad button');
        var entry = [], resolver = null, accepting = false;

        function draw() {
            slots.forEach(function (s, i) { s.textContent = entry[i] !== undefined ? num(entry[i]) : ''; s.classList.toggle('on', i === entry.length); });
            keys.forEach(function (b) {
                var d = b.getAttribute('data-d');
                b.disabled = !accepting || (d === 'ok' ? entry.length !== 3 : d === 'back' ? !entry.length : entry.length >= 3);
            });
        }
        function key(d) {
            if (!accepting) { return; }
            if (d === 'back') { entry.pop(); } else if (d === 'ok') {
                if (entry.length === 3 && resolver) { var r = resolver; resolver = null; accepting = false; r(entry.slice()); }
            } else if (entry.length < 3) { entry.push(Number(d)); }
            draw();
        }
        keys.forEach(function (b) { b.addEventListener('click', function () { key(b.getAttribute('data-d')); }); });
        state.keyHandler = function (e) {
            var bnIdx = '০১২৩৪৫৬৭৮৯'.indexOf(e.key);
            if (/^[0-9]$/.test(e.key)) { key(e.key); } else if (bnIdx >= 0) { key(String(bnIdx)); } else if (e.key === 'Backspace') { e.preventDefault(); key('back'); } else if (e.key === 'Enter') { key('ok'); }
        };
        document.addEventListener('keydown', state.keyHandler);

        async function present(snr) {
            var trip = Din.triplet();
            entry = [];
            accepting = false;
            status.textContent = t('dinListen');
            draw();
            await wait(400);
            var ms = Din.play(trip, snr);
            await wait(Math.max(ms - 300, 0));
            accepting = true;
            status.textContent = t('dinType');
            draw();
            var ans = await new Promise(function (res) { resolver = res; });
            var hits = ans.filter(function (d, i) { return d === trip[i]; }).length;
            state.dinDigits.n += 3;
            state.dinDigits.ok += hits;
            return hits === 3;
        }

        (async function () {
            var snr = cfg.startSnr, levels = [], misses = 0;
            var count = $('#ht-count');
            prog.style.width = '0%';
            count.textContent = t('dinTrial', { n: num(1), m: num(cfg.trials) });
            // First triplet: get it right, raising the SNR 4 dB after each miss.
            while (!(await present(snr))) {
                if (snr >= cfg.maxSnr && ++misses >= 3) { break; }
                snr = Math.min(snr + 4, cfg.maxSnr);
            }
            var completed = misses < 3;
            if (completed) {
                levels.push(snr);
                var missed = false;
                snr = Math.max(snr - cfg.fastStep, cfg.minSnr);
                for (var n = 2; n <= cfg.trials; n++) {
                    prog.style.width = Math.round((n - 1) / cfg.trials * 100) + '%';
                    count.textContent = t('dinTrial', { n: num(n), m: num(cfg.trials) });
                    levels.push(snr);
                    var ok = await present(snr);
                    if (!ok) { missed = true; }
                    var st = missed ? cfg.step : cfg.fastStep;
                    snr = Math.min(Math.max(snr + (ok ? -st : st), cfg.minSnr), cfg.maxSnr);
                }
                levels.push(snr); // the level the 25th triplet would have used
            }
            var used = levels.slice(cfg.trials >= 20 ? 4 : 6);
            state.din = completed ? {
                srt: Math.round(used.reduce(function (s, v) { return s + v; }, 0) / used.length * 10) / 10,
                levels: levels
            } : { srt: null, levels: levels };
            document.removeEventListener('keydown', state.keyHandler);
            state.running = false;
            if (state.dinOptional) {
                state.summary = score();
                screenResults();
                root.dispatchEvent(new CustomEvent('hearingtest:complete', { bubbles: true, detail: state.summary }));
            } else {
                screenQuestions();
            }
        })();
    }

    /* ---------------- Questionnaire ---------------- */

    function screenQuestions() {
        state.redraw = screenQuestions;
        var qs = ['q1', 'q2', 'q3', 'q4', 'q5'];
        render(steps('qs') + '<h3>' + t('qTitle') + '</h3><div class="ht-qs">' +
            qs.map(function (q) {
                var v = state.answers[q];
                return '<div class="ht-q"><div class="ht-q-text">' + t(q) + '</div><div class="ht-yn">' +
                    '<button type="button" data-q="' + q + '" data-v="1" class="' + (v === 1 ? 'on' : '') + '">' + t('yes') + '</button>' +
                    '<button type="button" data-q="' + q + '" data-v="0" class="' + (v === 0 ? 'on' : '') + '">' + t('no') + '</button>' +
                    '</div></div>';
            }).join('') +
            '</div><div class="ht-actions"><button type="button" class="ht-btn ht-primary ht-wide" id="ht-res">' + t('seeResults') + ic('right') + '</button></div>'
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
    var DIN_BAND = { good: 'normal', borderline: 'mild', poor: 'moderate' };

    function dinCategory(srt) {
        if (srt === null) { return 'poor'; }
        if (srt <= CONFIG.din.good) { return 'good'; }
        return srt <= CONFIG.din.poor ? 'borderline' : 'poor';
    }

    function score() {
        var tones = CONFIG.mode !== 'din';
        var din = state.din ? { srt: state.din.srt, category: dinCategory(state.din.srt), digitsCorrect: state.dinDigits.ok + '/' + state.dinDigits.n } : null;
        var ears = {};
        ['R', 'L'].forEach(function (e) {
            var res = state.results[e];
            var vals = PTA_FREQS.filter(function (f) { return res[f]; }).map(function (f) { return res[f].noResp ? res[f].db + 5 : res[f].db; });
            var pta = vals.length ? Math.round(vals.reduce(function (s, v) { return s + v; }, 0) / vals.length) : 0;
            var high = [4000, 8000].some(function (f) { return res[f] && res[f].db >= 35; });
            ears[e] = { pta: pta, band: band(pta), high: high, thresholds: res };
        });
        var worst = BAND_RANK[ears.R.band] >= BAND_RANK[ears.L.band] ? ears.R.band : ears.L.band;
        if (!tones) { worst = din ? DIN_BAND[din.category] : 'normal'; }
        var yes = Object.keys(state.answers).filter(function (k) { return state.answers[k] === 1; }).length;
        var flags = [];
        if (tones && worst === 'normal' && (ears.R.high || ears.L.high)) { flags.push('flagHigh'); }
        if (tones && Math.abs(ears.R.pta - ears.L.pta) >= 15) { flags.push('flagAsym'); }
        if (tones && din && din.category !== 'good') { flags.push('flagDin'); }
        if (yes >= 2) { flags.push('flagQs'); }
        var unreliable = (state.catchN >= 2 && state.catchFalse / state.catchN > 0.34) ||
            (state.retest && Math.abs(state.retest.first - state.retest.second) > 10) ||
            state.stray > 12;
        if (unreliable) { flags.push('flagUnreliable'); }
        return {
            mode: CONFIG.mode,
            overall: worst,
            right: tones ? { pta: ears.R.pta, band: ears.R.band, thresholds: flat(ears.R.thresholds) } : null,
            left: tones ? { pta: ears.L.pta, band: ears.L.band, thresholds: flat(ears.L.thresholds) } : null,
            din: din,
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
        var parts = ['Online hearing test (' + s.mode + '): overall ' + s.overall.toUpperCase()];
        if (s.right) {
            parts.push('Right ' + s.right.band + ' (PTA ~' + s.right.pta + ' dB)', 'Left ' + s.left.band + ' (PTA ~' + s.left.pta + ' dB)');
        }
        if (s.din) {
            parts.push('Digits-in-noise ' + s.din.category + (s.din.srt !== null ? ' (SRT ' + s.din.srt + ' dB SNR)' : ' (not completed)'));
        }
        if (s.flags.length) { parts.push('Flags: ' + s.flags.map(function (f) { return f.replace('flag', ''); }).join(', ')); }
        parts.push('Questionnaire yes: ' + s.questionnaireYes + '/5');
        if (s.right) { parts.push('R ' + JSON.stringify(s.right.thresholds) + ' L ' + JSON.stringify(s.left.thresholds)); }
        return parts.join(' | ');
    }

    /* ---------------- Results ---------------- */

    function screenResults() {
        state.redraw = screenResults;
        var s = state.summary;
        function earCard(key, e) {
            return '<div class="ht-earres ht-b-' + e.band + '"><div class="ht-earres-name">' + ic('ear') + t(key) + '</div>' +
                gauge(e.pta) +
                '<div class="ht-earres-band">' + t('band_' + e.band) + '</div>' +
                '<div class="ht-earres-avg">' + t('avg', { db: num(e.pta) }) + '</div></div>';
        }
        var tones = !!s.right;
        var resultLine = tones ? t('band_' + s.overall) : (s.din ? t('dinResTitle') + ': ' + t('din_' + s.din.category) : '');
        var sumKey = tones || !s.din ? 'sum_' + s.overall : 'sumdin_' + s.din.category;
        var dinCard = s.din ? '<div class="ht-din-res ht-b-' + DIN_BAND[s.din.category] + '">' +
            '<div class="ht-earres-name">' + ic('wave') + t('dinResTitle') + '</div>' +
            '<div class="ht-earres-band">' + t('din_' + s.din.category) + '</div>' +
            (s.din.srt !== null ? '<div class="ht-earres-avg">' + t('dinScore', { v: num(s.din.srt) }) + '</div>' : '') + '</div>' : '';
        var wa = CONFIG.whatsapp ? 'https://wa.me/' + CONFIG.whatsapp.replace(/\D/g, '') + '?text=' + encodeURIComponent(t('waMsg', { r: resultLine })) : '';

        render(steps('results') +
            '<h3>' + t('resTitle') + '</h3>' +
            '<div class="ht-summary ht-b-' + s.overall + '">' + ic(s.overall === 'normal' ? 'checkCircle' : 'info') + '<span>' + t(sumKey) + '</span></div>' +
            (tones ? '<div class="ht-ears">' + earCard('rightEar', s.right) + earCard('leftEar', s.left) + '</div>' : '') +
            dinCard +
            (s.flags.length ? '<ul class="ht-flags">' + s.flags.map(function (f) { return '<li>' + ic(f === 'flagUnreliable' ? 'alert' : 'info') + '<span>' + t(f) + '</span></li>'; }).join('') + '</ul>' : '') +
            (CONFIG.mode === 'quick' && !s.din ? '<div class="ht-offer"><div class="ht-offer-ic">' + ic('wave') + '</div><div class="ht-offer-text">' +
                '<div class="ht-offer-title">' + t('offerTitle') + '</div><p>' + t('offerText') + '</p>' +
                '<button type="button" class="ht-btn ht-glass" id="ht-din-offer">' + t('offerBtn') + ic('right') + '</button></div></div>' : '') +
            (tones ? '<details class="ht-chart"><summary>' + ic('chevron') + '<span class="ht-show">' + t('showChart') + '</span><span class="ht-hide">' + t('hideChart') + '</span></summary>' +
                '<div class="ht-chart-title">' + t('chartTitle') + '</div>' + audiogram() + '</details>' : '') +
            '<p class="ht-note">' + ic('info') + '<span>' + t('note') + '</span></p>' +
            '<div class="ht-cta"><div class="ht-cta-title">' + t('ctaTitle') + '</div><div class="ht-cta-btns">' +
            (CONFIG.bookingUrl ? '<a class="ht-btn ht-primary" href="' + esc(CONFIG.bookingUrl) + '">' + ic('calendar') + t('book') + '</a>' : '') +
            (CONFIG.phone ? '<a class="ht-btn ht-glass" href="tel:' + esc(CONFIG.phone) + '">' + ic('phone') + t('call') + '</a>' : '') +
            (wa ? '<a class="ht-btn ht-wa" href="' + esc(wa) + '" target="_blank" rel="noopener">' + ic('chat') + t('whatsapp') + '</a>' : '') +
            '</div>' + (CONFIG.leadUrl ? leadForm() : '') + '</div>' +
            '<div class="ht-actions ht-split ht-noprint"><button type="button" class="ht-btn ht-ghost" id="ht-print">' + ic('printer') + t('print') + '</button>' +
            '<button type="button" class="ht-btn ht-ghost" id="ht-again">' + ic('redo') + t('retake') + '</button></div>' +
            '<div id="ht-print-panel"></div>'
        );
        on('#ht-print', 'click', function () { printPanel(); });
        on('#ht-again', 'click', function () { reset(); screenIntro(); });
        on('#ht-din-offer', 'click', function () { state.dinOptional = true; screenDinIntro(); });
        animateGauges();
        if (CONFIG.leadUrl) { bindLeadForm(); }
    }

    /* ---------------- Printable report ---------------- */

    function printPanel() {
        var box = $('#ht-print-panel');
        if (box.innerHTML) { box.querySelector('input').focus(); return; }
        box.innerHTML = '<form class="ht-panel ht-printform" id="ht-printform" novalidate>' +
            '<div class="ht-printform-head">' + ic('printer') + '<div><div class="ht-offer-title">' + t('printTitle') + '</div><p>' + t('printHelp') + '</p></div></div>' +
            '<div class="ht-printform-fields">' +
            '<label class="ht-field"><span>' + t('printName') + '</span><input type="text" id="ht-pname" autocomplete="name" maxlength="80" value="' + esc(state.person.name) + '"></label>' +
            '<label class="ht-field ht-field-age"><span>' + t('printAge') + '</span><input type="number" id="ht-page" inputmode="numeric" min="1" max="120" value="' + esc(state.person.age) + '" aria-describedby="ht-page-err"></label>' +
            '</div><p class="ht-field-err" id="ht-page-err" hidden>' + t('printAgeErr') + '</p>' +
            '<p class="ht-hint">' + t('printTip') + '</p>' +
            '<div class="ht-actions ht-split"><button type="button" class="ht-btn ht-ghost" id="ht-pcancel">' + t('cancel') + '</button>' +
            '<button type="submit" class="ht-btn ht-primary">' + ic('printer') + t('printGo') + '</button></div></form>';
        var form = $('#ht-printform');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        $('#ht-pname').focus();
        on('#ht-pcancel', 'click', function () { box.innerHTML = ''; $('#ht-print').focus(); });
        on('#ht-page', 'input', function () { $('#ht-page-err').hidden = true; this.setAttribute('aria-invalid', 'false'); });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name = $('#ht-pname').value.trim(), age = $('#ht-page').value.trim(), err = $('#ht-page-err');
            var ageOk = age === '' || (/^\d{1,3}$/.test(age) && +age >= 1 && +age <= 120);
            err.hidden = ageOk;
            $('#ht-page').setAttribute('aria-invalid', String(!ageOk));
            if (!ageOk) { $('#ht-page').focus(); return; }
            state.person = { name: name, age: age };
            printReport();
        });
    }

    // Builds a clean A4 report outside the widget and prints only that.
    function printReport() {
        var s = state.summary, tones = !!s.right, old = document.getElementById('ht-report');
        if (old) { old.remove(); }
        var d = new Date(), loc = lang === 'bn' ? 'bn-BD' : 'en-GB';
        var date = d.toLocaleDateString(loc, { day: 'numeric', month: 'long', year: 'numeric' }) + ', ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });
        var dash = '—', p = state.person;
        var test = (tones ? t('rpTestTone', { n: num(CONFIG.freqs.length) }) : '') + (s.din ? (tones ? t('rpTestDin') : t('dinResTitle')) : '');
        function row(k, v) { return '<div><dt>' + k + '</dt><dd>' + v + '</dd></div>'; }
        function earRow(key, e, side) {
            var cells = CHART_FREQS.map(function (f) {
                var r = state.results[side][f];
                return '<td>' + (r ? (r.noResp ? '&gt;' : '') + num(r.db) : dash) + '</td>';
            }).join('');
            return '<tr><th scope="row">' + t(key) + '</th><td class="rp-band rp-' + e.band + '">' + t('band_' + e.band) + '</td><td>' + num(e.pta) + ' dB</td>' + cells + '</tr>';
        }
        var rep = document.createElement('div');
        rep.id = 'ht-report';
        rep.className = 'ht-report';
        rep.lang = lang;
        rep.innerHTML =
            '<header class="rp-head">' + LOGO_SVG + '<div><div class="rp-brand">' + esc(CONFIG.brand) + '</div><div class="rp-title">' + t('rpTitle') + '</div></div></header>' +
            '<div class="rp-warn">' + ic('alert') + '<div><strong>' + t('rpNotClinical') + '.</strong> ' + t('rpNotClinicalText') + '</div></div>' +
            '<dl class="rp-meta">' + row(t('rpName'), p.name ? esc(p.name) : dash) + row(t('rpAge'), p.age ? t('rpYears', { n: num(p.age) }) : dash) +
            row(t('rpDate'), date) + row(t('rpTest'), test) + '</dl>' +
            '<section><h2>' + t('rpOverall') + '</h2><p class="rp-summary">' + t(tones || !s.din ? 'sum_' + s.overall : 'sumdin_' + s.din.category) + '</p></section>' +
            (tones ? '<section><table class="rp-table"><thead><tr><th scope="col">' + t('rpEar') + '</th><th scope="col">' + t('rpResult') + '</th><th scope="col">' + t('rpAvg') + '</th>' +
                CHART_FREQS.map(function (f) { return '<th scope="col">' + num(f) + ' Hz</th>'; }).join('') + '</tr></thead><tbody>' +
                earRow('rightEar', s.right, 'R') + earRow('leftEar', s.left, 'L') + '</tbody></table>' +
                '<div class="rp-chart"><div class="rp-chart-title">' + t('chartTitle') + '</div>' + audiogram() + '</div></section>' : '') +
            (s.din ? '<section><h2>' + t('dinResTitle') + '</h2><p><strong>' + t('din_' + s.din.category) + '</strong>' +
                (s.din.srt !== null ? ' · ' + t('dinScore', { v: num(s.din.srt) }) : '') + '</p></section>' : '') +
            (s.flags.length ? '<section><h2>' + t('rpNotes') + '</h2><ul>' + s.flags.map(function (f) { return '<li>' + t(f) + '</li>'; }).join('') + '</ul></section>' : '') +
            '<section><h2>' + t('rpNext') + '</h2><p>' + t(s.overall === 'normal' && !s.flags.length ? 'rpNextNormal' : 'rpNextBook') + '</p>' +
            ((CONFIG.phone || location.host) ? '<p class="rp-contact"><strong>' + t('rpContact') + ':</strong> ' + esc(CONFIG.brand) +
                (CONFIG.phone ? ' · ' + esc(CONFIG.phone) : '') + (location.host ? ' · ' + esc(location.host) : '') + '</p>' : '') + '</section>' +
            '<footer class="rp-foot">' + t('note') + '</footer>';
        document.body.appendChild(rep);
        document.body.classList.add('ht-printing');
        var done = function () {
            document.body.classList.remove('ht-printing');
            window.removeEventListener('afterprint', done);
        };
        window.addEventListener('afterprint', done);
        window.print();
    }

    // Half-circle gauge over the WHO bands (-10 to 80 dB HL), marker at the ear's average.
    function gauge(pta) {
        var cx = 60, cy = 58, r = 46, lo = -10, hi = 80;
        function pt(v) {
            var a = Math.PI * (1 - (Math.min(Math.max(v, lo), hi) - lo) / (hi - lo));
            return [cx + r * Math.cos(a), cy - r * Math.sin(a)];
        }
        function arc(a, b, cls) {
            var p = pt(a), q = pt(b);
            return '<path class="' + cls + '" d="M' + p[0].toFixed(2) + ' ' + p[1].toFixed(2) + 'A' + r + ' ' + r + ' 0 0 1 ' + q[0].toFixed(2) + ' ' + q[1].toFixed(2) + '"/>';
        }
        var m = pt(pta), end = Math.min(Math.max(pta, lo + 0.5), hi);
        var fill = arc(lo, end, 'g-fill').replace('<path ', '<path pathLength="1" ');
        return '<svg class="ht-gauge" viewBox="0 0 120 68" aria-hidden="true" data-v="' + pta + '">' +
            arc(-10, 19.5, 'g-normal') + arc(20.5, 34.5, 'g-mild') + arc(35.5, 49.5, 'g-moderate') + arc(50.5, 80, 'g-significant') +
            fill + '<circle class="g-mark" cx="' + m[0].toFixed(2) + '" cy="' + m[1].toFixed(2) + '" r="6.5"/></svg>';
    }

    // Sweeps each gauge from the start of the scale to the ear's value: arc fills, marker rides along.
    function animateGauges() {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        root.querySelectorAll('.ht-gauge').forEach(function (svg, n) {
            var v = Number(svg.getAttribute('data-v')), fill = svg.querySelector('.g-fill'), mark = svg.querySelector('.g-mark');
            if (reduce) { return; }
            var cx = 60, cy = 58, r = 46, lo = -10, hi = 80, dur = 1100, delay = 250 + n * 150, t0 = null;
            var target = (Math.min(Math.max(v, lo), hi) - lo) / (hi - lo);
            fill.style.strokeDasharray = '1';
            fill.style.strokeDashoffset = '1';
            function frame(ts) {
                if (!svg.isConnected) { return; }
                if (t0 === null) { t0 = ts; }
                var k = Math.min(1, Math.max(0, (ts - t0 - delay) / dur)), e = 1 - Math.pow(1 - k, 3), f = target * e;
                var a = Math.PI * (1 - f);
                mark.setAttribute('cx', (cx + r * Math.cos(a)).toFixed(2));
                mark.setAttribute('cy', (cy - r * Math.sin(a)).toFixed(2));
                fill.style.strokeDashoffset = String(1 - e);
                if (k < 1) { requestAnimationFrame(frame); }
            }
            requestAnimationFrame(frame);
        });
    }

    function audiogram() {
        var W = 360, H = 260, L = 44, R = 14, T = 14, B = 34;
        var minDb = -10, maxDb = 100;
        var PAD = 16; // keep edge markers clear of the axis labels
        function x(f) { return L + PAD + CHART_FREQS.indexOf(f) * (W - L - R - 2 * PAD) / Math.max(CHART_FREQS.length - 1, 1); }
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
        return g + '<div class="ht-legend"><span class="ht-r"><i></i>' + t('legendR') + '</span><span class="ht-l"><i></i>' + t('legendL') + '</span></div>';
    }

    /* ---------------- Lead form (optional) ---------------- */

    function leadForm() {
        return '<form class="ht-form" id="ht-form" novalidate>' +
            '<label class="ht-field"><span>' + t('formName') + '</span><input type="text" name="name" autocomplete="name" maxlength="100" required></label>' +
            '<label class="ht-field"><span>' + t('formPhone') + '</span><input type="tel" name="phone" placeholder="01XXXXXXXXX" autocomplete="tel" inputmode="tel" maxlength="20" required></label>' +
            '<input type="text" name="website" class="ht-hp" tabindex="-1" autocomplete="off" aria-hidden="true">' +
            '<label class="ht-check"><input type="checkbox" name="consent" checked> <span>' + t('formConsent') + '</span></label>' +
            '<button type="submit" class="ht-btn ht-primary">' + ic('phone') + t('formSend') + '</button>' +
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
                form.innerHTML = msgBox('ok', t('formOk'));
                root.dispatchEvent(new CustomEvent('hearingtest:lead', { bubbles: true, detail: state.summary }));
            }).catch(function () {
                btn.disabled = false;
                btn.innerHTML = ic('phone') + t('formSend');
                msg.className = 'ht-form-msg err';
                msg.textContent = t('formErr');
            });
        });
    }

    /* ---------------- Boot ---------------- */

    function reset() {
        if (state && state.keyHandler) { document.removeEventListener('keydown', state.keyHandler); }
        state = {
            results: { R: {}, L: {} }, answers: {}, catchN: 0, catchFalse: 0, stray: 0, din: null, dinDigits: { n: 0, ok: 0 },
            retest: null, window: null, paused: false, running: false, dinOptional: false,
            person: { name: '', age: '' }, large: largeText(), startedAt: Date.now(), redraw: function () {}
        };
    }

    function largeText() {
        try { return localStorage.getItem('ht-large') === '1'; } catch (e) { return false; }
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
