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
                'INSERT INTO users (username, display_name)
                 VALUES (:username, :display_name)
                 ON CONFLICT (username)
                 DO UPDATE SET display_name = EXCLUDED.display_name
                 RETURNING id, username, display_name'
            );
            $userStmt->execute([
                'username' => $username,
                'display_name' => $username,
            ]);
            $user = $userStmt->fetch();

            if (!$user) {
                throw new RuntimeException('Unable to create development user.');
            }

            $serverStmt = $pdo->query(
                "SELECT id, name FROM servers ORDER BY created_at ASC, id ASC LIMIT 1"
            );
            $server = $serverStmt->fetch();

            if (!$server) {
                $insertServer = $pdo->prepare(
                    'INSERT INTO servers (name, owner_user_id)
                     VALUES (:name, :owner)
                     RETURNING id, name'
                );
                $insertServer->execute([
                    'name' => 'Grewire Workspace',
                    'owner' => $user['id'],
                ]);
                $server = $insertServer->fetch();
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
                 VALUES (:server_id, :name, :kind, :position)
                 ON CONFLICT (server_id, name)
                 DO UPDATE SET kind = EXCLUDED.kind, position = EXCLUDED.position'
            );

            foreach ([
                ['general', 'text', 0],
                ['development', 'text', 1],
                ['Lobby voice', 'voice', 2],
            ] as $channel) {
                $channelStmt->execute([
                    'server_id' => $server['id'],
                    'name' => $channel[0],
                    'kind' => $channel[1],
                    'position' => $channel[2],
                ]);
            }

            $pdo->commit();

            return [
                'user' => $user,
                'server' => $server,
            ];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
