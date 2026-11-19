# Sistem Informasi Geografis (SIG) Distribusi Makanan — PT Maju Jaya Food (Bandung)

Aplikasi web **Sistem Informasi Geografis (SIG) Distribusi Makanan** untuk **PT Maju Jaya Food** di wilayah **Kota Bandung dan sekitarnya**. Sistem ini dirancang untuk memetakan lokasi gudang pusat dan jaringan pelanggan (Toko, Minimarket, dan Restoran), menghitung rute jalan raya tercepat beserta estimasi jarak (`km`) dan waktu tempuh (`menit`) menggunakan **OSRM Routing API**, serta memonitor penugasan armada, stok barang, hingga laporan rekapitulasi pengiriman.

---

## 1. Ringkasan Studi Kasus

**PT Maju Jaya Food** merupakan perusahaan distribusi produk makanan kemasan, bahan pokok, minuman, dan *frozen food* yang berpusat di **Jl. Soekarno-Hatta No. 450, Batununggal, Kota Bandung** (`-6.94309700, 107.63354500`).

Melalui aplikasi SIG ini, divisi logistik dapat:
1. Memetakan sebaran spasial titik Gudang Utama dan seluruh titik mitra pelanggan di kawasan Bandung (Buah Batu, Dago, Riau/LLRE Martadinata, Kopo, Setiabudi, Asia Afrika, Antapani, Cibabat/Cimahi, Pasteur, dan Sarijadi).
2. Mensimulasikan rute pengiriman jalan raya (*Single-Stop* maupun *Multi-Stop* berurutan) berbasis **OpenStreetMap** dan **OSRM Public Routing API**.
3. Mengelola jadwal pengiriman armada (`Mobil Boks`, `Pickup Boks`, `Van Pendingin`, `Motor Box`), memvalidasi dan memotong stok gudang secara otomatis, serta memantau status pergerakan pengiriman (`Menunggu` → `Dalam pengiriman` → `Selesai dikirim`).
4. Menghasilkan laporan rekapitulasi distribusi yang dapat diekspor ke format **CSV (Excel/Google Sheets)** maupun dicetak langsung dengan kop resmi perusahaan (`@media print`).

---

## 2. Fitur Utama

### A. Autentikasi Admin & Keamanan Sesi
- Halaman login administrator bertema spasial logistik dengan fitur tampil/sembunyikan kata sandi dan pengisi otomatis akun demo.
- Proteksi sesi menggunakan `session_regenerate_id(true)`, verifikasi hash `password_verify()`, **PDO Prepared Statements** di seluruh query SQL, serta escaping output HTML melalui helper `e()` (`htmlspecialchars()`).

### B. Dashboard Monitoring Distribusi
- **4 Kartu KPI Utama:** Total Titik Pelanggan (dirinci per kategori `Toko`, `Minimarket`, `Restoran`), Total Katalog Barang & Stok Tersedia, Pengiriman Aktif (`Menunggu` & `Dalam pengiriman`), serta Pengiriman Selesai (`Selesai dikirim`).
- **Mini Peta Sebaran Wilayah Bandung (Leaflet.js):** Menampilkan marker khusus Gudang Utama dan seluruh titik pelanggan dengan warna berbeda per kategori beserta popup informasi alamat, PIC, nomor telepon, dan koordinat.
- **Tabel 5 Aktivitas Pengiriman Terbaru:** Ringkasan jadwal pengiriman terkini lengkap dengan jarak tempuh OSRM, estimasi waktu, dan status.

