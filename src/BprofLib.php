<?php

namespace Nexelity\Bprof;

use RuntimeException;

/**
 * Bprof: A Hierarchical Profiler for PHP
 */
class BprofLib
{
    /** @var string[] */
    private array $metrics;

    /**
     * Initialize the metrics array.
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

    /*
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
     * @return array<string> Returns a list of metric names.
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

    /*
     * Get the list of metrics present in $bprof_data as an array.
     * @return array<string> Returns a list of metric names.
     */
    public function getMetrics(array $perfdata): array
    {
        // get list of valid metrics
        $possible_metrics = $this->getPossibleMetrics();

        // return those that are present in the raw data.
        // We'll just look at the root of the subtree for this.
        $this->metrics = [];
        foreach ($possible_metrics as $metric => $desc) {
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
     * @param array $perfdata bprof format raw profiler data.
     * @param array  &$totals OUT argument for returning overall totals for various metrics.
     * @return array Returns a map from function name to its call count and inclusive & exclusive metrics (such as wall time, etc.).
     */
    public function computeFlatInfo(array $perfdata, array &$totals): array
    {
        $this->metrics = $this->getMetrics($perfdata);
        $totals = $this->emptyTotals();
        $symbolTab = $this->computeInclusiveTimes($perfdata);

        foreach ($this->metrics as $metric) {
            $totals[$metric] = $symbolTab['main()'][$metric];
        }

        $this->computeExclusiveMetrics($symbolTab, $totals, $perfdata);

        return $symbolTab;
    }

    private function computeExclusiveMetrics(array &$symbolTab, array &$totals, array $perfdata): void
    {
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
     * Compute inclusive metrics for function. This code was factored out
     * of bprof_compute_flat_info().
     *
     * The raw data contains inclusive metrics of a function for each
     * unique parent function it is called from. The total inclusive metrics
     * for a function is therefore the sum of inclusive metrics for the
     * function across all parents.
     *
     * @return array  Returns a map of function name to total (across all parents) inclusive metrics for the function.
     *
     */
    public function computeInclusiveTimes(array $perfdata): array
    {
        $this->metrics = $this->getMetrics($perfdata);

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
