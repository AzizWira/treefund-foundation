<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use App\Services\AdoptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MySqlUpgradeTest extends TestCase
{
    public function test_fresh_install_and_upgrade_keep_data_in_isolated_mysql(): void
    {
        $baseline = getenv('TREEFUND_MYSQL_BASELINE_MIGRATIONS') ?: '';
        $upgrade = getenv('TREEFUND_MYSQL_UPGRADE_DB') ?: '';
        $fresh = getenv('TREEFUND_MYSQL_FRESH_DB') ?: '';
        if (! $baseline || ! $upgrade || ! $fresh) {
            $this->markTestSkipped('Provide baseline migrations and two empty isolated MySQL databases.');
        }
        $this->assertDirectoryExists($baseline);
        $this->assertNotSame($upgrade, $fresh);

        $this->connectToEmptyDatabase($upgrade);
        $this->artisan('migrate', ['--path' => [$baseline], '--realpath' => true])->assertSuccessful();
        $this->seed();
        $donor = User::where('role', 'donor')->firstOrFail();
        $offer = Offer::published()->firstOrFail();
        $this->assertFalse(Schema::hasTable('adoptions'));
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame($donor->email, $donor->fresh()->email);
        $this->assertEquals($offer->price, $offer->fresh()->price);
        $order = app(AdoptionService::class)->checkout($donor, $offer, 1, 'Donatur Uji Migrasi');
        $this->assertDatabaseHas('adoptions', ['id' => $order->id, 'user_id' => $donor->id]);

        $this->connectToEmptyDatabase($fresh);
        $this->artisan('migrate')->assertSuccessful();
        $this->assertDatabaseCount('termin_rules', 3);
        $this->assertTrue(Schema::hasTable('disbursements'));
        $this->assertTrue(Schema::hasTable('monitorings'));
    }

    private function connectToEmptyDatabase(string $database): void
    {
        $this->assertMatchesRegularExpression('/^treefund_test_[a-z0-9_]+$/', $database);
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => getenv('TREEFUND_MYSQL_RACE_HOST') ?: '127.0.0.1',
            'database.connections.mysql.port' => getenv('TREEFUND_MYSQL_RACE_PORT') ?: '3306',
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => getenv('TREEFUND_MYSQL_RACE_USER') ?: 'root',
            'database.connections.mysql.password' => getenv('TREEFUND_MYSQL_RACE_PASSWORD') ?: '',
        ]);
        DB::purge('mysql');
        $this->assertSame([], DB::select('SHOW TABLES'), 'The test database must be empty.');
    }
}
