<link rel="stylesheet" type="text/css" href="css/font-awesome-4.7.0/css/font-awesome.min.css">

<!-- External Style Sheets  -->
<link rel="stylesheet" type="text/css" href="css/styles.css">
<link rel="stylesheet" type="text/css" href="css/table.css"/>
<link rel="stylesheet" type="text/css" href="css/menu.css"/>
<link rel="stylesheet" type="text/css" href="css/formstyle.css"/>
<link rel="stylesheet" type="text/css" href="css/buttonstyle.css">
<!-- External Scripts -->

<?php
include("validation/header.php");
include_once("company.php");

$__faviconHref = "images/nexgen-logo.png";
if(isset($_Logo) && trim((string)$_Logo) !== ""){
    $__logoFile = trim((string)$_Logo);
    $__candidates = array(
        "images/logo/".$__logoFile,
        "logo/".$__logoFile,
        $__logoFile,
    );
    foreach($__candidates as $__candidate){
        if($__candidate !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR.str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $__candidate))){
            $__faviconHref = str_replace("\\", "/", $__candidate);
            break;
        }
    }
}
?>
<script>
(function () {
    var root = document.documentElement;
    root.classList.add('xschool-loading');
    var didFinish = false;

    var finishLoading = function () {
        if (didFinish) {
            return;
        }
        didFinish = true;
        root.classList.add('xschool-loading-done');
        window.setTimeout(function () {
            root.classList.remove('xschool-loading');
            root.classList.remove('xschool-loading-done');
        }, 180);
    };

    var quickFinish = function () {
        window.requestAnimationFrame(function () {
            window.setTimeout(finishLoading, 60);
        });
    };

    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        quickFinish();
    } else {
        document.addEventListener('DOMContentLoaded', quickFinish, { once: true });
    }

    window.addEventListener('load', finishLoading, { once: true });
    window.addEventListener('pageshow', finishLoading, { once: true });
    window.setTimeout(finishLoading, 900);
})();
</script>
<script>
(function () {
    function updateLiveClock(clock) {
        if (!clock) {
            return;
        }

        var locale = clock.getAttribute('data-clock-locale') || document.documentElement.lang || navigator.language || 'en-GB';
        var timeNode = clock.querySelector('[data-live-clock-time]');
        var dateNode = clock.querySelector('[data-live-clock-date]');
        var zoneNode = clock.querySelector('[data-live-clock-zone]');
        var now = new Date();

        if (timeNode) {
            timeNode.textContent = new Intl.DateTimeFormat(locale, {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit'
            }).format(now);
        }

        if (dateNode) {
            dateNode.textContent = new Intl.DateTimeFormat(locale, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }).format(now);
        }

        if (zoneNode) {
            var zoneLabel = 'Local time';
            try {
                var zoneParts = new Intl.DateTimeFormat(locale, {
                    timeZoneName: 'short'
                }).formatToParts(now);
                var zonePart = zoneParts.find(function (part) {
                    return part.type === 'timeZoneName';
                });
                if (zonePart && zonePart.value) {
                    zoneLabel = zonePart.value + ' · Live now';
                } else if (Intl.DateTimeFormat().resolvedOptions().timeZone) {
                    zoneLabel = Intl.DateTimeFormat().resolvedOptions().timeZone + ' · Live now';
                } else {
                    zoneLabel = 'Local time · Live now';
                }
            } catch (error) {
                zoneLabel = 'Local time · Live now';
            }
            zoneNode.textContent = zoneLabel;
        }
    }

    function initLiveClocks() {
        var clocks = document.querySelectorAll('[data-live-clock]');
        if (!clocks.length) {
            return;
        }

        var renderAll = function () {
            clocks.forEach(updateLiveClock);
        };

        renderAll();
        window.setInterval(renderAll, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiveClocks, { once: true });
    } else {
        initLiveClocks();
    }
})();
</script>
<script type="text/javascript" src="scripts/xschool_script.js" defer></script>
<style>
:root{
    --xschool-watermark-image: url('<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>');
    --xschool-loader-logo-size: min(18vw, 132px);
    --xschool-loader-ring-size: min(28vw, 190px);
    --xschool-watermark-size: min(28vw, 240px);
    --xschool-watermark-glow-size: min(36vw, 320px);
}

.xschool-live-clock{
    --xclock-bg: linear-gradient(135deg, rgba(255,255,255,0.94), rgba(241,245,249,0.9));
    --xclock-border: rgba(148, 163, 184, 0.28);
    --xclock-ink: #0f172a;
    --xclock-muted: #5b6b7d;
    --xclock-accent: #0f766e;
    --xclock-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 8px;
    padding: 16px 18px;
    border-radius: 22px;
    border: 1px solid var(--xclock-border);
    background: var(--xclock-bg);
    color: var(--xclock-ink);
    box-shadow: var(--xclock-shadow);
    backdrop-filter: blur(10px);
    min-width: 220px;
}

.xschool-live-clock::before{
    content: "";
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 28%),
        radial-gradient(circle at bottom left, rgba(213, 155, 45, 0.16), transparent 32%);
    pointer-events: none;
}

