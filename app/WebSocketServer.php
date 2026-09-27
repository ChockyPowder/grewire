#!/usr/bin/env php
<?php

declare(strict_types=1);

const HOST = '127.0.0.1';
const PORT = 8081;

$server = stream_socket_server(
    'tcp://' . HOST . ':' . PORT,
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if ($server === false) {
    fwrite(STDERR, "WebSocket server failed: $errstr ($errno)\n");
    exit(1);
}

stream_set_blocking($server, false);
$clients = [];
$rooms = [];

function send_frame($socket, string $payload): void
{
    $length = strlen($payload);

    if ($length < 126) {
        $frame = chr(0x81) . chr($length);
    } elseif ($length <= 65535) {
        $frame = chr(0x81) . chr(126) . pack('n', $length);
    } else {
        $frame = chr(0x81) . chr(127) . pack('J', $length);
    }

    @fwrite($socket, $frame . $payload);
}

function send_json($socket, array $payload): void
{
    send_frame($socket, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function close_client($socket): void
{
    global $clients, $rooms;

    $id = (int) $socket;

    if (!isset($clients[$id])) {
        return;
    }

    $room = $clients[$id]['room'];
    unset($clients[$id]);

    if ($room !== null && isset($rooms[$room])) {
        unset($rooms[$room][$id]);

        foreach ($rooms[$room] as $peer) {
            send_json($peer, ['type' => 'peer-left', 'peerId' => (string) $id]);
        }

        if (!$rooms[$room]) {
            unset($rooms[$room]);
        }
    }

    fclose($socket);
}

function accept_websocket($socket): ?string
{
    $request = '';
    $started = microtime(true);

    while (microtime(true) - $started < 3) {
        $chunk = fread($socket, 4096);

        if ($chunk !== false && $chunk !== '') {
            $request .= $chunk;
            if (str_contains($request, "

")) {
                break;
            }
        }

        usleep(10000);
    }

    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $request, $matches)) {
        return null;
    }

    $key = trim($matches[1]);
    $accept = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

    $response =
        "HTTP/1.1 101 Switching Protocols
" .
        "Upgrade: websocket
" .
        "Connection: Upgrade
" .
        "Sec-WebSocket-Accept: {$accept}

";

    fwrite($socket, $response);

    return $request;
}

function read_frame($socket): ?array
{
    $header = fread($socket, 2);

    if ($header === '' || $header === false) {
        return null;
    }

    if (strlen($header) < 2) {
        return null;
    }

    $byte1 = ord($header[0]);
    $byte2 = ord($header[1]);

    $opcode = $byte1 & 0x0f;
    $masked = (bool) ($byte2 & 0x80);
    $length = $byte2 & 0x7f;

    if ($length === 126) {
        $extended = fread($socket, 2);
        if ($extended === false || strlen($extended) !== 2) return null;
        $length = unpack('n', $extended)[1];
    } elseif ($length === 127) {
        $extended = fread($socket, 8);
        if ($extended === false || strlen($extended) !== 8) return null;
        $parts = unpack('N2', $extended);
        $length = ($parts[1] << 32) | $parts[2];
    }

    $mask = '';
    if ($masked) {
        $mask = fread($socket, 4);
        if ($mask === false || strlen($mask) !== 4) return null;
    }

    $payload = '';
    while (strlen($payload) < $length) {
        $chunk = fread($socket, $length - strlen($payload));
        if ($chunk === false || $chunk === '') {
            return null;
        }
        $payload .= $chunk;
    }

    if ($masked) {
        for ($i = 0; $i < $length; $i++) {
            $payload[$i] = $payload[$i] ^ $mask[$i % 4];
        }
    }

    return ['opcode' => $opcode, 'payload' => $payload];
}

function broadcast_room(string $room, array $message, ?int $except = null): void
{
    global $rooms;

    if (!isset($rooms[$room])) {
        return;
    }

    foreach ($rooms[$room] as $id => $socket) {
        if ($except !== null && $id === $except) continue;
        send_json($socket, $message);
    }
}

fwrite(STDOUT, "Grewire signaling server listening on " . HOST . ":" . PORT . "\n");

while (true) {
    $read = [$server];

    foreach ($clients as $client) {
        $read[] = $client['socket'];
    }

    $write = null;
    $except = null;

    if (stream_select($read, $write, $except, 1) === false) {
        continue;
    }

    if (in_array($server, $read, true)) {
        $socket = @stream_socket_accept($server, 0);

        if ($socket !== false) {
            stream_set_blocking($socket, true);
            $request = accept_websocket($socket);

            if ($request === null) {
                fclose($socket);
            } else {
                stream_set_blocking($socket, false);

                $id = (int) $socket;
                $clients[$id] = [
                    'socket' => $socket,
                    'room' => null,
                    'name' => 'local-user',
                ];

                send_json($socket, ['type' => 'connected', 'peerId' => (string) $id]);
            }
        }
    }

    foreach ($read as $socket) {
        if ($socket === $server) continue;

        $id = (int) $socket;

        if (!isset($clients[$id])) continue;

        $frame = read_frame($socket);

        if ($frame === null) {
            close_client($socket);
            continue;
        }

        if ($frame['opcode'] === 0x8) {
            close_client($socket);
            continue;
        }

        if ($frame['opcode'] === 0x9) {
            send_frame($socket, $frame['payload']);
            continue;
        }

        if ($frame['opcode'] !== 0x1) continue;

        $message = json_decode($frame['payload'], true);

        if (!is_array($message)) continue;

        $type = (string) ($message['type'] ?? '');

        if ($type === 'join') {
            $room = trim((string) ($message['room'] ?? ''));
            $name = trim((string) ($message['name'] ?? 'local-user'));

            if ($room === '') continue;

            $clients[$id]['room'] = $room;
            $clients[$id]['name'] = $name;
            $rooms[$room] ??= [];

            foreach ($rooms[$room] as $peerId => $peerSocket) {
                send_json($peerSocket, [
                    'type' => 'peer-joined',
                    'peerId' => (string) $id,
                    'name' => $name,
                ]);

                send_json($socket, [
                    'type' => 'existing-peer',
                    'peerId' => (string) $peerId,
                    'name' => $clients[$peerId]['name'],
                ]);
            }

            $rooms[$room][$id] = $socket;

            send_json($socket, [
                'type' => 'room-joined',
                'peerId' => (string) $id,
                'participants' => count($rooms[$room]),
            ]);

            continue;
        }

        if ($type === 'leave') {
            close_client($socket);
            continue;
        }

        if (in_array($type, ['offer', 'answer', 'candidate'], true)) {
            $target = (int) ($message['target'] ?? 0);

            if ($target > 0 && isset($clients[$target])) {
                $message['from'] = (string) $id;
                send_json($clients[$target]['socket'], $message);
            }
        }
    }
}
