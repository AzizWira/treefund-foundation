<?php

namespace Database\Seeders;

use App\Models\Adoption;
use App\Models\Member;
use App\Models\Monitoring;
use App\Models\Offer;
use App\Models\Partner;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\User;
use App\Services\AdoptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DemoSeeder hanya untuk local/testing.');
        }
        $this->call(TerminRuleSeeder::class);
        $admin = User::firstOrCreate(['email' => 'admin@treefund.test'], ['name' => 'Rian Pratama', 'password' => Hash::make('TreeFund!2026')]);
        $admin->forceFill(['role' => 'admin', 'can_manage_finance' => true, 'email_verified_at' => now()])->save();
        $donor = User::firstOrCreate(['email' => 'donatur@treefund.test'], ['name' => 'Budi Santoso', 'password' => Hash::make('TreeFund!2026')]);
        $donor->forceFill(['role' => 'donor', 'email_verified_at' => now()])->save();
        $farmer = User::firstOrCreate(['email' => 'mitra@treefund.test'], ['name' => 'Joko Susilo', 'password' => Hash::make('TreeFund!2026')]);
        $farmer->forceFill(['role' => 'partner', 'email_verified_at' => now()])->save();
        $partner = Partner::firstOrCreate(['user_id' => $farmer->id], ['group_name' => 'KTH Ijen Makmur', 'address' => 'Desa Tamansari, Banyuwangi, Jawa Timur', 'bank_name' => 'Bank Contoh', 'bank_account' => '0000000000', 'account_holder' => 'KTH Ijen Makmur (Simulasi)', 'document' => 'demo/legal.txt', 'status' => 'approved', 'reviewed_by' => $admin->id]);
        Storage::disk('local')->put('demo/legal.txt', 'DATA DEMONSTRASI TREEFUND. Bukan dokumen legalitas asli.');
        foreach ([['Joko Susilo', 'Ketua Kelompok'], ['Slamet Riyadi', 'Koordinator Penanaman'], ['Wayan Arga', 'Pemeliharaan Bibit']] as [$name,$position]) {
            Member::firstOrCreate(['partner_id' => $partner->id, 'name' => $name], ['position' => $position, 'phone' => '']);
        }
        $plots = [['Klaster Agroforestri Ijen', 'Banyuwangi, Jawa Timur', -8.128492, 114.283910, 'Kopi Robusta', 75000, 'coffee'], ['Lereng Hijau Malang', 'Malang, Jawa Timur', -7.9142, 112.6282, 'Alpukat', 125000, 'avocado'], ['Hutan Desa Bondowoso', 'Bondowoso, Jawa Timur', -7.949, 113.99, 'Aren', 95000, 'forest'], ['Petak Konservasi Citarum', 'Bandung, Jawa Barat', -6.979, 107.61, 'Kemiri', 65000, 'seedling']];
        foreach ($plots as [$name,$location,$lat,$lng,$species,$price,$photo]) {
            $plot = Plot::firstOrCreate(['name' => $name], ['partner_id' => $partner->id, 'location' => $location, 'latitude' => $lat, 'longitude' => $lng, 'capacity' => 120, 'proposed_species' => $species, 'status' => 'approved', 'reviewed_by' => $admin->id]);
            Offer::firstOrCreate(['plot_id' => $plot->id, 'species' => $species], ['description' => 'Dukung pemulihan lahan melalui agroforestri produktif bersama kelompok tani setempat. Setiap adopsi dilengkapi identitas pohon, bukti tanam, serta monitoring terverifikasi.', 'photo' => 'assets/photos/'.$photo.'.jpg', 'price' => $price, 'active' => true]);
        }
        if (! Adoption::where('user_id', $donor->id)->where('is_demo', true)->exists()) {
            $order = app(AdoptionService::class)->checkout($donor, Offer::first(), 3, 'Budi Santoso');
            $order->update(['is_demo' => true]);
            app(AdoptionService::class)->applyProviderStatus($order, ['transaction_status' => 'settlement', 'transaction_id' => 'DEMO-NOT-MIDTRANS']);
            $trees = $order->items()->first()->trees;
            foreach ($trees as $i => $tree) {
                $planting = Planting::create(['tree_id' => $tree->id, 'planted_at' => today()->subMonths(4), 'latitude' => -8.128492 + $i * 0.0001, 'longitude' => 114.28391 + $i * 0.0001, 'photo' => 'demo/tree.svg', 'status' => $i === 2 ? 'pending' : 'approved', 'reviewed_by' => $i === 2 ? null : $admin->id]);
                if ($i < 2) {
                    Monitoring::create(['tree_id' => $tree->id, 'observed_at' => today()->subDays(7), 'height' => 40 + $i * 5, 'health' => 'healthy', 'care_note' => 'Daun sehat, pemupukan organik dan penyiraman berkala. Data demonstrasi.', 'photo' => 'demo/tree.svg', 'status' => $i === 0 ? 'approved' : 'pending', 'reviewed_by' => $i === 0 ? $admin->id : null]);
                }
            }
            Storage::disk('local')->put('demo/tree.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400"><rect width="640" height="400" fill="#e4ede2"/><path d="M320 320V170m0 50q-150-100-70-140 90 0 70 140m0-20q150-100 70-140-90 0-70 140" fill="#2d6a4f" stroke="#163e2b" stroke-width="14"/><text x="320" y="375" text-anchor="middle" fill="#163e2b" font-family="sans-serif" font-size="20">Dokumentasi demonstrasi TreeFund</text></svg>');
        }
        if (! Plot::where('status', 'pending')->exists()) {
            Plot::create(['partner_id' => $partner->id, 'name' => 'Pengajuan Lahan Wonosari', 'location' => 'Malang, Jawa Timur', 'latitude' => -8.02, 'longitude' => 112.49, 'capacity' => 80, 'proposed_species' => 'Alpukat', 'status' => 'pending']);
        }
    }
}
