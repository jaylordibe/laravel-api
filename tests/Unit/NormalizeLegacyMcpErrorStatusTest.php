<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeLegacyMcpErrorStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins exactly which MCP error responses App\Http\Middleware\NormalizeLegacyMcpErrorStatus answers with
 * HTTP 200, and which keep their status.
 *
 * The feature test reaches only the errors the three tools can produce. This drives the middleware
 * directly, so every code in its list, and every code deliberately left out of it, is protected: a
 * 2025-era client (the TypeScript SDK v1) cannot read a JSON-RPC error carried by a non-2xx response,
 * while the 2025 transport spec still requires 400 for protocol-version, header and request-level
 * errors, and the 2026-07-28 protocol mandates its own statuses.
 */
class NormalizeLegacyMcpErrorStatusTest extends TestCase
{

    /**
     * A 2025-era JSON-RPC request body.
     *
     * @var array<string, mixed>
     */
    private const array LEGACY_REQUEST = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'x']];

    /**
     * Run the middleware over a request body and the response the server produced.
     *
     * @param array<string, mixed>|string $requestBody
     * @param Response|JsonResponse $serverResponse
     *
     * @return int The resulting HTTP status.
     */
    private function statusAfterMiddleware(array|string $requestBody, Response|JsonResponse $serverResponse): int
    {
        $content = is_array($requestBody) ? json_encode($requestBody) : $requestBody;
        $request = Request::create('/api/mcp', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $content);

        return (new NormalizeLegacyMcpErrorStatus())->handle($request, fn (): Response|JsonResponse => $serverResponse)->getStatusCode();
    }

    /**
     * A JSON-RPC error response as laravel/mcp's HTTP transport sends it.
     *
     * @param int $code
     * @param int $status
     *
     * @return JsonResponse
     */
    private function jsonRpcError(int $code, int $status): JsonResponse
    {
        return new JsonResponse(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => $code, 'message' => 'x']], $status);
    }

    /**
     * Method-level errors answered in-band for 2025-era requests.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function inBandErrors(): array
    {
        return [
            'method not found (-32601, 404)' => [-32601, 404],
            'invalid params or unknown tool (-32602, 400)' => [-32602, 400],
            'internal error (-32603, 500)' => [-32603, 500],
        ];
    }

    #[Test]
    #[DataProvider('inBandErrors')]
    public function itAnswersALegacyMethodErrorWith200(int $code, int $status): void
    {
        self::assertSame(200, $this->statusAfterMiddleware(self::LEGACY_REQUEST, $this->jsonRpcError($code, $status)));
    }

    /**
     * Errors the 2025 transport spec (or the request's own malformation) keeps at 400.
     *
     * @return array<string, array{0: int}>
     */
    public static function requestLevelErrors(): array
    {
        return [
            'parse error (-32700)' => [-32700],
            'invalid request (-32600)' => [-32600],
            'header mismatch (-32020)' => [-32020],
            'unsupported protocol version (-32022)' => [-32022],
        ];
    }

    #[Test]
    #[DataProvider('requestLevelErrors')]
    public function itKeepsTheStatusOfARequestLevelError(int $code): void
    {
        self::assertSame(400, $this->statusAfterMiddleware(self::LEGACY_REQUEST, $this->jsonRpcError($code, 400)));
    }

    #[Test]
    public function itKeepsTheStatusOfAnUnparseableRequest(): void
    {
        self::assertSame(400, $this->statusAfterMiddleware('{"jsonrpc": "2.0", ', $this->jsonRpcError(-32700, 400)));
    }

    #[Test]
    public function itAnswersAMalformedParamsErrorOnALegacyRequestWith200(): void
    {
        // laravel/mcp reports non-object params as -32602, which is in-band like any other invalid
        // params; pinned so the choice stays deliberate.
        $request = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [1]];

        self::assertSame(200, $this->statusAfterMiddleware($request, $this->jsonRpcError(-32602, 400)));
    }

    /**
     * 2026-07-28 envelopes, by either key laravel/mcp recognises.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function modernEnvelopes(): array
    {
        return [
            'protocolVersion' => [['io.modelcontextprotocol/protocolVersion' => '2026-07-28']],
            'clientCapabilities only' => [['io.modelcontextprotocol/clientCapabilities' => []]],
        ];
    }

    /**
     * @param array<string, mixed> $meta
     */
    #[Test]
    #[DataProvider('modernEnvelopes')]
    public function itKeepsThe2026EraStatus(array $meta): void
    {
        $request = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'x', '_meta' => $meta]];

        self::assertSame(500, $this->statusAfterMiddleware($request, $this->jsonRpcError(-32603, 500)));
        self::assertSame(404, $this->statusAfterMiddleware($request, $this->jsonRpcError(-32601, 404)));
    }

    #[Test]
    public function itLeavesResponsesThatAreNotJsonRpcErrorsAlone(): void
    {
        self::assertSame(405, $this->statusAfterMiddleware(self::LEGACY_REQUEST, new Response('', 405)));
        self::assertSame(404, $this->statusAfterMiddleware(self::LEGACY_REQUEST, new JsonResponse(['message' => 'Request not found'], 404)));
        self::assertSame(200, $this->statusAfterMiddleware(self::LEGACY_REQUEST, new JsonResponse(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])));
    }

}
