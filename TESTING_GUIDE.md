# Testing Guide - After Welding Inspection Module

## Setup Awal

### 1. Import Database
```
Akses: http://localhost/phpmyadmin
1. Klik "Import"
2. Pilih file: C:\xampp\htdocs\qc_inspection\database\qc_inspections.sql
3. Klik "Go"
```

### 2. Setup Master Data
```
Akses: http://localhost/qc_inspection/sql-setup.php
- Script ini akan insert 37 inspection items untuk:
  * After Welding (20 items)
  * After Painting (10 items)
  * Final Check (7 items)
- Klik tombol setelah selesai
```

### 3. Login
```
URL: http://localhost/qc_inspection/auth/login.php
Username: admin / qc_inspector / supervisor
Password: (check database users table)
```

---

## Test Scenarios

### Test 1: View After Welding List
**URL**: `http://localhost/qc_inspection/inspection/after_welding/`

**Expected Result**:
- [ ] Halaman terbuka dengan benar
- [ ] Menampilkan tabel dengan kolom: No Inspeksi, Tanggal, Produk, Part Number, Serial Number, Line, Inspector, Hasil
- [ ] Statistik card menampilkan: Total, PASS, NG, HOLD (awalnya semua 0)
- [ ] Tombol "Tambah Inspeksi Baru" tersedia
- [ ] Menu sidebar "After Welding" highlight

---

### Test 2: Create Inspection (PASS Result)
**URL**: `http://localhost/qc_inspection/inspection/after_welding/` → Klik "Tambah Inspeksi Baru"

**Form Fields to Fill**:
- Tanggal Inspeksi: [Hari ini]
- Waktu Inspeksi: [Jam sekarang]
- Produk: Pilih salah satu (misal: "PRD-FUEL-001 - Fuel Tank")
- Part Number: "FT-2024-001"
- Serial Number: "SN-001"
- Production Order: "PO-2024-08-001"
- Lot Number: "LOT-001"
- Line: "LINE-01"
- Shift: "Shift 1"
- Catatan: "Inspeksi normal"

**Checklist Inspection**:
- Semua item harusnya menampilkan dari database
- Ubah semua hasil menjadi "OK"
- Defect/Lokasi/Foto fields harusnya DISABLED (tidak aktif)
- Klik "Simpan Inspeksi"

**Expected Result**:
- [ ] Form berhasil disimpan
- [ ] Redirect ke halaman list dengan pesan success
- [ ] Data baru muncul di tabel (No: AW-YYYYMMDD-0001)
- [ ] Status badge menunjukkan "PASS"
- [ ] Statistik PASS count bertambah menjadi 1

---

### Test 3: Create Inspection (NG Result with Defect)
**URL**: `http://localhost/qc_inspection/inspection/after_welding/` → Klik "Tambah Inspeksi Baru"

**Form Fields to Fill**:
- Tanggal Inspeksi: [Hari ini]
- Waktu Inspeksi: [Jam sekarang]
- Produk: Pilih salah satu
- Part Number: "FT-2024-002"
- Serial Number: "SN-002"
- Production Order: "PO-2024-08-002"
- Lot Number: "LOT-002"
- Line: "LINE-02"
- Shift: "Shift 2"

**Checklist Inspection**:
- Item 1 (Weld Appearance): Ubah ke "NG"
  - Defect field seharusnya ENABLED
  - Lokasi Defect field seharusnya ENABLED
  - Foto field seharusnya ENABLED
  - Pilih Defect: "D001 - Crack"
  - Lokasi: "Pada sudut sambungan kiri"
  - Catatan: "Retak halus"
  - Upload Foto: Pilih file gambar (.jpg, .png)
- Item 2-20: Ubah ke "OK"
- Klik "Simpan Inspeksi"

**Expected Result**:
- [ ] Form berhasil disimpan
- [ ] Redirect ke halaman list dengan pesan success
- [ ] Data baru muncul di tabel (No: AW-YYYYMMDD-0002)
- [ ] Status badge menunjukkan "NG"
- [ ] Statistik NG count bertambah menjadi 1
- [ ] Foto sudah terupload ke folder `/uploads/inspection/`

---

### Test 4: View Inspection Detail
**URL**: Dari tabel, klik icon "View" (mata) pada salah satu record

**Expected Result**:
- [ ] Halaman detail terbuka
- [ ] Menampilkan semua informasi header (Produk, Part Number, Serial, dll)
- [ ] Menampilkan final result: PASS atau NG atau HOLD
- [ ] Tabel detail menampilkan semua checklist items dengan hasil
- [ ] Jika ada NG, tampilkan defect name dan lokasi
- [ ] Jika ada foto, tombol "View" untuk modal foto tersedia
- [ ] Klik tombol View foto menampilkan modal dengan gambar
- [ ] Audit info menampilkan Created dan Updated timestamp
- [ ] Tombol "Kembali", "Edit", "Hapus" tersedia

---

### Test 5: Edit Inspection
**URL**: Dari view detail, klik tombol "Edit"

**Expected Result**:
- [ ] Form terbuka dengan data pre-filled
- [ ] Semua field dapat diubah
- [ ] Checklist items ter-load dengan hasil sebelumnya
- [ ] Ubah salah satu item dari "OK" menjadi "NG"
  - Pilih defect baru
  - Ubah lokasi
  - Upload foto baru (opsional)
- [ ] Ubah item NG menjadi "OK"
  - Defect/Lokasi/Foto field harusnya dikosongkan & disabled
- [ ] Klik "Simpan Perubahan"

**Expected Result**:
- [ ] Form berhasil disimpan
- [ ] Redirect ke halaman detail dengan pesan success
- [ ] Data sudah terupdate
- [ ] Final result recalculated (jika ada NG, status jadi NG)

