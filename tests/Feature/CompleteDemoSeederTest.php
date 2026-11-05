<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Monitoring;
use App\Models\Plot;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\CompleteDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompleteDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_demo_is_consistent_repeatable_and_has_no_outbound_calls(): void
    {
        Storage::fake('local');
        Mail::fake();
        Http::preventStrayRequests();
        $this->seed(CompleteDemoSeeder::class);
        $this->assertSame(14, User::count());
        $this->assertSame(36, Adoption::count());
        $this->assertSame(20, Adoption::where('status', 'paid')->count());
        $this->assertGreaterThan(0, Monitoring::count());
        foreach (Plot::all() as $plot) {
            $items = DB::table('adoption_items')->join('adoptions', 'adoptions.id', '=', 'adoption_items.adoption_id')->join('offers', 'offers.id', '=', 'adoption_items.offer_id')->where('offers.plot_id', $plot->id);
            $this->assertSame((int) (clone $items)->where('adoptions.status', 'paid')->sum('quantity'), $plot->confirmed);
            $this->assertSame((int) (clone $items)->where('adoptions.status', 'pending')->sum('quantity'), $plot->reserved);
            $this->assertGreaterThanOrEqual(0, $plot->available);
        }
        foreach (WorkOrder::with('item.trees.planting', 'disbursements')->get() as $work) {
            $this->assertSame($work->item->quantity, $work->item->trees->count());
            $this->assertLessThanOrEqual($work->item->adoption->total, $work->allocated_amount);
            $this->assertLessThanOrEqual($work->allocated_amount, $work->disbursements->sum('amount'));
        }
        $tables = ['users', 'partners', 'members', 'plots', 'offers', 'adoptions', 'trees', 'plantings', 'monitorings', 'certificates', 'disbursements', 'activity_notifications', 'audit_logs'];
        $counts = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);
        $this->seed(CompleteDemoSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
    }
}
