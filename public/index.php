<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$identity = dev_identity();
$appName = (string) env('APP_NAME', 'Grewire');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=2026092703">
    <link rel="stylesheet" href="/assets/voice.css?v=2026092703">
</head>
<body>
<div class="discord-shell">
    <aside class="server-rail" aria-label="Servers">
        <button class="server-icon active" title="Grewire">G</button>
        <button class="server-icon" title="Add server">+</button>
    </aside>

    <aside class="channel-sidebar">
        <button class="workspace-switcher" type="button">
            <span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="chevron">⌄</span>
        </button>

        <div class="channel-section">
            <div class="channel-section-title">
                <span>TEXT CHANNELS</span>
                <button type="button" title="Add channel">+</button>
            </div>
            <nav id="text-channels" aria-label="Text channels"></nav>
        </div>

        <div class="channel-section">
            <div class="channel-section-title">
                <span>VOICE CHANNELS</span>
                <button type="button" title="Add channel">+</button>
            </div>
            <nav id="voice-channels" aria-label="Voice channels"></nav>
        </div>

        <div class="current-user">
            <div class="user-avatar">L</div>
            <div class="current-user-info">
                <strong><?= htmlspecialchars($identity['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span>Online</span>
            </div>
            <button class="user-action" type="button" title="Settings">⚙</button>
        </div>
    </aside>

    <main class="chat-area">
        <header class="chat-header">
            <div class="chat-channel-name">
                <span class="channel-symbol">#</span>
                <strong id="channel-title">general</strong>
            </div>
            <div class="chat-actions">
                <button type="button" title="Notifications">◔</button>
                <button type="button" title="Pinned messages">⌑</button>
                <button type="button" title="Members">☷</button>
            </div>
        </header>

        <section class="messages" id="messages"></section>

        <section id="voice-panel" class="voice-panel hidden">
            <div class="voice-panel-main">
                <div>
                    <strong>Voice Connected</strong>
                    <span id="voice-status">Connecting…</span>
                </div>
                <div id="voice-participants"></div>
            </div>
            <button class="voice-leave" id="voice-join" type="button">Join voice</button>
        </section>

        <form class="composer" id="message-form">
            <button class="composer-add" type="button" title="Add attachment">+</button>
            <input id="message-input" maxlength="2000" autocomplete="off" placeholder="Message #general…" aria-label="Message">
            <button class="composer-emoji" type="button" title="Emoji">☺</button>
        </form>
    </main>
</div>

<script src="/assets/app.js?v=2026092703" defer></script>
<script src="/assets/voice.js?v=2026092703" defer></script>
</body>
</html>