### C. Peta Pengiriman Interaktif & Mesin Perutean OSRM (`Single & Multi-Stop`)
- **Builder Tujuan Pengiriman:** Mendukung pemilihan 1 tujuan maupun banyak tujuan berurutan (*Multi-Stop*: Stop 1, Stop 2, dst.) dengan fitur ubah urutan (**Naik/Turun**), **Hapus Stop**, serta pencegahan duplikasi pelanggan yang sama.
- **Kalkulasi Rute Jaringan Jalan (OSRM API):** Menggambar geometri jalur jalan raya (`L.geoJSON`) di atas peta OpenStreetMap lengkap dengan marker bernomor urutan tujuan (`1`, `2`, `3`, ...), total jarak (`km`), estimasi waktu tempuh (`menit`), serta rincian ruas jalan per segmen.
- **Mekanisme Fallback Haversine Otomatis:** Apabila layanan publik OSRM mengalami gangguan jaringan atau *timeout*, sistem otomatis beralih menghitung estimasi jarak menggunakan rumus **Haversine × 1.25** (faktor kelokan jalan perkotaan) dengan asumsi kecepatan rata-rata dalam kota Bandung (`30 km/jam`) disertai pemberitahuan jelas kepada pengguna.
- **Toggle Overlay Pengiriman Aktif:** Sakelar interaktif untuk menampilkan/menyembunyikan jalur pengiriman yang sedang berstatus `Dalam pengiriman` langsung di atas peta.
- **Integrasi Langsung ke Jadwal Pengiriman:** Tombol **"Buat Jadwal Pengiriman dari Rute Ini"** meneruskan pelanggan tujuan, jarak (`km`), waktu tempuh (`menit`), dan geometri rute langsung ke form tambah pengiriman.

### D. Manajemen & Monitoring Pengiriman
- **Modal-First CRUD:** Tambah dan edit jadwal pengiriman (`KRM001`, dst.) dalam satu halaman menggunakan Bootstrap Modal.
- **Integrasi OSRM Otomatis di Modal:** Memilih pelanggan tujuan pada form modal secara otomatis memanggil endpoint OSRM di latar belakang untuk mengisi jarak (`km`), estimasi waktu (`menit`), dan menyimpan geometri rute jalan.
- **Kalkulasi Berat & Validasi Stok Real-Time:** Menghitung `Total Berat (kg) = Jumlah Muatan × Berat/Satuan` secara *live* dan menolak penyimpanan apabila jumlah muatan melebihi stok gudang yang tersedia.
- **Transaksi Database Atomik (`PDO::beginTransaction()`):**
  - Saat pengiriman baru disimpan, stok produk di tabel `products` otomatis berkurang.
  - Saat pengiriman yang masih berstatus `Menunggu` dihapus, stok produk otomatis dikembalikan ke gudang.
- **Modal Detail & Monitoring Status:** Dilengkapi **Mini Peta Leaflet.js** yang menampilkan jalur rute pengiriman tersebut serta tombol perubahan status bertahap (**"Mulai Pengiriman"** → `Dalam pengiriman` dan **"Selesaikan Pengiriman"** → `Selesai dikirim`).

### E. Master Data Pelanggan, Barang, & Pengaturan Gudang
- **Data Pelanggan (`PLG-XXX`):** Dilengkapi **Interactive Map Coordinate Picker (Leaflet.js)** di dalam modal tambah/edit (klik peta Bandung atau geser marker untuk mengisi `Latitude` & `Longitude` otomatis), validasi nomor telepon hanya angka, serta proteksi hapus apabila pelanggan memiliki riwayat pengiriman.
- **Data Barang (`BRG-XXX`):** Pengelolaan katalog makanan distribusi (`Makanan Kemasan`, `Bahan Pokok`, `Minuman`, `Frozen Food`), satuan (`Kardus`, `Bal`, `Pack`, `Box`), berat per satuan (`kg`), indikator stok menipis, dan proteksi hapus.
- **Pengaturan Lokasi Gudang:** Pembaruan nama, alamat, dan titik koordinat Gudang Utama PT Maju Jaya Food menggunakan peta interaktif Leaflet.js yang dapat diklik atau digeser markernya.

