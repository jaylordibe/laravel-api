<?php

namespace App\Http\Controllers;

use App\Utils\HealthUtil;
use App\Utils\ResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Health endpoints for container platforms.
 *
 * TWO PROBES, TWO QUESTIONS. Conflating them is the mistake this class exists to
 * avoid:
 *
 *   LIVENESS  — "is this process wedged?"  Served by Laravel's built-in `/up`
 *               (registered in bootstrap/app.php). Failing it gets the container
 *               KILLED AND RESTARTED, so it must not depend on anything the
 *               container does not own. A liveness probe that checks the database
 *               turns a brief database blip into every replica restarting at
 *               once — a self-inflicted outage on top of a recoverable one.
 *
 *   READINESS — "can this replica serve a request right now?"  Served here.
 *               Failing it only removes the replica from the load balancer, which
 *               is the correct response to a dependency it cannot reach. When the
 *               dependency returns, so does the replica, with no restart.
 *
 * WORKER HEALTH IS NOT PART OF THIS. Whether Horizon is processing jobs says
 * nothing about whether this API replica can answer, and folding it in would take
 * the entire API out of rotation over a queue problem. The worker container has
 * its own probe — see docker/healthcheck.sh.
 */
class HealthController extends Controller
{

    /**
     * Readiness probe: verifies the dependencies required to serve a request.
     *
     * Returns 200 when every dependency answers and 503 when any does not, so a
     * load balancer can act on the status code alone.
     *
     * @return JsonResponse
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => HealthUtil::isDatabaseReady(),
            'redis' => HealthUtil::isRedisReady(),
        ];

        $isReady = !in_array(false, $checks, true);

        return ResponseUtil::json(
            [
                'success' => $isReady,
                // `message` keeps this response inside the ONE error envelope
                // docs/api-contract.md mandates ({"success", "message"}), so a
                // generic client error handler reading `.message` still works.
                // `status` and `checks` are additive, not a second shape.
                'message' => $isReady
                    ? 'Service is ready.'
                    : 'One or more dependencies are unavailable.',
                'status' => $isReady ? 'ready' : 'unavailable',
                // Names only, and a boolean each. Deliberately no exception
                // message, driver name, host or port: this endpoint is
                // unauthenticated by necessity, and a connection error is one of
                // the most reliable ways to hand an attacker your internal
                // hostnames and software versions. The real cause is logged.
                'checks' => $checks,
            ],
            $isReady ? SymfonyResponse::HTTP_OK : SymfonyResponse::HTTP_SERVICE_UNAVAILABLE
        );
    }

}
