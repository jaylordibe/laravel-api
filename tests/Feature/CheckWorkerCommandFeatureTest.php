<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `app:check-worker` is the worker container's healthcheck. Its one job is to judge
 * THIS host's Horizon master, not the cluster's: the case that matters is a wedged
 * replica sitting beside healthy ones, which `horizon:status` reports as healthy.
 *
 * The master repository is mocked rather than backed by Redis, so each test states
 * exactly which masters are live; the host's basename is pinned through Horizon's
 * own name resolver.
 */
class CheckWorkerCommandFeatureTest extends TestCase
{

    private const string HOST = 'worker-a';

    protected function setUp(): void
    {
        parent::setUp();

        MasterSupervisor::determineNameUsing(fn (): string => self::HOST);
    }

    protected function tearDown(): void
    {
        // A static resolver would otherwise leak into every later test.
        MasterSupervisor::$nameResolver = null;

        parent::tearDown();
    }

    #[Test]
    public function itPassesWhenThisHostsMasterIsRunning(): void
    {
        $this->givenLiveMasters(['worker-a-AbCd' => 'running']);

        self::assertSame(0, Artisan::call('app:check-worker'));
    }

    #[Test]
    public function itPassesWhenThisHostsMasterIsPaused(): void
    {
        // Pausing is an operator action; restarting the container would undo it.
        $this->givenLiveMasters(['worker-a-AbCd' => 'paused']);

        self::assertSame(0, Artisan::call('app:check-worker'));
    }

    #[Test]
    public function itFailsWhenOnlyAnotherHostsMasterIsAlive(): void
    {
        // The reason this command exists: `horizon:status` passes here.
        $this->givenLiveMasters(['worker-b-WxYz' => 'running']);

        self::assertSame(1, Artisan::call('app:check-worker'));
        self::assertStringContainsString('No live Horizon master for this host', Artisan::output());
    }

    #[Test]
    public function itDoesNotMistakeAHostWhoseNameMerelyStartsTheSame(): void
    {
        // `worker-a` must not claim `worker-ab`'s master; the separator is part of the match.
        $this->givenLiveMasters(['worker-ab-WxYz' => 'running']);

        self::assertSame(1, Artisan::call('app:check-worker'));
    }

    #[Test]
    public function itDoesNotMistakeAHostWhoseNameExtendsThisOneWithAHyphen(): void
    {
        // Host `worker-a-b` must not pass for host `worker-a`: a prefix match on
        // `worker-a-` would accept it.
        $this->givenLiveMasters(['worker-a-b-WxYz' => 'running']);

        self::assertSame(1, Artisan::call('app:check-worker'));
    }

    #[Test]
    public function itFailsWhenThisHostsMasterIsUnhealthyWhileAnotherHostsIsRunning(): void
    {
        // The wedged replica beside a healthy one: only this host's master counts.
        $this->givenLiveMasters([
            'worker-a-AbCd' => 'terminating',
            'worker-b-WxYz' => 'running',
        ]);

        self::assertSame(1, Artisan::call('app:check-worker'));
        self::assertStringContainsString('not running or paused', Artisan::output());
    }

    #[Test]
    public function itFailsWhenNoMasterIsAlive(): void
    {
        $this->givenLiveMasters([]);

        self::assertSame(1, Artisan::call('app:check-worker'));
    }

    #[Test]
    public function itFailsWhenThisHostsMasterIsInAnUnhealthyState(): void
    {
        $this->givenLiveMasters(['worker-a-AbCd' => 'terminating']);

        self::assertSame(1, Artisan::call('app:check-worker'));
        self::assertStringContainsString('not running or paused', Artisan::output());
    }

    /**
     * Bind a repository whose live masters are exactly the ones given.
     *
     * @param array<string, string> $statusesByName
     *
     * @return void
     */
    private function givenLiveMasters(array $statusesByName): void
    {
        $this->mock(MasterSupervisorRepository::class, function (MockInterface $mock) use ($statusesByName): void {
            $mock->shouldReceive('names')->andReturn(array_keys($statusesByName));
            $mock->shouldReceive('get')->andReturnUsing(fn (array $names): array => array_map(
                fn (string $name): object => (object) ['name' => $name, 'status' => $statusesByName[$name]],
                $names
            ));
        });
    }

}
