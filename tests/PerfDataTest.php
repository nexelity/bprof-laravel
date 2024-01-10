<?php

namespace Tests;

use Nexelity\Bprof\Casts\PerfData;
use Illuminate\Database\Eloquent\Model;
use Nexelity\Bprof\Models\Trace;
use Orchestra\Testbench\Concerns\WithWorkbench;
use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;

class PerfDataTest extends TestCase
{

    use WithWorkbench;

    private PerfData $cast;

    protected function setUp(): void
    {
        $this->cast = new PerfData();
    }

    /**
     * @return void
     * @covers \Nexelity\Bprof\Casts\PerfData::get
     */
    #[Test]
    public function testGet(): void
    {
        $model = $this->createMock(Model::class);
        $key = 'some_key';
        $value = gzcompress(serialize(['foo' => 'bar']));
        $attributes = [];

        $result = $this->cast->get($model, $key, $value, $attributes);

        $this->assertEquals(['foo' => 'bar'], $result);
    }

    /**
     * @return void
     * @covers \Nexelity\Bprof\Casts\PerfData::get
     */
    #[Test]
    public function testGetNullValue(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Performance data is empty or invalid.');
        $trace = new Trace(['perf_data' => null]);
        $this->cast->get($trace, '', null, []);
    }

    /**
     * @return void
     * @covers \Nexelity\Bprof\Casts\PerfData::get
     */
    #[Test]
    public function testGetUnzippedFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to unzip performance data, it could be corrupted.');
        $trace = new Trace(['perf_data' => 'zzzzz']);
        $this->cast->get($trace, '', 'wrong_data', []);
    }

}
