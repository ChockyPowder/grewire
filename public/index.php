<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!current_user()) {
    header('Location: /login.php');
    exit;
}

require __DIR__ . '/chat.php';
