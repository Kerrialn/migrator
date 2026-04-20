<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Config;

final class DatabaseConfig
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $dbname,
        private readonly string $user,
        private readonly string $password,
        private readonly string $driver = 'mysqli',
    ) {
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getDbname(): string
    {
        return $this->dbname;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function toConnectionParams(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'dbname' => $this->dbname,
            'user' => $this->user,
            'password' => $this->password,
            'driver' => $this->driver,
        ];
    }
}
