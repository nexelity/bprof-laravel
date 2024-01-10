<?php

namespace Tests;

use Nexelity\Bprof\BprofLib;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BprofLib class.
 * @coversDefaultClass \Nexelity\Bprof\BprofLib
 */
class BprofLibTest extends TestCase
{
    /** @var BprofLib */
    private BprofLib $BprofLib;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $this->BprofLib = new BprofLib();
    }

    /**
     * Tests initMetrics method.
     * Asserts if metrics are initialized properly, without any errors.
     * @covers ::initMetrics
     */
    public function testInitMetrics(): void
    {
        $perfdata = [
            'main()' => [
                'ct' => 3,
                'wt' => 1000,
                'ut' => 100,
                'st' => 100,
                'cpu' => 200,
                'mu' => 30000,
                'pmu' => 40000,
                'samples' => 20,
            ],
        ];

        try {
            $this->BprofLib->initMetrics($perfdata);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Exception should not be thrown');
        }
    }

    /**
     * Tests getPossibleMetrics methods.
     * Asserts if method returns expected possible metrics.
     * @covers ::getPossibleMetrics
     */
    public function testGetPossibleMetrics(): void
    {
        $expectedMetrics = [
            'wt' => ['Wall', 'microsecs', 'walltime'],
            'ut' => ['User', 'microsecs', 'user cpu time'],
            'st' => ['Sys', 'microsecs', 'system cpu time'],
            'cpu' => ['Cpu', 'microsecs', 'cpu time'],
            'mu' => ['MUse', 'bytes', 'memory usage'],
            'pmu' => ['PMUse', 'bytes', 'peak memory usage'],
            'samples' => ['Samples', 'samples', 'cpu time'],
        ];
        $this->assertEquals($expectedMetrics, $this->BprofLib->getPossibleMetrics());
    }

    /**
     * Test to confirm that the getMetrics method
     * in BprofLib returns the correct metric array
     * @covers ::getMetrics
     */
    public function testGetMetrics(): void
    {
        $bprofLib = new BprofLib();

        $perfData = [
            'main()' => [
                'wt' => 100,
                'ut' => 80,
                'st' => 20,
                'cpu' => 60,
                'mu' => 5000,
                'pmu' => 6200,
                'samples' => 300,
            ],
        ];

        $expected = [
            'wt',
            'ut',
            'st',
            'cpu',
            'mu',
            'pmu',
            'samples',
        ];

        $result = $bprofLib->getMetrics($perfData);

        // Assert that the result equals the expected metrics.
        $this->assertEquals($expected, $result);
    }

    /**
     * @covers ::parseParentChild
     */
    public function testParseParentChild(): void
    {
        $tests = [
            ['a>>>b', ['a', 'b']],
            ['c', [null, 'c']],
            ['d>>>', ['d', '']],
        ];

        foreach ($tests as $test) {
            $this->assertSame($test[1], $this->BprofLib->parseParentChild($test[0]));
        }
    }

    /**
     * @covers ::computeFlatInfo
     */
    public function testComputeFlatInfo()
    {
        $expected = [
            'ct' => 4,
            'wt' => 1000,
            'ut' => 100,
            'st' => 100,
            'cpu' => 200,
            'mu' => 30000,
            'pmu' => 40000,
            'samples' => 20
        ];

        $perfdata = [
            'main()' => [
                'ct' => 3,
                'wt' => 1000,
                'ut' => 100,
                'st' => 100,
                'cpu' => 200,
                'mu' => 30000,
                'pmu' => 40000,
                'samples' => 20,
            ],
            'main()>>>a' => [
                'ct' => 1,
                'wt' => 500,
                'ut' => 50,
                'st' => 50,
                'cpu' => 100,
                'mu' => 15000,
                'pmu' => 20000,
                'samples' => 10,
            ],
        ];

        $totals = [];

        $this->BprofLib->computeFlatInfo($perfdata, $totals);

        $this->assertSame($expected, $totals);
    }

    /**
     * The computeExclusiveMetrics method
     * calculates the exclusive metrics for each function and overall totals
     * for various metrics as per the raw profiler data.
     * In this test, we will cover the 4 main cases:
     * 1. when a function call is not part of the parent function
     * 2. when a function call is part of the parent function but the parent function is not in the symbol table
     * 3. when a function call is part of the parent function and the parent function is also in the symbol table
     * 4. when a function call itself is the parent function
     * @covers ::computeExclusiveMetrics
     */
    public function testComputeExclusiveMetrics(): void
    {
        $perfdata = [
            'main()' => ['ct' => 1, 'wt' => 400, 'mu' => 50],
            'main()>>>func1' => ['ct' => 1, 'wt' => 400, 'mu' => 50],
            'main()>>>func2' => ['ct' => 1, 'wt' => 200, 'mu' => 40],
            'func1' => ['ct' => 1, 'wt' => 500, 'mu' => 200],
            'func2' => ['ct' => 1, 'wt' => 300, 'mu' => 100],
        ];

        $symbolTab = [
            'main()' => ['ct' => 1, 'wt' => 600, 'mu' => 90],
            'func1' => ['ct' => 1, 'wt' => 500, 'mu' => 200],
            'func2()>>>func3' => ['ct' => 1, 'wt' => 300, 'mu' => 50],
        ];

        $expectedSymbolTab = [
            'main()' => ['ct' => 1, 'wt' => 600, 'mu' => 90, 'excl_wt' => 0, 'excl_mu' => 0],
            'func1' => ['ct' => 1, 'wt' => 500, 'mu' => 200, 'excl_wt' => 500, 'excl_mu' => 200],
            'func2()>>>func3' => ['ct' => 1, 'wt' => 300, 'mu' => 50, 'excl_wt' => 300, 'excl_mu' => 50],
        ];

        $totals = ['ct' => 1, 'wt' => 0, 'mu' => 0];

        $this->BprofLib->computeExclusiveMetrics($perfdata, $symbolTab, $totals);

        $this->assertEquals($expectedSymbolTab, $symbolTab);
        $this->assertEquals(['wt' => 0, 'mu' => 0, 'ct' => 4], $totals);
    }


    /**
     * @return void
     * @covers ::computeInclusiveTimes
     */
    public function testComputeInclusiveTimes(): void
    {
        $perfData = [
            'main()' => ['ct' => 1, 'wt' => 100, 'ut' => 50, 'st' => 50, 'cpu' => 100, 'mu' => 10000, 'pmu' => 20000, 'samples' => 100],
            'main()>>>count' => ['ct' => 1, 'wt' => 10, 'ut' => 5, 'st' => 5, 'cpu' => 10, 'mu' => 1000, 'pmu' => 2000, 'samples' => 10]
        ];
        $results = $this->BprofLib->computeInclusiveTimes($perfData);
        $expect = [
            'main()' => [
                'ct' => 1,
                'wt' => 100,
                'ut' => 50,
                'st' => 50,
                'cpu' => 100,
                'mu' => 10000,
                'pmu' => 20000,
                'samples' => 100,
            ],
            'count' => [
                'ct' => 1,
                'wt' => 10,
                'ut' => 5,
                'st' => 5,
                'cpu' => 10,
                'mu' => 1000,
                'pmu' => 2000,
                'samples' => 10,
            ]
        ];
        $this->assertEquals($expect, $results);
    }
}
