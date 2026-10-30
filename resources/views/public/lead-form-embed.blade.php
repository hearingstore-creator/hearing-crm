{{-- JavaScript served at /f/{key}/embed.js. Plain JS, no dependencies, safe to load on any website. --}}
/* CRM lead form popup.
 * Options on the <script> tag:
 *   data-button="false"     hide the floating button (use your own links instead)
 *   data-label="Free hearing check"  floating button text
 *   data-auto-open="20"     open once per visit after N seconds
 * Any link or button with class "crm-lead-popup" or href="#crm-lead-form" opens the popup. JS: CRMLeadForm.open()
 */
(function () {
    if (window.CRMLeadForm) { return; }
    var script = document.currentScript || {};
    var opt = function (k, d) { var v = script.getAttribute && script.getAttribute('data-' + k); return v === null || v === undefined ? d : v; };
    var FORM_URL = @json($url);
    var css = ''
        + '.crmlf-btn{position:fixed;right:22px;bottom:22px;z-index:2147483000;display:inline-flex;align-items:center;gap:10px;padding:10px 20px 10px 10px;border:1px solid rgba(255,255,255,.45);border-radius:999px;cursor:pointer;font:600 15px/1 Poppins,Inter,system-ui,sans-serif;color:#fff;background:linear-gradient(135deg,#ff3b47,#b80d17);box-shadow:0 14px 34px rgba(200,15,25,.4),inset 0 1px 0 rgba(255,255,255,.4);transition:transform .2s,box-shadow .2s;animation:crmlf-rise .5s cubic-bezier(.2,.8,.2,1)}'
        + '.crmlf-btn:hover{transform:translateY(-2px);box-shadow:0 18px 40px rgba(200,15,25,.5),inset 0 1px 0 rgba(255,255,255,.4)}'
        + '.crmlf-btn i{position:relative;display:grid;place-items:center;width:36px;height:36px;border-radius:50%;background:#fff;color:#d3141f;font-style:normal;font-size:17px}'
        + '.crmlf-btn i::after{content:"";position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(255,255,255,.55);animation:crmlf-ping 2.4s ease-out infinite}'
        + '.crmlf-ov{position:fixed;inset:0;z-index:2147483001;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(20,2,4,.4);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);opacity:0;visibility:hidden;transition:opacity .3s,visibility .3s}'
        + '.crmlf-ov.open{opacity:1;visibility:visible}'
        + '.crmlf-box{width:100%;max-width:420px;height:min(860px,calc(100vh - 32px));border-radius:38px;overflow:hidden;background:#2b0306;border:1px solid rgba(255,255,255,.3);box-shadow:0 40px 90px rgba(0,0,0,.5);transform:translateY(16px) scale(.96);transition:transform .45s cubic-bezier(.2,.9,.25,1)}'
        + '.crmlf-ov.open .crmlf-box{transform:none}'
        + '.crmlf-box iframe{display:block;width:100%;height:100%;border:0}'
        + '@media(max-width:600px){.crmlf-ov{padding:0;align-items:flex-end}.crmlf-box{max-width:none;height:92vh;border-radius:30px 30px 0 0;border-bottom:0;transform:translateY(100%)}.crmlf-btn{right:14px;bottom:16px;padding:9px 16px 9px 9px;font-size:14px}}'
        + '@media(prefers-reduced-motion:reduce){.crmlf-btn,.crmlf-btn i::after{animation:none}.crmlf-box{transition:none}}'
        + '@keyframes crmlf-rise{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}'
        + '@keyframes crmlf-ping{0%{transform:scale(.8);opacity:1}100%{transform:scale(1.5);opacity:0}}';

    var overlay, frame, lastFocus;

    var KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'];

    // First ad click / landing on the website, kept for 90 days so a visitor who comes back later is still credited
    function firstTouch() {
        var stored = null;
        try { stored = JSON.parse(localStorage.getItem('crmlf_ft') || 'null'); } catch (e) {}
        if (stored && stored.at && Date.now() - stored.at > 90 * 864e5) { stored = null; }
        var p = new URLSearchParams(location.search), now = {}, has = false;
        KEYS.forEach(function (k) { if (p.get(k)) { now[k] = p.get(k); has = true; } });
        if (!stored) {
            stored = { at: Date.now(), lp: location.href, rf: (document.referrer && document.referrer.indexOf(location.host) === -1) ? document.referrer : '', p: now };
            try { localStorage.setItem('crmlf_ft', JSON.stringify(stored)); } catch (e) {}
        }
        return { current: has ? now : null, stored: stored };
    }
    var touch = firstTouch();

    function src() {
        var q = '&ref=' + encodeURIComponent(location.href), params = touch.current || touch.stored.p || {};
        KEYS.forEach(function (k) { if (params[k]) { q += '&' + k + '=' + encodeURIComponent(params[k]); } });
        if (touch.stored.lp) { q += '&lp=' + encodeURIComponent(touch.stored.lp); }
        if (touch.stored.rf) { q += '&rf=' + encodeURIComponent(touch.stored.rf); }
        return FORM_URL + q;
    }

    function build() {
        var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
        overlay = document.createElement('div');
        overlay.className = 'crmlf-ov';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Contact form');
        overlay.innerHTML = '<div class="crmlf-box"></div>';
        overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
        document.body.appendChild(overlay);
    }

    function open() {
        if (!overlay) { build(); }
        if (!frame) {
            frame = document.createElement('iframe');
            frame.title = 'Contact form';
            frame.src = src();
            overlay.firstChild.appendChild(frame);
        }
        lastFocus = document.activeElement;
        overlay.classList.add('open');
        document.documentElement.style.overflow = 'hidden';
        try { sessionStorage.setItem('crmlf-seen', '1'); } catch (e) {}
        setTimeout(function () { frame.focus(); }, 300);
    }

    function close() {
        if (!overlay) { return; }
        overlay.classList.remove('open');
        document.documentElement.style.overflow = '';
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }

    window.addEventListener('message', function (e) {
        if (!frame || e.source !== frame.contentWindow || !e.data) { return; }
        if (e.data.crmLeadForm === 'close') { close(); }
        if (e.data.crmLeadForm === 'submitted') {
            try { sessionStorage.setItem('crmlf-done', '1'); } catch (err) {}
            if (window.dataLayer) { window.dataLayer.push({ event: 'crm_lead_submitted' }); }
            setTimeout(close, 8000);
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('.crm-lead-popup, a[href="#crm-lead-form"]');
        if (t) { e.preventDefault(); open(); }
    });

    function init() {
        if (opt('button', 'true') !== 'false') {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'crmlf-btn';
            b.id = 'crmlf-open';
            b.innerHTML = '<i>&#9742;</i><span></span>';
            b.lastChild.textContent = opt('label', 'Free hearing check');
            b.addEventListener('click', open);
            document.body.appendChild(b);
        }
        var auto = parseInt(opt('auto-open', '0'), 10);
        var seen = false;
        try { seen = sessionStorage.getItem('crmlf-seen') || sessionStorage.getItem('crmlf-done'); } catch (e) {}
        if (auto > 0 && !seen) { setTimeout(function () { if (!overlay || !overlay.classList.contains('open')) { open(); } }, auto * 1000); }
    }

    window.CRMLeadForm = { open: open, close: close };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
