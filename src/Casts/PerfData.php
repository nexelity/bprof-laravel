<?php

namespace Nexelity\Bprof\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PerfData implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        if (! $value) {
            return $value;
        }

        return unserialize(gzuncompress($value), ['allowed_classes' => [\stdClass::class]]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        if (! $value) {
            return $value;
        }

        return gzcompress(serialize($value));
    }
}
