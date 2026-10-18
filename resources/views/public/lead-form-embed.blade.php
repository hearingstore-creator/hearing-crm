{{-- JavaScript served at /f/{key}/embed.js. Plain JS, no dependencies, safe to load on any website. --}}
/* CRM lead form popup.
 * Options on the <script> tag:
 *   data-button="false"     hide the floating button (use your own links instead)
 *   data-label="Free hearing test"  floating button text
 *   data-auto-open="20"     open once per visit after N seconds
 * Any link or button with class "crm-lead-popup" or href="#crm-lead-form" opens the popup. JS: CRMLeadForm.open()
 */
(function () {
    if (window.CRMLeadForm) { return; }
    var script = document.currentScript || {};
    var opt = function (k, d) { var v = script.getAttribute && script.getAttribute('data-' + k); return v === null || v === undefined ? d : v; };
    var FORM_URL = @json($url);
    var css = ''
        + '.crmlf-btn{position:fixed;right:22px;bottom:22px;z-index:2147483000;display:inline-flex;align-items:center;gap:10px;padding:14px 20px 14px 16px;border:0;border-radius:999px;cursor:pointer;font:700 15px/1 Inter,system-ui,sans-serif;color:#fff;background:linear-gradient(135deg,#047857,#10b981 60%,#14b8a6);box-shadow:0 14px 32px -10px rgba(5,150,105,.75);transition:transform .2s,box-shadow .2s;animation:crmlf-rise .5s cubic-bezier(.2,.8,.2,1)}'
        + '.crmlf-btn:hover{transform:translateY(-2px);box-shadow:0 18px 38px -10px rgba(5,150,105,.85)}'
        + '.crmlf-btn i{position:relative;display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.2);font-style:normal}'
        + '.crmlf-btn i::after{content:"";position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(255,255,255,.55);animation:crmlf-ping 1.8s infinite}'
        + '.crmlf-ov{position:fixed;inset:0;z-index:2147483001;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}'
        + '.crmlf-ov.open{opacity:1;visibility:visible}'
        + '.crmlf-box{width:100%;max-width:480px;height:min(800px,calc(100vh - 32px));border-radius:22px;overflow:hidden;background:#fff;box-shadow:0 40px 90px -20px rgba(0,0,0,.5);transform:translateY(20px) scale(.97);transition:transform .3s cubic-bezier(.2,.8,.2,1)}'
        + '.crmlf-ov.open .crmlf-box{transform:none}'
        + '.crmlf-box iframe{display:block;width:100%;height:100%;border:0}'
        + '@media(max-width:520px){.crmlf-ov{padding:0;align-items:flex-end}.crmlf-box{max-width:none;height:92vh;border-radius:22px 22px 0 0}.crmlf-btn{right:14px;bottom:14px;padding:12px 16px 12px 12px;font-size:14px}}'
        + '@keyframes crmlf-rise{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}'
        + '@keyframes crmlf-ping{0%{transform:scale(.9);opacity:1}100%{transform:scale(1.6);opacity:0}}';

    var overlay, frame, lastFocus;

    function src() {
        var p = new URLSearchParams(location.search), q = '&ref=' + encodeURIComponent(location.href);
        ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { if (p.get(k)) { q += '&' + k + '=' + encodeURIComponent(p.get(k)); } });
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
            b.lastChild.textContent = opt('label', 'Get free consultation');
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
