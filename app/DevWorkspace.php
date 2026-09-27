<?php

declare(strict_types=1);

namespace Grewire;

use PDO;
use RuntimeException;

final class DevWorkspace
{
    public static function ensure(PDO $pdo, string $username): array
    {
        $pdo->beginTransaction();

        try {
            $userStmt = $pdo->prepare(
                'SELECT id, username, display_name FROM users WHERE username = :username LIMIT 1'
            );
            $userStmt->execute(['username' => $username]);
            $user = $userStmt->fetch();

            if (!$user) {
                throw new RuntimeException('User account not found.');
            }

            $serverStmt = $pdo->prepare(
                'SELECT s.id, s.name
                 FROM servers s
                 INNER JOIN server_members sm ON sm.server_id = s.id
                 WHERE sm.user_id = :user_id
                 ORDER BY s.created_at ASC, s.id ASC
                 LIMIT 1'
            );
            $serverStmt->execute(['user_id' => $user['id']]);
            $server = $serverStmt->fetch();

            if (!$server) {
                $insertServer = $pdo->prepare(
                    'INSERT INTO servers (name, owner_user_id)
                     VALUES (:name, :owner)
                     RETURNING id, name'
                );
                $insertServer->execute([
                    'name' => $user['display_name'] . "'s Server",
                    'owner' => $user['id'],
                ]);
                $server = $insertServer->fetch();

                if (!$server) {
                    throw new RuntimeException('Unable to create your initial server.');
                }

                $memberStmt = $pdo->prepare(
                    'INSERT INTO server_members (server_id, user_id)
                     VALUES (:server_id, :user_id)
                     ON CONFLICT DO NOTHING'
                );
                $memberStmt->execute([
                    'server_id' => $server['id'],
                    'user_id' => $user['id'],
                ]);

                $channelStmt = $pdo->prepare(
                    'INSERT INTO channels (server_id, name, kind, position)
                     VALUES (:server_id, :name, :kind, :position)'
                );

                foreach ([
                    ['general', 'text', 0],
                    ['Lobby voice', 'voice', 1],
                ] as $channel) {
                    $channelStmt->execute([
                        'server_id' => $server['id'],
                        'name' => $channel[0],
                        'kind' => $channel[1],
                        'position' => $channel[2],
                    ]);
                }
            }

            $pdo->commit();

            return [
                'user' => $user,
                'server' => $server,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
