# Website Online Hearing Test

A lightweight hearing **screening** test for the Hearing Store website (Laravel). It is independent of the CRM code. It uses plain JavaScript and the Web Audio API, has no dependencies, and is about 40 KB in total. It supports English and Bangla.

```
public/hearing-test/
  hearing-test.js     the test (audio engine, flow, scoring, results, lead form)
  hearing-test.css    styles, all scoped under .ht-root
  demo.html           open in a browser to try it (needs a web server for the digit sounds)
  digits/en, digits/bn  spoken digits for the Digits-in-Noise test (neural TTS voices)
laravel/
  hearing-test.blade.php           example website page
  hearing_test.php                 website config -> config/hearing_test.php
  HearingTestLeadController.php    phase 2: forwards leads to the CRM
```

## How the test works

1. **Intro and consent:** screening disclaimer and what the visitor needs (headphones, quiet room, silent mode off).
2. **Headphone check:** one beep in each ear in random order, and the visitor must name both sides. This catches swapped or mono headphones.
3. **Volume setup:** the visitor turns the device volume to **maximum** and checks they can hear a beep. Every device then has the same reference point. A comfortable volume would let someone with hearing loss turn it up and hide the loss. The tones start below audibility, so maximum volume is safe.
4. **Tone test, right ear then left ear** (4 pitches per ear: 1k, 2k, 4k and 500 Hz, the same set as ReSound, with a "Sound n of 8" counter):
   - Tones are **warble tones** by default: a ±5% pitch wobble at 5 Hz, the clinical standard for free-field testing and easier to notice than a flat beep. Use `data-tone-type="pure"` for plain sine tones.
   - Add 8 kHz with `data-freqs="1000,2000,4000,8000,500"`. Add a repeat of 1 kHz on the first ear as a reliability check with `data-retest="on"`.
   - **Slider (default, `data-tone-method="adjust"`):** the same approach as ReSound. A **continuous** tone starts very quiet. Its level follows + / − and the slider immediately, with 15 ms smoothing so there are no clicks. The visitor raises it in 5 dB steps until it is barely audible, then taps Continue. Continue also works at step 1, for people who already hear the quietest level. "I can't hear it at all" appears at the top step. The tone is muted while the page is in the background.
   - **Classic (`data-tone-method="classic"`):** tap when you hear it. Uses a simplified Hughson-Westlake procedure (down 10 / up 5, threshold = 2 ascending responses) with silent catch trials. It is more rigorous but slower and harder for visitors.
5. **Digits in Noise (DIN):** a speech-in-noise test, the method behind WHO's hearWHO app.
   - The visitor hears three spoken digits in background noise, played to both ears, and types them on a keypad. Each round of three digits is a "triplet".
   - The noise has the same frequency profile as the digit voice. It is generated in the browser from the recordings, so no separate noise file is needed.
   - The first triplet repeats 4 dB easier until it is answered correctly. The noise then gets louder in 4 dB steps until the first miss, then moves in 2 dB steps (louder after a correct answer, quieter after a wrong one).
   - **14 triplets by default (about 1 minute).** In 5,000 simulated runs per length, 14 triplets gave a typical score error of ±1.1 dB, against ±0.7 dB for the classic 24. Fewer than 1% of runs were off by more than 3 dB, the width of the "Borderline" band. Use `data-din-trials="24"` for the classic length.
   - Score: **SRT** (speech reception threshold) = the average SNR after the approach phase, plus the next level. Lower is better. It is shown as Good / Borderline / Poor.
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

## Digit recordings

The files in `digits/` were generated with Microsoft neural voices using `scripts/make_digits_tts.py`: **bn-BD-NabanitaNeural** (Bangla) and **en-US-JennyNeural** (English). They replace the earlier robot placeholders. Check the voice licence before commercial use. You can switch to a real recording later in either of two ways:

