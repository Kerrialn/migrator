<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Config;

final readonly class DatabaseConfig
{
    public function __construct(
        private string $host,
        private int $port,
        private string $dbname,
        private string $user,
        private string $password,
        private string $driver = 'mysqli',
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

    /** @return array{host: string, port: int, dbname: string, user: string, password: string, driver: string} */
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
