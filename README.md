# TreeFund

Project Laravel 13 berbasis desain Stitch untuk crowdfunding adopsi pohon dan pemantauan lapangan. Antarmuka mencakup Donatur, Mitra Petani, dan Admin Verifikator. Pembayaran dikunci ke **Midtrans Sandbox**; aplikasi ini tidak mengirim transaksi ke endpoint produksi.

## Mulai di Windows

Konfigurasi awal memakai MySQL XAMPP (`127.0.0.1:3306`, database `treefund`, user `root`, tanpa password). Aktifkan MySQL dan buat database `treefund` sebelum instalasi. SQLite tetap didukung bila dipilih pada `.env`.

Prasyarat: PHP 8.3+, Composer, ekstensi PHP `pdo_sqlite` untuk pengembangan SQLite atau `pdo_mysql` untuk MySQL, serta `curl`, `fileinfo`, `mbstring`, `openssl`, `dom`, dan `gd`. Node.js 22.12+ hanya diperlukan jika akan membangun ulang aset. Aset frontend hasil build dan gambar/font lokal sudah disertakan.

```powershell
cd D:\KAMPUS\treefund
.\install.ps1 -Demo
php artisan serve
```

Jika kebijakan PowerShell memblokir script, gunakan perintah manual berikut tanpa mengubah kebijakan mesin:

```powershell
composer install
# Hanya jika .env belum ada:
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DemoSeeder
php artisan storage:link
php artisan serve
```

Jangan menyalin ulang `.env.example` atau mengganti `APP_KEY` pada instalasi yang sudah berisi konfigurasi/data. `install.ps1` mempertahankan `.env` yang sudah ada, menjalankan migrasi tambahan, dan tidak menjalankan `migrate:fresh`.

Buka `http://127.0.0.1:8000`. Gunakan pengaturan database yang sesuai dengan `.env` Anda. Default seeder hanya mengisi aturan termin, bukan akun publik.

### Akun demonstrasi

Tersedia hanya setelah `DemoSeeder` dijalankan pada lingkungan `local` atau `testing`:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Admin + kewenangan keuangan | admin@treefund.test | TreeFund!2026 |
| Mitra Petani | mitra@treefund.test | TreeFund!2026 |
| Donatur | donatur@treefund.test | TreeFund!2026 |

Data demo meliputi katalog, satu transaksi simulasi, kode pohon, bukti tanam, dan log monitoring. Transaksi demo diberi penanda dan tidak berasal dari Midtrans. Jangan memakai akun demonstrasi pada deployment publik.

## Konfigurasi Midtrans Sandbox

