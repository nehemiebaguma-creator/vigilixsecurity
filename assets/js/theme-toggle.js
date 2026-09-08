(function () {
    'use strict';

    var STORAGE_KEY = 'vigilance.theme';
    var THEMES = ['dark', 'light'];
    var LIGHT_MODE_FINAL_VERSION = '20260722-map3';
    var toggleButton = null;

    function safeGetTheme() {
        try {
            return window.localStorage ? window.localStorage.getItem(STORAGE_KEY) : null;
        } catch (error) {
            return null;
        }
    }

    function safeSetTheme(theme) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(STORAGE_KEY, theme);
            }
        } catch (error) {
            // The UI still works even when localStorage is blocked.
        }
    }

    function systemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'light';
        }

        return 'dark';
    }

    function normalizeTheme(theme) {
        return THEMES.indexOf(theme) !== -1 ? theme : systemTheme();
    }

    function installLightFinalStylesheet() {
        if (!document.head || document.getElementById('vigilance-light-mode-final-css')) {
            return;
        }

        var href = '/vigilance/assets/css/light-mode-final.css?v=' + LIGHT_MODE_FINAL_VERSION;
        var script = document.currentScript || document.querySelector('script[src*="theme-toggle.js"]');

        if (script && script.src) {
            try {
                href = new URL('../css/light-mode-final.css?v=' + LIGHT_MODE_FINAL_VERSION, script.src).toString();
            } catch (error) {
                // Keep the default local path.
            }
        }

        var link = document.createElement('link');
        link.id = 'vigilance-light-mode-final-css';
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    function updateToggle(theme) {
        if (!toggleButton) {
            return;
        }

        var isLight = theme === 'light';
        var knob = toggleButton.querySelector('.vg-theme-toggle__knob');
        var text = toggleButton.querySelector('.vg-theme-toggle__text');

        if (knob) {
            knob.innerHTML = isLight ? '&#9728;' : '&#9790;';
        }

        if (text) {
            text.textContent = isLight ? 'Mode clair' : 'Mode sombre';
        }

        toggleButton.setAttribute('aria-pressed', isLight ? 'true' : 'false');
        toggleButton.setAttribute('title', isLight ? 'Passer en mode sombre' : 'Passer en mode clair');
    }

    function installLightReadabilityGuard() {
        if (!document.head || document.getElementById('vigilance-light-readability-guard')) {
            return;
        }

        if (document.body && document.body.hasAttribute('data-vg-light-page')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'vigilance-light-readability-guard';
        style.textContent = [
            'html[data-theme="light"] body.ops-body,',
            'html[data-theme="light"] body:not(.landing-body):not(.auth-shell){background:radial-gradient(circle at 10% 8%,rgba(0,111,201,.09),transparent 26%),radial-gradient(circle at 88% 10%,rgba(247,214,24,.13),transparent 24%),linear-gradient(180deg,#fbfdff 0%,#eef4fa 100%)!important;color:#101827!important;}',
            'html[data-theme="light"] body.ops-body::before{opacity:.18!important;filter:none!important;}',
            'html[data-theme="light"] body.ops-body::after{opacity:.34!important;filter:none!important;background:linear-gradient(180deg,rgba(255,255,255,.18),rgba(238,244,251,.08))!important;}',
            'html[data-theme="light"] body.ops-body *,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) *{text-shadow:none!important;}',
            'html[data-theme="light"] body.ops-body h1,html[data-theme="light"] body.ops-body h2,html[data-theme="light"] body.ops-body h3,html[data-theme="light"] body.ops-body h4,html[data-theme="light"] body.ops-body h5,html[data-theme="light"] body.ops-body h6,html[data-theme="light"] body.ops-body strong,html[data-theme="light"] body.ops-body label,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) h1,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) h2,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) h3,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) h4,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) strong,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) label{color:#0f172a!important;}',
            'html[data-theme="light"] body.ops-body p,html[data-theme="light"] body.ops-body small,html[data-theme="light"] body.ops-body li,html[data-theme="light"] body.ops-body em,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) p,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) small,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) li{color:#40516a!important;}',
            'html[data-theme="light"] .ops-body .brand-header,html[data-theme="light"] .ops-body .brand-lockup,html[data-theme="light"] .ops-body .ops-admin-shell,html[data-theme="light"] .ops-body .ops-admin-hero,html[data-theme="light"] .ops-body .ops-admin-panel,html[data-theme="light"] .ops-body .ops-admin-item,html[data-theme="light"] .ops-body .ops-admin-kpi,html[data-theme="light"] .ops-body .ops-panel,html[data-theme="light"] .ops-body .ops-card,html[data-theme="light"] .ops-body .panel,html[data-theme="light"] .ops-body .card,html[data-theme="light"] .ops-body .stat-card,html[data-theme="light"] .ops-body .info-card,html[data-theme="light"] .ops-body .event-card,html[data-theme="light"] .ops-body .form-card,html[data-theme="light"] .ops-body .table-card,html[data-theme="light"] .ops-body .list-card{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,251,255,.96))!important;border-color:rgba(35,57,88,.16)!important;color:#101827!important;box-shadow:0 18px 42px rgba(31,45,66,.1)!important;}',
            'html[data-theme="light"] .ops-body [class*="panel"],html[data-theme="light"] .ops-body [class*="card"],html[data-theme="light"] .ops-body [class*="item"],html[data-theme="light"] .ops-body [class*="module"],html[data-theme="light"] .ops-body [class*="brief"],html[data-theme="light"] .ops-body [class*="mini"],html[data-theme="light"] .ops-body [class*="kpi"],html[data-theme="light"] .ops-body [class*="result"],html[data-theme="light"] .ops-body [class*="match"],html[data-theme="light"] .ops-body [class*="event"],html[data-theme="light"] .ops-body [class*="note"],html[data-theme="light"] .ops-body [class*="empty"],html[data-theme="light"] .ops-body [class*="tech"],html[data-theme="light"] .ops-body [class*="feedback"],html[data-theme="light"] .ops-body [class*="placeholder"]{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,251,255,.96))!important;border-color:rgba(35,57,88,.16)!important;color:#101827!important;}',
            'html[data-theme="light"] .ops-body [class*="media"],html[data-theme="light"] .ops-body [class*="preview"],html[data-theme="light"] .ops-body [class*="visual"]{border-color:rgba(35,57,88,.16)!important;}',
            'html[data-theme="light"] .ops-body input,html[data-theme="light"] .ops-body textarea,html[data-theme="light"] .ops-body select,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) input,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) textarea,html[data-theme="light"] body:not(.landing-body):not(.auth-shell) select{background:#fff!important;border-color:rgba(35,57,88,.2)!important;color:#101827!important;box-shadow:inset 0 1px 0 rgba(16,24,39,.03)!important;}',
            'html[data-theme="light"] input::placeholder,html[data-theme="light"] textarea::placeholder{color:#748197!important;opacity:1!important;}',
            'html[data-theme="light"] .ops-body .ops-btn,html[data-theme="light"] .ops-body .btn,html[data-theme="light"] .ops-body .btn-secondary,html[data-theme="light"] .ops-body button:not(.vg-theme-toggle){background:linear-gradient(180deg,#fff,#edf4fb)!important;border-color:rgba(35,57,88,.2)!important;color:#101827!important;box-shadow:0 10px 24px rgba(31,45,66,.1)!important;}',
            'html[data-theme="light"] .ops-body .btn-primary,html[data-theme="light"] .ops-body .vg-button-primary,html[data-theme="light"] .ops-body .ops-btn-primary{background:linear-gradient(180deg,#2da8ff,#006fc9)!important;border-color:rgba(0,95,171,.32)!important;color:#fff!important;}',
            'html[data-theme="light"] .ops-body .btn-danger,html[data-theme="light"] .ops-body .ops-btn-danger,html[data-theme="light"] .ops-body .ops-siren-btn,html[data-theme="light"] .ops-body [class*="danger"] button{background:linear-gradient(180deg,#ff5d66,#cf1731)!important;border-color:rgba(207,23,49,.32)!important;color:#fff!important;}',
            'html[data-theme="light"] .ops-body .sentinel-pill--online,html[data-theme="light"] .ops-body .sentinel-tone--ok,html[data-theme="light"] .ops-body .badge-success,html[data-theme="light"] .ops-body [class*="success"]{background:#ecfff7!important;border-color:rgba(11,143,102,.28)!important;color:#075e45!important;}',
            'html[data-theme="light"] .ops-body .sentinel-pill--limited,html[data-theme="light"] .ops-body .sentinel-pill--degraded,html[data-theme="light"] .ops-body .sentinel-tone--warn,html[data-theme="light"] .ops-body .badge-warning,html[data-theme="light"] .ops-body [class*="warn"]{background:#fff7e6!important;border-color:rgba(180,107,0,.28)!important;color:#7c4a00!important;}',
            'html[data-theme="light"] .ops-body .sentinel-pill--offline,html[data-theme="light"] .ops-body .sentinel-pill--empty,html[data-theme="light"] .ops-body .sentinel-tone--critical,html[data-theme="light"] .ops-body .badge-danger,html[data-theme="light"] .ops-body [class*="critical"],html[data-theme="light"] .ops-body [class*="danger"]{background:#fff1f3!important;border-color:rgba(207,23,49,.3)!important;color:#8b0e21!important;}',
            'html[data-theme="light"] .ops-body .sentinel-eyebrow,html[data-theme="light"] .ops-body .sentinel-headline-chip,html[data-theme="light"] .ops-body .eyebrow,html[data-theme="light"] .ops-body .ops-label,html[data-theme="light"] .ops-body .ops-map-tag,html[data-theme="light"] .ops-body .badge-info,html[data-theme="light"] .ops-body [class*="chip"],html[data-theme="light"] .ops-body [class*="pill"],html[data-theme="light"] .ops-body [class*="tone"]{background:#eef6ff!important;border-color:rgba(0,111,201,.22)!important;color:#0057a8!important;}',
            'html[data-theme="light"] .ops-body .ops-danger-pill,html[data-theme="light"] .ops-body [class*="danger-pill"]{background:#fff1f3!important;border-color:rgba(207,23,49,.3)!important;color:#8b0e21!important;}',
            'html[data-theme="light"] .ops-body .ops-video-pill{background:#eef6ff!important;border-color:rgba(0,111,201,.28)!important;color:#0057a8!important;cursor:pointer!important;}',
            'html[data-theme="light"] .ops-body .ops-agent-pill,html[data-theme="light"] .ops-body .ops-agent-picker-empty{background:#fff!important;border-color:rgba(35,57,88,.18)!important;color:#101827!important;box-shadow:0 12px 28px rgba(31,45,66,.08)!important;}',
            'html[data-theme="light"] .ops-body .ops-agent-pill strong{color:#101827!important;}',
            'html[data-theme="light"] .ops-body .ops-agent-pill small{color:#40516a!important;}',
            'html[data-theme="light"] .ops-body .ops-agent-pill em{background:#ecfff7!important;border-color:rgba(11,143,102,.28)!important;color:#075e45!important;}',
            'html[data-theme="light"] .ops-body .ops-agent-pill--standby{background:#fff7e6!important;border-color:rgba(180,107,0,.28)!important;}',
            'html[data-theme="light"] .ops-body .ops-agent-pill--standby em{background:#fff!important;color:#7c4a00!important;border-color:rgba(180,107,0,.28)!important;}',
            'html[data-theme="light"] .ptt-panel,html[data-theme="light"] .ptt-header,html[data-theme="light"] .ptt-rx,html[data-theme="light"] .ptt-members,html[data-theme="light"] .ptt-panel select,html[data-theme="light"] .ptt-panel button:not(.vg-theme-toggle){background:linear-gradient(180deg,#fff,#f3f7fc)!important;border-color:rgba(35,57,88,.18)!important;color:#101827!important;}',
            'html[data-theme="light"] .ptt-panel *,html[data-theme="light"] .ptt-private-note,html[data-theme="light"] .ptt-empty{color:#40516a!important;text-shadow:none!important;}',
            'html[data-theme="light"] .ptt-title,html[data-theme="light"] .ptt-panel strong{color:#101827!important;}',
            'html[data-theme="light"] body.auth-shell,html[data-theme="light"] body.landing-body{background:radial-gradient(circle at 12% 8%,rgba(0,111,201,.08),transparent 26%),radial-gradient(circle at 86% 12%,rgba(247,214,24,.13),transparent 24%),linear-gradient(180deg,#fbfdff 0%,#eef4fa 100%)!important;color:#101827!important;}',
            'html[data-theme="light"] .auth-shell h1,html[data-theme="light"] .auth-shell h2,html[data-theme="light"] .auth-shell h3,html[data-theme="light"] .auth-shell strong,html[data-theme="light"] .auth-shell label,html[data-theme="light"] .landing-body h1,html[data-theme="light"] .landing-body h2,html[data-theme="light"] .landing-body h3,html[data-theme="light"] .landing-body strong{color:#0f172a!important;text-shadow:none!important;}',
            'html[data-theme="light"] .auth-shell p,html[data-theme="light"] .auth-shell small,html[data-theme="light"] .auth-shell li,html[data-theme="light"] .landing-body p,html[data-theme="light"] .landing-body small,html[data-theme="light"] .landing-body li{color:#40516a!important;text-shadow:none!important;}',
            'html[data-theme="light"] .auth-shell .auth-card,html[data-theme="light"] .auth-shell .auth-panel,html[data-theme="light"] .auth-shell .auth-role-card,html[data-theme="light"] .auth-shell .auth-kpi,html[data-theme="light"] .landing-body .hero-card,html[data-theme="light"] .landing-body .capability-card,html[data-theme="light"] .landing-body .ops-card,html[data-theme="light"] .landing-body .scene-card,html[data-theme="light"] .landing-body .story-card,html[data-theme="light"] .landing-body .login-card{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,251,255,.96))!important;border-color:rgba(35,57,88,.16)!important;color:#101827!important;box-shadow:0 18px 42px rgba(31,45,66,.1)!important;}',
            'html[data-theme="light"] .auth-shell input,html[data-theme="light"] .auth-shell textarea,html[data-theme="light"] .auth-shell select,html[data-theme="light"] .landing-body input,html[data-theme="light"] .landing-body textarea,html[data-theme="light"] .landing-body select{background:#fff!important;border-color:rgba(35,57,88,.2)!important;color:#101827!important;}',
            'html[data-theme="light"] .auth-shell .btn-primary,html[data-theme="light"] .landing-body .btn-primary,html[data-theme="light"] .landing-body .hero-cta-primary{background:linear-gradient(180deg,#2da8ff,#006fc9)!important;border-color:rgba(0,95,171,.32)!important;color:#fff!important;}',
            'html[data-theme="light"] .ops-body .sentinel-pill--online,html[data-theme="light"] .ops-body .sentinel-tone--ok{background:#ecfff7!important;color:#075e45!important;}',
            'html[data-theme="light"] .ops-body .sentinel-pill--limited,html[data-theme="light"] .ops-body .sentinel-pill--degraded,html[data-theme="light"] .ops-body .sentinel-tone--warn{background:#fff7e6!important;color:#7c4a00!important;}',
            'html[data-theme="light"] .ops-body .sentinel-pill--offline,html[data-theme="light"] .ops-body .sentinel-pill--empty,html[data-theme="light"] .ops-body .sentinel-tone--critical{background:#fff1f3!important;color:#8b0e21!important;}',
            'html[data-theme="light"] .ops-body [style*="color:#fff"],html[data-theme="light"] .ops-body [style*="color:#ffffff"],html[data-theme="light"] .ops-body [style*="color:#f7fbff"],html[data-theme="light"] .ops-body [style*="color:#f5fbff"],html[data-theme="light"] .ops-body [style*="color:#f4fbff"],html[data-theme="light"] .ops-body [style*="color:#edf4ff"],html[data-theme="light"] .ops-body [style*="color:#ebf5ff"],html[data-theme="light"] .ops-body [style*="color:#eef5ff"]{color:#0f172a!important;}',
            'html[data-theme="light"] .ops-body [style*="color:#99bddf"],html[data-theme="light"] .ops-body [style*="color:#a7c9ea"],html[data-theme="light"] .ops-body [style*="color:#8fbbe4"],html[data-theme="light"] .ops-body [style*="color:#82b6e8"],html[data-theme="light"] .ops-body [style*="color:#81b5e8"],html[data-theme="light"] .ops-body [style*="color:#88b8e7"],html[data-theme="light"] .ops-body [style*="color:#9cb6cf"],html[data-theme="light"] .ops-body [style*="color:#8fb8de"],html[data-theme="light"] .ops-body [style*="color:#a9c7e6"],html[data-theme="light"] .ops-body [style*="color:#b9d5f0"],html[data-theme="light"] .ops-body [style*="color:#cfe6ff"]{color:#40516a!important;}',
            'html[data-theme="light"] .ops-body [style*="background:rgba(5,"],html[data-theme="light"] .ops-body [style*="background:rgba(6,"],html[data-theme="light"] .ops-body [style*="background:rgba(7,"],html[data-theme="light"] .ops-body [style*="background:rgba(8,"],html[data-theme="light"] .ops-body [style*="background:linear-gradient(180deg, rgba(5,"],html[data-theme="light"] .ops-body [style*="background:linear-gradient(180deg, rgba(6,"],html[data-theme="light"] .ops-body [style*="background:linear-gradient(180deg, rgba(7,"],html[data-theme="light"] .ops-body [style*="background:linear-gradient(180deg, rgba(8,"],html[data-theme="light"] .ops-body [style*="background:linear-gradient(135deg, rgba(10,"]{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,251,255,.96))!important;border-color:rgba(35,57,88,.16)!important;}',
            'html[data-theme="light"] .ops-body img,html[data-theme="light"] .ops-body video,html[data-theme="light"] .ops-body iframe{background:#e8f0f8!important;}',
            'html[data-theme="light"] body{background:#f7fafc!important;color:#0b1220!important;text-shadow:none!important;}',
            'html[data-theme="light"] body::before,html[data-theme="light"] body::after{opacity:.06!important;filter:none!important;}',
            'html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,table,thead,tbody,tr,td,th,ul,ol,li,div)[class]:not(.vg-theme-toggle):not(.vg-theme-toggle *):not([class*="logo"]):not([class*="brand"]):not([class*="media"]):not([class*="image"]):not([class*="photo"]):not([class*="video"]):not([class*="map"]):not([class*="camera"]):not([class*="hud"]):not([class*="radar"]):not([class*="globe"]):not([class*="satellite"]):not([class*="camera-wall"]):not([class*="live-shell"]):not([class*="ip-shell"]){background:linear-gradient(180deg,#ffffff,#f8fbff)!important;border-color:rgba(35,57,88,.18)!important;color:#0b1220!important;box-shadow:0 14px 34px rgba(15,23,42,.08)!important;text-shadow:none!important;}',
            'html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,div)[class]:not([class*="logo"]):not([class*="brand"]):not([class*="icon"]):not(.vg-theme-toggle):not(.vg-theme-toggle *)::before,html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,div)[class]:not([class*="logo"]):not([class*="brand"]):not([class*="icon"]):not(.vg-theme-toggle):not(.vg-theme-toggle *)::after{background:transparent!important;box-shadow:none!important;filter:none!important;}',
            'html[data-theme="light"] body :where(h1,h2,h3,h4,h5,h6,strong,label,legend,th){color:#0b1220!important;text-shadow:none!important;}',
            'html[data-theme="light"] body :where(p,small,li,td,span,em,div):not(.vg-theme-toggle):not(.vg-theme-toggle *):not(.btn):not([class*="btn"]):not([class*="button"]):not([class*="badge"]):not([class*="pill"]):not([class*="chip"]):not([class*="status"]):not([class*="danger"]):not([class*="success"]):not([class*="warning"]):not([class*="warn"]):not([class*="critical"]){color:#334155!important;text-shadow:none!important;}',
            'html[data-theme="light"] body a:not(.btn):not([class*="btn"]):not([class*="button"]){color:#0057a8!important;text-shadow:none!important;}',
            'html[data-theme="light"] body input,html[data-theme="light"] body textarea,html[data-theme="light"] body select{background:#ffffff!important;color:#0b1220!important;border-color:rgba(35,57,88,.24)!important;box-shadow:inset 0 1px 0 rgba(15,23,42,.04)!important;}',
            'html[data-theme="light"] body input::placeholder,html[data-theme="light"] body textarea::placeholder{color:#64748b!important;opacity:1!important;}',
            'html[data-theme="light"] body .btn-primary,html[data-theme="light"] body [class*="primary"]:is(button,a),html[data-theme="light"] body button[type="submit"]{background:linear-gradient(180deg,#168be8,#006fc9)!important;border-color:rgba(0,95,171,.34)!important;color:#ffffff!important;}',
            'html[data-theme="light"] body .btn-danger,html[data-theme="light"] body [class*="danger"]:is(button,a),html[data-theme="light"] body [class*="critical"]:is(button,a){background:linear-gradient(180deg,#ff5d66,#cf1731)!important;border-color:rgba(207,23,49,.34)!important;color:#ffffff!important;}',
            'html[data-theme="light"] body :where(.badge,.pill,[class*="badge"],[class*="pill"],[class*="chip"]){background:#eef6ff!important;border-color:rgba(0,111,201,.22)!important;color:#0057a8!important;}',
            'html[data-theme="light"] body :where(.badge-danger,.alert-danger,[class*="danger"],[class*="critical"]):not(button):not(a){background:#fff1f3!important;border-color:rgba(207,23,49,.3)!important;color:#8b0e21!important;}',
            'html[data-theme="light"] body :where(.badge-success,.alert-success,[class*="success"],[class*="online"],.ok,[class*="--ok"],[class*="-ok"],[class*="_ok"]):not(button):not(a){background:#ecfff7!important;border-color:rgba(11,143,102,.28)!important;color:#075e45!important;}',
            'html[data-theme="light"] body :where(.badge-warning,.alert-warning,[class*="warning"],[class*="warn"],[class*="limited"],[class*="degraded"]):not(button):not(a){background:#fff7e6!important;border-color:rgba(180,107,0,.28)!important;color:#7c4a00!important;}',
            'html[data-theme="light"] body :where(img,video,iframe,canvas,svg){background:#e8f0f8!important;color:initial!important;}',
            'html[data-theme="light"] .app-shell::before,html[data-theme="light"] body.auth-shell::before,html[data-theme="light"] body.ops-body::before{opacity:.22!important;filter:drop-shadow(0 16px 34px rgba(0,63,126,.18))!important;}',
            'html[data-theme="light"] :where(.brand-logo-wrap,.brand-mark,.brand__mark,.auth-logo,.vg-brand-strip img,.vg-brand-card__row img,img[src*="brand-media.php"]){visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;background:radial-gradient(circle at 50% 18%,rgba(45,168,255,.22),transparent 48%),linear-gradient(180deg,#061525,#0b2d4a)!important;border:1px solid rgba(0,111,201,.34)!important;box-shadow:0 16px 36px rgba(15,23,42,.18),inset 0 0 0 1px rgba(255,255,255,.08)!important;filter:none!important;}',
            'html[data-theme="light"] :where(.brand-logo-wrap img,.brand-mark img,.brand__mark img,.vg-brand-strip img,.vg-brand-card__row img,img[src*="brand-media.php"]){visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;background:transparent!important;filter:drop-shadow(0 8px 18px rgba(0,35,80,.24))!important;}',
            'html[data-theme="light"] :where(.brand__wordmark,.auth-wordmark,img[src*="vigilance-wordmark"]){display:block!important;visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;border-radius:18px!important;padding:8px 10px!important;background:linear-gradient(180deg,#061525,#0b2d4a)!important;border:1px solid rgba(0,111,201,.3)!important;box-shadow:0 14px 32px rgba(15,23,42,.16)!important;filter:none!important;}',
            'html[data-theme="light"] .auth-brand-copy::before{content:none!important;display:none!important;}',
            'html[data-theme="light"] :where(img[alt*="Armoiries"],img[src*="rdc-coat-of-arms"]){display:block!important;visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;box-sizing:border-box!important;padding:10px!important;border-radius:24px!important;background:rgba(255,255,255,.86)!important;border:1px solid rgba(0,111,201,.22)!important;box-shadow:0 18px 42px rgba(15,23,42,.14)!important;filter:drop-shadow(0 10px 22px rgba(0,45,90,.2)) contrast(1.04) saturate(1.04)!important;}',
            'html[data-theme="light"] .vg-global-brand{background:linear-gradient(180deg,rgba(250,253,255,.98),rgba(235,244,252,.96))!important;border-color:rgba(18,45,78,.18)!important;box-shadow:0 18px 42px rgba(14,34,58,.14)!important;}',
            'html[data-theme="light"] .vg-global-brand,html[data-theme="light"] .vg-global-brand *{color:#07111f!important;text-shadow:none!important;}',
            'html[data-theme="light"] .vg-global-brand__copy strong{color:#07111f!important;}',
            'html[data-theme="light"] .vg-global-brand__copy small{color:#2f4058!important;}',
            'html[data-theme="light"] .vg-global-brand__logo{background:rgba(6,14,22,.96)!important;border-color:rgba(90,170,255,.18)!important;}',
            'html[data-theme="light"] .vg-global-brand__logo img{background:transparent!important;opacity:1!important;visibility:visible!important;mix-blend-mode:normal!important;filter:drop-shadow(0 8px 18px rgba(0,35,80,.22))!important;}',
            'html[data-theme="light"] body :where(.brand__wordmark,.auth-wordmark,img[src*="vigilance-wordmark"]){display:block!important;visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;background:transparent!important;border:0!important;padding:0!important;box-shadow:none!important;filter:drop-shadow(0 14px 28px rgba(15,23,42,.2))!important;}',
            'html[data-theme="light"] body.landing-body .brand__copy,html[data-theme="light"] body.auth-shell .auth-brand-copy{background:transparent!important;border-color:transparent!important;box-shadow:none!important;}',
            'html[data-theme="light"] body.landing-body .brand__copy small{color:#334155!important;font-weight:700!important;}',
            'html[data-theme="light"] body.auth-shell .auth-brand h1{color:#0f172a!important;opacity:1!important;}',
            'html[data-theme="light"] .vg-theme-toggle{background:linear-gradient(180deg,#fff,#f2f6fb)!important;border-color:rgba(35,57,88,.2)!important;color:#0f172a!important;box-shadow:0 18px 38px rgba(31,45,66,.18)!important;}',
            'html[data-theme="light"] .vg-theme-toggle *{color:inherit!important;}',
            'html[data-theme="light"] .vg-theme-toggle__knob{background:linear-gradient(180deg,#2da8ff,#006fc9)!important;color:#fff!important;}',
            'html[data-theme="light"]{--bg:#e9f1f8!important;--bg-alt:#f2f6fb!important;--card-bg:#f8fbff!important;--text:#07111f!important;--muted-text:#2f4058!important;--muted:#2f4058!important;--border:rgba(18,45,78,.22)!important;--primary:#006fc9!important;--danger:#c91632!important;--success:#087253!important;--warning:#8a5200!important;color-scheme:light;}',
            'html[data-theme="light"] body,html[data-theme="light"] body.ops-body,html[data-theme="light"] body.auth-shell,html[data-theme="light"] body.landing-body,html[data-theme="light"] body:not(.landing-body):not(.auth-shell){background:radial-gradient(circle at 8% 10%,rgba(0,111,201,.12),transparent 28%),radial-gradient(circle at 88% 8%,rgba(247,214,24,.16),transparent 24%),linear-gradient(180deg,#f3f7fb 0%,#e6eef7 58%,#dde8f2 100%)!important;color:#07111f!important;}',
            'html[data-theme="light"] body::before,html[data-theme="light"] body::after,html[data-theme="light"] .app-shell::before,html[data-theme="light"] .app-shell::after,html[data-theme="light"] .ops-body::before,html[data-theme="light"] .ops-body::after{opacity:.1!important;filter:none!important;background:transparent!important;}',
            'html[data-theme="light"] body.ops-body::before{content:""!important;position:fixed!important;inset:0!important;z-index:0!important;pointer-events:none!important;background:radial-gradient(circle at 50% 36%,rgba(0,111,201,.13),transparent 28%),url("/vigilance/assets/images/rdc-coat-of-arms.svg") center clamp(86px,16vh,190px)/min(50vw,780px) no-repeat!important;opacity:.18!important;filter:drop-shadow(0 18px 38px rgba(0,63,126,.18)) saturate(1.08) contrast(1.04)!important;}',
            'html[data-theme="light"] .app-shell::before{content:""!important;position:fixed!important;inset:0!important;z-index:0!important;pointer-events:none!important;background:radial-gradient(circle at 72% 45%,rgba(0,111,201,.10),transparent 22%),url("/vigilance/assets/images/rdc-coat-of-arms.svg") right 38px center/min(34vw,640px) no-repeat!important;opacity:.16!important;filter:drop-shadow(0 16px 34px rgba(0,63,126,.16)) saturate(1.06) contrast(1.04)!important;}',
            'html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,table,thead,tbody,tr,td,th,ul,ol,li,div)[class]:not(.vg-theme-toggle):not(.vg-theme-toggle *):not([class*="logo"]):not([class*="brand"]):not([class*="media"]):not([class*="image"]):not([class*="photo"]):not([class*="video"]):not([class*="map"]):not([class*="leaflet"]):not([class*="camera"]):not([class*="hud"]):not([class*="radar"]):not([class*="globe"]):not([class*="satellite"]):not([class*="camera-wall"]):not([class*="live-shell"]):not([class*="ip-shell"]){background:linear-gradient(180deg,rgba(248,251,255,.99),rgba(237,245,252,.97))!important;border-color:rgba(18,45,78,.2)!important;color:#07111f!important;box-shadow:0 18px 42px rgba(14,34,58,.11)!important;text-shadow:none!important;}',
            'html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,table,div)[class]:not([class*="logo"]):not([class*="brand"]):not([class*="icon"]):not([class*="map"]):not([class*="leaflet"]):not([class*="camera"]):not([class*="hud"]):not([class*="radar"]):not([class*="globe"]):not([class*="satellite"]):not(.vg-theme-toggle):not(.vg-theme-toggle *)::before,html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,table,div)[class]:not([class*="logo"]):not([class*="brand"]):not([class*="icon"]):not([class*="map"]):not([class*="leaflet"]):not([class*="camera"]):not([class*="hud"]):not([class*="radar"]):not([class*="globe"]):not([class*="satellite"]):not(.vg-theme-toggle):not(.vg-theme-toggle *)::after{background:transparent!important;box-shadow:none!important;filter:none!important;opacity:.05!important;}',
            'html[data-theme="light"] body :where(h1,h2,h3,h4,h5,h6,strong,b,label,legend,th,.title,[class*="title"],[class*="headline"],[class*="heading"],[class*="headline"],[class*="eyebrow"]){color:#07111f!important;opacity:1!important;text-shadow:none!important;}',
            'html[data-theme="light"] body :where(p,small,li,td,span,em,div):not(.vg-theme-toggle):not(.vg-theme-toggle *):not(.btn):not([class*="btn"]):not([class*="button"]):not([class*="badge"]):not([class*="pill"]):not([class*="chip"]):not([class*="status"]):not([class*="danger"]):not([class*="success"]):not([class*="warning"]):not([class*="warn"]):not([class*="critical"]){color:#2f4058!important;opacity:1!important;text-shadow:none!important;}',
            'html[data-theme="light"] body :where(.hero-copy,.ops-admin-hero,.sentinel-hero,.vgx-sync-band,.agent-ops-hero,.prospects-head,.auth-hero,.hero-card){background:linear-gradient(135deg,rgba(226,239,250,.98),rgba(248,251,255,.96))!important;border-color:rgba(0,111,201,.18)!important;color:#07111f!important;}',
            'html[data-theme="light"] body :where(input,select,textarea){background:#fbfdff!important;color:#07111f!important;border-color:rgba(18,45,78,.26)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.8),0 8px 18px rgba(14,34,58,.06)!important;}',
            'html[data-theme="light"] body :where(.btn-primary,.vg-button-primary,.site-nav__cta,[class*="primary"]:is(button,a),button[type="submit"]){background:linear-gradient(180deg,#0b8ee8,#0062b8)!important;border-color:rgba(0,88,168,.38)!important;color:#fff!important;}',
            'html[data-theme="light"] body :where(.btn-danger,.ops-btn-danger,[class*="danger"]:is(button,a),[class*="critical"]:is(button,a)){background:linear-gradient(180deg,#f0445b,#bd1230)!important;border-color:rgba(189,18,48,.36)!important;color:#fff!important;}',
            'html[data-theme="light"] body :where(.badge,.pill,[class*="badge"],[class*="pill"],[class*="chip"]){background:#e7f2ff!important;border-color:rgba(0,111,201,.24)!important;color:#004f99!important;}',
            'html[data-theme="light"] body :where(img,video,iframe,canvas,svg){opacity:1!important;filter:none!important;}',
            'html[data-theme="light"] :where(.brand__wordmark,.auth-wordmark,img[src*="vigilance-wordmark"]){display:block!important;visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;border-radius:18px!important;padding:8px 10px!important;background:linear-gradient(180deg,#061525,#0b2d4a)!important;border:1px solid rgba(0,111,201,.32)!important;box-shadow:0 14px 32px rgba(15,23,42,.18)!important;filter:none!important;}',
            'html[data-theme="light"] :where(img[alt*="Armoiries"],img[src*="rdc-coat-of-arms"]){display:block!important;visibility:visible!important;opacity:1!important;mix-blend-mode:normal!important;padding:10px!important;border-radius:24px!important;background:rgba(255,255,255,.9)!important;border:1px solid rgba(0,111,201,.22)!important;box-shadow:0 18px 42px rgba(15,23,42,.14)!important;filter:drop-shadow(0 10px 22px rgba(0,45,90,.2)) contrast(1.04) saturate(1.04)!important;}',
            'html[data-theme="light"] .vg-global-brand{background:linear-gradient(180deg,rgba(250,253,255,.98),rgba(235,244,252,.96))!important;border-color:rgba(18,45,78,.18)!important;box-shadow:0 18px 42px rgba(14,34,58,.14)!important;}',
            'html[data-theme="light"] .vg-global-brand,html[data-theme="light"] .vg-global-brand *{color:#07111f!important;text-shadow:none!important;}',
            'html[data-theme="light"] .vg-global-brand__copy strong{color:#07111f!important;}',
            'html[data-theme="light"] .vg-global-brand__copy small{color:#2f4058!important;}',
            'html[data-theme="light"] .vg-global-brand__logo{background:radial-gradient(circle at 50% 18%,rgba(45,168,255,.22),transparent 48%),linear-gradient(180deg,#061525,#0b2d4a)!important;border-color:rgba(0,111,201,.34)!important;}',
            'html[data-theme="light"] .vg-theme-toggle{top:18px!important;right:24px!important;bottom:auto!important;z-index:10080!important;}',
            'html[data-theme="light"] body.landing-body .vg-theme-toggle{top:18px!important;right:24px!important;bottom:auto!important;}',
            'html[data-theme="light"] body.landing-body :where(.hero-media,.scene-card__media,.ops-panel__visual){display:block!important;visibility:visible!important;opacity:1!important;position:relative!important;min-height:320px!important;background-color:#071525!important;background-size:cover!important;background-repeat:no-repeat!important;background-blend-mode:normal!important;border-color:rgba(18,45,78,.24)!important;box-shadow:0 22px 54px rgba(14,34,58,.16)!important;overflow:hidden!important;isolation:isolate!important;}',
            'html[data-theme="light"] body.landing-body .hero-media{background-image:url("../images/hero-ops-photo.jpg")!important;background-position:left center!important;}',
            'html[data-theme="light"] body.landing-body .scene-card:nth-child(1) .scene-card__media{background-image:url("../images/vgt-hero.jpg")!important;background-position:center!important;}',
            'html[data-theme="light"] body.landing-body .scene-card:nth-child(2) .scene-card__media{background-image:url("../images/hero-ops-photo.jpg")!important;background-position:center!important;}',
            'html[data-theme="light"] body.landing-body .scene-card:nth-child(3) .scene-card__media{background-image:url("../images/agent-chien.jpg")!important;background-position:center!important;}',
            'html[data-theme="light"] body.landing-body .scene-card:nth-child(4) .scene-card__media,html[data-theme="light"] body.landing-body .ops-panel__visual{background-image:url("../images/vgt-features.jpg")!important;background-position:center!important;}',
            'html[data-theme="light"] body.landing-body :where(.hero-media img,.scene-card__media img,.ops-panel__visual img){display:block!important;visibility:visible!important;opacity:1!important;position:absolute!important;inset:0!important;width:100%!important;height:100%!important;object-fit:cover!important;mix-blend-mode:normal!important;background:transparent!important;filter:saturate(1.14) contrast(1.1) brightness(.9)!important;z-index:0!important;}',
            'html[data-theme="light"] body.landing-body .hero-media img{object-position:left center!important;}',
            'html[data-theme="light"] body.landing-body :where(.hero-media__veil,.scene-card__overlay){display:none!important;opacity:0!important;background:none!important;}',
            'html[data-theme="light"] body.landing-body :where(.hero-badge,.ops-signal){position:absolute!important;z-index:3!important;background:rgba(255,255,255,.94)!important;border-color:rgba(18,45,78,.18)!important;color:#07111f!important;box-shadow:0 14px 28px rgba(14,34,58,.16)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-core{background:radial-gradient(circle at 50% 52%,rgba(79,152,231,.18) 0%,rgba(79,152,231,0) 24%),radial-gradient(circle at 15% 16%,rgba(0,111,201,.16),transparent 22%),radial-gradient(circle at 85% 14%,rgba(247,214,24,.14),transparent 18%),linear-gradient(rgba(113,149,194,.16) 1px,transparent 1px),linear-gradient(90deg,rgba(113,149,194,.16) 1px,transparent 1px),linear-gradient(180deg,#f7fbff 0%,#e4eef8 100%)!important;background-size:auto,auto,auto,46px 46px,46px 46px,100% 100%!important;border-color:rgba(31,67,106,.2)!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.68),inset 0 0 120px rgba(39,111,196,.06),0 24px 54px rgba(14,34,58,.12)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-core::before,html[data-theme="light"] body.ops-body .ops-hud-core::after{border-color:rgba(31,67,106,.16)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-satellite{background:linear-gradient(rgba(0,111,201,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(0,111,201,.06) 1px,transparent 1px),linear-gradient(180deg,transparent 0%,rgba(210,225,239,.36) 56%,rgba(187,207,229,.46) 100%)!important;background-size:52px 52px,52px 52px,100% 100%!important;opacity:.34!important;filter:none!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-satellite::after{background:linear-gradient(180deg,transparent 0%,rgba(0,111,201,.05) 48%,transparent 49%,transparent 100%)!important;opacity:.2!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-national{opacity:.36!important;filter:drop-shadow(0 18px 34px rgba(0,63,126,.18)) saturate(1.06) contrast(1.08)!important;mix-blend-mode:multiply!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-ellipse,html[data-theme="light"] body.ops-body .ops-hud-globe__ring,html[data-theme="light"] body.ops-body .ops-hud-globe__latitude{border-color:rgba(0,111,201,.24)!important;box-shadow:none!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-globe{background:radial-gradient(circle at 50% 50%,rgba(0,111,201,.2) 0%,rgba(0,111,201,.08) 42%,rgba(255,255,255,0) 72%),radial-gradient(circle at 50% 50%,rgba(255,255,255,.18),rgba(255,255,255,0) 68%)!important;mix-blend-mode:normal!important;opacity:.9!important;box-shadow:0 0 0 1px rgba(0,111,201,.08),0 24px 52px rgba(33,77,121,.12),inset 0 0 52px rgba(92,190,255,.08)!important;filter:drop-shadow(0 14px 28px rgba(0,111,201,.12))!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-globe::before{background:conic-gradient(from 0deg,rgba(0,111,201,0) 0deg,rgba(0,111,201,0) 284deg,rgba(0,111,201,.1) 310deg,rgba(0,163,224,.26) 334deg,rgba(0,111,201,0) 360deg)!important;mix-blend-mode:normal!important;opacity:.74!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-globe::after{border-color:rgba(0,111,201,.24)!important;box-shadow:0 0 0 1px rgba(0,111,201,.08),0 0 34px rgba(0,111,201,.14),inset 0 0 28px rgba(0,163,224,.08)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-route{background:linear-gradient(90deg,rgba(0,163,224,.12),rgba(0,163,224,.74),rgba(216,31,70,.56))!important;box-shadow:0 0 18px rgba(0,111,201,.18)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-statusbar{background:rgba(248,252,255,.86)!important;border:1px solid rgba(31,67,106,.12)!important;backdrop-filter:blur(10px)!important;box-shadow:0 18px 34px rgba(31,45,66,.08)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-statuschip,html[data-theme="light"] body.ops-body .ops-hud-national-badge,html[data-theme="light"] body.ops-body .ops-map-tag{background:linear-gradient(180deg,rgba(251,253,255,.98),rgba(233,242,251,.96))!important;border-color:rgba(31,67,106,.16)!important;color:#24405f!important;}',
            'html[data-theme="light"] body.ops-body .ops-map-tag strong,html[data-theme="light"] body.ops-body .ops-hud-statuschip strong{color:#0f172a!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-camera-node::before{background:linear-gradient(90deg,rgba(0,111,201,.08),rgba(0,111,201,.56),rgba(190,30,55,.34))!important;box-shadow:0 0 18px rgba(0,111,201,.16)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-camera-node::after{border-color:rgba(0,111,201,.16)!important;background:radial-gradient(circle,rgba(0,111,201,.14),rgba(0,111,201,0) 68%)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-camera-node .ops-camera-tile{background:linear-gradient(180deg,rgba(233,241,249,.98),rgba(213,226,239,.97))!important;border-color:rgba(46,78,114,.22)!important;box-shadow:0 22px 44px rgba(31,45,66,.14),inset 0 1px 0 rgba(255,255,255,.72)!important;}',
            'html[data-theme="light"] body.ops-body .ops-camera-preview{background:#102133!important;}',
            'html[data-theme="light"] body.ops-body .ops-camera-preview::after{background:linear-gradient(180deg,rgba(10,21,37,.08),rgba(17,35,56,.42))!important;}',
            'html[data-theme="light"] body.ops-body .ops-camera-tile strong,html[data-theme="light"] body.ops-body .ops-camera-state,html[data-theme="light"] body.ops-body .ops-camera-reason{color:#0f172a!important;text-shadow:none!important;}',
            'html[data-theme="light"] body.ops-body .ops-camera-tile span{color:#42566f!important;text-shadow:none!important;}',
            'html[data-theme="light"] body.ops-body .ops-camera-dismiss{background:rgba(255,255,255,.96)!important;border-color:rgba(30,64,116,.2)!important;color:#102133!important;box-shadow:0 10px 22px rgba(31,45,66,.14)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-console{background:transparent!important;border-color:transparent!important;box-shadow:none!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-console::before{background:linear-gradient(90deg,rgba(24,92,175,.12) 1px,transparent 1px),linear-gradient(rgba(24,92,175,.1) 1px,transparent 1px)!important;background-size:120px 120px!important;opacity:.2!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-layout--command{background:radial-gradient(circle at 50% 0%,rgba(0,111,201,.08),transparent 30%),linear-gradient(180deg,rgba(249,252,255,.98),rgba(229,238,247,.95))!important;border-color:rgba(31,67,106,.2)!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.74),0 28px 64px rgba(14,34,58,.14)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-techbar,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-command-bridge,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-command-bridge__smartcard,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-command-bridge__workflow-item,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-bridge-stat,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-sidebar--command .ops-nav-grid,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-sidebar--command .ops-nav-card,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-call-item,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-mission-item,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-report-card,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-communication-card,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-empty-state,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-closure-shell,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-closure-card,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-closure-note{background:linear-gradient(180deg,rgba(252,254,255,.98),rgba(233,242,250,.96))!important;border-color:rgba(31,67,106,.18)!important;box-shadow:0 18px 42px rgba(14,34,58,.12)!important;color:#0f172a!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-alert-feed-item{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(239,245,252,.96))!important;border-color:rgba(31,67,106,.16)!important;color:#102133!important;box-shadow:0 14px 30px rgba(14,34,58,.1)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-alert-feed-item--critical{background:linear-gradient(180deg,#fff4f6,#fffdfd)!important;border-color:rgba(189,18,48,.18)!important;box-shadow:0 16px 32px rgba(132,22,44,.08)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-modal{background:rgba(16,28,44,.28)!important;backdrop-filter:blur(8px)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-dialog{background:linear-gradient(180deg,#fcfeff,#eef4fb)!important;border-color:rgba(31,67,106,.18)!important;box-shadow:0 32px 84px rgba(14,34,58,.24)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-dialog__header{background:linear-gradient(180deg,rgba(252,254,255,.98),rgba(238,245,252,.95))!important;border-color:rgba(31,67,106,.14)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-hero,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-fact,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-note,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-briefing-card,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-selection-card,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-proof__fact,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-proof__approval,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-sequence-item,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-list-item,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-camera-item,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-stack--soft div,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-chain__step{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(238,245,252,.96))!important;border-color:rgba(31,67,106,.16)!important;color:#102133!important;box-shadow:0 14px 30px rgba(14,34,58,.1)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-readout{background:linear-gradient(180deg,#fff8eb,#fffdf8)!important;border-color:rgba(173,112,20,.18)!important;color:#493722!important;box-shadow:0 14px 30px rgba(86,63,31,.08)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-dialog textarea,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-dialog input,html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-dialog select{background:#ffffff!important;border-color:rgba(31,67,106,.2)!important;color:#102133!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.72),0 8px 18px rgba(14,34,58,.06)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-close-icon{background:linear-gradient(180deg,#ffffff,#eef4fb)!important;border-color:rgba(31,67,106,.18)!important;color:#102133!important;box-shadow:0 12px 28px rgba(14,34,58,.14)!important;}',
            'html[data-theme="light"] body.ops-body[data-vg-light-page="ops-control"] .ops-dispatch-close-icon:hover{background:linear-gradient(180deg,#f0445b,#bd1230)!important;border-color:rgba(189,18,48,.34)!important;color:#ffffff!important;}',
            'html[data-theme="light"]{--bg:#dff3ff!important;--bg-alt:#eaf8ff!important;--card-bg:#f3fbff!important;--text:#071a2f!important;--muted-text:#2f5876!important;--border:rgba(0,151,226,.28)!important;--primary:#009de8!important;color-scheme:light;}',
            'html[data-theme="light"] body,html[data-theme="light"] body.ops-body,html[data-theme="light"] body.auth-shell,html[data-theme="light"] body.landing-body,html[data-theme="light"] body:not(.landing-body):not(.auth-shell){background:radial-gradient(circle at 8% 8%,rgba(0,170,255,.22),transparent 30%),radial-gradient(circle at 84% 10%,rgba(124,214,255,.22),transparent 26%),radial-gradient(circle at 50% 100%,rgba(0,111,201,.12),transparent 34%),linear-gradient(180deg,#effaff 0%,#dff3ff 54%,#cfeaff 100%)!important;color:#071a2f!important;}',
            'html[data-theme="light"] body :where(section,article,aside,main,header,footer,nav,form,table,thead,tbody,tr,td,th,ul,ol,li,div)[class]:not(.vg-theme-toggle):not(.vg-theme-toggle *):not([class*="logo"]):not([class*="brand"]):not([class*="media"]):not([class*="image"]):not([class*="photo"]):not([class*="video"]):not([class*="map"]):not([class*="leaflet"]):not([class*="camera"]):not([class*="hud"]):not([class*="radar"]):not([class*="globe"]):not([class*="satellite"]):not([class*="camera-wall"]):not([class*="live-shell"]):not([class*="ip-shell"]){background:linear-gradient(180deg,rgba(247,253,255,.98),rgba(224,244,255,.96))!important;border-color:rgba(0,151,226,.24)!important;box-shadow:0 18px 42px rgba(0,96,160,.12)!important;color:#071a2f!important;}',
            'html[data-theme="light"] body :where(button,.btn,.vg-button,.ops-btn,[class*="button"]):not(.vg-theme-toggle):not(.vg-theme-toggle *){background:linear-gradient(180deg,#f8fdff,#dff3ff)!important;border-color:rgba(0,151,226,.30)!important;color:#071a2f!important;box-shadow:0 12px 28px rgba(0,111,201,.13)!important;}',
            'html[data-theme="light"] body :where(.btn-primary,.vg-button-primary,.site-nav__cta,[class*="primary"]:is(button,a),button[type="submit"],.ops-btn-primary){background:linear-gradient(180deg,#35c6ff,#008fe3)!important;border-color:rgba(0,119,200,.40)!important;color:#fff!important;box-shadow:0 16px 34px rgba(0,143,227,.25)!important;}',
            'html[data-theme="light"] body :where(.badge,.pill,[class*="badge"],[class*="pill"],[class*="chip"],[class*="tag"]){background:#def4ff!important;border-color:rgba(0,168,240,.32)!important;color:#005f9f!important;}',
            'html[data-theme="light"] body :where(input,select,textarea){background:#f7fdff!important;border-color:rgba(0,151,226,.30)!important;color:#071a2f!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 8px 18px rgba(0,111,201,.08)!important;}',
            'html[data-theme="light"] .vg-theme-toggle{background:linear-gradient(180deg,#f8fdff,#dff3ff)!important;border-color:rgba(0,151,226,.32)!important;color:#071a2f!important;box-shadow:0 18px 38px rgba(0,111,201,.18)!important;}',
            'html[data-theme="light"] .vg-theme-toggle__knob{background:linear-gradient(180deg,#35c6ff,#008fe3)!important;}',
            'html[data-theme="light"] body.ops-body .ops-hud-core{background:radial-gradient(circle at 50% 52%,rgba(53,198,255,.24) 0%,rgba(53,198,255,0) 26%),radial-gradient(circle at 15% 16%,rgba(0,168,240,.22),transparent 24%),radial-gradient(circle at 85% 14%,rgba(124,214,255,.20),transparent 20%),linear-gradient(rgba(0,168,240,.18) 1px,transparent 1px),linear-gradient(90deg,rgba(0,168,240,.18) 1px,transparent 1px),linear-gradient(180deg,#f1fbff 0%,#d9f0ff 100%)!important;background-size:auto,auto,auto,46px 46px,46px 46px,100% 100%!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-atlas-stage,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-atlas-stage[class]{background:radial-gradient(circle at 25% 18%,rgba(45,201,255,.18),transparent 30%),radial-gradient(circle at 72% 68%,rgba(0,111,201,.24),transparent 36%),linear-gradient(rgba(87,205,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(87,205,255,.08) 1px,transparent 1px),linear-gradient(180deg,#07192d 0%,#04111f 100%)!important;background-size:auto,auto,72px 72px,72px 72px,100% 100%!important;border-color:rgba(0,168,240,.34)!important;box-shadow:0 28px 74px rgba(0,70,125,.22),inset 0 0 0 1px rgba(255,255,255,.05)!important;color:#edf8ff!important;}',
            'html[data-theme="light"] body.ops-body #vigilance-map,html[data-theme="light"] body.ops-body #vigilance-map.leaflet-container{background:linear-gradient(180deg,rgba(3,11,22,.08),rgba(3,11,22,.30)),url("/vigilance/assets/images/kinshasa-map-fallback.svg") center/cover no-repeat,linear-gradient(180deg,#081b31,#04111f)!important;border-color:rgba(89,207,255,.34)!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.05),inset 0 0 120px rgba(0,111,201,.12)!important;filter:saturate(1.12) contrast(1.07) brightness(.94)!important;}',
            'html[data-theme="light"] body.ops-body #vigilance-map .leaflet-pane,html[data-theme="light"] body.ops-body #vigilance-map .leaflet-map-pane,html[data-theme="light"] body.ops-body #vigilance-map .leaflet-tile-pane,html[data-theme="light"] body.ops-body #vigilance-map .leaflet-overlay-pane,html[data-theme="light"] body.ops-body #vigilance-map .leaflet-marker-pane,html[data-theme="light"] body.ops-body #vigilance-map .leaflet-tooltip-pane,html[data-theme="light"] body.ops-body #vigilance-map .leaflet-popup-pane{background:transparent!important;opacity:1!important;visibility:visible!important;}',
            'html[data-theme="light"] body.ops-body #vigilance-map .leaflet-tile{opacity:1!important;visibility:visible!important;mix-blend-mode:normal!important;filter:saturate(1.18) contrast(1.08) brightness(.88)!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-marker-icon,html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-marker-shadow,html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-interactive{opacity:1!important;visibility:visible!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-atlas-panel,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-atlas-overview div,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-atlas-key,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-radar-metric,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-tactical-card,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-dispatch-item,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-engagement-item,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-sector-item,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-event-item,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-panel{background:linear-gradient(180deg,rgba(8,24,42,.92),rgba(4,13,26,.86))!important;border-color:rgba(112,215,255,.24)!important;box-shadow:0 18px 44px rgba(2,16,30,.24),inset 0 1px 0 rgba(255,255,255,.04)!important;color:#edf8ff!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap :where(.vgx-atlas-panel,.vgx-atlas-overview,.vgx-atlas-key,.vgx-radar-metric,.vgx-tactical-card,.vgx-map-panel,.vgx-dispatch-item,.vgx-engagement-item,.vgx-sector-item,.vgx-event-item) :where(h2,h3,h4,strong,b,label){color:#f7fcff!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap :where(.vgx-atlas-panel,.vgx-atlas-overview,.vgx-atlas-key,.vgx-radar-metric,.vgx-tactical-card,.vgx-map-panel,.vgx-dispatch-item,.vgx-engagement-item,.vgx-sector-item,.vgx-event-item) :where(p,span,small,div){color:#bde5ff!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-control-stack :where(.vgx-map-view-switch,.vgx-map-zoom-readout,.vgx-map-compass),html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-control-zoom a,html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-control-scale,html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-control-layers{background:linear-gradient(180deg,rgba(8,24,42,.96),rgba(4,13,26,.92))!important;border-color:rgba(112,215,255,.28)!important;color:#eef8ff!important;box-shadow:0 14px 32px rgba(2,16,30,.28)!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-view-switch__btn{color:#bfe7ff!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-view-switch__btn.is-active{background:linear-gradient(180deg,rgba(0,142,227,.92),rgba(0,88,168,.92))!important;border-color:rgba(115,230,255,.48)!important;color:#ffffff!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-label{background:rgba(3,13,26,.88)!important;border-color:rgba(125,219,255,.38)!important;box-shadow:0 10px 24px rgba(0,22,45,.38),0 0 0 1px rgba(125,219,255,.10) inset!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-label strong,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-label small,html[data-theme="light"] body.ops-body .vgx-map-wrap .vgx-map-label__meta{color:#f7fcff!important;}',
            'html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-popup-content-wrapper,html[data-theme="light"] body.ops-body .vgx-map-wrap .leaflet-popup-tip{background:rgba(5,18,34,.97)!important;border-color:rgba(112,215,255,.32)!important;color:#eef8ff!important;}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function applyTheme(theme, persist) {
        var nextTheme = normalizeTheme(theme);
        document.documentElement.setAttribute('data-theme', nextTheme);
        document.documentElement.style.colorScheme = nextTheme;

        if (persist) {
            safeSetTheme(nextTheme);
        }

        updateToggle(nextTheme);
        return nextTheme;
    }

    function buildToggle() {
        if (!document.body) {
            return;
        }

        toggleButton = document.querySelector('.vg-theme-toggle');
        if (!toggleButton) {
            toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.className = 'vg-theme-toggle';
            toggleButton.setAttribute('aria-label', 'Changer le theme clair sombre');
            toggleButton.innerHTML = '<span class="vg-theme-toggle__knob" aria-hidden="true"></span><span class="vg-theme-toggle__text"></span>';
            document.body.appendChild(toggleButton);
        }

        if (toggleButton.dataset.vgThemeBound === 'true') {
            updateToggle(normalizeTheme(document.documentElement.getAttribute('data-theme')));
            return;
        }

        toggleButton.addEventListener('click', function () {
            var currentTheme = normalizeTheme(document.documentElement.getAttribute('data-theme'));
            applyTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
        });
        toggleButton.dataset.vgThemeBound = 'true';

        updateToggle(normalizeTheme(document.documentElement.getAttribute('data-theme')));
    }

    installLightReadabilityGuard();
    installLightFinalStylesheet();
    applyTheme(safeGetTheme(), false);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildToggle, { once: true });
    } else {
        buildToggle();
    }

    window.VigilanceTheme = {
        apply: function (theme) {
            return applyTheme(theme, true);
        },
        current: function () {
            return normalizeTheme(document.documentElement.getAttribute('data-theme'));
        },
        toggle: function () {
            var currentTheme = normalizeTheme(document.documentElement.getAttribute('data-theme'));
            return applyTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
        }
    };
})();
