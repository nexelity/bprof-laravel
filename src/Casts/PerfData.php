<?php

namespace Nexelity\Bprof\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PerfData implements CastsAttributes
{
    /**
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function get($model, string $key, mixed $value, array $attributes)
    {

        if (!$value || !is_string($value)) {
            throw new \RuntimeException('Performance data is empty or invalid.');
        }

        $unzipped = @gzuncompress($value);
        if (!$unzipped) {
            throw new \RuntimeException('Failed to unzip performance data, it could be corrupted.');
        }

        $unserialized = @unserialize($unzipped, ['allowed_classes' => [\stdClass::class]]);
        if (!$unserialized || !is_array($unserialized)) {
            throw new \RuntimeException('Failed to unserialize performance data, it could be corrupted.');
        }

        return $unserialized;
    }

    /**
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     * @return string
     */
    public function set($model, string $key, mixed $value, array $attributes)
    {
        if (!$value) {
            throw new \RuntimeException('Performance data is empty.');
        }

        $serialized = serialize($value);
        $compressed = @gzcompress($serialized);

        if (!$compressed) {
            throw new \RuntimeException('Failed to compress performance data.');
        }

        return $compressed;
    }
}
