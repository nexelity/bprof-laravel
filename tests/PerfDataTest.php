<?php

namespace Tests;

use Nexelity\Bprof\Casts\PerfData;
use Illuminate\Database\Eloquent\Model;
use Nexelity\Bprof\Models\Trace;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;

/**
 * Tests for PerfDataTest class.
 * @coversDefaultClass \Nexelity\Bprof\Casts\PerfData
 */
class PerfDataTest extends TestCase
{
    use WithWorkbench;

    /**
     * @covers ::get
     */
    public function testGet(): void
    {
        $cast = new PerfData();
        $model = $this->createMock(Model::class);
        $key = 'some_key';
        $value = gzcompress(serialize(['foo' => 'bar']));
        $attributes = [];

        $result = $cast->get($model, $key, $value, $attributes);

        $this->assertEquals(['foo' => 'bar'], $result);
    }

    /**
     * @covers ::get
     */
    public function testGetUnzippedFailure(): void
    {
        $cast = new PerfData();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to unzip performance data, it could be corrupted.');
        $cast->get(new Trace(), '', 'wrong_data', []);
    }

    /**
     * @covers ::get
     */
    public function testGetUnserializedFailure(): void
    {
        $cast = new PerfData();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to unserialize performance data, it could be corrupted.');
        $cast->get(new Trace(), '', base64_decode('eJzLSM3JyTc0MgYADZUCqw=='), []);
    }

    /**
     * @covers ::get
     */
    public function testGetEmptyValue(): void
    {
        $cast = new PerfData();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Performance data is empty or invalid.');
        $cast->get(new Trace(), '', '', []);
    }

    /**
     * @covers ::get
     */
    public function testGetNullValue(): void
    {
        $cast = new PerfData();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Performance data is empty or invalid.');
        $cast->get(new Trace(), '', null, []);
    }

    /**
     * @covers ::set
     */
    public function testSetSuccessfullyCompressesPerformanceData(): void
    {
        $cast = new PerfData();
        $model = $this->getMockBuilder(Model::class)->getMock();
        $sampleData = ['foo' => 'bar'];

        $result = $cast->set(
            $model,
            'someKey',
            $sampleData,
            []
        );

        $this->assertIsString($result);
    }

    /**
     * @covers ::set
     */
    public function testSetThrowsExceptionWhenPerformanceDataIsEmpty(): void
    {
        $cast = new PerfData();
        $model = $this->getMockBuilder(Model::class)->getMock();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Performance data is empty.');

        $cast->set(
            $model,
            'someKey',
            null,
            []
        );
    }
}
