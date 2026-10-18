# Hearing Store – Website Consultation Form Integration

**For:** Website developer (hearingstore.com.bd, WordPress)
**Form owner:** Hearing Store CRM – `https://crm.zenhear.com`
**Last updated:** 14 Oct 2026

---

## 1. What this is

A hosted "Free Consultation" lead form that runs on the CRM server and opens as a **popup on the WordPress site**. Visitors never leave the page. Every submission goes straight into the CRM (lead + deal + follow-up for the team).

- No plugin, no API key and no secret are needed on the website.
- One `<script>` tag adds a **floating button (bottom-right)** and makes any `#crm-lead-form` link open the popup.
- The existing **WhatsApp button stays on the left**; this button sits on the right.

| Item | Value |
|---|---|
| Popup script | `https://crm.zenhear.com/f/consultation/embed.js` |
| Direct form link | `https://crm.zenhear.com/f/consultation` |
| Trigger link | `#crm-lead-form` (or CSS class `crm-lead-popup`) |
| Fields | Name\*, Mobile\* (BD format), Interest (chips), Email, Message |

---

## 2. Installation (5 minutes)

### Step 1 – Add the script once, site-wide (footer)

Use **WPCode** (Code Snippets → Header & Footer → *Footer*) or the theme's footer hook:

```html
<script src="https://crm.zenhear.com/f/consultation/embed.js" data-label="Free Consultation" async></script>
```

Theme alternative (`functions.php`):

```php
add_action('wp_footer', function () {
    echo '<script src="https://crm.zenhear.com/f/consultation/embed.js" data-label="Free Consultation" async></script>';
});
```

### Step 2 – Connect the site buttons

Set the link of every "Free Consultation" / "Get Consultation" button to:

```
#crm-lead-form
```

- **Elementor:** Button → Content → Link → `#crm-lead-form`
- **Gutenberg:** Button → Link → `#crm-lead-form`
- **Menu:** Appearance → Menus → Custom Link → URL `#crm-lead-form`
- **Custom HTML:** `<a href="#crm-lead-form">Free Consultation</a>` or `<button class="crm-lead-popup">Free Consultation</button>`

### Step 3 – Clear cache

Purge WP Rocket / LiteSpeed / Cloudflare cache after saving.

---

## 3. Options (attributes on the `<script>` tag)

| Attribute | Default | Effect |
|---|---|---|
| `data-label="..."` | `Get free consultation` | Floating button text (Bangla is fine, e.g. `ফ্রি পরামর্শ`) |
| `data-button="false"` | shown | Hide the floating button; only your own `#crm-lead-form` buttons open the popup |
| `data-auto-open="20"` | off | Open the popup once per visit after N seconds (not again after it was opened or submitted) |

Example:

```html
<script src="https://crm.zenhear.com/f/consultation/embed.js"
        data-label="ফ্রি পরামর্শ" data-auto-open="25" async></script>
```

---

## 4. JavaScript API & events

```js
CRMLeadForm.open();   // open the popup from your own code
CRMLeadForm.close();  // close it
```

**Conversion tracking:** after a successful submission the script pushes to Google Tag Manager:

```js
window.dataLayer.push({ event: 'crm_lead_submitted' });
```

Create a GTM trigger on the custom event `crm_lead_submitted` for GA4 / Meta Pixel / Google Ads conversions.

**Source tracking (automatic):** the page URL and any `utm_source`, `utm_medium` and `utm_campaign` on the visitor's URL are sent with the lead, so the CRM shows which page/campaign each lead came from. Keep UTM tags on ad links.

---

## 5. Behaviour (UX)

- Popup: centred card on desktop; slides up from the bottom on mobile.
- Close: ✕ button, click outside, or `Esc`.
- After submit: "Thank you" screen (English + Bangla) with a **Close** button; auto-closes after 8 s. The visitor stays on the same page.
- Direct link (Facebook/SMS): full page with a **← Back to website** button (returns to the referring page, else `hearingstore.com.bd`).
- Branding: Hearing Store logo, green gradient (positive/calm) with a thin red brand accent.
- Mobile number must be Bangladeshi (`01XXXXXXXXX`, `+8801…` accepted).

---

## 6. Security & compatibility notes

- **No secrets in the browser.** The form uses a signed time token, a honeypot field, rate limits and a blocklist on the CRM side. Spam is filtered automatically.
- The form is loaded in an **iframe** from `crm.zenhear.com`. If the site sends a **Content-Security-Policy**, allow:
  ```
  script-src  https://crm.zenhear.com;
  frame-src   https://crm.zenhear.com;
  ```
- Security plugins (Wordfence, iThemes) do not need changes. Only strict CSP headers would block it.
- The script is ~5 KB, loads `async` and does not block page rendering. No jQuery dependency.
- z-index of the button/popup is very high (`2147483000+`). If a chat widget sits bottom-right, move one of them.
- Please **do not** copy the form HTML into WordPress. Always load the script, so design/logic updates from the CRM apply automatically.

---

## 7. Testing checklist

1. Open the site in an incognito window → green button visible bottom-right, WhatsApp still on the left.
2. Click a page button linked to `#crm-lead-form` → popup opens.
3. Submit a test with a real BD number → "Thank you" → popup closes, same page.
4. Ask the CRM admin to confirm the lead in **Website Leads** and on the **Deals** board.
5. (If GTM used) Check the `crm_lead_submitted` event in GTM Preview.

---

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| Button not showing | Clear all caches; confirm the script is in the page source (footer); check browser console for CSP errors |
| Button shows but popup is blank | CSP `frame-src` blocking `crm.zenhear.com` |
| `#crm-lead-form` jumps to top instead of opening | Script not loaded on that page, or a page builder rewrote the link (use class `crm-lead-popup` instead) |
| "Please enter a valid mobile number" | Only Bangladeshi mobile numbers are accepted |
| Overlaps another widget | Hide the floating button with `data-button="false"` and use page buttons, or move the other widget |

---

## 9. Future domain change

The CRM will later move from `crm.zenhear.com` to a final domain. When that happens, only the domain in the `<script src="...">` tag changes. The old address will redirect for a transition period.

**Contact:** Hearing Store CRM admin, for form changes, new forms (other landing pages can have their own form/link) or lead questions.
