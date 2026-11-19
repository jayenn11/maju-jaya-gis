# TECH_SPEC.md - Spesifikasi Teknis Database & Arsitektur "PT Maju Jaya Food"

## 1. Struktur Basis Data Relasional (`maju_jaya_gis`)

### Tabel 1: `admins`
- `id` (INT, PK, AI)
- `name` (VARCHAR 100)
- `username` (VARCHAR 50, Unique)
- `password` (VARCHAR 255 — `password_hash`)
- `created_at` (TIMESTAMP)

### Tabel 2: `warehouses` (Titik Awal Pengiriman / Gudang Distribusi)
- `id` (INT, PK, AI)
- `name` (VARCHAR 120) — *Contoh: Gudang Utama PT Maju Jaya Food Bandung*
- `address` (TEXT)
- `latitude` (DECIMAL 10,8) — *Contoh: -6.94309700 (Kawasan Soekarno-Hatta, Bandung)*
- `longitude` (DECIMAL 11,8) — *Contoh: 107.63354500*
- `updated_at` (TIMESTAMP)

### Tabel 3: `customers` (Titik Tujuan Pengiriman: Toko, Minimarket, Restoran)
- `id` (INT, PK, AI)
- `code` (VARCHAR 20, Unique) — *Contoh: PLG-001*
- `name` (VARCHAR 120) — *Contoh: Toko Berkah*
- `category` (ENUM: `'Toko'`, `'Minimarket'`, `'Restoran'`)
- `pic_name` (VARCHAR 100)
- `phone` (VARCHAR 25)
- `address` (TEXT)
- `latitude` (DECIMAL 10,8)
- `longitude` (DECIMAL 11,8)
- `created_at` (TIMESTAMP)

### Tabel 4: `products` (Katalog Barang Makanan Distribusi)
- `id` (INT, PK, AI)
- `code` (VARCHAR 20, Unique) — *Contoh: BRG-001*
- `name` (VARCHAR 120) — *Contoh: Makanan Kemasan Ringan*
- `category` (VARCHAR 60) — *Makanan Kemasan, Bahan Pokok, Minuman, Frozen Food*
- `unit` (VARCHAR 30) — *Kardus, Bal, Pack, Box*
- `weight_per_unit` (DECIMAL 8,2) — *Berat dalam kg per satuan*
- `stock` (INT)

### Tabel 5: `shipments` (Data & Monitoring Pengiriman)
- `id` (INT, PK, AI)
- `shipment_code` (VARCHAR 20, Unique) — *Contoh: KRM001*
- `warehouse_id` (INT, FK -> `warehouses.id`)
- `customer_id` (INT, FK -> `customers.id`, On Delete Restrict)
- `product_id` (INT, FK -> `products.id`, On Delete Restrict)
- `quantity` (INT) — *Contoh: 20 (kardus)*
- `total_weight` (DECIMAL 10,2) — *Contoh: 50.00 (kg)*
- `vehicle_type` (VARCHAR 50) — *Contoh: Mobil Boks, Pickup Boks, Van Pendingin*
- `driver_name` (VARCHAR 100) — *Contoh: Andi*
- `distance_km` (DECIMAL 8,2) — *Hasil kalkulasi OSRM API (contoh: 8.50)*
- `duration_minutes` (INT) — *Estimasi waktu tempuh OSRM API (contoh: 22)*
- `route_geometry` (LONGTEXT, Nullable) — *GeoJSON / encoded polyline rute jalan dari OSRM*
- `status` (ENUM: `'Menunggu'`, `'Dalam pengiriman'`, `'Selesai dikirim'`, Default: `'Menunggu'`)
- `shipment_date` (DATE)
- `notes` (TEXT, Nullable)
- `created_at` (TIMESTAMP)

---

## 2. Alur Integrasi OSRM Routing API
- Endpoint OSRM: `https://router.project-osrm.org/route/v1/driving/{lng_gudang},{lat_gudang};{lng_pelanggan},{lat_pelanggan}?overview=full&geometries=geojson`
- Output yang diekstrak:
  - `routes[0].distance` (meter) -> dikonversi menjadi kilometer (`round(distance / 1000, 2)`).
  - `routes[0].duration` (detik) -> dikonversi menjadi menit (`ceil(duration / 60)`).
  - `routes[0].geometry` -> dirender sebagai `L.geoJSON()` polyline pada peta Leaflet.js.