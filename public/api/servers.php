<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_user();
$pdo = db()->pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $workspace = Grewire\DevWorkspace::ensure($pdo, (string)$user['username']);

        $stmt = $pdo->prepare(
            'SELECT s.id, s.name, s.owner_user_id, s.created_at
             FROM servers s
             INNER JOIN server_members sm ON sm.server_id = s.id
             WHERE sm.user_id = :user_id
             ORDER BY s.created_at ASC, s.id ASC'
        );
        $stmt->execute(['user_id' => $user['id']]);

        json_response([
            'ok' => true,
            'servers' => $stmt->fetchAll(),
            'user' => $user,
            'active_server_id' => $workspace['server']['id'],
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            json_response(['ok' => false, 'error' => 'Request body must be JSON.'], 400);
        }

        $name = trim((string)($input['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            json_response(['ok' => false, 'error' => 'Server name must be between 1 and 100 characters.'], 422);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO servers (name, owner_user_id)
             VALUES (:name, :owner)
             RETURNING id, name, owner_user_id, created_at'
        );
        $stmt->execute(['name' => $name, 'owner' => $user['id']]);
        $server = $stmt->fetch();

        $member = $pdo->prepare(
            'INSERT INTO server_members (server_id, user_id)
             VALUES (:server_id, :user_id)'
        );
        $member->execute(['server_id' => $server['id'], 'user_id' => $user['id']]);

        $channel = $pdo->prepare(
            'INSERT INTO channels (server_id, name, kind, position)
             VALUES (:server_id, :name, :kind, :position)'
        );
        $channel->execute([
            'server_id' => $server['id'],
            'name' => 'general',
            'kind' => 'text',
            'position' => 0,
        ]);
        $channel->execute([
            'server_id' => $server['id'],
            'name' => 'Lobby voice',
            'kind' => 'voice',
            'position' => 1,
        ]);

        $pdo->commit();

        json_response(['ok' => true, 'server' => $server], 201);
    }

    header('Allow: GET, POST');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
