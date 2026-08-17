# QC INSPECTION - After Welding Module

## Modul After Welding Inspection Telah Selesai ✅

### File yang telah dibuat:

1. **`inspection/after_welding/index.php`** - Halaman list/view data inspeksi
   - Menampilkan tabel semua inspeksi After Welding
   - Statistik: Total, PASS, NG, HOLD
   - Tombol untuk tambah, view, edit, dan delete
   - Pagination otomatis

2. **`inspection/after_welding/create.php`** - Form input inspeksi baru
   - Section 1: Informasi Inspeksi (Header)
     - Tanggal, Waktu, Produk, Part Number, Serial Number
     - Production Order, Lot Number, Line, Shift, Catatan
   - Section 2: Checklist Inspeksi
     - Tabel dinamis dari master `inspection_items` (After Welding)
     - Kolom: No, Item, Standard, Metode, Hasil (OK/NG/N/A)
     - Defect, Lokasi Defect, Foto, Catatan
     - Field defect/foto/lokasi hanya enabled jika Hasil=NG (JavaScript)
   - Auto-numbering: AW-YYYYMMDD-0001
   - Simpan ke `inspection_headers` dan `inspection_details`
   - Upload foto ke `/uploads/inspection/`
   - Auto-calculate final_result: PASS jika semua OK, NG jika ada NG

3. **`inspection/after_welding/view.php`** - Lihat detail inspeksi
   - Tampilkan informasi lengkap dari header
   - Tabel detail dengan hasil inspeksi
   - Modal untuk view foto defect
   - Audit info (created_at, updated_at)
   - Tombol kembali, edit, hapus

4. **`inspection/after_welding/edit.php`** - Edit inspeksi
   - Form mirip dengan create.php
   - Pre-fill data dari database
   - Update header dan re-insert detail
   - Re-upload foto (opsional)
   - Recalculate final_result

5. **`inspection/after_welding/delete.php`** - Hapus inspeksi
   - Hapus dari database (cascade delete)
   - Hapus file foto dari server
   - Log activity

### Helper Functions (di `includes/functions.php`):

1. **`logActivity($db, $user_id, $activity, $module, $reference_id)`**
   - Mencatat setiap aksi (Create, Update, Delete) ke `activity_logs`
   - Menyimpan IP address user

2. **`handlePhotoUpload($file, $detail_id, $db)`**
   - Upload foto defect ke `/uploads/inspection/`
   - Validasi: max 2MB, tipe JPEG/PNG/GIF/WebP
   - Generate unique filename: `defect_YYYYMMDD_HHMMSS_RANDOM.ext`
   - Insert ke `defect_photos` table

### Fitur Utama:

✅ **Auto-numbering**: AW-YYYYMMDD-0001, AW-YYYYMMDD-0002, dst
✅ **Dynamic Checklist**: Load dari master inspection_items (After Welding)
✅ **Conditional Fields**: Defect/Foto/Lokasi hanya aktif jika NG
✅ **Photo Upload**: Upload multiple foto per inspeksi
✅ **Auto Final Result**: PASS/NG automatic calculation
✅ **CRUD Lengkap**: Create, Read, Update, Delete
✅ **Activity Logging**: Setiap aksi dicatat
✅ **Pagination**: List data dengan pagination
✅ **Responsive**: Bootstrap 5 responsive design

### Database Structure:

**inspection_headers**
```
- id (PK)
- inspection_no (AW-YYYYMMDD-0001)
- inspection_type (After Welding)
- inspection_date, inspection_time
- product_id, part_number, serial_number
- production_order, lot_number
- line, shift
- inspector_id
- final_result (PASS/NG/HOLD)
- remark
- created_at, updated_at
```

**inspection_details**
```
- id (PK)
- inspection_header_id (FK)
- inspection_item_id (FK)
- standard, method
- result (OK/NG/N/A)
- defect_id (FK)
- defect_location
- remark
- created_at
```

**defect_photos**
```
- id (PK)
- inspection_detail_id (FK)
- file_name
- file_path
- uploaded_at
```

### Penggunaan:

1. **Buka halaman list**: `http://localhost/qc_inspection/inspection/after_welding/`
2. **Klik "Tambah Inspeksi Baru"** untuk input inspeksi
3. **Isi form header** (produk, part number, dll)
4. **Isi checklist** dengan hasil inspeksi
5. **Jika ada NG**, pilih defect, lokasi, dan upload foto
6. **Klik Simpan**
7. **Lihat hasil** di halaman list atau detail

### Menu Sidebar:

Menu "After Welding" sudah ada di sidebar (Inspection > After Welding)

### File Konfigurasi:

✅ `config/config.php` - Database config, BASE_URL
✅ `config/db.php` - MySQLi connection
✅ `includes/functions.php` - Helper functions (updated)

### Next Step:

Setelah After Welding selesai, kita akan membuat:
- After Painting (mirip struktur)
- Final Check (mirip struktur)
- Data Inspection View (unified view semua inspeksi)
- Analysis & Reports

---

**Setup Database**:
1. Buka phpMyAdmin: `http://localhost/phpmyadmin`
2. Import file: `database/qc_inspections.sql`
3. Pastikan master data sudah ada (inspection_items, defects, products)

**Catatan Penting**:
- Foto diupload ke `/uploads/inspection/`
- Ensure folder permissions: `777` (writable)
- Max file size: 2MB
- Supported formats: JPEG, PNG, GIF, WebP
