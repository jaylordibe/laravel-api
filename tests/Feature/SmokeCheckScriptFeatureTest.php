<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * `.github/scripts/smoke-check.sh` is what decides whether a production or staging deploy succeeded. It runs
 * here against a stub server whose base path picks the answer, so every outcome the script must reject is
 * exercised by the real script rather than restated.
 */
class SmokeCheckScriptFeatureTest extends TestCase
{

    /**
     * The stub server, shared by every test in this class.
     *
     * @var Process|null
     */
    private static ?Process $server = null;

    /**
     * The stub server's base URL.
     *
     * @var string
     */
    private static string $baseUrl = '';

    /**
     * Start the stub server once, serving the body the real readiness endpoint returns.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (self::$server !== null) {
            return;
        }

        $readyBody = $this->getJson('/api/health/ready')->assertOk()->getContent();
        $directory = sys_get_temp_dir() . '/smoke-check-' . getmypid();
        @mkdir($directory);
        file_put_contents($directory . '/ready.json', $readyBody);
        file_put_contents($directory . '/router.php', <<<'PHP'
            <?php
            $scenario = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'))[0];
            $json = fn (string $body) => header('Content-Type: application/json') ?: print($body);
            match ($scenario) {
                'ready' => $json(file_get_contents(__DIR__ . '/ready.json')),
                'unavailable' => http_response_code(503) && $json('{"success":false,"message":"One or more dependencies are unavailable.","status":"unavailable","checks":{"database":false,"redis":true}}'),
                'not-ready' => $json('{"success":true,"message":"ok","status":"starting"}'),
                'malformed' => print('<html>Welcome to nginx</html>'),
                'redirect' => http_response_code(302) && header('Location: /ready/api/health/ready'),
                'not-found' => http_response_code(404) && $json('{"message":"Not Found"}'),
                'unauthorized' => http_response_code(401) && $json('{"message":"Unauthenticated."}'),
                // Only just past the 1s client timeout: the stub serves one request at a time.
                'slow' => sleep(2) || $json(file_get_contents(__DIR__ . '/ready.json')),
                default => http_response_code(500),
            };
            PHP);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$server = new Process(['php', '-S', "127.0.0.1:{$port}", $directory . '/router.php']);
        self::$server->start();
        self::$baseUrl = "http://127.0.0.1:{$port}";

        // Wait until it accepts connections.
        $isAccepting = false;

        for ($attempt = 0; $attempt < 50 && !$isAccepting; $attempt++) {
            $isAccepting = @fsockopen('127.0.0.1', $port) !== false;

            if (!$isAccepting) {
                usleep(100_000);
            }
        }

        self::assertTrue($isAccepting, 'The stub server did not start: ' . self::$server->getErrorOutput());
    }

    /**
     * Stop the stub server.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;

        parent::tearDownAfterClass();
    }

    /**
     * Run the smoke check against a base URL, with retries kept short.
     *
     * @param string $baseUrl
     * @param array<string, string> $env
     *
     * @return Process
     */
    private function runSmokeCheck(string $baseUrl, array $env = []): Process
    {
        $process = new Process(
            [base_path('.github/scripts/smoke-check.sh'), $baseUrl],
            base_path(),
            $env + ['SMOKE_ATTEMPTS' => '2', 'SMOKE_INTERVAL_SECONDS' => '0', 'SMOKE_CONNECT_TIMEOUT_SECONDS' => '1', 'SMOKE_MAX_TIME_SECONDS' => '2']
        );
        $process->setTimeout(30);
        $process->run();

        return $process;
    }

    #[Test]
    public function aReadyDeploymentPasses(): void
    {
        $process = $this->runSmokeCheck(self::$baseUrl . '/ready');

        self::assertSame(0, $process->getExitCode(), $process->getOutput());
        self::assertStringContainsString('/ready/api/health/ready', $process->getOutput());
    }

    #[Test]
    public function aTrailingSlashOnTheBaseUrlIsAccepted(): void
    {
        self::assertSame(0, $this->runSmokeCheck(self::$baseUrl . '/ready/')->getExitCode());
    }

    public static function failingScenarios(): array
    {
        return [
            'redirect (never followed)' => ['redirect', 'HTTP 302'],
            'not found' => ['not-found', 'HTTP 404'],
            'unauthorized' => ['unauthorized', 'HTTP 401'],
            'server error' => ['server-error', 'HTTP 500'],
            'dependency unavailable' => ['unavailable', 'HTTP 503'],
            'success status but not ready' => ['not-ready', 'unexpected body'],
            'malformed body' => ['malformed', 'unexpected body'],
        ];
    }

    #[Test]
    #[DataProvider('failingScenarios')]
    public function anUnhealthyAnswerFailsTheDeploy(string $scenario, string $expectedOutput): void
    {
        $process = $this->runSmokeCheck(self::$baseUrl . '/' . $scenario);

        self::assertSame(1, $process->getExitCode(), $process->getOutput());
        self::assertStringContainsString($expectedOutput, $process->getOutput());
    }

    #[Test]
    public function aRedirectOrClientErrorFailsWithoutRetrying(): void
    {
        $process = $this->runSmokeCheck(self::$baseUrl . '/not-found', ['SMOKE_ATTEMPTS' => '30', 'SMOKE_INTERVAL_SECONDS' => '5']);

        self::assertSame(1, $process->getExitCode());
        // Thirty attempts five seconds apart would exceed the process timeout; a client error is final.
        self::assertStringNotContainsString('attempt', $process->getOutput());
    }

    #[Test]
    public function aConnectionFailureFails(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $closedPort = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $process = $this->runSmokeCheck("http://127.0.0.1:{$closedPort}");

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('HTTP 000', $process->getOutput());
    }

    #[Test]
    public function aHungServerIsCutOffByTheTotalTimeout(): void
    {
        // The stub answers "ready" after 2s, so passing would mean the 1s total timeout was not applied.
        $process = $this->runSmokeCheck(self::$baseUrl . '/slow', ['SMOKE_ATTEMPTS' => '1', 'SMOKE_MAX_TIME_SECONDS' => '1']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('HTTP 000', $process->getOutput());
    }

    #[Test]
    public function aMissingUrlFails(): void
    {
        $process = $this->runSmokeCheck('');

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('no deployment URL', $process->getOutput());
    }

    #[Test]
    public function bothDeployWorkflowsRunTheScriptAgainstTheReadinessProbeAndKeepTheirGuards(): void
    {
        foreach (['production' => 'PRODUCTION_URL', 'staging' => 'STAGING_URL'] as $environment => $variable) {
            $workflow = file_get_contents(base_path(".github/workflows/deploy-to-{$environment}.yml"));

            self::assertStringContainsString(".github/scripts/smoke-check.sh \"\${{$variable}}\"", $workflow);
            self::assertStringContainsString("if [ -z \"\${{$variable}}\" ]; then", $workflow, 'An unconfigured target must still fail the deploy.');
            self::assertStringContainsString("if: \${{ vars.DEPLOY_ENABLED == 'true' }}", $workflow, 'Deploys stay opt-in.');
            self::assertStringNotContainsString('app-versions/latest', $workflow);
        }
    }

}
