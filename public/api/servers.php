<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    $workspace = Grewire\DevWorkspace::ensure(
        db()->pdo(),
        (string) dev_identity()['username']
    );

    $stmt = db()->pdo()->prepare(
        'SELECT s.id, s.name, s.created_at
         FROM servers s
         INNER JOIN server_members sm ON sm.server_id = s.id
         WHERE sm.user_id = :user_id
         ORDER BY s.created_at ASC, s.id ASC'
    );
    $stmt->execute(['user_id' => $workspace['user']['id']]);

    json_response([
        'ok' => true,
        'servers' => $stmt->fetchAll(),
        'user' => $workspace['user'],
        'active_server_id' => $workspace['server']['id'],
    ]);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
