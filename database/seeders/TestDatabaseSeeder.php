<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

/**
 * Test database seed: the app's seed data plus the Passport personal access client sign-in needs.
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
