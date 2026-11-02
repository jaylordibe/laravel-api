<?php

namespace Tests\Feature;

use App\Mcp\Servers\ApiServer;
use App\Models\User;
use App\Mcp\Tools\EchoTool;
use App\Mcp\Tools\GetServerInfoTool;
use App\Mcp\Tools\PingTool;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\SignsInMcpClients;
use Tests\TestCase;

/**
 * Covers the MCP endpoint at /api/mcp: routes/ai.php, App\Http\Middleware\EnsureMcpToken,
 * App\Mcp\Servers\ApiServer and its tools. Requests carry an access token from the MCP OAuth sign-in
 * (Tests\SignsInMcpClients); that flow, and every way the token stops working, is McpOAuthFeatureTest.
 *
 * The protocol is exercised through the full HTTP stack with real JSON-RPC, in both protocol eras:
 * the 2025 `initialize` handshake (what n8n and most clients speak today) and the 2026-07-28
 * stateless protocol (`server/discover`). Tool behaviour is also asserted through laravel/mcp's
 * own testing API. Interoperability with external clients (MCP Inspector, Claude Code) is verified
 * separately, against a running server — see docs/mcp.md.
 *
 * The rate limiter in front of the endpoint is not asserted: Tests\TestCase disables
 * ThrottleRequests for the whole suite.
 */
class McpFeatureTest extends TestCase
{

    use SignsInMcpClients;

    /**
     * The endpoint.
     *
     * @var string
     */
    private string $endpoint = '/api/mcp';

    /**
     * An MCP access token for a signed-in user, obtained per test.
     *
     * @var string
     */
    private string $n8nToken;

    /**
     * The one browser origin allowed in these tests.
     *
     * @var string
     */
    private string $allowedOrigin = 'https://allowed.example.com';

    /**
     * Sign a client in for a fresh user and allow one browser origin.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['custom.mcp.allowed_origins' => $this->allowedOrigin]);
        $this->n8nToken = $this->signInMcpClient(User::factory()->create()->email)['access_token'];
    }

    /**
     * POST a JSON-RPC message to the endpoint.
     *
     * @param array<string, mixed> $message
     * @param array<string, string> $headers
     *
     * @return TestResponse
     */
    private function rpc(array $message, array $headers = []): TestResponse
    {
        return $this->withHeaders([
            'Accept' => 'application/json, text/event-stream',
            ...$headers,
        ])->postJson($this->endpoint, $message);
    }

