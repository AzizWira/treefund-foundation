<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Partner;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['admin', 'Administrator', 'admin'], ['mitra', 'Mitra TreeFund', 'partner'], ['donatur', 'Donatur TreeFund', 'donor']] as [$email, $name, $role]) {
            $user = User::firstOrNew(['email' => $email.'@treefund.test']);
            $user->forceFill(['name' => $name, 'role' => $role, 'password' => 'TreeFund!2026', 'email_verified_at' => now(), 'can_manage_finance' => $role === 'admin'])->save();
        }
        $partner = Partner::updateOrCreate(['user_id' => User::where('role', 'partner')->firstOrFail()->id], [
            'group_name' => 'Kelompok Tani Contoh TreeFund', 'address' => 'Jawa Timur',
            'bank_name' => 'Bank Contoh', 'bank_account' => '0000000000', 'account_holder' => 'Kelompok Tani Contoh',
            'document' => 'contoh-belum-diunggah.pdf', 'status' => 'approved',
        ]);
        foreach ([
            ['Lereng Hijau Malang', 'Malang, Jawa Timur', -7.98, 112.63, 'Alpukat', 125000, 'assets/photos/seedling.jpg'],
            ['Hutan Harapan Banyuwangi', 'Banyuwangi, Jawa Timur', -8.12, 114.28, 'Durian', 150000, 'assets/photos/forest.jpg'],
            ['Wana Lestari Batu', 'Batu, Jawa Timur', -7.87, 112.52, 'Kopi', 100000, 'assets/photos/seedling.jpg'],
        ] as [$name, $location, $lat, $lng, $species, $price, $photo]) {
            $plot = Plot::updateOrCreate(['partner_id' => $partner->id, 'name' => $name], [
                'location' => $location, 'latitude' => $lat, 'longitude' => $lng, 'capacity' => 100,
                'reserved' => 0, 'confirmed' => 0, 'proposed_species' => $species, 'status' => 'approved',
            ]);
            Offer::updateOrCreate(['plot_id' => $plot->id, 'species' => $species], [
                'description' => 'Contoh penawaran '.$species.' untuk pengembangan katalog publik TreeFund.',
                'price' => $price, 'photo' => $photo, 'active' => true,
            ]);
        }
    }
}
