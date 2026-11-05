<?php

namespace Database\Seeders;

use App\Models\ActivityNotification;
use App\Models\Adoption;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Monitoring;
use App\Models\Offer;
use App\Models\Partner;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\TerminRule;
use App\Models\Tree;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CompleteDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('CompleteDemoSeeder hanya untuk local/testing.');
        }

        Storage::disk('local')->put('demo/complete/legal.txt', 'DOKUMEN SIMULASI TREEFUND. Bukan legalitas atau bukti transfer asli.');
        Storage::disk('local')->put('demo/complete/tree.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500"><rect width="800" height="500" fill="#e4ede2"/><path d="M400 400V190" stroke="#77583c" stroke-width="25"/><ellipse cx="400" cy="180" rx="125" ry="140" fill="#2d6a4f"/><text x="400" y="470" text-anchor="middle" font-family="sans-serif" font-size="24">TreeFund — Dokumentasi simulasi</text></svg>');

        DB::transaction(function (): void {
            $this->call(TerminRuleSeeder::class);
            $admin = $this->account('admin', 'Rian Pratama', 'admin', true);
            $this->account('verifikator', 'Dewi Lestari', 'admin');
            $donors = collect(['Budi Santoso', 'Siti Rahmawati', 'Andi Wijaya', 'Nadia Putri', 'Komunitas Hijau', 'PT Bumi Lestari'])->map(fn (string $name, int $index): User => $this->account($index === 0 ? 'donatur' : 'donatur'.($index + 1), $name, 'donor'));
            $partners = collect();
            foreach (['approved', 'approved', 'approved', 'pending', 'revision', 'rejected'] as $index => $status) {
                $user = $this->account($index === 0 ? 'mitra' : 'mitra'.($index + 1), ['Joko Susilo', 'Agus Wibowo', 'Wayan Arga', 'Sri Wahyuni', 'Dedi Kurnia', 'Hendra Saputra'][$index], 'partner');
                $partner = Partner::firstOrCreate(['user_id' => $user->id], ['group_name' => ['KTH Ijen Makmur', 'KTH Lereng Lestari', 'KTH Citarum Hijau', 'KTH Wonosari', 'KTH Tunas Harapan', 'KTH Bumi Asri'][$index], 'address' => 'Alamat kelompok simulasi Indonesia', 'bank_name' => 'Bank Simulasi', 'bank_account' => '000000000'.($index + 1), 'account_holder' => $user->name.' (Simulasi)', 'document' => 'demo/complete/legal.txt', 'status' => $status, 'reviewed_by' => $status === 'pending' ? null : $admin->id, 'review_note' => in_array($status, ['revision', 'rejected']) ? 'Simulasi: dokumen legalitas perlu diperbaiki.' : null]);
                $partners->push($partner);
                foreach (['Ketua', 'Sekretaris', 'Bendahara', 'Koordinator Lapangan'] as $member => $position) {
                    Member::firstOrCreate(['partner_id' => $partner->id, 'name' => $member === 0 ? $user->name : 'Anggota Demo '.($index + 1).'-'.$member], ['position' => $position, 'phone' => null]);
                }
            }

            $offers = collect();
            $locations = [['Ijen', 'Banyuwangi, Jawa Timur', -8.128492, 114.28391], ['Lereng Arjuno', 'Malang, Jawa Timur', -7.9142, 112.6282], ['Citarum', 'Bandung, Jawa Barat', -6.979, 107.61]];
            foreach (['Kopi Robusta', 'Alpukat', 'Aren', 'Kemiri', 'Durian', 'Mangga', 'Jati', 'Mahoni', 'Sengon', 'Nangka', 'Petai', 'Jengkol'] as $index => $species) {
                [$region, $location, $lat, $lng] = $locations[$index % 3];
                $plot = Plot::firstOrCreate(['name' => 'Demo Lengkap — '.$region.' '.($index + 1)], ['partner_id' => $partners[$index % 3]->id, 'location' => $location, 'latitude' => $lat + intdiv($index, 3) * 0.002, 'longitude' => $lng, 'capacity' => 100 + $index * 10, 'proposed_species' => $species, 'status' => 'approved', 'reviewed_by' => $admin->id]);
                $offers->push(Offer::firstOrCreate(['plot_id' => $plot->id, 'species' => $species], ['description' => 'Program adopsi '.$species.' bersama kelompok tani '.$region.'. Mencakup bibit, penanaman, perawatan, dan laporan berkala. Data simulasi untuk demonstrasi.', 'price' => 65000 + $index * 10000, 'photo' => 'assets/photos/'.['coffee', 'avocado', 'forest', 'seedling'][$index % 4].'.jpg', 'active' => $index !== 11]));
            }
            foreach (['pending', 'revision', 'rejected'] as $index => $status) {
                Plot::firstOrCreate(['name' => 'Demo Lengkap — Pengajuan '.$status], ['partner_id' => $partners[0]->id, 'location' => 'Banyuwangi, Jawa Timur', 'latitude' => -8.14, 'longitude' => 114.29, 'capacity' => 80, 'proposed_species' => 'Alpukat', 'status' => $status, 'reviewed_by' => $status === 'pending' ? null : $admin->id, 'review_note' => $status === 'pending' ? null : 'Simulasi: perbaiki batas lahan dan dokumen pendukung.']);
            }

            for ($index = 0; $index < 36; $index++) {
                $reference = 'TF-DEMO-LENGKAP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                if (Adoption::where('reference', $reference)->exists()) {
                    continue;
                }
                $offer = $offers[$index % 11];
                $donor = $donors[$index % $donors->count()];
                $quantity = 2 + $index % 4;
                $status = ['paid', 'paid', 'paid', 'paid', 'pending', 'expired', 'failed', 'review', 'paid'][$index % 9];
                $date = now()->subDays(260 - $index * 3);
                $order = Adoption::create(['user_id' => $donor->id, 'reference' => $reference, 'certificate_name' => $donor->name, 'total' => $offer->price * $quantity, 'status' => $status, 'is_demo' => true, 'expires_at' => $status === 'pending' ? now()->addHour() : $date->copy()->addHour(), 'paid_at' => $status === 'paid' ? $date : null, 'provider_status' => match ($status) {
                    'paid' => 'settlement', 'failed' => 'deny', 'expired' => 'expire', 'review' => 'settlement', default => 'pending'
                }, 'transaction_id' => 'DEMO-ONLY-'.$reference, 'created_at' => $date, 'updated_at' => $date]);
                $item = $order->items()->create(['offer_id' => $offer->id, 'quantity' => $quantity, 'unit_price' => $offer->price]);
                if ($status === 'pending') {
                    $offer->plot->increment('reserved', $quantity);
                }
                if ($status === 'paid') {
                    $offer->plot->increment('confirmed', $quantity);
                    Certificate::create(['adoption_id' => $order->id, 'number' => 'CERT-'.$reference, 'created_at' => $date]);
                    $work = WorkOrder::create(['adoption_item_id' => $item->id, 'partner_id' => $offer->plot->partner_id, 'allocated_amount' => intdiv($order->total * 80, 100), 'status' => 'waiting', 'created_at' => $date]);
                    $stage = intdiv($index, 9) + ($index % 9 === 8 ? 1 : 0);
                    for ($number = 1; $number <= $quantity; $number++) {
                        $tree = Tree::create(['adoption_item_id' => $item->id, 'code' => $reference.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)]);
                        if ($stage === 0) {
                            continue;
                        }
                        $plantStatus = $stage === 1 ? ($number % 2 === 0 ? 'revision' : 'pending') : 'approved';
                        Planting::create(['tree_id' => $tree->id, 'planted_at' => $date->copy()->addDays(3), 'latitude' => (float) $offer->plot->latitude + $number * 0.0001, 'longitude' => $offer->plot->longitude, 'photo' => 'demo/complete/tree.svg', 'status' => $plantStatus, 'reviewed_by' => $plantStatus === 'approved' ? $admin->id : null, 'review_note' => $plantStatus === 'revision' ? 'Simulasi: foto perlu menampilkan label pohon.' : null]);
                        if ($stage >= 3) {
                            foreach ([90, 120] as $round => $days) {
                                $observed = $date->copy()->addDays($days);
                                if ($observed->isFuture()) {
                                    continue;
                                }
                                $reportStatus = $round === 0 ? 'approved' : ['approved', 'pending', 'revision'][$number % 3];
                                Monitoring::create(['tree_id' => $tree->id, 'observed_at' => $observed, 'height' => 65 + $number * 8 + $round * 20, 'health' => ['healthy', 'care', 'dead'][$number % 3], 'care_note' => 'Simulasi: penyiraman, pemupukan organik, dan pemeriksaan daun.', 'photo' => 'demo/complete/tree.svg', 'status' => $reportStatus, 'reviewed_by' => $reportStatus === 'approved' ? $admin->id : null, 'review_note' => $reportStatus === 'revision' ? 'Simulasi: lengkapi detail tindakan perawatan.' : null]);
                            }
                        }
                    }
                    foreach (TerminRule::whereIn('milestone', $stage >= 3 ? ['paid', 'planted', 'monitored'] : ($stage >= 2 ? ['paid', 'planted'] : ['paid']))->get() as $rule) {
                        $fundStatus = ['pending', 'approved', 'paid', 'revision'][$index % 4];
                        Disbursement::create(['work_order_id' => $work->id, 'termin_rule_id' => $rule->id, 'amount' => intdiv($work->allocated_amount * $rule->percentage, 100), 'status' => $fundStatus, 'note' => 'SIMULASI pencairan, bukan transfer bank asli.', 'approved_by' => in_array($fundStatus, ['approved', 'paid']) ? $admin->id : null, 'recorded_by' => $fundStatus === 'paid' ? $admin->id : null, 'transferred_at' => $fundStatus === 'paid' ? today()->subDays(3) : null, 'proof' => $fundStatus === 'paid' ? 'demo/complete/legal.txt' : null]);
                    }
                }
                ActivityNotification::create(['user_id' => $donor->id, 'title' => '[Demo] Pembaruan adopsi '.$reference, 'body' => 'Status simulasi: '.$status.'. Buka rincian adopsi untuk melihat perkembangan.', 'url' => '/orders/'.$order->id, 'read_at' => $index % 2 === 0 ? now() : null, 'created_at' => $date]);
                AuditLog::create(['actor_id' => $admin->id, 'action' => 'demo.seeded', 'subject_type' => Adoption::class, 'subject_id' => $order->id, 'details' => ['simulation' => true, 'status' => $status], 'created_at' => $date]);
            }
            foreach ($partners as $partner) {
                ActivityNotification::firstOrCreate(['user_id' => $partner->user_id, 'title' => '[Demo] Status kelompok '.$partner->group_name], ['body' => 'Status pengajuan kelompok simulasi: '.$partner->status.'.', 'url' => '/dashboard']);
            }
            ActivityNotification::firstOrCreate(['user_id' => $admin->id, 'title' => '[Demo] Antrean verifikasi siap ditinjau'], ['body' => 'Tinjau pengajuan mitra, lahan, bukti tanam, monitoring, dan pencairan simulasi.', 'url' => '/dashboard']);
        });
    }

    private function account(string $alias, string $name, string $role, bool $finance = false): User
    {
        return User::firstOrCreate(['email' => $alias.'@treefund.test'], ['name' => $name, 'password' => Hash::make('TreeFund!2026'), 'role' => $role, 'can_manage_finance' => $finance, 'email_verified_at' => now()]);
    }
}
