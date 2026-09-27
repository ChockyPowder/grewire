<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

function valid_uuid(string $value): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $value
    );
}

function find_dev_user(): array
{
    $identity = dev_identity();
    $workspace = Grewire\DevWorkspace::ensure(db()->pdo(), (string) $identity['username']);

    return $workspace['user'];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$channelId = trim((string) ($_GET['channel_id'] ?? ''));

if ($channelId === '' || !valid_uuid($channelId)) {
    json_response(['ok' => false, 'error' => 'A valid channel_id is required.'], 400);
}

try {
    $pdo = db()->pdo();

    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT
                m.id,
                m.body,
                m.created_at,
                COALESCE(u.display_name, u.username, ''Deleted user'') AS author
             FROM messages m
             LEFT JOIN users u ON u.id = m.author_user_id
             WHERE m.channel_id = :channel_id
             ORDER BY m.id ASC
             LIMIT 200'
        );
        $stmt->execute(['channel_id' => $channelId]);

        json_response([
            'ok' => true,
            'messages' => $stmt->fetchAll(),
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            json_response(['ok' => false, 'error' => 'Request body must be JSON.'], 400);
        }

        $body = trim((string) ($input['body'] ?? ''));

        if ($body === '' || mb_strlen($body) > 4000) {
            json_response(['ok' => false, 'error' => 'Message must be between 1 and 4000 characters.'], 422);
        }

        $channelStmt = $pdo->prepare(
            'SELECT id, name, kind FROM channels WHERE id = :id LIMIT 1'
        );
        $channelStmt->execute(['id' => $channelId]);
        $channel = $channelStmt->fetch();

        if (!$channel) {
            json_response(['ok' => false, 'error' => 'Channel not found.'], 404);
        }

        if ($channel['kind'] !== 'text') {
            json_response(['ok' => false, 'error' => 'Messages can only be sent to text channels.'], 422);
        }

        $user = find_dev_user();

        $stmt = $pdo->prepare(
            'INSERT INTO messages (channel_id, author_user_id, body)
             VALUES (:channel_id, :author_user_id, :body)
             RETURNING id, body, created_at'
        );
        $stmt->execute([
            'channel_id' => $channelId,
            'author_user_id' => $user['id'],
            'body' => $body,
        ]);

        $message = $stmt->fetch();
        $message['author'] = $user['display_name'];

        json_response([
            'ok' => true,
            'message' => $message,
        ], 201);
    }

    header('Allow: GET, POST');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
