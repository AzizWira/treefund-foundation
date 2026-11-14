# Menyiapkan desain TreeFund untuk Figma Make

Aplikasi Laravel adalah sumber perilaku dan data. Figma Design menyimpan frame editable, sedangkan Figma Make menjadi prototipe. Mengambil tampilan halaman tidak memindahkan database, kebijakan akses, atau webhook PHP.

## Alur yang disarankan

1. Jalankan TreeFund dan login dengan akun demonstrasi sesuai peran.
2. Gunakan daftar pada `design/screen-manifest.json` untuk menangkap halaman pada ukuran desktop yang konsisten, lalu ulangi halaman lapangan pada ukuran ponsel.
3. Tangkap layar website sebagai layer melalui Figma Chrome extension atau fitur Code to canvas yang tersedia pada akun Anda.
4. Paste hasilnya pada Figma Design. Susun frame per peran: Publik, Donatur, Mitra Petani, dan Admin.
5. Periksa kembali font, ikon, jarak, tabel, serta peta. Peta/elemen kompleks mungkin perlu disusun ulang sebagai komponen visual.
6. Kirim frame melalui **Send to Figma Make**, kemudian lengkapi alur prototipe dan state yang diperlukan.

Dokumentasi: https://help.figma.com/hc/en-us/articles/40826832449303-Turn-coded-screens-into-editable-design-layers

## Prompt dasar untuk Make

Gunakan frame TreeFund yang dilampirkan sebagai referensi visual. Pertahankan layout, sidebar hijau tua, latar terang, tipografi, warna, kartu, tabel, tombol, dan susunan formulir. Buat prototipe interaktif dengan tiga peran: Donatur, Mitra Petani, dan Admin Verifikator. Gunakan data simulasi yang konsisten antarhalaman. Perubahan hanya untuk melengkapi state dan merapikan konsistensi, bukan mengganti konsep visual.

Alur Donatur: katalog → checkout → status pembayaran Sandbox → e-sertifikat → riwayat → peta pohon → timeline. Alur Mitra: profil dan legalitas → pengajuan lahan → status/revisi → work order → bukti tanam → monitoring → pengajuan termin. Alur Admin: antrean → pemeriksaan → persetujuan/revisi → katalog → alokasi dana → verifikasi pencairan → pencatatan transfer → rekap.

Tambahkan state kosong, loading, gagal jaringan, input tidak valid, kuota penuh, pembayaran pending/berhasil/gagal/kedaluwarsa, pengajuan dikembalikan, dan laporan terverifikasi. Prototipe Make menggunakan simulasi perilaku; jangan memasukkan Server Key Midtrans, dokumen asli, atau kredensial pengguna.

## Batas hasil transfer

Frame hasil capture perlu QA visual. Layer tidak otomatis menjadi komponen library, dan peta interaktif tidak otomatis berubah menjadi peta native Figma. Untuk konsistensi yang baik, periksa satu halaman per peran sebelum menangkap semua halaman.

File native Figma/Make dan tautan publikasinya harus dibuat pada akun Figma yang Anda akses. Folder project ini menyediakan aplikasi, referensi, daftar layar, dan instruksi transfer; tidak menyertakan file `.fig` atau `.make` hasil native.
