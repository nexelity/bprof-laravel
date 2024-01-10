<?php

namespace Nexelity\Bprof\Models;

use Illuminate\Database\Eloquent\Model;
use Nexelity\Bprof\Casts\PerfData;

/**
 * Nexelity\Bprof\Models\Trace
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $url
 * @property string|null $method
 * @property string|null $server_name
 * @property mixed|null $perfdata
 * @property mixed|null $queries
 * @property string|null $cookie
 * @property string|null $post
 * @property string|null $user_id
 * @property string|null $get
 * @property int|null $pmu
 * @property int|null $wt
 * @property int|null $cpu
 * @property string|null $ip
 * @property string|null $user_name
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Trace newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Trace newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Trace query()
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereCookie($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereCpu($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereGet($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace wherePerfdata($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace wherePmu($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace wherePost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereServerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereWt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trace whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Trace extends Model
{
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'url', 'method', 'pmu', 'wt', 'cpu',
        'server_name', 'cookie', 'user_id', 'ip',
        'post', 'get', 'perfdata', 'queries', 'status_code',
    ];

    protected $casts = [
        'perfdata' => PerfData::class,
        'queries' => PerfData::class,
    ];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the table name from config
        $table = config('bprof.db_table');
        if ($table && is_string($table)) {
            $this->table = $table;
        }

        // Set the connection name from config
        $connection = config('bprof.db_connection');
        if ($connection && is_string($connection)) {
            $this->connection = $connection;
        }
    }

    public static function boot()
    {
        parent::boot();
        static::creating(static function ($model) {
            $model->uuid = \Str::uuid();
        });
    }
}
