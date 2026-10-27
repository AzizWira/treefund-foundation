<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlReservationRaceTest extends TestCase
{
    public function test_two_workers_cannot_reserve_the_same_last_slot(): void
    {
        $database = getenv('TREEFUND_MYSQL_RACE_DB') ?: '';
        if ($database === '') {
            $this->markTestSkipped('Set TREEFUND_MYSQL_RACE_DB to a fresh isolated database.');
        }
        $this->assertMatchesRegularExpression('/^treefund_test_[a-z0-9_]+$/', $database);
        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_URL' => '',
            'DB_HOST' => getenv('TREEFUND_MYSQL_RACE_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('TREEFUND_MYSQL_RACE_PORT') ?: '3306',
            'DB_DATABASE' => $database,
            'DB_USERNAME' => getenv('TREEFUND_MYSQL_RACE_USER') ?: 'root',
            'DB_PASSWORD' => getenv('TREEFUND_MYSQL_RACE_PASSWORD') ?: '',
        ];
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.host' => $environment['DB_HOST'], 'database.connections.mysql.port' => $environment['DB_PORT'], 'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => $environment['DB_USERNAME'], 'database.connections.mysql.password' => $environment['DB_PASSWORD']]);
        DB::purge('mysql');
        $this->assertSame([], DB::select('SHOW TABLES'), 'Use an empty test database.');
        $this->artisan('migrate')->assertSuccessful();
        $this->seed();
        $offer = Offer::published()->firstOrFail();
        $offer->plot->update(['capacity' => 1, 'reserved' => 0, 'confirmed' => 0]);
        $donors = User::factory()->count(2)->create(['role' => 'donor']);
        $workers = $donors->map(fn (User $donor): Process => new Process([PHP_BINARY, base_path('tests/Support/reserve-quota.php'), (string) $donor->id, (string) $offer->id], base_path(), $environment, timeout: 30));
        DB::beginTransaction();
        try {
            Plot::lockForUpdate()->findOrFail($offer->plot_id);
            foreach ($workers as $worker) {
                $worker->start();
            }
            foreach ($workers as $worker) {
                $this->assertTrue($worker->waitUntil(fn ($type, $output) => $type === Process::OUT && str_contains($output, 'ready')));
            }
        } finally {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
        }
        $results = $workers->map(fn (Process $worker) => trim(str_replace('ready', '', $worker->getOutput())))->sort()->values()->all();
        $this->assertSame(['rejected', 'reserved'], $results);
        $this->assertEquals(1, $offer->plot->fresh()->reserved);
        $this->assertSame(1, Adoption::count());
    }
}
