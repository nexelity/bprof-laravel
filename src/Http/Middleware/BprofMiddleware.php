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
        return !$request->is(
            ... config('bprof.ignored_paths', [])
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
        $bprof->initMetrics($perfdata, null, null);

        // Compute flat info
        $totals = [];
        $bprof->computeFlatInfo($perfdata, $totals);

        // Get queries
        $queries = LaravelBprofService::$queries;
        LaravelBprofService::$queries = [];

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
            'post' => serialize(
                collect($request->post())
                    ->except(config('bprof.excluded_params', ['password']))
                    ->toArray()
            ),
            'get' => serialize(
                collect($request->query())
                    ->except(config('bprof.excluded_params', ['password']))
                    ->toArray()
            ),
            'queries' => array_map(static fn(QueryTrace $query) => $query->toArray(), $queries),
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
        ]);

        // Add header
        $response->headers->set(
            'X-Bprof-Url',
            sprintf('%s/trace/?id=%s', config('bprof.viewer_url'), $trace->uuid)
        );

        return $response;
    }
}
