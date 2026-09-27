<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    $identity = dev_identity();
    $workspace = Grewire\DevWorkspace::ensure(
        db()->pdo(),
        (string) $identity['username']
    );

    $stmt = db()->pdo()->prepare(
        'SELECT id, name, kind, position
         FROM channels
         WHERE server_id = :server_id
         ORDER BY position ASC, id ASC'
    );
    $stmt->execute(['server_id' => $workspace['server']['id']]);

    json_response([
        'ok' => true,
        'workspace' => $workspace['server'],
        'user' => $workspace['user'],
        'channels' => $stmt->fetchAll(),
    ]);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