1. **Record a real voice (recommended).** This is what clinical DIN tests use, and it has no licensing questions.
   - Open `hearing-test/tools/digit-recorder.html` on your website, or on a local server, on a phone or laptop. The microphone needs https or localhost.
   - Record each digit with one clear speaker:
     - Bangla: এক, দুই, তিন, চার, পাঁচ, ছয়, সাত, আট, নয়.
     - English: zero, one, two, three, four, five, six, eight, nine.
   - The tool trims silence, matches levels and saves `1.wav`, `2.wav` and so on.
   - Copy the files into `digits/bn/` or `digits/en/` and delete the old `.mp3` files there. The test uses MP3 first and WAV if there is no MP3.
2. **Natural text-to-speech** (Microsoft neural voices `bn-BD-NabanitaNeural` / `en-US-JennyNeural`). Run it on your own computer, which needs internet:
   - **Windows:** install Python from python.org (tick "Add to PATH"), then double-click `scripts/make-digits-tts.bat`.
   - **Mac or Linux:** `pip install edge-tts`, then `python scripts/make_digits_tts.py`.
   - Options: `--bn-voice bn-BD-PradeepNeural` (male), `--en-voice en-GB-SoniaNeural`, `--rate -10%`, `--only bn`.
   - No ffmpeg needed. The test trims silence and matches loudness itself. A file is only replaced once its new version has downloaded completely.
   - Check the voice licence before commercial use.

3. **Re-norm the cut-offs.** DIN scores depend on the voice and the recordings, so the default `-9` / `-6` are only provisional.
   - Have at least 20 adults aged 18–30 with normal hearing (confirmed on your audiometer) take the test with `data-mode="din"`, in each language.
   - Read each score from the results screen.
   - Set `data-din-good` = mean + 2 SD (the normal limit) and `data-din-poor` = that value + 3 dB.
   - Ideally, an audiologist also checks a few people with known hearing loss.

## Visitor-friendly features

Following senior-friendly and accessibility guidance (UI UX Pro Max "Accessible & Ethical" style, WCAG):
- 17px base text, nothing under 12px, and 44–50px touch targets.
- A **larger text** button (the "Tт" icon) in the header. The setting is remembered on that device.
- Plain-language pitch names (Low / Middle / High / Very high pitch) with the Hz value shown small.
- "Softer" and "Louder" labels under the − / + buttons, and a **Back** button to redo the previous sound.
- The detailed audiogram is folded behind "Show detailed chart" so the results stay calm.
- Form fields have visible labels.
- **Print / save report:** the visitor can add an optional name and age (validated as 1–120). This prints a one-page A4 report with:
  - a prominent **"Not a clinical test"** notice
  - the date and test type
  - results for each ear, with the value at each pitch
  - the audiogram
  - the noise test result, if taken
  - notes and a recommended next step
  - your contact details
  
  "Save as PDF" in the print window creates a PDF. The name and age never leave the device. Set the brand name with `data-brand` (default "Hearing Store").

## Install on the website (Laravel)

The widget uses a frosted-glass card with line icons, and is designed for **Manrope** (English) and **Hind Siliguri** (Bangla). Load both fonts from Google Fonts in the page head, as `laravel/hearing-test.blade.php` does. Without them it falls back to the system font.


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
| `data-brand` | Clinic name on the printed report. Default `Hearing Store`. |
| `data-tone-type` | `warble` (default) or `pure`. |
| `data-freqs` | Pitches tested per ear, in order. Default `1000,2000,4000,500`. |
| `data-retest` | `on` repeats 1 kHz on the first ear as a reliability check. Off by default. |
| `data-tone-method` | `adjust` (default: slider) or `classic` (tap when heard). |
| `data-mode` | `quick` (default: tones, then the noise test offered on the results page), `full` (tones then noise test in one go), `tones` (tones only) or `din` (noise test only, about 1 min). |
| `data-audio-base` | Folder holding `en/` and `bn/` digit files. Defaults to `digits/` next to the script. |
| `data-din-trials` | Number of triplets in the noise test, 8–30. Default `14`. |
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
