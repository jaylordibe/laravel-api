<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 2025-era MCP method errors with HTTP 200, so the client can read them.
 *
 * laravel/mcp derives the HTTP status from the JSON-RPC error code for every request
 * (Laravel\Mcp\Server\Transport\HttpTransport): -32601 becomes 404, -32603 becomes 500, and other
 * errors become 400. For the 2026-07-28 protocol that is fine: it mandates 404 for -32601 and 400 for
 * header, version and `_meta` errors, and permits laravel/mcp's 400/500 for the rest.
 * It breaks 2025-era clients, though: the TypeScript SDK v1 client (which n8n and other MCP hosts
 * use) throws "Error POSTing to endpoint" on any non-2xx response and never reads the JSON-RPC
 * error inside it, so a call to an unknown tool surfaces as a transport failure instead of an
 * error the model can act on. The official TypeScript server answers these in-band, with 200.
 *
 * Only method-level errors on requests WITHOUT the 2026-07-28 `_meta` envelope are changed:
 * unknown method (-32601), invalid params or unknown tool (-32602), internal error (-32603).
 * Protocol-version and header errors keep their 400, which the 2025 transport spec requires, and
 * parse errors keep theirs. Responses that are not JSON-RPC error bodies (this API's own 401, 403,
 * 404 and 429) are never touched.
 *
 * Registered after AuthenticateMcpClient in routes/ai.php, so it only ever sees authenticated
 * requests, and before laravel/mcp's own route middleware, so it sees the final MCP response.
 */
class NormalizeLegacyMcpErrorStatus
{

    /**
     * JSON-RPC error codes answered in-band (HTTP 200) for 2025-era requests.
     *
     * @var array<int, int>
     */
    private const array IN_BAND_ERROR_CODES = [-32601, -32602, -32603];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400 || !$this->isLegacyRequest($request)) {
            return $response;
        }

        $body = json_decode((string) $response->getContent(), true);
        $errorCode = is_array($body) && ($body['jsonrpc'] ?? null) === '2.0' ? ($body['error']['code'] ?? null) : null;

        if (in_array($errorCode, self::IN_BAND_ERROR_CODES, true)) {
            $response->setStatusCode(Response::HTTP_OK);
        }

        return $response;
    }

    /**
     * Whether the request speaks the 2025-era protocol, decided by laravel/mcp's own rule
     * (JsonRpcRequest::isLegacy) so this middleware and the server can never disagree about the era.
     * A body that is not a single JSON-RPC request has no envelope and counts as legacy; its errors
     * (-32700, -32600) are not rewritten anyway.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function isLegacyRequest(Request $request): bool
    {
        $body = json_decode((string) $request->getContent(), true);

        if (!is_array($body) || !is_string($body['method'] ?? null)) {
            return true;
        }

        $message = new JsonRpcRequest(
            id: is_int($body['id'] ?? null) || is_string($body['id'] ?? null) ? $body['id'] : 0,
            method: $body['method'],
            params: is_array($body['params'] ?? null) ? $body['params'] : [],
        );

        return $message->isLegacy();
    }

}
