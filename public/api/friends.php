<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_user();
$pdo = db()->pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $query = trim((string)($_GET['q'] ?? ''));

        $friendsStmt = $pdo->prepare(
            'SELECT DISTINCT u.id, u.username, u.display_name
             FROM friendships f
             INNER JOIN users u ON u.id = CASE WHEN f.user_id = :uid1 THEN f.friend_user_id ELSE f.user_id END
             WHERE (f.user_id = :uid2 OR f.friend_user_id = :uid3) AND f.status = :status
             ORDER BY u.display_name, u.username'
        );
        $friendsStmt->execute(['uid1'=>$user['id'],'uid2'=>$user['id'],'uid3'=>$user['id'],'status'=>'accepted']);

        $incomingStmt = $pdo->prepare(
            'SELECT f.id, u.id AS user_id, u.username, u.display_name, f.created_at
             FROM friendships f INNER JOIN users u ON u.id = f.user_id
             WHERE f.friend_user_id = :uid AND f.status = :status ORDER BY f.created_at DESC'
        );
        $incomingStmt->execute(['uid'=>$user['id'],'status'=>'pending']);

        $outgoingStmt = $pdo->prepare(
            'SELECT f.id, u.id AS user_id, u.username, u.display_name, f.created_at
             FROM friendships f INNER JOIN users u ON u.id = f.friend_user_id
             WHERE f.user_id = :uid AND f.status = :status ORDER BY f.created_at DESC'
        );
        $outgoingStmt->execute(['uid'=>$user['id'],'status'=>'pending']);

        $search = [];
        if ($query !== '') {
            $searchStmt = $pdo->prepare(
                'SELECT id, username, display_name FROM users
                 WHERE id <> :uid AND (username ILIKE :q OR display_name ILIKE :q)
                 ORDER BY username LIMIT 20'
            );
            $searchStmt->execute(['uid'=>$user['id'],'q'=>'%'.$query.'%']);
            $search = $searchStmt->fetchAll();
        }

        json_response([
            'ok'=>true,
            'friends'=>$friendsStmt->fetchAll(),
            'incoming'=>$incomingStmt->fetchAll(),
            'outgoing'=>$outgoingStmt->fetchAll(),
            'search'=>$search,
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) json_response(['ok'=>false,'error'=>'Request body must be JSON.'],400);

        $action=(string)($input['action']??'');
        $targetId=trim((string)($input['user_id']??''));
        $requestId=(int)($input['request_id']??0);

        if ($action==='send') {
            if ($targetId==='' || $targetId===$user['id']) json_response(['ok'=>false,'error'=>'Choose another user.'],422);

            $targetStmt=$pdo->prepare('SELECT id, username, display_name FROM users WHERE id=:id LIMIT 1');
            $targetStmt->execute(['id'=>$targetId]);
            if (!$targetStmt->fetch()) json_response(['ok'=>false,'error'=>'User not found.'],404);

            $existing=$pdo->prepare(
                'SELECT id,status FROM friendships
                 WHERE (user_id=:a AND friend_user_id=:b) OR (user_id=:b2 AND friend_user_id=:a2) LIMIT 1'
            );
            $existing->execute(['a'=>$user['id'],'b'=>$targetId,'b2'=>$targetId,'a2'=>$user['id']]);
            $row=$existing->fetch();

            if ($row) {
                if ($row['status']==='accepted') json_response(['ok'=>false,'error'=>'You are already friends.'],409);
                if ($row['status']==='pending') json_response(['ok'=>false,'error'=>'A friend request already exists.'],409);
                json_response(['ok'=>false,'error'=>'This friend request cannot be sent.'],409);
            }

            $stmt=$pdo->prepare('INSERT INTO friendships (user_id,friend_user_id,status) VALUES (:a,:b,:status)');
            $stmt->execute(['a'=>$user['id'],'b'=>$targetId,'status'=>'pending']);
            json_response(['ok'=>true,'message'=>'Friend request sent.'],201);
        }

        if ($action==='accept' || $action==='decline') {
            if ($requestId<1) json_response(['ok'=>false,'error'=>'Invalid friend request.'],422);
            $request=$pdo->prepare('SELECT id FROM friendships WHERE id=:id AND friend_user_id=:uid AND status=:status LIMIT 1');
            $request->execute(['id'=>$requestId,'uid'=>$user['id'],'status'=>'pending']);
            if (!$request->fetch()) json_response(['ok'=>false,'error'=>'Friend request not found.'],404);

            if ($action==='accept') {
                $stmt=$pdo->prepare('UPDATE friendships SET status=:status WHERE id=:id');
                $stmt->execute(['status'=>'accepted','id'=>$requestId]);
            } else {
                $stmt=$pdo->prepare('DELETE FROM friendships WHERE id=:id');
                $stmt->execute(['id'=>$requestId]);
            }
            json_response(['ok'=>true]);
        }

        if ($action==='cancel') {
            if ($requestId<1) json_response(['ok'=>false,'error'=>'Invalid friend request.'],422);
            $stmt=$pdo->prepare('DELETE FROM friendships WHERE id=:id AND user_id=:uid AND status=:status');
            $stmt->execute(['id'=>$requestId,'uid'=>$user['id'],'status'=>'pending']);
            json_response(['ok'=>true]);
        }

        if ($action==='remove') {
            if ($targetId==='') json_response(['ok'=>false,'error'=>'User is required.'],422);
            $stmt=$pdo->prepare(
                'DELETE FROM friendships WHERE status=:status
                 AND ((user_id=:a AND friend_user_id=:b) OR (user_id=:b2 AND friend_user_id=:a2))'
            );
            $stmt->execute(['status'=>'accepted','a'=>$user['id'],'b'=>$targetId,'b2'=>$targetId,'a2'=>$user['id']]);
            json_response(['ok'=>true]);
        }

        json_response(['ok'=>false,'error'=>'Unknown friend action.'],400);
    }

    header('Allow: GET, POST');
    json_response(['ok'=>false,'error'=>'Method not allowed.'],405);
} catch (Throwable $exception) {
    json_response(['ok'=>false,'error'=>$exception->getMessage()],500);
}
