<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

/**
 * Seeds each test database once, when RefreshDatabase migrates it (see Tests\TestCase).
 *
 * The application's own seed data, plus the Passport personal access client that sign-in needs to issue
 * a token. Outside tests that client is created by `passport:client --personal` (start.sh).
 */
class TestDatabaseSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @param ClientRepository $clientRepository
     *
     * @return void
     */
    public function run(ClientRepository $clientRepository): void
    {
        $this->call(DatabaseSeeder::class);

        $clientRepository->createPersonalAccessGrantClient('API Personal Access Client', 'users');
    }

}
