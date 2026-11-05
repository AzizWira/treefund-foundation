<?php

namespace Database\Seeders;

use App\Models\TerminRule;
use Illuminate\Database\Seeder;

class TerminRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Termin 1', 30, 'paid'], ['Termin 2', 40, 'planted'], ['Termin 3', 30, 'monitored']] as [$name,$percent,$milestone]) {
            TerminRule::firstOrCreate(['name' => $name], ['percentage' => $percent, 'milestone' => $milestone]);
        }
    }
}