### F. Laporan Rekapitulasi Pengiriman (`CSV & Print`)
- **Auto-Filter:** Filter otomatis berdasarkan rentang tanggal (`Dari Tanggal` – `Sampai Tanggal`), `Status Pengiriman`, `Pelanggan Tujuan`, dan kata kunci pencarian.
- **4 Kartu Ringkasan Rekapitulasi:** Total Pengiriman (beserta jumlah selesai), Total Jarak Tempuh (`km`), Total Berat Muatan (`kg`), dan Rata-rata Waktu Tempuh (`menit`).
- **Export CSV & Cetak Resmi:** Unduh laporan ke file `.csv` (dilengkapi UTF-8 BOM agar rapi di Microsoft Excel/Google Sheets) atau cetak langsung melalui tombol **Cetak** (`@media print` otomatis menyembunyikan sidebar/topbar/filter dan menampilkan kop resmi **PT MAJU JAYA FOOD** beserta tanda tangan penanggung jawab).

### G. Standar Komponen UI/UX Modern
- **Custom Searchable Dropdown:** Seluruh pilihan kategori, pelanggan, barang, satuan, armada, dan filter status menggunakan komponen dropdown kustom yang mendukung pencarian ketik *real-time* (0 elemen `<select>` bawaan browser) dan melayang bebas di atas modal (`.modal-content { overflow: visible }`).
- **Custom Bootstrap Confirmation Modal:** Seluruh konfirmasi hapus, perubahan status pengiriman, dan logout menggunakan modal konfirmasi Bootstrap seragam (tanpa `window.confirm()` atau `alert()`).
- **Live Search (`debounce 300ms`) & Light Pagination:** Pencarian dan penyaringan tabel bekerja instan saat diketik tanpa tombol submit manual.

---

## 3. Tumpukan Teknologi (Tech Stack)

| Komponen | Teknologi |
| :--- | :--- |
| **Backend** | PHP 8.2+ (Native Modular, PDO Prepared Statements) |
| **Database** | MySQL 8.0 / MariaDB (`maju_jaya_gis`, `utf8mb4`) |
| **Frontend UI** | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3 (CDN), Bootstrap Icons 1.11 |
| **Peta & GIS** | Leaflet.js 1.9.4 (CDN) & OpenStreetMap (OSM) Raster Tiles |
| **Mesin Rute Jalan** | OSRM Public Routing API (`router.project-osrm.org`) + Fallback Haversine |

---

## 4. Struktur Direktori Proyek

```text
maju-jaya-gis/
├── index.php                  # Front-Controller & Router utama (?page=...)
├── config/
│   └── database.php           # Koneksi PDO MySQL, helper XSS e(), base_url() dinamis, & validasi koordinat
├── database/
│   └── schema.sql             # Skema DDL 5 tabel berelasi FK + Data Awal (Seeder) wilayah Bandung
├── api/
│   ├── auth.php               # Endpoint Login & Logout administrator
│   ├── customers.php          # Endpoint CRUD Data Pelanggan + proteksi FK pengiriman
│   ├── products.php           # Endpoint CRUD Data Barang + proteksi FK pengiriman
│   ├── warehouse.php          # Endpoint Pembaruan Titik Lokasi Gudang Utama
│   ├── routing.php            # Service OSRM Routing API (Single/Multi-Stop) + Fallback Haversine
│   ├── shipments.php          # Endpoint CRUD Pengiriman, mutasi stok atomik, & update status
│   └── reports.php            # Endpoint Rekapitulasi Laporan & Export CSV
├── views/
│   ├── layouts/
│   │   ├── header.php         # Sidebar gelap (#0F172A), Topbar, & pemuatan CSS/Leaflet
│   │   └── footer.php         # Footer, Global Confirmation Modal, & Toast Container
│   ├── login.php              # Antarmuka Login Admin + Kotak Akun Demo
│   ├── dashboard.php          # Halaman Dashboard Monitoring & Mini Peta Sebaran Bandung
│   ├── peta.php               # Halaman Peta Interaktif & Perencana Rute OSRM Multi-Stop
│   ├── pengiriman.php         # Halaman Manajemen & Monitoring Pengiriman
│   ├── pelanggan.php          # Halaman Master Data Pelanggan + Modal Map Coordinate Picker
│   ├── barang.php             # Halaman Master Data Barang (Katalog Produk)
│   ├── gudang.php             # Halaman Pengaturan Lokasi Gudang Utama
│   └── laporan.php            # Halaman Laporan Rekapitulasi, Export CSV, & Cetak Resmi
├── assets/
│   ├── css/
│   │   └── style.css          # Custom Styling Dashboard, Custom Dropdown, Peta, & @media print
│   └── js/
│       └── app.js             # Global JS Helpers (Custom Searchable Dropdown, Confirm Modal, Toast, Debounce)
├── AGENTS.md                  # Standar arsitektur, kualitas data spasial, dan aturan ketat UI/UX
├── TECH_SPEC.md               # Spesifikasi teknis skema database & integrasi OSRM API
├── .gitignore                 # Daftar pengecualian file Git
└── README.md                  # Dokumentasi utama proyek
```

