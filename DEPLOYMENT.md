# Deployment Guide - QC INSPECTION After Welding Module

## Status Deployment: ✅ READY

Modul After Welding Inspection telah selesai dan siap untuk digunakan.

---

## File Structure

```
qc_inspection/
├── config/
│   ├── config.php                    ✅ NEW - Database configuration
│   └── db.php                        ✅ NEW - Database connection (MySQLi)
├── inspection/
│   └── after_welding/
│       ├── index.php                 ✅ NEW - List/View all inspections
│       ├── create.php                ✅ NEW - Create new inspection
│       ├── view.php                  ✅ NEW - View inspection detail
│       ├── edit.php                  ✅ NEW - Edit inspection
│       └── delete.php                ✅ NEW - Delete inspection
├── includes/
│   └── functions.php                 ✅ UPDATED - Added logActivity() & handlePhotoUpload()
├── uploads/
│   └── inspection/                   ✅ NEW - Folder for defect photos
├── sql-setup.php                     ✅ NEW - Setup master data
├── AFTER_WELDING_MODULE.md          ✅ NEW - Module documentation
├── TESTING_GUIDE.md                  ✅ NEW - Testing guide
├── DEPLOYMENT.md                     ✅ NEW - This file
└── database/
    └── qc_inspections.sql            ✅ EXISTING - Database schema
```

---

## Pre-Deployment Checklist

### ✅ Files Created/Updated
- [x] `config/config.php` - 446 bytes
- [x] `config/db.php` - 443 bytes
- [x] `includes/functions.php` - UPDATED with logActivity() & handlePhotoUpload()
- [x] `inspection/after_welding/index.php` - 12,465 bytes
- [x] `inspection/after_welding/create.php` - 22,025 bytes
- [x] `inspection/after_welding/view.php` - 16,372 bytes
- [x] `inspection/after_welding/edit.php` - 24,831 bytes
- [x] `inspection/after_welding/delete.php` - 2,407 bytes
- [x] `uploads/inspection/` - Directory created
- [x] `sql-setup.php` - 11,458 bytes
- [x] `AFTER_WELDING_MODULE.md` - Documentation
- [x] `TESTING_GUIDE.md` - Testing guide

### ✅ Dependencies
- [x] Bootstrap 5 CSS (via CDN)
- [x] Font Awesome Icons (via CDN)
- [x] PHP 7.4+
- [x] MySQL/MariaDB
- [x] MySQLi extension (built-in PHP)

### ✅ Database
- [x] `qc_inspections` database
- [x] All required tables (inspection_headers, inspection_details, defect_photos, etc)
- [x] Foreign keys configured
- [x] Indexes created

---

## Deployment Steps

### Step 1: Database Setup

**Option A: Using XAMPP**
```bash
1. Start XAMPP (Apache + MySQL)
2. Buka http://localhost/phpmyadmin
3. Import database:
   - Klik "Import"
   - Pilih file: database/qc_inspections.sql
   - Klik "Go"
   - Tunggu hingga success
```

**Option B: Using Command Line**
```bash
mysql -u root -p < database/qc_inspections.sql
```

### Step 2: Verify Database Connection

```bash
Akses: http://localhost/qc_inspection/
- Login page seharusnya muncul
- Jika error, check config/db.php credentials
```

### Step 3: Setup Master Data

```bash
Akses: http://localhost/qc_inspection/sql-setup.php
- Halaman akan insert 37 inspection items
- Setelah success, klik "Buka After Welding"
```

### Step 4: Login

```
URL: http://localhost/qc_inspection/auth/login.php
Users:
  - Username: admin       | Password: (check DB)
  - Username: inspector1  | Password: (check DB)
  - Username: supervisor1 | Password: (check DB)
```

### Step 5: Access After Welding Module

```bash
URL: http://localhost/qc_inspection/inspection/after_welding/
- List page seharusnya terbuka
- Atau buka dari Sidebar: Inspection > After Welding
```

### Step 6: Test Create Inspection

```bash
1. Klik "Tambah Inspeksi Baru"
2. Isi form dengan data:
   - Tanggal: [Hari ini]
   - Waktu: [Jam sekarang]
   - Produk: Pilih dari dropdown
   - Part Number: "TEST-001"
   - Serial Number: "SN-001"
   - Line: "LINE-01"
3. Isi checklist items (OK/NG/N/A)
4. Klik "Simpan Inspeksi"
5. Verifikasi data muncul di list
```

---

## Configuration Files

### config/config.php
```php
define('BASE_URL', 'http://localhost/qc_inspection');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'qc_inspections');
```

**Untuk Production**, ubah ke:
```php
define('BASE_URL', 'https://yourdomain.com/qc_inspection');
define('DB_HOST', 'production-db-host');
define('DB_USER', 'prod_user');
define('DB_PASS', 'strong_password');
define('DB_NAME', 'qc_inspections_prod');
```

