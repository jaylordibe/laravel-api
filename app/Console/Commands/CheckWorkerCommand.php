<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;

/**
 * The worker container's healthcheck: is THIS container's Horizon master alive?
 *
 * `horizon:status` cannot answer that. It reports on every master that has sent a
 * heartbeat in the last 14 seconds, across all hosts, so with several worker
 * replicas a wedged one reports healthy for as long as any other replica is up —
 * and the platform never replaces it.
 *
 * Horizon names each master after the host it runs on (`MasterSupervisor::basename()`,
 * the slugged hostname, plus a random suffix), and a container's hostname is its own.
 * So this keeps only the live masters whose name carries this host's basename. A
 * master stops being listed 14 seconds after its last heartbeat, which is what
 * catches one that is still a running process but no longer doing its loop.
 *
 * A PAUSED master is healthy on purpose: pausing is an operator action, and a
 * healthcheck that restarted the container would silently undo it.
 */
class CheckWorkerCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exit 0 when this host\'s Horizon master is alive (running or paused), 1 otherwise.';

    /**
     * Master statuses that mean the worker is doing what it was told to.
     *
     * @var array<int, string>
     */
    private const array HEALTHY_STATUSES = ['running', 'paused'];

    /**
     * Execute the console command.
     *
     * @param MasterSupervisorRepository $masters
     *
     * @return int
     */
    public function handle(MasterSupervisorRepository $masters): int
    {
        $basename = MasterSupervisor::basename();

        // The whole name, not a prefix: Horizon appends exactly `-` and four random
        // alphanumerics (MasterSupervisor::name()), so a prefix match would let host
        // `worker` claim the master of a host named `worker-2`.
        $ownName = '/^' . preg_quote($basename, '/') . '-[A-Za-z0-9]{4}$/';

        $ownNames = array_values(array_filter(
            $masters->names(),
            fn (string $name): bool => preg_match($ownName, $name) === 1
        ));

        if (empty($ownNames)) {
            $this->error("No live Horizon master for this host ({$basename}-*).");

            return self::FAILURE;
        }

        foreach ($masters->get($ownNames) as $master) {
            if (in_array($master->status ?? null, self::HEALTHY_STATUSES, true)) {
                $this->info("Horizon master {$master->name} is {$master->status}.");

                return self::SUCCESS;
            }
        }

        $this->error("This host's Horizon master is not running or paused.");

        return self::FAILURE;
    }

}
