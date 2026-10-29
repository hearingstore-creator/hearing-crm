# Website Online Hearing Test

A lightweight hearing **screening** test for the Hearing Store website (Laravel). It is independent of the CRM code. It uses plain JavaScript and the Web Audio API, has no dependencies, and is about 40 KB in total. It supports English and Bangla.

```
public/hearing-test/
  hearing-test.js     the test (audio engine, flow, scoring, results, lead form)
  hearing-test.css    styles, all scoped under .ht-root
  demo.html           open in a browser to try it (needs a web server for the digit sounds)
  digits/en, digits/bn  spoken digits for the Digits-in-Noise test (PLACEHOLDER robot voice – replace)
laravel/
  hearing-test.blade.php           example website page
  hearing_test.php                 website config -> config/hearing_test.php
  HearingTestLeadController.php    phase 2: forwards leads to the CRM
```

## How the test works

1. **Intro and consent:** screening disclaimer and what the visitor needs (headphones, quiet room, silent mode off).
2. **Headphone check:** one beep in each ear in random order, and the visitor must name both sides. This catches swapped or mono headphones.
3. **Volume setup:** the visitor turns the device volume to **maximum** and checks they can hear a beep. Every device then has the same reference point. A comfortable volume would let someone with hearing loss turn it up and hide the loss. The tones start below audibility, so maximum volume is safe.
4. **Tone test, right ear then left ear** (5 pitches per ear plus a 1 kHz repeat, with a "Sound n of 11" counter):
   - **Slider (default, `data-tone-method="adjust"`):** the same approach as ReSound. Each tone starts silent and the visitor presses + (or drags the slider) in 5 dB steps until it is barely audible, then taps Continue. "I can't hear it at all" appears at the top step.
   - **Classic (`data-tone-method="classic"`):** tap when you hear it. Uses a simplified Hughson-Westlake procedure (down 10 / up 5, threshold = 2 ascending responses) with silent catch trials. It is more rigorous but slower and harder for visitors.
5. **Digits in Noise (DIN):** a speech-in-noise test, the method behind WHO's hearWHO app.
   - The visitor hears three spoken digits in background noise, played to both ears, and types them on a keypad. Each round of three digits is a "triplet".
   - The noise has the same frequency profile as the digit voice. It is generated in the browser from the recordings, so no separate noise file is needed.
   - The first triplet repeats 4 dB easier until it is answered correctly. After that, a correct triplet makes the noise louder by 2 dB and a wrong one makes it quieter by 2 dB, for 24 triplets.
   - Score: **SRT** (speech reception threshold) = the average SNR of triplets 5 to 25. Lower is better. It is shown as Good / Borderline / Poor.
   - Combined speech and noise loudness stays constant, so the volume never jumps.
   - Speech and noise come out of the same speaker, so the result depends much less on the phone and headphones than the tone test does.
   - English leaves out "seven" and Bangla leaves out "শূন্য", because both are two syllables (standard DIN practice).
6. **Five quick questions** about everyday listening difficulty and tinnitus.
7. **Results:**
   - A band for each ear from the 4-frequency average (500/1k/2k/4k), using WHO-style grades: Normal under 20 dB, Mild 20–34, Moderate 35–49, Significant 50 or more.
   - An approximate audiogram (right ear red O, left ear blue X).
   - Notes for high-frequency dip, ear asymmetry (15 dB or more), questionnaire result and unreliable responses.
   - Book / Call / WhatsApp buttons and an optional call-back form.

Levels are **approximate dB HL**. A browser can't be calibrated to a clinical standard, so the module always presents itself as a screening and never as a diagnosis. Per-frequency headphone corrections are in `CORR` at the top of `hearing-test.js`.

## Digit recordings (must be replaced before going live)

The files in `digits/` are **robot-voice placeholders** generated with espeak-ng, so the test can be tried now. Before launch:

1. Record one clear speaker (the same person for every digit) in a quiet room:
   - English: zero, one, two, three, four, five, six, eight, nine.
   - Bangla: এক, দুই, তিন, চার, পাঁচ, ছয়, সাত, আট, নয়.
   - Say each digit in a neutral, even tone at the same distance from the microphone.
