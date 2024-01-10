<?php

namespace Nexelity\Bprof\Http\Middleware;

use Closure;
use http\Exception\RuntimeException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nexelity\Bprof\BprofLib;
use Nexelity\Bprof\DTO\QueryTrace;
use Nexelity\Bprof\LaravelBprofService;
use Nexelity\Bprof\Models\Trace;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class BprofMiddleware
{
    /**
     * Determine if the request is to an approved URI.
     */
    protected static function requestIsToApprovedUri(Request $request): bool
    {
        /** @var string[] $ignoredPaths */
        $ignoredPaths = config('bprof.ignored_paths', []);
        return !$request->is(
            ... $ignoredPaths
        );
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param mixed ...$guards
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // Check if bprof should run
        $enabled = config('bprof.enabled', false);
        if (!$enabled || !self::requestIsToApprovedUri($request)) {
            return $next($request);
        }

        // Check if bprof is loaded
        if (!extension_loaded('bprof')) {
            throw new RuntimeException('BProf extension is not loaded');
        }

        // Reset queries
        LaravelBprofService::$queries = [];

        // Start profiling
        bprof_enable();

        /** @var Response $response */
        $response = $next($request);
        $perfdata = bprof_disable();

        // Don't log 404s
        if ($response->getStatusCode() === 404) {
            return $response;
        }

        /** @var BprofLib $bprof */
        $bprof = app()->make(BprofLib::class);

        // Initialize metrics
        $bprof->initMetrics($perfdata);

        // Compute flat info
        $totals = [];
        $bprof->computeFlatInfo($perfdata, $totals);

        // Get queries
        $queries = LaravelBprofService::$queries;
        LaravelBprofService::$queries = [];

        /** @var array<string> $excludedParams */
        $excludedParams = config('bprof.excluded_params', ['password']);

        // Get post params
        $post = $request->post();
        if (is_array($post)) {
            $post = collect($post)
                ->except($excludedParams)
                ->toArray();
        }

        // Get query params
        $get = $request->query();
        if (is_array($get)) {
            $get = collect($get)
                ->except($excludedParams)
                ->toArray();
        }

        // Save trace
        $trace = Trace::create([
            'uuid' => Uuid::uuid4(),
            'url' => $request->getRequestUri(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'pmu' => $totals['pmu'],
            'wt' => $totals['wt'],
            'perfdata' => $perfdata,
            'cpu' => $totals['cpu'],
            'server_name' => config('bprof.server_name'),
            'cookie' => serialize($request->cookie()),
            'post' => serialize($post),
            'get' => serialize($get),
            'queries' => array_map(static fn(QueryTrace $query) => $query->toArray(), $queries),
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        // If we have a viewer url provided, add the trace URL to the response headers
        /** @var string $viewerUrl */
        $viewerUrl = config('bprof.viewer_url');
        if ($viewerUrl) {
            // Add header
            $response->headers->set(
                'X-Bprof-Url',
                sprintf('%s/trace/?id=%s', $viewerUrl, $trace->uuid)
            );
        }

        return $response;
    }
}
