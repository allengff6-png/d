<?php
/*
Plugin Name: Windows Defender Popup Demo (Prank)
Description: Demonstrates a fullscreen overlay that spawns multiple Defender-like popups with a final image. Trigger with shortcode [wdp_prank total="15" batch="3" delay="1200" autostart="1"]. Press Ctrl+Q or Esc to close.
Version: 1.0.0
Author: Cursor Agent
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Keep a global registry of shortcode instances to print assets once in footer
if (!isset($GLOBALS['wdp_prank_instances'])) {
    $GLOBALS['wdp_prank_instances'] = array();
}

/**
 * Print CSS/JS in the footer once, and initialize all instances found on the page.
 */
function wdp_prank_print_assets() {
    // Only print if shortcode was used
    if (empty($GLOBALS['wdp_prank_instances'])) {
        return;
    }

    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    ?>
    <style>
        .wdp-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            background: #000;
            display: none; /* toggled to block when active */
        }
        .wdp-overlay.wdp-active { display: block; }
        .wdp-overlay-bg {
            position: absolute;
            inset: 0;
            background-position: center center;
            background-size: cover;
            filter: none;
        }
        .wdp-popup {
            position: absolute;
            width: 520px;
            height: 360px;
            background: #fff;
            border: 1px solid #c7c7c7;
            box-shadow: 0 10px 40px rgba(0,0,0,0.35);
            font-family: Segoe UI, Arial, sans-serif;
            user-select: none;
        }
        .wdp-titlebar {
            height: 30px;
            background: #0078D7;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6px;
        }
        .wdp-titlebar-left { display: flex; align-items: center; gap: 8px; }
        .wdp-titlebar-left img { width: 18px; height: 18px; display: block; }
        .wdp-titlebar-title { font-size: 12px; font-weight: 700; }
        .wdp-titlebar-buttons { display: flex; gap: 4px; }
        .wdp-title-btn { width: 24px; height: 20px; line-height: 20px; text-align: center; font-weight: 700; }

        .wdp-header { padding: 15px 20px 5px; }
        .wdp-threat { color: #d00; font-weight: 700; font-size: 14px; }
        .wdp-meta { margin-top: 6px; font-size: 12px; white-space: pre-line; }
        .wdp-divider { height: 1px; background: #d0d0d0; margin: 10px; }
        .wdp-message { font-size: 12px; text-align: center; padding: 5px 20px; }
        .wdp-buttons { display: flex; gap: 20px; justify-content: center; padding: 15px; }
        .wdp-btn {
            min-width: 120px;
            padding: 8px 12px;
            border: 1px solid #c7c7c7;
            background: #f3f3f3;
            cursor: pointer;
            font-size: 12px;
        }
        .wdp-btn-primary { background: #0078D7; color: #fff; border-color: #0078D7; }
        .wdp-bottombar {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: #f3f3f3; border-top: 1px solid #e0e0e0;
            font-size: 11px; padding: 6px 10px; color: #000; text-align: left;
        }

        .wdp-final {
            position: absolute; width: 600px; height: 350px;
            left: 50%; top: 50%; transform: translate(-50%, -50%);
            display: none; /* toggled at the end */
            box-shadow: 0 16px 48px rgba(0,0,0,0.45);
        }
        .wdp-final img { width: 100%; height: 100%; display: block; }

        .wdp-controls { position: relative; margin: 10px 0; }
        .wdp-start-btn { padding: 8px 14px; font-size: 14px; cursor: pointer; }
        .wdp-hint { font-size: 12px; color: #666; margin-top: 6px; }
    </style>

    <script>
    (function(){
        function initContainer(container){
            const totalToSpawn = parseInt(container.dataset.total, 10) || 15;
            const batchSize = Math.max(1, parseInt(container.dataset.batch, 10) || 3);
            const batchDelayMs = Math.max(0, parseInt(container.dataset.delay, 10) || 1200);
            const autostart = container.dataset.autostart !== '0';
            const bgUrl = container.dataset.bg || '';
            const logoUrl = container.dataset.logo || '';
            const finalUrl = container.dataset.final || '';

            // Create overlay structure (per instance)
            const overlay = document.createElement('div');
            overlay.className = 'wdp-overlay';

            const overlayBg = document.createElement('div');
            overlayBg.className = 'wdp-overlay-bg';
            overlayBg.style.backgroundImage = 'url(' + bgUrl + ')';
            overlay.appendChild(overlayBg);

            const finalWrap = document.createElement('div');
            finalWrap.className = 'wdp-final';
            const finalImg = document.createElement('img');
            finalImg.src = finalUrl;
            finalWrap.appendChild(finalImg);
            overlay.appendChild(finalWrap);

            // Controls (only if autostart is false)
            const controls = document.createElement('div');
            controls.className = 'wdp-controls';
            const startBtn = document.createElement('button');
            startBtn.textContent = 'Start Demo Overlay';
            startBtn.className = 'wdp-start-btn';
            const hint = document.createElement('div');
            hint.className = 'wdp-hint';
            hint.textContent = 'Press Ctrl+Q or Esc to close the overlay.';
            controls.appendChild(startBtn);
            controls.appendChild(hint);

            container.appendChild(controls);
            document.body.appendChild(overlay);

            let isRunning = false;

            function closeOverlay(){
                overlay.classList.remove('wdp-active');
                isRunning = false;
                // Remove all child popups created for this overlay
                overlay.querySelectorAll('.wdp-popup').forEach(function(node){ node.remove(); });
            }

            document.addEventListener('keydown', function(e){
                const key = e.key ? e.key.toLowerCase() : '';
                if ((e.ctrlKey && key === 'q') || key === 'escape') {
                    closeOverlay();
                }
            });

            function beep(){
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const o = ctx.createOscillator();
                    const g = ctx.createGain();
                    o.type = 'square';
                    o.frequency.value = 1000;
                    o.connect(g); g.connect(ctx.destination);
                    g.gain.setValueAtTime(0.03, ctx.currentTime);
                    o.start();
                    setTimeout(function(){ o.stop(); ctx.close(); }, 300);
                } catch (err) {
                    // ignore
                }
            }

            function createPopup(x, y){
                const popup = document.createElement('div');
                popup.className = 'wdp-popup';

                const titlebar = document.createElement('div');
                titlebar.className = 'wdp-titlebar';

                const left = document.createElement('div');
                left.className = 'wdp-titlebar-left';
                const logo = document.createElement('img');
                logo.src = logoUrl;
                left.appendChild(logo);
                const title = document.createElement('div');
                title.className = 'wdp-titlebar-title';
                title.textContent = 'Windows Defender Security Center';
                left.appendChild(title);

                const right = document.createElement('div');
                right.className = 'wdp-titlebar-buttons';
                ['—','▢','✕'].forEach(function(txt){
                    const b = document.createElement('div');
                    b.className = 'wdp-title-btn';
                    b.textContent = txt;
                    right.appendChild(b);
                });

                titlebar.appendChild(left);
                titlebar.appendChild(right);
                popup.appendChild(titlebar);

                const header = document.createElement('div');
                header.className = 'wdp-header';
                const threat = document.createElement('div');
                threat.className = 'wdp-threat';
                threat.textContent = 'Threat Detected!';
                header.appendChild(threat);
                const meta = document.createElement('div');
                meta.className = 'wdp-meta';
                meta.textContent = 'App: Ads.financetrack(2).dll\nAlert level: Severe\nStatus: Active';
                header.appendChild(meta);
                popup.appendChild(header);

                const divider = document.createElement('div');
                divider.className = 'wdp-divider';
                popup.appendChild(divider);

                const msg = document.createElement('div');
                msg.className = 'wdp-message';
                msg.textContent = 'Access to this PC has been blocked for security reasons.\nPlease choose an action below to continue.';
                popup.appendChild(msg);

                const btns = document.createElement('div');
                btns.className = 'wdp-buttons';
                const deny = document.createElement('button');
                deny.className = 'wdp-btn';
                deny.textContent = 'Deny';
                deny.addEventListener('click', function(){ createPopup(); });
                const allow = document.createElement('button');
                allow.className = 'wdp-btn wdp-btn-primary';
                allow.textContent = 'Allow';
                allow.addEventListener('click', function(){ createPopup(); });
                btns.appendChild(deny);
                btns.appendChild(allow);
                popup.appendChild(btns);

                const bottom = document.createElement('div');
                bottom.className = 'wdp-bottombar';
                bottom.textContent = 'Microsoft Defender Antivirus';
                popup.appendChild(bottom);

                // Random placement if not specified
                const screenW = window.innerWidth;
                const screenH = window.innerHeight;
                const px = (typeof x === 'number') ? x : Math.floor(Math.random() * Math.max(1, screenW - 550)) + 20;
                const py = (typeof y === 'number') ? y : Math.floor(Math.random() * Math.max(1, screenH - 400)) + 20;
                popup.style.left = px + 'px';
                popup.style.top = py + 'px';

                overlay.appendChild(popup);
                return popup;
            }

            function showFinal(){
                finalWrap.style.display = 'block';
            }

            function spawnMany(total, batch, delay){
                let spawned = 0;
                function spawnBatch(){
                    if (!isRunning) return;
                    const remaining = total - spawned;
                    const thisBatch = Math.min(batch, remaining);
                    for (let i = 0; i < thisBatch; i++) {
                        createPopup();
                    }
                    spawned += thisBatch;
                    beep();
                    if (spawned < total) {
                        setTimeout(spawnBatch, delay);
                    } else {
                        setTimeout(showFinal, 2000);
                    }
                }
                spawnBatch();
            }

            function start(){
                if (isRunning) return;
                isRunning = true;
                overlay.classList.add('wdp-active');
                spawnMany(totalToSpawn, batchSize, batchDelayMs);
            }

            startBtn.addEventListener('click', start);

            if (autostart) {
                controls.style.display = 'none';
                setTimeout(start, 500);
            }
        }

        function initAll(){
            document.querySelectorAll('.wdp-container').forEach(function(container){
                if (container.getAttribute('data-wdp-initialized') === '1') return;
                initContainer(container);
                container.setAttribute('data-wdp-initialized', '1');
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'wdp_prank_print_assets');

/**
 * Shortcode to render an instance container and register it for footer initialization
 *
 * Usage: [wdp_prank total="15" batch="3" delay="1200" autostart="1"]
 * - total: total popups to spawn (default 15)
 * - batch: popups per batch (default 3)
 * - delay: milliseconds between batches (default 1200)
 * - autostart: 1 to auto-start on render, 0 to show a button (default 1)
 */
function wdp_prank_shortcode($atts) {
    $atts = shortcode_atts(array(
        'total' => '15',
        'batch' => '3',
        'delay' => '1200',
        'autostart' => '1',
    ), $atts, 'wdp_prank');

    $total = intval($atts['total']);
    $batch = max(1, intval($atts['batch']));
    $delay = max(0, intval($atts['delay']));
    $autostart = ($atts['autostart'] === '0') ? '0' : '1';

    // External images (replace with your own if desired)
    $background_url = 'https://i.ibb.co/wFhz3hv0/Screenshot-2025-08-19-010346.png';
    $logo_url = 'https://i.ibb.co/tTRTcr3F/microsoft1.png';
    $final_url = 'https://i.ibb.co/qMxH8mG2/Your-paragraph-text.png';

    // Unique container id
    if (function_exists('wp_unique_id')) {
        $container_id = wp_unique_id('wdp-');
    } else {
        $container_id = 'wdp-' . uniqid();
    }

    // Track that we have at least one instance on the page
    $GLOBALS['wdp_prank_instances'][] = $container_id;

    ob_start();
    ?>
    <div id="<?php echo esc_attr($container_id); ?>"
         class="wdp-container"
         data-total="<?php echo esc_attr($total); ?>"
         data-batch="<?php echo esc_attr($batch); ?>"
         data-delay="<?php echo esc_attr($delay); ?>"
         data-autostart="<?php echo esc_attr($autostart); ?>"
         data-bg="<?php echo esc_url($background_url); ?>"
         data-logo="<?php echo esc_url($logo_url); ?>"
         data-final="<?php echo esc_url($final_url); ?>">
        <noscript>This demo requires JavaScript.</noscript>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wdp_prank', 'wdp_prank_shortcode');
add_shortcode('windows_defender_prank', 'wdp_prank_shortcode');