.xschool-live-clock > *{
    position: relative;
    z-index: 1;
}

.xschool-live-clock__top{
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.xschool-live-clock__eyebrow{
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--xclock-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.xschool-live-clock__status{
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,0.5);
    border: 1px solid rgba(255,255,255,0.32);
    color: var(--xclock-accent);
    font-size: 0.72rem;
    font-weight: 700;
}

.xschool-live-clock__status i{
    font-size: 0.58rem;
    animation: xschoolClockPulse 1.4s ease-in-out infinite;
}

.xschool-live-clock__time{
    font-variant-numeric: tabular-nums;
    font-size: clamp(1.7rem, 3vw, 2.35rem);
    font-weight: 800;
    letter-spacing: 0.04em;
    line-height: 1;
}

.xschool-live-clock__date{
    color: var(--xclock-muted);
    font-size: 0.96rem;
    font-weight: 600;
    line-height: 1.4;
}

.xschool-live-clock__zone{
    color: var(--xclock-muted);
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

html.xschool-loading,
html.xschool-loading body{
    overflow: hidden;
}

html.xschool-loading::before,
html.xschool-loading::after{
    content:"";
    position:fixed;
    left:50%;
    top:50%;
    pointer-events:none;
    z-index:99999;
    opacity:1;
}

html.xschool-loading::before{
    inset:0;
    left:0;
    top:0;
    transform:none;
    background:
        radial-gradient(circle at center, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 18%, rgba(241, 245, 249, 0.94) 38%, rgba(255, 255, 255, 0.92) 100%),
        var(--xschool-watermark-image) 50% 50%/ var(--xschool-loader-logo-size) auto no-repeat;
    pointer-events:auto;
}

html.xschool-loading::after{
    width:var(--xschool-loader-ring-size);
    height:var(--xschool-loader-ring-size);
    transform:translate(-50%, -50%);
    border-radius:50%;
    border:4px solid rgba(15, 39, 66, 0.12);
    border-top-color:#0ea5e9;
    border-right-color:#d59b2d;
    border-bottom-color:#0f766e;
    box-shadow:
        0 0 0 10px rgba(255, 255, 255, 0.48),
        0 14px 34px rgba(15, 23, 42, 0.14);
    animation:xschoolLoaderSpin 1s linear infinite, xschoolLoaderPulse 1.8s ease-in-out infinite;
}

html.xschool-loading-done::before,
html.xschool-loading-done::after{
    opacity:0;
    transition:opacity 0.16s ease;
}

body:not(.landing-page)::before{
    content:"";
    position:fixed;
    left:50%;
    top:50%;
    width:var(--xschool-watermark-size);
    height:var(--xschool-watermark-size);
    transform:translate3d(-50%, -50%, 0);
    transform-origin:center center;
    background-image:var(--xschool-watermark-image);
    background-position:center center;
    background-size:contain;
    background-repeat:no-repeat;
    opacity:0.055;
    filter:grayscale(1) contrast(1.05);
    pointer-events:none;
    z-index:-1;
}

body:not(.landing-page)::after{
    content:"";
    position:fixed;
    left:50%;
    top:50%;
    width:var(--xschool-watermark-glow-size);
    height:var(--xschool-watermark-glow-size);
    transform:translate3d(-50%, -50%, 0);
    border-radius:50%;
    background:radial-gradient(circle, rgba(16, 37, 60, 0.04), transparent 68%);
    pointer-events:none;
    z-index:-2;
}

@media (max-width: 820px){
    :root{
        --xschool-loader-ring-size: min(42vw, 170px);
        --xschool-watermark-size: min(46vw, 220px);
    }

    html.xschool-loading::after{
        border-width:3px;
    }

    body:not(.landing-page)::before{
        opacity:0.048;
        filter:none;
    }

    body:not(.landing-page)::after{
        display:none;
    }
}

@keyframes xschoolLoaderSpin{
    from{
        transform:translate(-50%, -50%) rotate(0deg);
    }
    to{
        transform:translate(-50%, -50%) rotate(360deg);
    }
}

@keyframes xschoolLoaderPulse{
    0%,
    100%{
        box-shadow:
            0 0 0 10px rgba(255, 255, 255, 0.48),
            0 14px 34px rgba(15, 23, 42, 0.14);
    }
    50%{
        box-shadow:
            0 0 0 16px rgba(255, 255, 255, 0.3),
            0 18px 42px rgba(15, 23, 42, 0.18);
    }
}

@keyframes xschoolClockPulse{
    0%,
    100%{
        opacity: 0.45;
        transform: scale(0.92);
    }
    50%{
        opacity: 1;
        transform: scale(1.12);
    }
}

@media (max-width: 640px){
    .xschool-live-clock{
        min-width: 0;
        width: 100%;
        padding: 14px 16px;
    }

    .xschool-live-clock__top{
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<title>XSCHOOL V<?php echo date("Y");?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<meta name="theme-color" content="#0b63ce">
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>">
<link rel="shortcut icon" href="<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>">
