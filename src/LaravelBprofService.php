<?php

namespace Nexelity\Bprof;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Nexelity\Bprof\DTO\QueryTrace;
use Str;

class LaravelBprofService
{
    /**
     * @var array<QueryTrace>
     */
    public static array $queries = [];

    /**
     * @param string $type
     * @param string|null $connection
     * @param string $query
     * @param float|null $time
     * @return void
     */
    public static function handleQueryExecutedEvent(
        string  $type,
        ?string $connection,
        string  $query,
        ?float  $time
    ): void
    {
        self::$queries[] = new QueryTrace(
            type: $type,
            query: $query,
            time: $time ?? 0.0,
            connection: $connection ?? 'N/A',
            stack: self::getStackTrace()
        );
    }

    /**
     * Replace the placeholders with the actual bindings.
     * @param QueryExecuted $event
     * @return string
     */
    public static function replaceBindings(QueryExecuted $event): string
    {
        $sql = $event->sql;
        if (!$sql || !is_string($sql)) {
            return $sql;
        }

        foreach (self::formatBindings($event) as $key => $binding) {
            $regex = is_numeric($key)
                ? "/\?(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/"
                : "/:{$key}(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/";

            if ($binding === null) {
                $binding = 'null';
            } elseif (!is_int($binding) && !is_float($binding)) {
                $binding = self::quoteStringBinding($event, $binding);
            }

            $sql = (string) preg_replace(
                pattern: $regex,
                replacement: (string) $binding,
                subject: $sql,
                limit: 1
            );
        }

        return $sql;
    }

    /**
     * @param int $forgetLines skip the first N lines of the stack trace
     */
    protected static function getStackTrace(int $forgetLines = 0): string
    {
        // Get the stack trace.
        $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))->forget($forgetLines);

        // Filter out the stack frames from the ignored packages.
        $collection = $trace->filter(function ($frame) {
            if (!isset($frame['file'])) {
                return false;
            }

            return !Str::contains($frame['file'], self::ignoredPackages());
        });

        // Rebuild the stack trace as a formatted string.
        return $collection->map(static function (array $frame) {
            return sprintf(
                '%s:%s',
                $frame['file'] ?? 'unknown_file',
                $frame['line'] ?? '0'
            );
        })->join(PHP_EOL);
    }

    /**
     * @return string[]
     */
    protected static function ignoredPackages(): array
    {
        $ignoredPackages = config('bprof.ignored_packages', []);
        if (!is_array($ignoredPackages)) {
            throw new \RuntimeException('The ignored_packages config value must be an array.');
        }
        return $ignoredPackages;
    }

    /**
     * Format the given bindings to strings.
     * @return array<int|string, string|float|int|null>
     */
    protected static function formatBindings(QueryExecuted $event): array
    {
        return $event->connection->prepareBindings($event->bindings);
    }

    /**
     * Add quotes to string bindings.
     */
    protected static function quoteStringBinding(QueryExecuted $event, string $binding): string
    {
        try {
            return $event->connection->getPdo()->quote($binding);
        } catch (\PDOException $e) {
            throw_if($e->getCode() !== 'IM001', $e);
        }

        // Fallback when PDO::quote function is missing...
        $binding = \strtr($binding, [
            chr(26) => '\\Z',
            chr(8) => '\\b',
            '"' => '\"',
            "'" => "\'",
            '\\' => '\\\\',
        ]);

        return "'" . $binding . "'";
    }
}