2. Trim the silence at the start and end. Export mono MP3 files named `0.mp3`–`9.mp3` into `digits/en/` and `digits/bn/`. The test equalises loudness automatically.
3. **Re-norm the cut-offs.** DIN scores depend on the voice and the recordings, so the default `-9` / `-6` are only provisional.
   - Have at least 20 adults aged 18–30 with normal hearing (confirmed on your audiometer) take the test with `data-mode="din"`, in each language.
   - Read each score from the results screen.
   - Set `data-din-good` = mean + 2 SD (the normal limit) and `data-din-poor` = that value + 3 dB.
   - Ideally, an audiologist also checks a few people with known hearing loss.

## Install on the website (Laravel)

1. Copy `public/hearing-test/` (including `digits/`) into the website's `public/` folder.
2. Copy `laravel/hearing_test.php` to `config/hearing_test.php`, and add to `.env`:
   ```
   HEARING_TEST_PHONE=+8801XXXXXXXXX
   HEARING_TEST_WHATSAPP=8801XXXXXXXXX
   HEARING_TEST_BOOKING_URL=/book-appointment
   ```
3. Add a page. See `laravel/hearing-test.blade.php`, and change the layout name and stacks to match the site:
   ```php
   Route::view('hearing-test', 'hearing-test')->name('hearing-test');
   ```

### Script options (`data-*` on the `<script>` tag)

| Attribute | Purpose |
|---|---|
| `data-lang` | `en` (default) or `bn`. Visitors can also switch language in the header. |
| `data-phone` | Shows a "Call us" button (`tel:`). |
| `data-whatsapp` | Digits only. Shows a WhatsApp button with a pre-filled message. |
| `data-booking-url` | Shows a "Book appointment" button. |
| `data-lead-url` | Shows the call-back form and POSTs JSON here. Leave empty to show no form. |
| `data-tone-method` | `adjust` (default: slider) or `classic` (tap when heard). |
| `data-mode` | `full` (default: tones then Digits in Noise), `tones` (tones only) or `din` (Digits in Noise only, about 3 min). |
| `data-audio-base` | Folder holding `en/` and `bn/` digit files. Defaults to `digits/` next to the script. |
| `data-din-good`, `data-din-poor` | Score cut-offs in dB SNR. Default `-9` and `-6` (provisional, see below). |
| `data-target` | Container selector, default `#hearing-test`. |
| `data-freqs` | Override the frequency list, e.g. `1000,2000,4000,500`. |

### Analytics events

```js
document.addEventListener('hearingtest:complete', e => gtag('event', 'hearing_test_complete', { result: e.detail.overall }));
document.addEventListener('hearingtest:lead', e => gtag('event', 'generate_lead', { source: 'hearing_test' }));
```

## Phase 2: send leads into the CRM

The CRM already accepts signed website leads at `POST /api/v1/leads` (`WebLeadController`). The browser never talks to the CRM directly. Instead, the website server signs and forwards each lead.

1. In the CRM, create (or reuse) a website **lead capture form** and copy its key and secret.
2. Website `.env`:
   ```
   HEARING_TEST_CRM_ENABLED=true
   CRM_URL=https://crm.zenhear.com
   CRM_LEAD_FORM_KEY=...
   CRM_LEAD_FORM_SECRET=...
   ```
3. Copy `laravel/HearingTestLeadController.php` to `app/Http/Controllers/`, then add the route:
   ```php
   Route::post('hearing-test/lead', [HearingTestLeadController::class, 'store'])
       ->name('hearing-test.lead')->middleware('throttle:5,1');
   ```
4. The Blade page then adds `data-lead-url` automatically and the call-back form appears on the results screen.

Each lead arrives in the CRM with `inquiry_for = hearing_test` and a one-line result summary in the message, for example:
`Online hearing test (full): overall MILD | Right normal (PTA ~10 dB) | Left mild (PTA ~30 dB) | Digits-in-noise borderline (SRT -7.4 dB SNR) | Flags: Asym, Din | Questionnaire yes: 2/5 | R {...} L {...}`

## Privacy

Nothing is stored or sent anywhere unless the visitor submits the call-back form.

## Testing checklist

- Android Chrome, iPhone Safari (silent switch off) and desktop Chrome/Edge.
- Wired earphones and Bluetooth earbuds. The response window allows 1.5 s for Bluetooth delay.
- Try both languages and the print/save option.
