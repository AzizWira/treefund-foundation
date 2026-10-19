<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Plot;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home(): mixed
    {
        $featuredOffers = Offer::with('plot.partner')->where('active', true)
            ->whereHas('plot', fn ($query) => $query->where('status', 'approved')->whereHas('partner', fn ($partner) => $partner->where('status', 'approved')))
            ->latest('id')->limit(3)->get();

        return view('landing', ['featuredOffers' => $featuredOffers, 'publicStats' => ['planted' => 0, 'reports' => 0]]);
    }

    public function information(string $topic): mixed
    {
        $pages = [
            'tentang' => ['Tentang TreeFund', 'Mempertemukan kepedulian dengan aksi nyata.', 'TreeFund menghubungkan donatur dan kelompok tani melalui adopsi pohon agroforestri. Setiap adopsi terhubung dengan identitas pohon, penugasan penanaman, serta laporan lapangan yang diperiksa oleh Admin.'],
            'cara-kerja' => ['Cara Kerja', 'Dari satu adopsi hingga pohon bertumbuh.', 'Pilih bibit dan lahan yang telah terverifikasi, selesaikan pembayaran, lalu ikuti penanaman dan perkembangan pohon melalui portofolio akun Anda.'],
            'privasi' => ['Privasi Data', 'Data Anda dijaga sesuai kebutuhan akses.', 'Dokumen legalitas, rekening, transaksi, dan foto laporan dibatasi menurut peran serta kepemilikan. Peta publik menampilkan lokasi lahan yang dipublikasikan, tanpa nama donatur, kode pohon pribadi, atau dokumen kelompok.'],
            'ketentuan' => ['Ketentuan Penggunaan', 'Adopsi yang transparan dan bertanggung jawab.', 'TreeFund saat ini digunakan untuk pengembangan akademik dengan pembayaran Midtrans Sandbox. E-sertifikat merupakan bukti adopsi, bukan sertifikasi kredit karbon. Perkembangan pohon mengikuti bukti lapangan yang diverifikasi.'],
            'transparansi' => ['Transparansi Pemantauan', 'Setiap perkembangan memiliki bukti.', 'Mitra mengunggah foto, tanggal, dan koordinat penanaman, kemudian laporan perkembangan berkala. Admin memeriksa laporan sebelum informasi tampil pada portofolio Donatur. Pencairan dana dilakukan bertahap berdasarkan milestone dan bukti transfer.'],
        ];
        abort_unless(isset($pages[$topic]), 404);
        [$title, $heading, $body] = $pages[$topic];

        return view('information', compact('title', 'heading', 'body', 'topic'));
    }

    public function map(): mixed
    {
        return view('public-map');
    }

    public function mapData(Request $request): mixed
    {
        $plots = Plot::with('partner', 'offers')->where('status', 'approved')
            ->whereHas('partner', fn ($query) => $query->where('status', 'approved'))
            ->whereHas('offers', fn ($query) => $query->where('active', true))->get();

        return response()->json($plots->map(fn ($plot) => [
            'name' => $plot->name, 'location' => $plot->location,
            'group' => $plot->partner->group_name,
            'latitude' => (float) $plot->latitude, 'longitude' => (float) $plot->longitude,
            'species' => $plot->offers->where('active', true)->pluck('species')->values(),
            'available' => $plot->available,
        ]));
    }
}
