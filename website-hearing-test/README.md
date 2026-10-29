# Website Online Hearing Test

A lightweight hearing **screening** test for the Hearing Store website (Laravel). It is independent of the CRM code. It uses plain JavaScript and the Web Audio API, has no dependencies, and is about 40 KB in total. It supports English and Bangla.

```
public/hearing-test/
  hearing-test.js     the test (audio engine, flow, scoring, results, lead form)
  hearing-test.css    styles, all scoped under .ht-root
  demo.html           open in a browser to try it
laravel/
  hearing-test.blade.php           example website page
  hearing_test.php                 website config -> config/hearing_test.php
  HearingTestLeadController.php    phase 2: forwards leads to the CRM
```

## How the test works

1. **Intro and consent:** screening disclaimer and what the visitor needs (headphones, quiet room, silent mode off).
2. **Headphone check:** one beep in each ear in random order, and the visitor must name both sides. This catches swapped or mono headphones.
3. **Volume setup:** a 1 kHz reference tone (about 60 dB HL) plays, and the visitor sets the device volume so it sounds "clear and comfortable, like normal speech". After this step the volume must not change.
4. **Pure-tone test, right ear then left ear:**
   - Frequencies: 1k, 2k, 4k, 8k and 500 Hz. 1 kHz is retested on the first ear to check reliability.
   - Each presentation is three pulsed beeps, starting after a random delay. The visitor taps the big button or presses Space.
   - The threshold is found with a simplified **Hughson-Westlake** procedure: down 10 dB after a response, up 5 dB after a miss. The threshold is the lowest level heard on 2 ascending presentations.
   - About 12% of presentations are silent **catch trials**, used to detect random tapping.
5. **Five quick questions** about everyday listening difficulty and tinnitus.
6. **Results:**
   - A band for each ear from the 4-frequency average (500/1k/2k/4k), using WHO-style grades: Normal under 20 dB, Mild 20–34, Moderate 35–49, Significant 50 or more.
   - An approximate audiogram (right ear red O, left ear blue X).
   - Notes for high-frequency dip, ear asymmetry (15 dB or more), questionnaire result and unreliable responses.
   - Book / Call / WhatsApp buttons and an optional call-back form.

Levels are **approximate dB HL**. A browser can't be calibrated to a clinical standard, so the module always presents itself as a screening and never as a diagnosis. Per-frequency headphone corrections are in `CORR` at the top of `hearing-test.js`.

## Install on the website (Laravel)

1. Copy `public/hearing-test/` into the website's `public/` folder.
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
`Online hearing test: overall MILD | Right normal (PTA ~10 dB) | Left mild (PTA ~30 dB) | Flags: Asym | Questionnaire yes: 2/5 | R {...} L {...}`

## Privacy

Nothing is stored or sent anywhere unless the visitor submits the call-back form.

## Testing checklist

- Android Chrome, iPhone Safari (silent switch off) and desktop Chrome/Edge.
- Wired earphones and Bluetooth earbuds. The response window allows 1.5 s for Bluetooth delay.
- Try both languages and the print/save option.
