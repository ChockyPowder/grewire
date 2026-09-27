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
        'SELECT DISTINCT u.id, u.username, u.display_name, u.created_at
         FROM friendships f
         INNER JOIN users u ON u.id = f.friend_user_id
         WHERE f.user_id = ? AND f.status = ?
         UNION
         SELECT DISTINCT u.id, u.username, u.display_name, u.created_at
         FROM friendships f
         INNER JOIN users u ON u.id = f.user_id
         WHERE f.friend_user_id = ? AND f.status = ?
         ORDER BY display_name ASC, username ASC'
    );
    $stmt->execute([
        $workspace['user']['id'], 'accepted',
        $workspace['user']['id'], 'accepted',
    ]);

    json_response([
        'ok' => true,
        'friends' => $stmt->fetchAll(),
        'user' => $workspace['user'],
    ]);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
