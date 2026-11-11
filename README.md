# Nubiko Restock

Aplikasi web internal untuk mencatat **restock (barang masuk)**, **stock out (barang keluar / terjual)**, dan memantau **sisa stok** produk Nubiko, mulai dari master produk, pengiriman dari gudang, sampai laporan yang bisa diexport.

Dibangun dengan Laravel + Inertia + Vue, dan berjalan di Railway dengan database PostgreSQL.

---

## Daftar Isi

1. [Fitur](#fitur)
2. [Teknologi](#teknologi)
3. [Cara Kerja Stok](#cara-kerja-stok)
4. [Struktur Data](#struktur-data)
5. [Aturan Bisnis](#aturan-bisnis)
6. [Format File Import](#format-file-import)
7. [Pengguna, Peran, dan Pendaftaran](#pengguna-peran-dan-pendaftaran)
8. [Instalasi Lokal](#instalasi-lokal)
9. [Deploy ke Railway](#deploy-ke-railway)
10. [Struktur Folder](#struktur-folder)
11. [Endpoint API](#endpoint-api)
12. [Catatan Data dan Perawatan](#catatan-data-dan-perawatan)
13. [Troubleshooting](#troubleshooting)

---

## Fitur

| Halaman              | Fungsi                                                                                                                                                |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Dashboard**        | Ringkasan dan grafik pergerakan stok.                                                                                                                 |
| **Restock Incoming** | Catat dan lihat pengiriman barang masuk per tanggal, gudang asal, produk, qty, dan status inbound. Import dari CSV/Excel, ubah status, hapus, export. |
| **Stock Out**        | Catat barang keluar/terjual. Import dari CSV/Excel dengan pratinjau sebelum disimpan, filter, dan export.                                             |
| **Product**          | Sisa stok tiap produk (total masuk, total keluar, sisa, stok minimum).                                                                                |
| **Warehouse**        | Tampilan data per gudang.                                                                                                                             |
| **Master Product**   | Daftar produk: SKU, tipe, nama, kategori, harga, stok minimum. Bisa digenerate otomatis dari data restock.                                            |
| **User Agreement**   | Persetujuan akun pengguna baru oleh admin.                                                                                                            |

Fitur umum:

- **Filter**: pencarian (nama produk / SKU / gudang), rentang tanggal (ada pintasan Yesterday, 7 days, 30 days, 90 days, 365 days), filter per produk, dan filter status (restock).
- **Export** ke Excel (.xlsx), CSV, dan PDF. Hasil export mengikuti filter yang sedang aktif.
- **Import dua tahap** (pratinjau lalu konfirmasi): setiap baris diberi tanda valid atau salah, dan hanya data valid yang disimpan.
- **Login dengan persetujuan admin**: akun baru berstatus _pending_ sampai disetujui.

---

## Teknologi

| Lapisan   | Yang dipakai                                                                       |
| --------- | ---------------------------------------------------------------------------------- |
| Backend   | PHP 8.3+, Laravel 13                                                               |
| Auth      | Session (login web) + Laravel Sanctum                                              |
| Frontend  | Inertia.js, Vue 3 (`<script setup>` + TypeScript), Vite, Tailwind CSS, ikon Lucide |
| Database  | PostgreSQL (query memakai `ILIKE` dan `FILTER`, jadi **butuh Postgres**)           |
| Export    | `maatwebsite/excel` (Excel/CSV), `barryvdh/laravel-dompdf` (PDF)                   |
| Integrasi | `revolution/laravel-google-sheets`                                                 |
| Hosting   | Railway (auto-deploy dari GitHub)                                                  |

Kebutuhan versi: **PHP 8.3 atau lebih baru** dan **Node.js 20.19+ atau 22.12+** (Vite versi baru menolak Node 18).

---

## Cara Kerja Stok

```
Sisa stok  =  total restock berstatus "Sudah Inbound"  −  total stock out
```

- Restock berstatus **Belum Inbound** (masih dalam perjalanan) **tidak dihitung** sebagai stok.
- Restock dan stock out dihubungkan ke produk lewat **nama produk** (teks) dan **SKU**. Karena itu nama di file import harus **persis sama** dengan nama di Master Product.
- Stok minimum default tiap produk adalah **10** dan bisa diubah di Master Product.

---

## Struktur Data

### `products` (Master Product)

| Kolom          | Keterangan                                                                      |
| -------------- | ------------------------------------------------------------------------------- |
| `sku`          | Unik. Produk yang dibuat otomatis memakai format `AUTO-0001`, `AUTO-0002`, dst. |
| `tipe`         | `satuan` (default) atau `bundling`.                                             |
| `nama_produk`  | Nama resmi produk.                                                              |
| `kategori`     | Opsional.                                                                       |
| `harga`        | Default 0.                                                                      |
| `stok_minimum` | Default 10.                                                                     |

### `restocks`

| Kolom              | Keterangan                                                           |
| ------------------ | -------------------------------------------------------------------- |
| `tanggal_kirim`    | Tanggal pengiriman.                                                  |
| `kota_asal_gudang` | Gudang asal (mis. Depok, Surabaya, Depok B2B).                       |
| `sku`              | Diisi otomatis dari Master Product berdasarkan nama produk.          |
| `nama_produk`      | Harus ada di Master Product.                                         |
| `qty`              | Jumlah, minimal 1.                                                   |
| `status`           | `sudah` (tampil **Sudah Inbound**) atau `belum` (**Belum Inbound**). |

### `stock_outs`

| Kolom            | Keterangan                 |
| ---------------- | -------------------------- |
| `tanggal_keluar` | Tanggal barang keluar.     |
| `sku`            | SKU dari Master Product.   |
| `nama_produk`    | Nama produk saat dicatat.  |
| `qty`            | Jumlah, minimal 1.         |
| `keterangan`     | Opsional (mis. `terjual`). |

### `users`

`name`, `email`, `password`, `role` (`admin` atau `staff`), `status` (`pending`, `approved`, `rejected`).

Tabel lain: `cache`, `jobs`, dan `personal_access_tokens` (bawaan Laravel/Sanctum).

---

## Aturan Bisnis

1. **Produk harus pernah di-restock sebelum bisa dicatat stock out.** Baris stock out untuk produk yang belum punya satu pun data restock ditolak, baik lewat form maupun import.
2. **Produk baru dari import restock didaftarkan otomatis** ke Master Product dengan SKU `AUTO-xxxx`, harga 0, dan stok minimum 10. Pratinjau import menampilkan jumlah produk baru sebelum disimpan.
3. **Input manual restock** hanya menerima produk yang sudah ada di Master Product.
4. **Satu browser, satu pendaftaran akun** (lihat bagian Pengguna).
5. **Hanya admin** yang bisa menghapus data produk dan stock out.
6. Spasi di awal/akhir nama produk dibuang saat import, tetapi perbedaan huruf, spasi di tengah, atau tanda baca tetap dianggap **produk berbeda**.

---

## Format File Import

File boleh `.csv`, `.xlsx`, atau `.xls` (maksimal 5 MB). Baris pertama harus berupa **header**. Nama header tidak peka huruf besar/kecil, dan spasi dibaca sebagai garis bawah.

### Restock

```csv
tanggal_kirim,kota_asal_gudang,nama_produk,qty,status
2026-01-02,Depok,NUBIKO Baby Cream 50 gr,2830,Sudah Inbound
2026-10-29,Salatiga,Nubiko Essential Soothe with Melicica 30 gr,1008,Belum Inbound
```

- `tanggal_kirim`: format `YYYY-MM-DD` (tanggal Excel juga dikenali).
- `status`: `Sudah Inbound` atau `Belum Inbound`.
- Baris tanpa tanggal ditolak.

### Stock Out

```csv
tanggal_keluar,sku,nama_produk,qty,keterangan
2026-01-02,AUTO-0014,NUBIKO Baby Cream 50 gr,358,terjual
```

- Cukup isi salah satu dari `sku` atau `nama_produk`. Jika keduanya ada, **SKU yang dipakai**.
- `keterangan` boleh kosong (maksimal 255 karakter).

### Tips penting

- **Jangan menyimpan ulang CSV lewat Excel.** Nama produk yang mengandung koma (mis. `Nubiko Ready, Set, Explore!`) bisa dibungkus tanda kutip dan membuat baris rusak. Simpan sebagai **CSV UTF-8** langsung dari sumbernya.
- Gunakan UTF-8 agar karakter seperti `™` terbaca benar.
- Pastikan kolom tanggal dan status terisi di semua baris sebelum import.

---

## Pengguna, Peran, dan Pendaftaran

**Alur pendaftaran**

1. Pengunjung membuka `/register` dan mengisi nama, email, dan password.
2. Akun dibuat dengan peran `staff` dan status `pending`.
3. Browser menerima cookie `nb_registered` (berlaku 1 tahun). Selama cookie itu ada, halaman daftar diganti pesan:
    - **Menunggu disetujui oleh admin** (jika status masih `pending`), atau
    - **Akun sudah disetujui** dengan tombol ke halaman login (jika status `approved`).
4. Admin membuka halaman **User Agreement** untuk menyetujui atau menolak.
5. Pengguna baru bisa login setelah statusnya `approved`. Akun `pending` atau `rejected` ditolak saat login dengan pesan yang sesuai.

**Batasan**: pembatasan "satu pendaftaran per browser" memakai cookie, jadi bisa dilewati dengan mode incognito atau browser lain. Pengaman utamanya tetap persetujuan admin. Akun yang ditolak atau dihapus admin tidak lagi memblokir browser tersebut.

**Membuat admin pertama** (jalankan di `php artisan tinker`):

```php
\App\Models\User::where('email', 'email@anda.com')
    ->update(['role' => 'admin', 'status' => 'approved']);
```

---

## Instalasi Lokal

### Prasyarat

- PHP **8.3+** dengan ekstensi: `openssl`, `curl`, `mbstring`, `fileinfo`, `gd`, `intl`, `zip`, `pdo_pgsql`, `pgsql`
- Composer
- Node.js **20.19+ atau 22.12+** dan npm
- PostgreSQL (lokal, atau database terpisah seperti Supabase)

### Langkah

```bash
git clone https://github.com/agisadrian/nubiko-restock.git
cd nubiko-restock

composer install
npm install

cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

Atur koneksi database di `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nubiko_restock
DB_USERNAME=postgres
DB_PASSWORD=
```

Lalu jalankan:

```bash
php artisan migrate
npm run dev                 # terminal 1
php artisan serve           # terminal 2, buka http://127.0.0.1:8000
```

Jika memakai Laragon, taruh folder project di `C:\laragon\www` dan akses lewat `http://nubiko-restock.test`.

> **Peringatan:** jangan menjalankan `php artisan migrate:fresh`, `migrate:refresh`, atau `db:wipe` ke database produksi. Gunakan database lokal atau database terpisah untuk pengembangan.

### Ke database Railway dari laptop

Host internal Railway (`*.railway.internal`) tidak bisa diakses dari luar. Aktifkan **TCP Proxy** di service Postgres, lalu pakai host dan port publiknya. Ingat bahwa itu data **produksi**: jangan `migrate` dari lokal, dan gunakan key Midtrans/kredensial sandbox bila ada.

---

## Deploy ke Railway

Project ini dideploy dari GitHub ke Railway, dan **migrasi database dijalankan otomatis saat deploy**.

1. Commit dan push perubahan:
    ```bash
    git add .
    git commit -m "pesan perubahan"
    git push
    ```
2. Railway membangun dan men-deploy otomatis. Pantau di **Deployments** sampai status **Active**, dan cek log untuk baris migration berstatus `DONE`.
3. Refresh halaman dengan **Ctrl+F5** setelah deploy selesai.

Variabel environment diatur di tab **Variables** pada service Laravel, bukan di file `.env`. File `.env` **tidak boleh** di-commit.

Migrasi yang menambah atau mengisi data (mis. `seed_master_products`, `rebuild_master_products`) memakai _upsert_ berdasarkan SKU, jadi aman dijalankan ulang dan tidak membuat duplikat.

---

## Struktur Folder

```
app/
  Exports/                      Class export (Excel/CSV/PDF)
  Http/Controllers/
    AuthController.php          Login, daftar, logout
    ProductController.php       Master Product (CRUD, generate dari restock)
    ProdukController.php        Perhitungan sisa stok per produk
    RestockController.php       Restock (CRUD, import, export, status)
    StockOutController.php      Stock out (CRUD, import, export, grafik)
    UserController.php          Persetujuan pengguna
    WarehouseController.php     Data per gudang
  Models/                       Product, Restock, StockOut, User
database/migrations/            Struktur tabel dan migrasi data
resources/js/Pages/             Dashboard, Login, Register, MasterProduk,
                                Produk, Restock, StockOut, Warehouse, Users
routes/web.php                  Seluruh route halaman dan API
```

---

## Endpoint API

Semua endpoint berada di bawah prefiks `/api` dan memerlukan login. Daftar lengkap ada di `routes/web.php`.

**Master Product**

| Method | Path                              | Keterangan                                    |
| ------ | --------------------------------- | --------------------------------------------- |
| GET    | `/products`                       | Daftar produk (`?search=`).                   |
| POST   | `/products`                       | Tambah produk.                                |
| PATCH  | `/products/{id}`                  | Ubah produk.                                  |
| DELETE | `/products/{id}`                  | Hapus produk (**admin**).                     |
| POST   | `/products/generate-from-restock` | Buat produk dari nama di data restock.        |
| GET    | `/products/options`               | Opsi produk (SKU, nama, tipe) untuk dropdown. |
| GET    | `/products/list-names`            | Daftar nama produk.                           |

**Restock**

| Method | Path                                | Keterangan                                                                           |
| ------ | ----------------------------------- | ------------------------------------------------------------------------------------ |
| GET    | `/restock`                          | Daftar, mendukung `search`, `status`, `nama_produk`, `date_from`, `date_to`, `page`. |
| GET    | `/restock/export-{excel\|csv\|pdf}` | Export, mengikuti filter yang sama.                                                  |

Endpoint tambah, ubah status, hapus, dan import restock juga ada di `RestockController`.

**Stock Out**

| Method | Path                                  | Keterangan                                                                 |
| ------ | ------------------------------------- | -------------------------------------------------------------------------- |
| GET    | `/stock-out`                          | Daftar, mendukung `search`, `nama_produk`, `date_from`, `date_to`, `page`. |
| POST   | `/stock-out`                          | Tambah satu data.                                                          |
| DELETE | `/stock-out/{id}`                     | Hapus (**admin**).                                                         |
| GET    | `/stock-out/chart-stats`              | Data grafik.                                                               |
| POST   | `/stock-out/import-preview`           | Unggah file dan lihat pratinjau validasi.                                  |
| POST   | `/stock-out/import-confirm`           | Simpan baris yang valid.                                                   |
| GET    | `/stock-out/export-{excel\|csv\|pdf}` | Export.                                                                    |

**Stok**

| Method | Path      | Keterangan                                                         |
| ------ | --------- | ------------------------------------------------------------------ |
| GET    | `/produk` | Total masuk, total keluar, sisa stok, dan stok minimum per produk. |

---

## Catatan Data dan Perawatan

- **Nama produk adalah kunci penghubung.** Kalau satu produk punya dua penulisan nama (mis. nama lama dan nama baru setelah rename), stoknya terpecah dan salah satu bisa tampil minus. Gabungkan jadi satu nama di Master Product dan di file import.
- **Menghapus produk dari master tidak menghapus riwayatnya.** Hapus juga baris restock dan stock out produk itu agar tidak ada nama yatim di halaman Produk. Contoh query ada di bawah.
- **Stok minus** berarti restock produk itu belum tercatat (atau masih berstatus Belum Inbound), bukan kesalahan sistem.
- Simpan cadangan sebelum operasi massal, misalnya salin tabel di database yang sama:
    ```sql
    CREATE TABLE restocks_bak AS SELECT * FROM restocks;
    ```

### Query perawatan

Hapus semua restock satu produk berdasarkan SKU:

```sql
DELETE FROM restocks WHERE sku = 'AUTO-0014';
```

Hapus produk beserta restock dan stock out-nya dalam satu perintah:

```sql
WITH produk AS (
  SELECT sku, nama_produk FROM products WHERE sku = 'AUTO-0014'
),
hapus_restock AS (
  DELETE FROM restocks
  WHERE sku IN (SELECT sku FROM produk)
     OR nama_produk IN (SELECT nama_produk FROM produk)
),
hapus_stock_out AS (
  DELETE FROM stock_outs
  WHERE sku IN (SELECT sku FROM produk)
     OR nama_produk IN (SELECT nama_produk FROM produk)
)
DELETE FROM products WHERE sku IN (SELECT sku FROM produk);
```

Cek jumlah data:

```sql
SELECT (SELECT count(*) FROM products)  AS produk,
       (SELECT count(*) FROM restocks)  AS restock,
       (SELECT count(*) FROM stock_outs) AS stock_out;
```

> Editor query bawaan Railway menambahkan `LIMIT` di akhir perintah. Jalankan perintah tulis satu per satu, tanpa `BEGIN`/`COMMIT`, dan tanpa titik koma di akhir. Jika masih error, gunakan HeidiSQL atau `psql` dengan alamat publik database.

---

## Troubleshooting

| Masalah                                                                | Penyebab dan solusi                                                                                                        |
| ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `composer install` gagal: _requires php ^8.3_                          | PHP lokal masih 8.1/8.2. Pasang PHP 8.3+ dan pilih versinya di Laragon.                                                    |
| _The openssl extension is required_                                    | Aktifkan `extension=openssl` dan `extension_dir` di `php.ini` yang dibaca PHP (`php --ini`).                               |
| `npm run dev` error `styleText`                                        | Node terlalu lama. Pakai Node 20.19+ atau 22.12+.                                                                          |
| _could not find driver_                                                | Aktifkan `pdo_pgsql` dan `pgsql` di `php.ini`.                                                                             |
| Halaman Stock Out putih                                                | Endpoint `/products/options` error 500 (kolom `tipe` belum ada di tabel `products`). Pastikan semua migrasi sudah jalan.   |
| Pratinjau import: _belum pernah di-restock_                            | Produk itu belum punya satu pun data restock. Import restock-nya dulu.                                                     |
| Pratinjau import restock menunjukkan produk baru yang tidak diharapkan | Nama di file tidak persis sama dengan Master Product. Samakan namanya dulu.                                                |
| Baris import bernama produk berkoma terbaca salah                      | CSV tersimpan ulang lewat Excel. Ekspor ulang sebagai CSV UTF-8.                                                           |
| Kolom SKU kosong di Stock Out atau Restock                             | Migrasi `add_sku_to_*` belum jalan, atau data lama belum terisi SKU-nya. Jalankan migrasinya lalu cek `WHERE sku IS NULL`. |
| `dubious ownership` saat `git`                                         | `git config --global --add safe.directory <path-project>`                                                                  |
| Semua pengunjung dianggap satu IP di Railway                           | Aplikasi ada di belakang proxy. Atur `trustProxies` di `bootstrap/app.php` jika memakai fitur berbasis IP.                 |

---

## Lisensi

Proyek internal Nubiko. Tidak untuk didistribusikan tanpa izin.
