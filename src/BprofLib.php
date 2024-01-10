<?php

namespace Nexelity\Bprof;

use RuntimeException;

/**
 * Bprof: A Hierarchical Profiler for PHP
 */
class BprofLib
{
    /** @var string[] */
    private array $metrics = [];

    /**
     * Initialize the metrics array.
     * @param array<string, array<string, numeric>> $perfdata bprof format raw profiler data.
     */
    public function initMetrics(array $perfdata): void
    {
        // parent/child report doesn't support exclusive times yet.
        // So, change sort hyperlinks to the closest fit.
        $possibleMetrics = $this->getPossibleMetrics();
        foreach ($possibleMetrics as $metric => $desc) {
            if (isset($perfdata['main()'][$metric])) {
                $this->metrics[] = $metric;
            }
        }
    }

    /**
     * The list of possible metrics collected as part of bprof that
     * require inclusive/exclusive handling while reporting.
     * @return array<string, string[]> Returns a map from metric name to metric description.
     */
    public function getPossibleMetrics(): array
    {
        return [
            'wt' => ['Wall', 'microsecs', 'walltime'],
            'ut' => ['User', 'microsecs', 'user cpu time'],
            'st' => ['Sys', 'microsecs', 'system cpu time'],
            'cpu' => ['Cpu', 'microsecs', 'cpu time'],
            'mu' => ['MUse', 'bytes', 'memory usage'],
            'pmu' => ['PMUse', 'bytes', 'peak memory usage'],
            'samples' => ['Samples', 'samples', 'cpu time'],
        ];
    }

    /**
     * Returns an array of metrics that are present in the raw data.
     * @return array<string, numeric> Returns a list of metric names.
     */
    private function emptyTotals(): array
    {
        return [
            'ct' => 0,
            'wt' => 0,
            'ut' => 0,
            'st' => 0,
            'cpu' => 0,
            'mu' => 0,
            'pmu' => 0,
            'samples' => 0,
        ];
    }

    /**
     * Get the list of metrics present in $bprof_data as an array.
     * @param array<string, array<string, numeric>> $perfdata bprof format raw profiler data.
     * @return array<string> Returns a list of metric names.
     */
    public function getMetrics(array $perfdata): array
    {
        // get list of valid metrics
        $possibleMetrics = $this->getPossibleMetrics();

        // return those that are present in the raw data.
        // We'll just look at the root of the subtree for this.
        foreach ($possibleMetrics as $metric => $desc) {
            if (isset($perfdata['main()'][$metric])) {
                $this->metrics[] = $metric;
            }
        }

        return $this->metrics;
    }

    /**
     * Takes a parent/child function name encoded as
     * 'a>>>b' and returns ['a', 'b'].
     * @return array<string|null> Returns a list of parent and child function names.
     */
    public function parseParentChild(string $parentChild): array
    {
        // split parent/child
        $parts = explode('>>>', $parentChild);

        if (count($parts) === 2) {
            // Both parent and child are set
            list($parent, $child) = $parts;
        } else {
            // Only child is set, parent is null
            $child = $parts[0];
            $parent = null;
        }

        return [$parent, $child];
    }

    /**
     * Analyze hierarchical raw data, and compute per-function (flat) inclusive and exclusive metrics.
     * Also, store overall totals in the 2nd argument.
     * @param array<string, array<string, numeric>> $perfdata bprof format raw profiler data.
     * @param array<string> &$totals overall totals for various metrics.
     * @return array<string, array<string, numeric>> Returns a map from function name to its call count and inclusive & exclusive metrics (such as wall time, etc.).
     */
    public function computeFlatInfo(array $perfdata, array &$totals): array
    {
        $this->metrics = $this->getMetrics($perfdata);
        $totals = $this->emptyTotals();
        $symbolTab = $this->computeInclusiveTimes($perfdata);

        foreach ($this->metrics as $metric) {
            $totals[$metric] = $symbolTab['main()'][$metric];
        }

        $this->computeExclusiveMetrics($perfdata, $symbolTab, $totals);

        return $symbolTab;
    }

    /**
     * @param array<string, array<string, numeric>> $perfdata
     * @param array<string, array<string, numeric>> $symbolTab
     * @param array<string> &$totals
     * @return void
     */
    public function computeExclusiveMetrics(array $perfdata, array &$symbolTab, array &$totals): void
    {

        if (empty($this->metrics)) {
            $this->metrics = $this->getMetrics($perfdata);
        }

        foreach ($symbolTab as $symbol => $info) {
            foreach ($this->metrics as $metric) {
                $symbolTab[$symbol]['excl_' . $metric] = $symbolTab[$symbol][$metric];
            }
            $totals['ct'] += $info['ct'];
        }

        foreach ($perfdata as $parentChild => $info) {
            [$parent, $child] = $this->parseParentChild($parentChild);
            if ($parent) {
                foreach ($this->metrics as $metric) {
                    if (isset($symbolTab[$parent])) {
                        $symbolTab[$parent]['excl_' . $metric] -= $info[$metric];
                    }
                }
            }
        }
    }


    /**
     * Computes the inclusive times and call count for each function in the given performance data.
     *
     * @param array $perfdata The performance data containing parent-child relationships and metrics.
     *
     * @return array The computed inclusive times and call count for each function.
     * @throws RuntimeException If the parent and child are the same.
     *
     */
    public function computeInclusiveTimes(array $perfdata): array
    {
        if (empty($this->metrics)) {
            $this->metrics = $this->getMetrics($perfdata);
        }
        $symbolTab = [];

        /*
         * First compute inclusive time for each function and total
         * call count for each function across all parents the
         * function is called from.
         */
        foreach ($perfdata as $parentChild => $info) {
            [$parent, $child] = $this->parseParentChild($parentChild);

            if ($parent === $child) {
                /*
                 * bprof PHP extension should never trigger this situation anymore.
                 * Recursion is handled in the bprof PHP extension by giving nested
                 * calls a unique recursion-depth appended name (for example, foo@1).
                 */
                throw new RuntimeException(sprintf('Error in Raw Data: parent & child are both: %s', $parent));
            }

            if (!isset($symbolTab[$child])) {
                /* increment call count for this child */
                $symbolTab[$child] = ['ct' => $info['ct']];

                /* update inclusive times/metric for this child  */
                foreach ($this->metrics as $metric) {
                    $symbolTab[$child][$metric] = $info[$metric];
                }
            } else {
                /* increment call count for this child */
                $symbolTab[$child]['ct'] += $info['ct'];

                /* update inclusive times/metric for this child  */
                foreach ($this->metrics as $metric) {
                    $symbolTab[$child][$metric] += $info[$metric];
                }
            }
        }

        return $symbolTab;
    }

}
