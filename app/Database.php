<?php

declare(strict_types=1);

namespace Grewire;

use PDO;
use PDOException;

final class Database
{
    private ?PDO $connection = null;
    public function __construct(private readonly array $config) {}
    public function pdo(): PDO
    {
        if ($this->connection instanceof PDO) return $this->connection;
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $this->config['host'], $this->config['port'], $this->config['name']);
        try {
            $this->connection = new PDO($dsn, $this->config['user'], $this->config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch (PDOException $e) { throw new PDOException('Unable to connect to PostgreSQL. Check DB_* values and that PostgreSQL is running.', (int)$e->getCode(), $e); }
        return $this->connection;
    }
    public function ping(): bool { $this->pdo()->query('SELECT 1'); return true; }
}
