<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_user();
$pdo = db()->pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $workspace = Grewire\DevWorkspace::ensure($pdo, (string)$user['username']);
        $serverId = trim((string)($_GET['server_id'] ?? ''));

        if ($serverId === '') {
            $serverId = (string)$workspace['server']['id'];
        }

        $serverStmt = $pdo->prepare(
            'SELECT s.id, s.name
             FROM servers s
             INNER JOIN server_members sm ON sm.server_id = s.id
             WHERE s.id = :server_id AND sm.user_id = :user_id
             LIMIT 1'
        );
        $serverStmt->execute(['server_id'=>$serverId,'user_id'=>$user['id']]);
        $server = $serverStmt->fetch();

        if (!$server) {
            json_response(['ok'=>false,'error'=>'You are not a member of that server.'],403);
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, kind, position
             FROM channels
             WHERE server_id = :server_id
             ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['server_id'=>$server['id']]);

        json_response([
            'ok'=>true,
            'workspace'=>$server,
            'user'=>$user,
            'channels'=>$stmt->fetchAll(),
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) json_response(['ok'=>false,'error'=>'Request body must be JSON.'],400);

        $serverId = trim((string)($input['server_id'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));

        if ($serverId === '' || $name === '' || mb_strlen($name) > 100) {
            json_response(['ok'=>false,'error'=>'A server and channel name are required.'],422);
        }

        if (!preg_match('/^[A-Za-z0-9 _-]+$/', $name)) {
            json_response(['ok'=>false,'error'=>'Channel name may only use letters, numbers, spaces, hyphens, and underscores.'],422);
        }

        $memberStmt=$pdo->prepare(
            'SELECT 1 FROM server_members WHERE server_id=:server_id AND user_id=:user_id LIMIT 1'
        );
        $memberStmt->execute(['server_id'=>$serverId,'user_id'=>$user['id']]);
        if (!$memberStmt->fetch()) {
            json_response(['ok'=>false,'error'=>'You are not a member of that server.'],403);
        }

        $posStmt=$pdo->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM channels WHERE server_id=:server_id');
        $posStmt->execute(['server_id'=>$serverId]);
        $position=(int)$posStmt->fetchColumn();

        try {
            $stmt=$pdo->prepare(
                'INSERT INTO channels (server_id,name,kind,position)
                 VALUES (:server_id,:name,:kind,:position)
                 RETURNING id,name,kind,position'
            );
            $stmt->execute(['server_id'=>$serverId,'name'=>$name,'kind'=>'text','position'=>$position]);
        } catch (PDOException $exception) {
            if ($exception->getCode()==='23505') {
                json_response(['ok'=>false,'error'=>'A channel with that name already exists.'],409);
            }
            throw $exception;
        }

        json_response(['ok'=>true,'channel'=>$stmt->fetch()],201);
    }

    header('Allow: GET, POST');
    json_response(['ok'=>false,'error'=>'Method not allowed.'],405);
} catch (Throwable $exception) {
    json_response(['ok'=>false,'error'=>$exception->getMessage()],500);
}