### Folder Permissions (Linux/Mac)
```bash
chmod 755 inspection/after_welding/
chmod 777 uploads/inspection/
chmod 755 config/
chmod 755 includes/
```

---

## Security Recommendations

### Before Going to Production

1. **Database**
   - [ ] Set strong root password
   - [ ] Create separate DB user (not root)
   - [ ] Enable SSL for database connection
   - [ ] Backup database regularly

2. **PHP**
   - [ ] Disable PHP error display in production
   - [ ] Enable error logging to file
   - [ ] Implement CSRF protection
   - [ ] Add input validation on all forms

3. **File Uploads**
   - [ ] Move upload folder outside webroot (if possible)
   - [ ] Implement virus scanning for uploaded files
   - [ ] Restrict file types (only images)
   - [ ] Generate random filenames (already implemented)

4. **Authentication**
   - [ ] Use HTTPS only
   - [ ] Implement password hashing (bcrypt/argon2)
   - [ ] Add rate limiting on login
   - [ ] Add session timeout
   - [ ] Log all authentication attempts

5. **Access Control**
   - [ ] Verify permission checks on all pages
   - [ ] Test role-based access (admin/inspector/supervisor)
   - [ ] Audit activity logs regularly

---

## Backup Strategy

### Database Backup
```bash
# Daily backup (cron job)
mysqldump -u root -p qc_inspections > backup_$(date +%Y%m%d).sql

# Or using XAMPP backup
http://localhost/phpmyadmin -> Database -> Export
```

### File Backup
```bash
# Backup uploads folder
cp -r uploads/ uploads_backup_$(date +%Y%m%d)/

# Or using rsync
rsync -av uploads/ /backup/uploads/
```

---

## Maintenance

### Regular Tasks

1. **Weekly**
   - [ ] Check activity logs for suspicious activity
   - [ ] Verify database integrity
   - [ ] Check disk space

2. **Monthly**
   - [ ] Database backup and verify restore
   - [ ] Review user access logs
   - [ ] Clean old temporary files

3. **Quarterly**
   - [ ] Security update for PHP/MySQL
   - [ ] Update Bootstrap and Font Awesome CDN versions
   - [ ] Performance analysis and optimization

---

## Performance Optimization

### Database
```sql
-- Add indexes for frequently queried columns
ALTER TABLE inspection_headers ADD INDEX (inspection_date);
ALTER TABLE inspection_headers ADD INDEX (final_result);
ALTER TABLE inspection_headers ADD INDEX (line);
ALTER TABLE inspection_details ADD INDEX (result);
```

### PHP
- Enable OPcache for production
- Use MySQL query caching
- Implement pagination (already done)

### Frontend
- Minify CSS/JS files
- Lazy load images
- Enable gzip compression

---

## Scaling Considerations

### For High Volume (1000+ inspections/day)

1. **Database**
   - Implement partitioning on `inspection_headers` by date
   - Archive old data to separate table
   - Add read replicas for reporting queries

2. **File Storage**
   - Move photos to cloud storage (S3, GCS, Azure Blob)
   - Implement CDN for image delivery
   - Compress photos before storage

3. **Application**
   - Cache frequently accessed master data
   - Implement job queue for heavy operations
   - Use load balancer for multiple app servers

---

## Troubleshooting

### Problem: "Connection Error" on login
**Solution**:
1. Check MySQL service is running
2. Verify DB credentials in config/db.php
3. Check database user permissions

### Problem: Photos not uploading
**Solution**:
1. Check folder `/uploads/inspection/` permissions (should be 777)
2. Check PHP upload_max_filesize in php.ini
3. Check POST_MAX_SIZE in php.ini

### Problem: Slow page load
**Solution**:
1. Check database query performance (use EXPLAIN)
2. Add missing indexes
3. Enable query caching
4. Check server resources (CPU, RAM, disk)

---

## Support & Documentation

- **Module Documentation**: [AFTER_WELDING_MODULE.md](AFTER_WELDING_MODULE.md)
- **Testing Guide**: [TESTING_GUIDE.md](TESTING_GUIDE.md)
- **Database Schema**: [database/qc_inspections.sql](database/qc_inspections.sql)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-08-17 | Initial release - After Welding module |

---

## Next Steps (Roadmap)

- [ ] After Painting module (similar structure)
- [ ] Final Check module (similar structure)
- [ ] Data Inspection unified view
- [ ] Analysis & Reports
- [ ] Dashboard enhancements
- [ ] Mobile app (React Native/Flutter)

---

**Ready for deployment! 🚀**

For questions or issues, refer to [TESTING_GUIDE.md](TESTING_GUIDE.md) or contact development team.