---

### Test 6: Delete Inspection
**URL**: Dari view detail, klik tombol "Hapus"

**Expected Result**:
- [ ] Confirmation dialog muncul
- [ ] Klik "OK" untuk confirm
- [ ] Record dihapus dari database
- [ ] Redirect ke halaman list dengan pesan success
- [ ] Data sudah hilang dari tabel
- [ ] Statistik count berkurang
- [ ] Foto di folder `/uploads/inspection/` terhapus

---

### Test 7: Form Validation
**URL**: Klik "Tambah Inspeksi Baru" → Klik "Simpan Inspeksi" tanpa isi field

**Expected Result**:
- [ ] Alert error menampilkan: "Tanggal Inspeksi harus diisi", dll
- [ ] Form tidak disimpan
- [ ] Data kembali ke form view

---

### Test 8: Pagination
**URL**: Jika sudah ada >10 data, scroll down di halaman list

**Expected Result**:
- [ ] Pagination navigation muncul
- [ ] Tombol: First, Previous, 1, 2, 3..., Next, Last
- [ ] Klik nomor halaman menampilkan data halaman itu
- [ ] Klik Next/Previous berfungsi dengan baik

---

### Test 9: Conditional Field Enabling (JavaScript)
**URL**: Form create → Ubah result item

**Expected Result**:
- [ ] Jika result = "OK": Defect, Lokasi, Foto fields DISABLED
- [ ] Jika result = "NG": Defect, Lokasi, Foto fields ENABLED
- [ ] Jika result = "N/A": Defect, Lokasi, Foto fields DISABLED
- [ ] Ubah dari NG ke OK: Fields dikosongkan & di-disable
- [ ] Perubahan real-time (tidak perlu reload halaman)

---

### Test 10: Photo Upload
**URL**: Form create → Item dengan result NG → Upload foto

**Expected Result**:
- [ ] Dapat memilih file image (JPG, PNG, GIF, WebP)
- [ ] Validasi: File max 2MB
- [ ] Jika file >2MB: Error message "Ukuran file terlalu besar"
- [ ] Jika file non-image (txt, pdf): Error message "Tipe file tidak didukung"
- [ ] Form submit: File berhasil diupload
- [ ] File tersimpan di: `/uploads/inspection/defect_YYYYMMDD_HHMMSS_XXXX.jpg`
- [ ] Path tersimpan di database `defect_photos` table

---

### Test 11: Activity Logging
**Database Check**: 
```sql
SELECT * FROM activity_logs WHERE module = 'After Welding Inspection' ORDER BY created_at DESC LIMIT 10;
```

**Expected Result**:
- [ ] Setiap aksi Create, Update, Delete tercatat
- [ ] Kolom activity menampilkan: "Create", "Update", "Delete"
- [ ] Kolom reference_id menampilkan: Inspection number (AW-YYYYMMDD-XXXX)
- [ ] Kolom ip_address menampilkan: IP address user
- [ ] Timestamp tercatat dengan benar

---

### Test 12: Permission Check
**Test as QC Inspector**:
- [ ] Dapat CREATE inspeksi
- [ ] Dapat VIEW semua inspeksi
- [ ] Dapat EDIT inspeksi sendiri & orang lain
- [ ] Dapat DELETE inspeksi

**Test as Supervisor**:
- [ ] Dapat VIEW semua inspeksi
- [ ] TIDAK dapat CREATE
- [ ] TIDAK dapat EDIT
- [ ] TIDAK dapat DELETE

---

## Checklist Persiapan Testing

### Database
- [ ] Database `qc_inspections` sudah di-import
- [ ] Table `inspection_headers` ada
- [ ] Table `inspection_details` ada
- [ ] Table `defect_photos` ada
- [ ] Table `inspection_items` ada dengan master data

### Master Data
- [ ] Master Products sudah ada (minimal 2 produk)
- [ ] Master Defects sudah ada (minimal 3 defect)
- [ ] Master Inspection Items sudah ada (37 items dari sql-setup.php)

### Users
- [ ] User admin ada (username: admin)
- [ ] User qc_inspector ada
- [ ] User supervisor ada

### Folders
- [ ] Folder `/uploads/inspection/` sudah dibuat
- [ ] Folder permission: 777 (writable)

### Files
- [ ] `config/config.php` ada dengan BASE_URL yang benar
- [ ] `config/db.php` ada dengan koneksi yang benar
- [ ] `includes/functions.php` sudah update dengan helper functions

---

## Troubleshooting

### Halaman Error: "Query Error"
**Cause**: Database connection error atau database belum di-import
**Solution**:
1. Cek config/db.php - pastikan DB_HOST, DB_USER, DB_PASS benar
2. Cek database sudah di-import di phpMyAdmin
3. Cek MySQL service sudah running

### Upload Foto Gagal
**Cause**: Folder `/uploads/inspection/` tidak writable
**Solution**:
1. Cek folder sudah ada
2. Set permission: `chmod 777 uploads/inspection/`
3. Atau buat manual di File Explorer dan beri write permission

### Form tidak menampilkan checklist items
**Cause**: Master data inspection_items belum di-insert
**Solution**:
1. Jalankan `http://localhost/qc_inspection/sql-setup.php`
2. Refresh halaman form

### Defect field tidak enable ketika ubah ke NG
**Cause**: JavaScript tidak berfungsi
**Solution**:
1. Buka browser console (F12)
2. Check apakah ada error JavaScript
3. Pastikan Bootstrap JS sudah ter-load
4. Refresh halaman

---

## Notes

- Testing dilakukan di environment: XAMPP, Windows
- Browser tested: Chrome, Firefox
- Database: MySQL/MariaDB
- PHP Version: 7.4+

---

**Selamat Testing! 🎉**