1. Masuk ke [Dashboard Midtrans Sandbox](https://dashboard.sandbox.midtrans.com/) dan pilih lingkungan **Sandbox**.
2. Ambil Merchant ID, Client Key, dan Server Key pada menu **Settings → Access Keys**.
3. Isi `.env` di komputer sendiri. Jangan commit atau membagikan file tersebut.

```dotenv
APP_URL=http://127.0.0.1:8000
MIDTRANS_MERCHANT_ID=isi_merchant_id
MIDTRANS_SERVER_KEY=SB-Mid-server-isi_server_key
MIDTRANS_CLIENT_KEY=SB-Mid-client-isi_client_key
TREEFUND_RESERVATION_MINUTES=60
```

```powershell
php artisan config:clear
php artisan treefund:doctor
```

4. Login sebagai Donatur, buka katalog, pilih bibit dan jumlah pohon, lalu lanjutkan checkout. Klik **Bayar dengan Midtrans Sandbox** untuk membuka Snap.
5. Ikuti [panduan pengujian Sandbox Midtrans](https://docs.midtrans.com/docs/testing-payment-on-sandbox) untuk menyimulasikan metode bayar yang dipilih.
6. Gunakan **Periksa Status Pembayaran** pada halaman detail pesanan untuk mengambil status langsung dari server Midtrans. Alur ini tetap bisa digunakan ketika server lokal belum memiliki URL publik.

Server Key hanya dipakai backend. Total dihitung dari harga katalog. Token Snap disimpan pada pesanan dan digunakan kembali. Callback JavaScript di browser tidak menjadi dasar penerbitan sertifikat atau pengurangan kuota permanen.

### Webhook otomatis

Midtrans tidak dapat menghubungi alamat `127.0.0.1` di komputer Anda. Untuk menguji webhook otomatis, gunakan domain HTTPS pengembangan yang dapat diakses Midtrans, misalnya melalui tunnel yang Anda jalankan sendiri. Tidak ada layanan tunnel yang dibuka otomatis oleh project.

```dotenv
APP_URL=https://domain-dev-anda.example
```

Atur **Payment Notification URL** pada Dashboard Midtrans Sandbox menjadi:

```text
https://domain-dev-anda.example/payments/midtrans/notification
```

Jalankan `php artisan config:clear` setelah mengubah `.env`. Konfigurasikan URL finish kembali ke aplikasi jika dibutuhkan. Endpoint webhook hanya dikecualikan dari CSRF pada path yang disebutkan di atas; formulir aplikasi tetap memakai CSRF.

Webhook memvalidasi signature SHA-512, nomor pesanan, dan nominal. Status diambil ulang dari API Midtrans yang diautentikasi karena `transaction_status` tidak tercakup dalam signature. Pengiriman ulang webhook tidak menerbitkan sertifikat, pohon, work order, atau notifikasi sukses dua kali. Status berhasil tidak diturunkan oleh notifikasi kedaluwarsa yang datang belakangan.

**Belum dikonfigurasi:** tidak ada kunci Midtrans asli yang disertakan. Koneksi transaksi ke merchant Anda harus diuji setelah kedua kunci diisi. Pengujian otomatis menggunakan respons Midtrans yang dikendalikan melalui HTTP fake.

## MySQL

Buat database kosong bernama `treefund` pada MySQL, lalu ubah konfigurasi berikut:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=treefund
DB_USERNAME=root
DB_PASSWORD=isi_password_lokal
```

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DemoSeeder
```

Jangan menulis `DB_DATABASE=database/database.sqlite` pada konfigurasi MySQL. Kode kuota dan pencairan menggunakan transaksi dan penguncian baris pada MySQL. Pengujian yang dijalankan dalam pengerjaan ini menggunakan SQLite; uji konkurensi pada MySQL perlu dilakukan di lingkungan target.

## Alur fitur

| Kebutuhan | Implementasi |
| --- | --- |
| FR-01 | Registrasi Donatur/Mitra, login/logout, hak akses dan pengaturan akun |
| FR-02–03 | Pengajuan profil, legalitas, lahan, koordinat dan kuota; keputusan serta revisi Admin |
| FR-04 | Katalog publik dan pengelolaan penawaran; nonaktif tanpa menghapus riwayat |
| FR-05–07 | Reservasi kuota, checkout, Snap Sandbox, webhook dan rekonsiliasi pembayaran |
| FR-08–09 | PDF e-sertifikat, kode unik per pohon dan work order |
| FR-10–11 | Unggah bukti tanam, koordinat GPS/manual, foto dan verifikasi |
| FR-12–14 | Monitoring, revisi, riwayat laporan dan peta Leaflet/OpenStreetMap |
| FR-15 | Notifikasi dalam aplikasi dan status sudah dibaca |
| FR-16–17 | Alokasi dana oleh Admin Keuangan, milestone termin, persetujuan dan pencatatan transfer manual |
| FR-18 | Rekap kegiatan/dana dan export transaksi CSV |
| Tambahan desain | Data anggota Poktan, pengaturan akun, notifikasi dan audit aktivitas |

### Aturan yang perlu disepakati kelompok

Persentase awal **30% / 40% / 30%** merupakan konfigurasi contoh, bukan ketentuan yang ditetapkan proposal. Milestone contoh: pembayaran berhasil, seluruh bukti tanam disetujui, lalu seluruh pohon memiliki monitoring disetujui. Admin Keuangan dapat mengubah persentase sebelum ada pengajuan pencairan.

Alokasi work order diawali **Rp 0**. Admin Keuangan harus menetapkan alokasi yang disetujui melalui menu Pencairan Dana. Nilainya dibatasi penerimaan pada rincian adopsi dan dikunci setelah ada pengajuan. Transfer bank dilakukan manual oleh Admin; aplikasi hanya mencatat nominal, tanggal, serta bukti transfer, dan tidak memakai Midtrans untuk membayar mitra.

### Privasi dan penyimpanan

Dokumen legalitas, bukti tanam, monitoring, dan bukti transfer tersimpan di `storage/app/private`. Akses melalui controller yang memeriksa peran dan kepemilikan; dokumen ini tidak dilayani sebagai file publik. Foto katalog tersimpan di disk public. Upload dibatasi jenis dan ukuran berkas.

## Penjadwalan

```powershell
php artisan schedule:work
```

Atau pasang cron pada hosting untuk menjalankan `php artisan schedule:run` setiap menit. Rekonsiliasi pembayaran dijalankan tiap lima menit, dan pengingat monitoring diperiksa setiap pagi. Jika status Midtrans belum dapat dipastikan, kuota tetap ditahan dan percobaan berikutnya akan memeriksa ulang; kuota tidak dilepas hanya karena permintaan jaringan gagal.

## Pengujian dan build

```powershell
php artisan test
php vendor/laravel/pint/builds/pint --test
npm ci --ignore-scripts
npm run build
```

Tes mencakup hak akses antarperan dan antaruser, harga dari server, kuota penuh, signature dan nominal webhook, konfirmasi status server, pengiriman ulang webhook, pelepasan kuota, kerahasiaan file, verifikasi laporan, alokasi dana, pencairan, unduh PDF, serta render halaman.

## Desain dan Figma Make

Kode HTML referensi Stitch tersedia di `resources/reference`. Gambar yang bisa diambil dari tautan referensi telah dikemas lokal, dan tautan logo/avatar yang tidak tersedia diganti aset dari ZIP. Foto/gambar aplikasi tidak dibuat oleh plugin tambahan. Font dan ikon juga tersedia lokal.

Halaman mempertahankan pola sidebar hijau tua, latar terang, heading serif, kartu, tabel, serta struktur portal referensi. Copy tentang satelit real-time, RTK, VCS dan audit karbon diselaraskan dengan fungsi yang benar-benar diimplementasikan: laporan lapangan terverifikasi dan WebGIS titik tanam. Project tidak mengklaim menjalankan telemetri satelit atau penerbitan kredit karbon.

Lihat `FIGMA_MAKE.md` dan `design/screen-manifest.json` untuk daftar halaman serta alur penyiapan desain editable dan prototipe. Belum ada file native Figma Make yang dibuat pada akun Figma.

## Deployment shared hosting

Gunakan PHP 8.3+, database MySQL, dan HTTPS. Arahkan document root ke folder `public`, jangan root project. Isi `.env` sesuai hosting dan gunakan `APP_DEBUG=false`. Jalankan `composer install --no-dev --optimize-autoloader`, migrasi, `storage:link`, dan pasang cron. Pastikan `storage` serta `bootstrap/cache` dapat ditulis. Endpoint pembayaran tetap Sandbox pada project ini.

Cadangkan database dan `storage/app` bersama-sama. Pemulihan backup dan konfigurasi hosting harus diuji pada lingkungan target sebelum dipakai di luar demonstrasi.

## Membuat Admin di luar akun demo

```powershell
php artisan treefund:admin admin@contoh.id --name="Admin TreeFund" --finance
```

Kata sandi diminta secara tersembunyi melalui terminal. Akun yang sudah ada tidak dipromosikan atau diubah oleh perintah ini. Opsi `--finance` memberikan kewenangan verifikasi dan pencatatan pencairan.

## Navigasi website dan portal

- Website publik: `/`, `/catalog`, `/peta`, `/faq`, dan `/informasi/*`. Tidak memakai sidebar dashboard, termasuk ketika pengunjung sudah login.
- Katalog portal Donatur: `/donor/catalog`. E-sertifikat memiliki halaman tersendiri di `/certificates`; transaksi berada di `/orders`.
- Verifikasi Admin memakai tabel dan panel detail yang dibuka melalui tombol Tinjau Detail. Bukti foto hanya dimuat saat panel terlihat.
- Detail pesanan yang telah membuka pembayaran meminta status ke backend setiap 10 detik selama tab terlihat. Saat kembali dari simulator, pemeriksaan berjalan lagi. Hasil yang terkonfirmasi memperbarui halaman. Tombol Periksa Status Pembayaran tetap tersedia.
- Pembaruan saat halaman ditutup memerlukan webhook pada URL publik atau scheduler `treefund:reconcile`; localhost tidak dapat menerima webhook Midtrans.
- `design/screen-manifest.json` mencatat 40 layar/rute. `design/reference-coverage.json` memetakan seluruh HTML referensi termasuk varian mobile dan langkah formulir.
- Server Key dan Client Key harus berasal dari merchant serta lingkungan Sandbox yang sama. Perubahan `.env` memerlukan `php artisan config:clear` dan restart server bila konfigurasi lama masih terbaca.
## SMTP, verifikasi, dan email aktivitas

Verifikasi akun baru mendukung kode enam digit **atau** tautan bertanda tangan dalam satu email. Gunakan salah satu. Masa berlaku 15 menit, kode maksimal lima percobaan, kirim ulang setiap 60 detik. Pengiriman ulang mengganti kode serta tautan lama. Kode disimpan sebagai hash; payload antrean email dienkripsi dengan APP_KEY.

Isi SMTP sendiri pada `.env`, lalu jalankan `php artisan config:clear`. Laravel 13 memakai `MAIL_SCHEME=smtp` untuk port 587/STARTTLS atau `MAIL_SCHEME=smtps` untuk port 465. Gunakan host, port, kredensial, dan alamat pengirim yang diberikan penyedia SMTP. Tidak perlu memasang paket tambahan.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=host_dari_penyedia
MAIL_PORT=587
MAIL_USERNAME=akun_smtp
MAIL_PASSWORD=password_atau_app_password
MAIL_FROM_ADDRESS=email_pengirim
MAIL_FROM_NAME=TreeFund
```

Setelah mengubah `.env`, restart proses `artisan serve` dan worker yang sudah berjalan agar konfigurasi terbaru terbaca. Pertahankan `APP_KEY` lama karena antrean email dienkripsi.

### Menjalankan pengiriman dan pengingat

Email aktivitas serta keamanan memakai antrean `default`. Jalankan worker di terminal terpisah:

```powershell
php artisan queue:work --tries=3
```

Jalankan scheduler pada terminal terpisah agar pengingat pembayaran, monitoring, ringkasan Admin, dan rekonsiliasi berjalan:

```powershell
php artisan schedule:work
```

Pada deployment, kelola worker dengan process manager dan pasang scheduler Laravel pada sistem hosting. SMTP gagal akan dicoba ulang; kegagalan email tidak membatalkan transaksi bisnis yang sudah dikonfirmasi. Tinjau job gagal menggunakan `php artisan queue:failed` dan jalankan ulang dengan `php artisan queue:retry ID` setelah memperbaiki konfigurasi.

### Uji dan desain

```powershell
# Satu email nyata, langsung melalui SMTP tanpa worker:
php artisan treefund:mail-test alamat_penerima@example.com
# Pratinjau lokal dengan data contoh, tidak mengirim email:
php artisan treefund:email-preview
```

Galeri menampilkan 17 varian pesan dengan satu identitas TreeFund: verifikasi kode/tautan, reset password, keamanan, pesanan/pembayaran, sertifikat, laporan pohon, keputusan pengajuan, work order, pencairan, pengingat, serta antrean/ringkasan Admin. Email mempunyai versi HTML dan teks biasa. Dokumen legalitas, data rekening, dan foto privat tetap diakses melalui aplikasi, bukan disisipkan ke email.

Pengguna dapat menonaktifkan email pembaruan pohon, pengingat, dan ringkasan Admin melalui Pengaturan Akun. Email keamanan dan transaksi penting tetap aktif. Alamat baru harus diverifikasi kembali setelah diubah, dan perubahan alamat memerlukan kata sandi saat ini.

Akun lama yang belum terverifikasi akan diarahkan ke verifikasi; Pengaturan Akun tetap bisa dibuka untuk mengganti alamat. Alamat demo `@treefund.test` bukan inbox nyata: ubah ke email yang Anda kuasai untuk menguji penerimaan SMTP. DemoSeeder pada instalasi lokal baru menandai akun demo sebagai terverifikasi agar demonstrasi tetap dapat digunakan, sedangkan akun pengguna baru wajib verifikasi.

Migrasi tambahan `2026_10_18_000002_add_email_verification_and_preferences.php` menambah tabel tantangan verifikasi dan preferensi email, tanpa menghapus data aplikasi. Pada instalasi yang belum menjalankannya, gunakan `php artisan migrate`, bukan `migrate:fresh`.
Pengingat monitoring dirangkum per mitra dan dikirim paling banyak sekali per minggu agar tidak menumpuk satu email untuk setiap pohon.