---

## 5. Panduan Instalasi & Menjalankan Aplikasi

Aplikasi ini menggunakan fungsi helper `base_url()` dinamis di `config/database.php`, sehingga **dapat langsung dijalankan baik melalui XAMPP (`htdocs/maju-jaya-gis`) maupun PHP Built-in Server (`php -S localhost:8000`)** tanpa perlu mengubah path kode.

### Langkah 1: Persiapan & Impor Database MySQL
1. Pastikan layanan **MySQL / MariaDB** sudah aktif.
2. Impor file `database/schema.sql` melalui **phpMyAdmin** atau terminal CLI:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   *(Skrip `database/schema.sql` akan otomatis membuat database `maju_jaya_gis` beserta 5 tabel: `admins`, `warehouses`, `customers`, `products`, `shipments` dan mengisi data awal riil wilayah Kota Bandung).*

### Langkah 2: Konfigurasi Koneksi Database (`config/database.php`)
Buka file `config/database.php` dan sesuaikan kredensial MySQL lokal Anda jika diperlukan:
```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'maju_jaya_gis');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'rahasia');
```
> **Catatan Kompatibilitas XAMPP:** `config/database.php` telah dilengkapi *automatic fallback* ke password kosong (`''`). Apabila MySQL pada XAMPP Anda tidak menggunakan password (`root` tanpa password), koneksi tetap berjalan otomatis tanpa error.

### Langkah 3A: Menjalankan Melalui XAMPP (Apache)
1. Salin atau letakkan folder `maju-jaya-gis` ke dalam direktori `htdocs` XAMPP Anda:
   - Windows: `C:\xampp\htdocs\maju-jaya-gis`
   - macOS: `/Applications/XAMPP/xamppfiles/htdocs/maju-jaya-gis`
2. Nyalakan modul **Apache** dan **MySQL** pada XAMPP Control Panel.
3. Buka browser dan akses:
   ```text
   http://localhost/maju-jaya-gis/
   ```

### Langkah 3B: Menjalankan Melalui PHP Built-in Server
1. Buka terminal dan masuk ke direktori proyek `maju-jaya-gis`:
   ```bash
   cd /path/to/maju-jaya-gis
   ```
2. Jalankan perintah server bawaan PHP:
   ```bash
   php -S localhost:8000
   ```
3. Buka browser dan akses:
   ```text
   http://localhost:8000/
   ```

---

## 6. Kredensial Akun Demo

Gunakan akun administrator berikut pada halaman Login untuk menguji seluruh fitur sistem:

| Field | Nilai |
| :--- | :--- |
| **Username** | `admin` |
| **Password** | `admin123` |
| **Peran** | Administrator Logistik — PT Maju Jaya Food (Bandung) |