    /**
     * POST a JSON-RPC message as the n8n client, speaking the 2025 protocol.
     *
     * @param string $method
     * @param array<string, mixed> $params
     * @param int $id
     *
     * @return TestResponse
     */
    private function legacyCall(string $method, array $params = [], int $id = 1): TestResponse
    {
        return $this->rpc(
            ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params],
            ['Authorization' => 'Bearer ' . $this->n8nToken, 'MCP-Protocol-Version' => '2025-06-18']
        );
    }

    /**
     * The 2025 handshake request.
     *
     * @return array<string, mixed>
     */
    private function initializeMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
            ],
        ];
    }

    #[Test]
    public function initializesA2025EraSessionAndIdentifiesTheServer(): void
    {
        $response = $this->rpc($this->initializeMessage(), ['Authorization' => 'Bearer ' . $this->n8nToken]);

        $response->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.serverInfo.name', config('app.name'))
            ->assertJsonPath('result.serverInfo.version', ApiServer::VERSION);
        self::assertArrayHasKey('tools', $response->json('result.capabilities'));
    }

    #[Test]
    public function answers2026EraDiscovery(): void
    {
        $response = $this->rpc([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'server/discover',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => (object) [],
                    'io.modelcontextprotocol/clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->n8nToken,
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'server/discover',
        ]);

        $response->assertOk();
        self::assertContains('2026-07-28', $response->json('result.supportedVersions'));
        self::assertArrayHasKey('tools', $response->json('result.capabilities'));
        self::assertSame(
            ['name' => config('app.name'), 'version' => ApiServer::VERSION],
            $response->json('result._meta')['io.modelcontextprotocol/serverInfo']
        );
    }

    #[Test]
    public function callsAToolOverThe2026EraProtocol(): void
    {
        $response = $this->rpc([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'echo',
                'arguments' => ['message' => 'Hello MCP'],
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => (object) [],
                    'io.modelcontextprotocol/clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->n8nToken,
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'tools/call',
            'Mcp-Name' => 'echo',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.resultType', 'complete')
            ->assertJsonPath('result.structuredContent', ['success' => true, 'message' => 'Hello MCP']);
    }

    #[Test]
    public function discoversExactlyTheFourToolsInAStableOrderWithSchemas(): void
    {
        $response = $this->legacyCall('tools/list')->assertOk();

        $tools = $response->json('result.tools');
        self::assertSame(['ping', 'echo', 'get_server_info', 'get_current_user'], array_column($tools, 'name'));

        foreach ($tools as $tool) {
            self::assertNotEmpty($tool['description']);
            self::assertSame('object', $tool['inputSchema']['type']);
            self::assertSame('object', $tool['outputSchema']['type']);
            self::assertTrue($tool['annotations']['readOnlyHint']);
            self::assertTrue($tool['annotations']['idempotentHint']);
        }

        $echo = $tools[1];
        self::assertSame(['message'], $echo['inputSchema']['required']);
        self::assertSame('string', $echo['inputSchema']['properties']['message']['type']);
        self::assertSame(EchoTool::MESSAGE_MAX_LENGTH, $echo['inputSchema']['properties']['message']['maxLength']);
    }

    #[Test]
    public function pingAnswersPongOverHttp(): void
    {
        $this->legacyCall('tools/call', ['name' => 'ping', 'arguments' => (object) []])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent', ['success' => true, 'message' => 'pong']);
    }

    #[Test]
    public function echoReturnsItsMessageUnchangedOverHttp(): void
    {
        $message = "  Hello MCP — ünïcødé ✓  ";

        $this->legacyCall('tools/call', ['name' => 'echo', 'arguments' => ['message' => $message]])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent', ['success' => true, 'message' => $message]);
    }

    #[Test]
    public function getServerInfoIdentifiesTheApplicationAndNothingElse(): void
    {
        $this->legacyCall('tools/call', ['name' => 'get_server_info', 'arguments' => (object) []])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent', [
                'name' => config('app.name'),
                'mcp' => true,
                'status' => 'ready',
                'version' => ApiServer::VERSION,
            ]);
    }

    #[Test]
    public function toolsBehaveThroughTheMcpTestingApi(): void
    {
        ApiServer::tool(PingTool::class)
            ->assertOk()
            ->assertStructuredContent(['success' => true, 'message' => 'pong']);

        ApiServer::tool(EchoTool::class, ['message' => 'Hello MCP'])
            ->assertOk()
            ->assertStructuredContent(['success' => true, 'message' => 'Hello MCP']);

        ApiServer::tool(GetServerInfoTool::class)
            ->assertOk()
            ->assertStructuredContent([
                'name' => config('app.name'),
                'mcp' => true,
                'status' => 'ready',
                'version' => ApiServer::VERSION,
            ]);
    }

    /**
     * Arguments `echo` must refuse.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidEchoArguments(): array
    {
        return [
            'an empty message' => [['message' => '']],
            'a whitespace-only message' => [['message' => '   ']],
            'a message over the maximum' => [['message' => str_repeat('x', EchoTool::MESSAGE_MAX_LENGTH + 1)]],
            'a non-string message' => [['message' => 42]],
            'no message' => [[]],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[DataProvider('invalidEchoArguments')]
    public function echoRejectsInvalidArgumentsAsAToolError(array $arguments): void
    {
        $response = $this->legacyCall('tools/call', ['name' => 'echo', 'arguments' => (object) $arguments])->assertOk();

        $response->assertJsonPath('result.isError', true);
        self::assertNull($response->json('result.structuredContent'));
        self::assertNotEmpty($response->json('result.content.0.text'));
    }

    #[Test]
    public function echoIgnoresAnArgumentItsOpenSchemaAllows(): void
    {
        // The advertised inputSchema does not close the object (laravel/mcp offers no hook for
        // `additionalProperties: false`), so a schema-valid extra argument must not fail the call.
        $this->legacyCall('tools/call', ['name' => 'echo', 'arguments' => ['message' => 'hi', 'extra' => true]])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent', ['success' => true, 'message' => 'hi']);
    }

    #[Test]
    public function echoAcceptsAMessageOfExactlyTheMaximumLength(): void
    {
        $message = str_repeat('x', EchoTool::MESSAGE_MAX_LENGTH);

        ApiServer::tool(EchoTool::class, ['message' => $message])
            ->assertOk()
            ->assertStructuredContent(['success' => true, 'message' => $message]);
    }

    #[Test]
    public function answersAnUnknownToolWithAProtocolError(): void
    {
        // HTTP 200: a 2025-era client (the TS SDK v1 that n8n uses) treats any non-2xx as a transport
        // failure and never reads the JSON-RPC error inside it.
        $this->legacyCall('tools/call', ['name' => 'drop_database', 'arguments' => (object) []])
            ->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    #[Test]
    public function answersAnUnknownMethodWithMethodNotFound(): void
    {
        $this->legacyCall('tools/destroy', [], 7)
            ->assertOk()
            ->assertJsonPath('id', 7)
            ->assertJsonPath('error.code', -32601);
    }

    #[Test]
    public function keepsThe2026EraStatusForAnUnknownMethod(): void
    {
        $this->rpc([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/destroy',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => (object) [],
                    'io.modelcontextprotocol/clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->n8nToken,
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'tools/destroy',
        ])->assertNotFound()->assertJsonPath('error.code', -32601);
    }

    #[Test]
    public function treatsAnEnvelopeWithOnlyClientCapabilitiesAs2026Era(): void
    {
        // laravel/mcp's own era rule (JsonRpcRequest::isLegacy): either envelope key makes it 2026-era,
        // so its 400 for the missing protocolVersion must not be rewritten to 200.
        $this->rpc([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/list',
            'params' => ['_meta' => ['io.modelcontextprotocol/clientCapabilities' => (object) []]],
        ], [
            'Authorization' => 'Bearer ' . $this->n8nToken,
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'tools/list',
        ])->assertStatus(400)->assertJsonPath('error.code', -32602);
    }

    #[Test]
    public function keepsThe400ForAnUnsupportedLegacyProtocolVersionHeader(): void
    {
        $this->rpc(
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []],
            ['Authorization' => 'Bearer ' . $this->n8nToken, 'MCP-Protocol-Version' => '2024-11-05']
        )->assertStatus(400);
    }

    #[Test]
    public function answersMalformedJsonWithAJsonRpcParseError(): void
    {
        $response = $this->call('POST', $this->endpoint, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->n8nToken,
        ], '{"jsonrpc": "2.0", ');

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', -32700);
        self::assertStringNotContainsString('.php', $response->getContent());
    }

    #[Test]
    public function acceptsEachSignedInClientWithItsOwnToken(): void
    {
        $secondToken = $this->signInMcpClient(User::factory()->create()->email)['access_token'];

        $this->rpc($this->initializeMessage(), ['Authorization' => 'Bearer ' . $secondToken])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', config('app.name'));
    }

    #[Test]
    public function refusesARequestWithoutAToken(): void
    {
        $response = $this->rpc($this->initializeMessage())->assertUnauthorized();

        // Where to sign in (RFC 9728 §5.1): what starts a client's OAuth flow.
        self::assertSame(
            'Bearer realm="mcp", resource_metadata="' . url('/.well-known/oauth-protected-resource/api/mcp') . '"',
            $response->headers->get('WWW-Authenticate')
        );
    }

    /**
     * Authorization headers that must not authenticate.
     *
     * @return array<string, array{0: string}>
     */
    public static function invalidAuthorizationHeaders(): array
    {
        return [
            'an unknown token' => ['Bearer ' . bin2hex(random_bytes(32))],
            'a non-Bearer scheme' => ['Basic ' . base64_encode('n8n:secret')],
            'an empty bearer' => ['Bearer '],
        ];
    }

    #[Test]
    #[DataProvider('invalidAuthorizationHeaders')]
    public function refusesAnInvalidCredential(string $authorization): void
    {
        $this->rpc($this->initializeMessage(), ['Authorization' => $authorization])->assertUnauthorized();
    }

    #[Test]
    public function refusesATruncatedToken(): void
    {
        $response = $this->rpc($this->initializeMessage(), ['Authorization' => 'Bearer ' . substr($this->n8nToken, 0, -1)]);

        $response->assertUnauthorized();
        self::assertStringContainsString('resource_metadata=', (string) $response->headers->get('WWW-Authenticate'));
    }

    #[Test]
    public function refusesAPassportUserToken(): void
    {
        $userToken = $this->loginSystemAdminUser();
        $this->forgetAuthenticatedUsers();

        $this->rpc($this->initializeMessage(), ['Authorization' => 'Bearer ' . $userToken])->assertUnauthorized();
    }

    #[Test]
    public function refusesAToolCallWithoutATokenBeforeExecutingIt(): void
    {
        $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'ping', 'arguments' => (object) []]]);

        $response->assertUnauthorized();
        self::assertStringNotContainsString('pong', $response->getContent());
    }

    #[Test]
    public function refusesEveryMethodWithoutAToken(): void
    {
        $this->get($this->endpoint)->assertUnauthorized();
        $this->delete($this->endpoint)->assertUnauthorized();
    }

    #[Test]
    public function refusesABrowserOriginThatIsNotAllowedEvenWithAValidToken(): void
    {
        $this->rpc($this->initializeMessage(), [
            'Authorization' => 'Bearer ' . $this->n8nToken,
            'Origin' => 'https://evil.example.com',
        ])->assertForbidden();
    }

    #[Test]
    public function servesAnAllowedBrowserOrigin(): void
    {
        $this->rpc($this->initializeMessage(), [
            'Authorization' => 'Bearer ' . $this->n8nToken,
            'Origin' => $this->allowedOrigin,
        ])->assertOk();
    }

    #[Test]
    public function leavesTheRestApiUnaffected(): void
    {
        $this->get('/up')->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->n8nToken])
            ->getJson('/api/users/auth')
            ->assertUnauthorized();
    }

}
