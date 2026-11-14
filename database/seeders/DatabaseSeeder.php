<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(app()->environment(['local', 'testing']) ? CompleteDemoSeeder::class : TerminRuleSeeder::class);
    }
}
