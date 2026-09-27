<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_user();
$pdo = db()->pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function ensure_dm_conversation(PDO $pdo, string $a, string $b): array
{
    $stmt = $pdo->prepare(
        'SELECT id FROM dm_conversations
         WHERE LEAST(user_one_id,user_two_id)=LEAST(:a,:b)
           AND GREATEST(user_one_id,user_two_id)=GREATEST(:a2,:b2)
         LIMIT 1'
    );
    $stmt->execute(['a'=>$a,'b'=>$b,'a2'=>$a,'b2'=>$b]);
    $conversation=$stmt->fetch();

    if ($conversation) return $conversation;

    $insert=$pdo->prepare(
        'INSERT INTO dm_conversations (user_one_id,user_two_id)
         VALUES (:a,:b)
         RETURNING id'
    );
    $insert->execute(['a'=>$a,'b'=>$b]);
    return $insert->fetch();
}

try {
    if ($method === 'GET') {
        $conversationId=trim((string)($_GET['conversation_id']??''));
        $friendId=trim((string)($_GET['friend_id']??''));

        if ($conversationId==='') {
            if ($friendId==='') json_response(['ok'=>false,'error'=>'A friend or conversation is required.'],422);

            $friend=$pdo->prepare(
                'SELECT u.id,u.username,u.display_name
                 FROM users u
                 WHERE u.id=:friend_id
                   AND EXISTS (
                     SELECT 1 FROM friendships f
                     WHERE f.status=:status
                       AND ((f.user_id=:uid AND f.friend_user_id=u.id) OR (f.user_id=u.id AND f.friend_user_id=:uid2))
                   )
                 LIMIT 1'
            );
            $friend->execute(['friend_id'=>$friendId,'status'=>'accepted','uid'=>$user['id'],'uid2'=>$user['id']]);
            $friendUser=$friend->fetch();

            if (!$friendUser) json_response(['ok'=>false,'error'=>'You can only message accepted friends.'],403);

            $conversation=ensure_dm_conversation($pdo,$user['id'],$friendId);
            $conversationId=$conversation['id'];
        }

        $allowed=$pdo->prepare(
            'SELECT c.id,
                    CASE WHEN c.user_one_id=:uid THEN u2.id ELSE u1.id END AS friend_id,
                    CASE WHEN c.user_one_id=:uid2 THEN u2.username ELSE u1.username END AS friend_username,
                    CASE WHEN c.user_one_id=:uid3 THEN u2.display_name ELSE u1.display_name END AS friend_display_name
             FROM dm_conversations c
             INNER JOIN users u1 ON u1.id=c.user_one_id
             INNER JOIN users u2 ON u2.id=c.user_two_id
             WHERE c.id=:conversation_id AND (c.user_one_id=:uid4 OR c.user_two_id=:uid5)
             LIMIT 1'
        );
        $allowed->execute([
            'uid'=>$user['id'],'uid2'=>$user['id'],'uid3'=>$user['id'],
            'uid4'=>$user['id'],'uid5'=>$user['id'],'conversation_id'=>$conversationId
        ]);
        $conversation=$allowed->fetch();

        if (!$conversation) json_response(['ok'=>false,'error'=>'Conversation not found.'],404);

        $stmt=$pdo->prepare(
            'SELECT m.id,m.body,m.created_at,u.username AS author,u.display_name AS author_display_name
             FROM dm_messages m
             INNER JOIN users u ON u.id=m.author_user_id
             WHERE m.conversation_id=:conversation_id
             ORDER BY m.created_at ASC,m.id ASC
             LIMIT 500'
        );
        $stmt->execute(['conversation_id'=>$conversationId]);

        json_response([
            'ok'=>true,
            'conversation'=>$conversation,
            'messages'=>$stmt->fetchAll()
        ]);
    }

    if ($method === 'POST') {
        $input=json_decode(file_get_contents('php://input'),true);
        if (!is_array($input)) json_response(['ok'=>false,'error'=>'Request body must be JSON.'],400);

        $friendId=trim((string)($input['friend_id']??''));
        $body=trim((string)($input['body']??''));

        if ($friendId==='' || $body==='') json_response(['ok'=>false,'error'=>'A friend and message are required.'],422);
        if (mb_strlen($body)>4000) json_response(['ok'=>false,'error'=>'Message is too long.'],422);

        $friend=$pdo->prepare(
            'SELECT u.id FROM users u
             WHERE u.id=:friend_id AND EXISTS (
               SELECT 1 FROM friendships f
               WHERE f.status=:status
                 AND ((f.user_id=:uid AND f.friend_user_id=u.id) OR (f.user_id=u.id AND f.friend_user_id=:uid2))
             ) LIMIT 1'
        );
        $friend->execute(['friend_id'=>$friendId,'status'=>'accepted','uid'=>$user['id'],'uid2'=>$user['id']]);
        if (!$friend->fetch()) json_response(['ok'=>false,'error'=>'You can only message accepted friends.'],403);

        $conversation=ensure_dm_conversation($pdo,$user['id'],$friendId);

        $stmt=$pdo->prepare(
            'INSERT INTO dm_messages (conversation_id,author_user_id,body)
             VALUES (:conversation_id,:author,:body)
             RETURNING id,body,created_at'
        );
        $stmt->execute([
            'conversation_id'=>$conversation['id'],
            'author'=>$user['id'],
            'body'=>$body
        ]);

        json_response(['ok'=>true,'conversation_id'=>$conversation['id'],'message'=>$stmt->fetch()],201);
    }

    header('Allow: GET, POST');
    json_response(['ok'=>false,'error'=>'Method not allowed.'],405);
} catch (Throwable $exception) {
    json_response(['ok'=>false,'error'=>$exception->getMessage()],500);
}
