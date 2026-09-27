<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$identity = dev_identity();
$appName = (string) env('APP_NAME', 'Grewire');
$debug = env_bool('APP_DEBUG', false);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=2026092702">
    <link rel="stylesheet" href="/assets/voice.css?v=2026092702">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <div class="brand-lockup">
            <div class="brand">Grewire</div>
            <div class="brand-tagline">Private Community Platform</div>
        </div>

        <div class="topbar-user">
            <span>Signed in as <strong><?= htmlspecialchars($identity['username'], ENT_QUOTES, 'UTF-8') ?></strong></span>
            <a href="/">Workspace</a>
            <span class="muted">Development mode</span>
        </div>
    </header>

    <nav class="subnav">
        <a class="active" href="/">Workspace</a>
        <a href="#messages">Messages</a>
        <a href="#voice">Voice</a>
        <a href="#status">System Status</a>
    </nav>

    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-title">Channels</div>

            <nav aria-label="Channels">
                <button class="channel active" type="button"># general</button>
                <button class="channel" type="button"># development</button>
                <button class="channel" type="button">◉ Lobby voice</button>
            </nav>

            <div class="sidebar-block">
                <div class="sidebar-heading">Account</div>
                <div class="account-card">
                    <div class="avatar">L</div>
                    <div>
                        <strong><?= htmlspecialchars($identity['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Online · local dev</span>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main" id="messages">
            <div class="page-title">
                <div>
                    <h1><span id="channel-title-main"># general</span></h1>
                    <div class="page-subtitle">Grewire Workspace</div>
                </div>

                <div class="connection" id="connection-status" data-state="checking">
                    <span class="connection-dot"></span>
                    <span>Checking database…</span>
                </div>
            </div>

            <section class="chat-panel">
                <div class="panel-header">
                    <span id="panel-heading">Conversation</span>
                    <span class="panel-header-meta">Persistent PostgreSQL messages</span>
                </div>

                <div class="chat-body">
                    <section class="messages" id="messages">
                        <article class="message">
                            <div class="avatar system">G</div>
                            <div>
                                <div class="message-meta"><strong>Grewire</strong> <time>now</time></div>
                                <div class="message-body">
                                    Welcome to Grewire. Select a channel to load its conversation.
                                </div>
                            </div>
                        </article>
                    </section>

                    <section id="voice-panel" class="voice-panel hidden" aria-label="Voice controls">
                        <div>
                            <strong>Voice</strong>
                            <span id="voice-status">Not connected</span>
                        </div>
                        <div id="voice-participants"></div>
                        <button class="btn btn-primary" id="voice-join" type="button">Join voice</button>
                    </section>

                    <form class="composer" id="message-form">
                        <input id="message-input" maxlength="2000" autocomplete="off" placeholder="Message #general…" aria-label="Message">
                        <button class="btn btn-primary" type="submit">Send</button>
                    </form>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat"><b id="channel-count">-</b><span>Channels</span></div>
                <div class="stat"><b id="message-count">-</b><span>Loaded Messages</span></div>
                <div class="stat"><b>PostgreSQL</b><span>Database</span></div>
                <div class="stat"><b>DEV</b><span>Authentication</span></div>
            </section>
        </main>
    </div>

    <footer class="footer" id="status">
        <span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) env('APP_ENV', 'local'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($debug): ?>
            <span>Debug enabled</span>
        <?php endif; ?>
    </footer>
</div>

<script src="/assets/app.js?v=2026092702" defer></script>
<script src="/assets/voice.js?v=2026092702" defer></script>
</body>
</html>
