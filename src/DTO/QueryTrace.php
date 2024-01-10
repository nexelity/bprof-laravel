<?php

namespace Nexelity\Bprof\DTO;

/**
 * QueryTrace DTO
 */
class QueryTrace
{
    public function __construct(
        string $type,
        string $query,
        float $time,
        string $connection,
        string $stack
    ) {
        $this->type = $type;
        $this->query = $query;
        $this->time = $time;
        $this->connection = $connection;
        $this->stack = $stack;
    }

    public string $type;
    public string $query;
    public float $time;
    public string $connection;
    public string $stack;

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'query' => $this->query,
            'time' => $this->time,
            'connection' => $this->connection,
            'stack' => $this->stack,
        ];
    }
}
