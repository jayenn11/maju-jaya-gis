# AGENTS.md - Standar Pengembangan SIG Distribusi Makanan "PT Maju Jaya Food"

## 1. Konteks & Tumpukan Teknologi
- **Nama Aplikasi:** Sistem Informasi Geografis Distribusi Makanan – PT Maju Jaya Food (Bandung)
- **Tech Stack:** PHP 8.2+ (Native Modular + PDO Prepared Statements), MySQL 8.0 (`maju_jaya_gis`), Bootstrap 5.3 (CDN), Leaflet.js 1.9 (CDN), OpenStreetMap Tiles, dan OSRM Public Routing API (`https://router.project-osrm.org/route/v1/driving/`).
- **Lingkungan Target:** Dapat dijalankan langsung melalui XAMPP (`htdocs/maju-jaya-gis`) maupun PHP built-in server (`php -S localhost:8000`). Gunakan relative path yang dinamis agar kompatibel di keduanya.

## 2. Standar Kualitas & Integritas Data
- **Keamanan Query & XSS:** Seluruh query database WAJIB menggunakan PDO Prepared Statements. Seluruh output teks dari database ke HTML wajib di-escape menggunakan `htmlspecialchars()`.
- **Validasi Koordinat Spasial:** Koordinat `latitude` (-90 s.d. 90) dan `longitude` (-180 s.d. 180) wajib divalidasi sebelum disimpan ke database atau dikirim ke OSRM API.
- **Fallback OSRM API:** Jika layanan OSRM mengalami gangguan jaringan sementara, sistem wajib menangani error secara elegan dan menyediakan estimasi cadangan berbasis Haversine dengan pemberitahuan jelas kepada pengguna.

## 3. Standar Antarmuka Pengguna (UI/UX Bootstrap 5)
- **Layout:** Clean Sidebar Dashboard (Sidebar kiri gelap/slate `#0F172A`, Topbar informasi halaman & admin aktif, area konten utama latar `#F8FAFC` dengan kartu putih bersih).
- **Interaktivitas:** Form Tambah/Edit menggunakan Bootstrap Modal; konfirmasi hapus menggunakan Bootstrap Confirmation Modal (hindari `window.confirm()` bawaan browser); pencarian tabel bekerja secara instan (live filter).
- **Bahasa:** Seluruh label antarmuka, status pengiriman, dan laporan menggunakan Bahasa Indonesia sesuai spesifikasi proyek.

## 4. Standar Komponen UI/UX Ketat (Wajib Dipatuhi di Seluruh Halaman)
- **Custom Styled & Searchable Dropdown:** Dilarang menggunakan `<select>` bawaan browser yang tidak ter-styling. Gunakan komponen Custom Dropdown JS/Bootstrap seragam (mendukung pencarian real-time di dalamnya untuk opsi dinamis seperti Pelanggan, Barang, Kendaraan, dan filter status).
- **Modal-First CRUD:** Seluruh form Tambah dan Edit data wajib menggunakan Bootstrap Modal di halaman yang sama (tidak pindah ke halaman form terpisah).
- **Floating Dropdown di Dalam Modal:** Kartu konten modal (`.modal-content`) wajib memiliki `overflow: visible` agar panel custom dropdown dapat melayang melewati batas bawah modal tanpa memunculkan scrollbar internal.
- **Custom Confirmation Modal:** Dilarang menggunakan `window.confirm()` atau `alert()` bawaan browser. Gunakan satu komponen global Bootstrap Confirmation Modal yang elegan untuk konfirmasi hapus maupun perubahan status.
- **Live Search & Auto-Filter:** Pencarian tabel dan filter kategori/status bekerja secara instan saat diketik (`debounce 300ms`) atau diubah tanpa perlu menekan tombol "Cari/Filter".
- **Validasi Input & Layout Tabel:** Field nomor telepon wajib memiliki validasi angka saja (`only numbers` di frontend & backend); tombol aksi tabel wajib `text-nowrap` agar tidak terpotong menjadi 2 baris; kolom nama pelanggan/alamat diberi lebar proporsional; serta pagination menggunakan palet terang selaras tema (biru/slate terang, hindari warna hitam pekat).

## 4. Standar Komponen UI/UX Ketat (Wajib Dipatuhi di Seluruh Halaman)
- **Custom Styled & Searchable Dropdown:** Dilarang menggunakan `<select>` bawaan browser yang tidak ter-styling. Gunakan komponen Custom Dropdown JS/Bootstrap seragam (mendukung pencarian real-time di dalamnya untuk opsi dinamis seperti Pelanggan, Barang, Kendaraan, dan filter status).
- **Modal-First CRUD:** Seluruh form Tambah dan Edit data wajib menggunakan Bootstrap Modal di halaman yang sama (tidak pindah ke halaman form terpisah).
- **Floating Dropdown di Dalam Modal:** Kartu konten modal (`.modal-content`) wajib memiliki `overflow: visible` agar panel custom dropdown dapat melayang melewati batas bawah modal tanpa memunculkan scrollbar internal.
- **Custom Confirmation Modal:** Dilarang menggunakan `window.confirm()` atau `alert()` bawaan browser. Gunakan satu komponen global Bootstrap Confirmation Modal yang elegan untuk konfirmasi hapus maupun perubahan status.
- **Live Search & Auto-Filter:** Pencarian tabel dan filter kategori/status bekerja secara instan saat diketik (`debounce 300ms`) atau diubah tanpa perlu menekan tombol "Cari/Filter".
- **Validasi Input & Layout Tabel:** Field nomor telepon wajib memiliki validasi angka saja (`only numbers` di frontend & backend); tombol aksi tabel wajib `text-nowrap` agar tidak terpotong menjadi 2 baris; kolom nama pelanggan/alamat diberi lebar proporsional; serta pagination menggunakan palet terang selaras tema (biru/slate terang, hindari warna hitam pekat).
