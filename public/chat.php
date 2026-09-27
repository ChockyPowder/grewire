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
    <link rel="stylesheet" href="/assets/app.css?v=2026092705">
    <link rel="stylesheet" href="/assets/voice.css?v=2026092705">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <div class="brand-lockup">
            <div><div class="brand">Grewire</div></div>
            <div class="brand-tagline">Connect with Grewire</div>
        </div>
        <div class="topbar-user">
            <span class="user-name"><?= htmlspecialchars($identity['username'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="#" onclick="return false">Chat</a>
            <a href="#" onclick="return false">Logout</a>
        </div>
    </header>

    <nav class="subnav" id="main-nav">
        <a class="active" href="#" id="nav-chat">Chat</a>
        <a href="#" id="nav-servers">Servers</a>
        <a href="#" id="nav-friends">Friends</a>
    </nav>

    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-title">Workspace</div>

            <div class="sidebar-section">
                <div class="sidebar-heading">Text Channels</div>
                <nav id="text-channels" aria-label="Text channels"></nav>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-heading">Voice Channels</div>
                <nav id="voice-channels" aria-label="Voice channels"></nav>
            </div>

            <div class="sidebar-account">
                <div class="user-avatar">L</div>
                <div>
                    <strong><?= htmlspecialchars($identity['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span>Online</span>
                </div>
                <button class="user-action" type="button" title="Settings">⚙</button>
            </div>
        </aside>

        <main class="main">
            <div class="page-title">
                <div>
                    <h1 id="page-heading">Grewire</h1>
                    <div class="page-subtitle" id="page-subtitle">Your conversations</div>
                </div>
                <a class="btn btn-primary" id="page-action" href="#" onclick="return false">+ New Channel</a>
            </div>

            <section class="directory-panel hidden" id="servers-view">
                <div class="panel-header">
                    <div class="panel-header-left"><strong>Servers</strong></div>
                    <span class="panel-header-meta">Servers you are in</span>
                </div>
                <div class="directory-list" id="server-list"></div>
            </section>

            <section class="directory-panel hidden" id="friends-view">
                <div class="panel-header">
                    <div class="panel-header-left"><strong>Friends</strong></div>
                    <span class="panel-header-meta">Your added friends</span>
                </div>
                <div class="directory-list" id="friend-list"></div>
            </section>

            <div class="chat-panel" id="chat-view">
                <div class="panel-header">
                    <div class="panel-header-left">
                        <span>#</span>
                        <strong id="channel-title">general</strong>
                    </div>
                    <span class="panel-header-meta">Chat</span>
                </div>

                <div class="chat-body">
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
                </div>
            </div>
        </main>
    </div>
</div>

<script src="/assets/app.js?v=2026092705" defer></script>
<script src="/assets/voice.js?v=2026092705" defer></script>
</body>
</html>
