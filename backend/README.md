# Office Order Generator - PHP Backend with XAMPP

This is the PHP backend for the Office Order Document Generator application, designed to work with XAMPP (Apache + MySQL + PHP).

## 🚀 Installation & Setup

### Prerequisites
- XAMPP installed on your system
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled

### Step 1: Setup XAMPP
1. Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services from XAMPP Control Panel

### Step 2: Database Setup
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `office_order_generator`
3. Import the database schema:
   - Go to the Import tab
   - Select the file `database/schema.sql`
   - Click "Go" to execute

### Step 3: Backend Installation
1. Copy the entire `backend` folder to your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\office-order-backend\
   ```

2. Rename `htaccess.txt` to `.htaccess` in the backend folder

3. Update database configuration in `config/database.php` if needed:
   ```php
   private $host = 'localhost';
   private $db_name = 'office_order_generator';
   private $username = 'root';
   private $password = ''; // Default XAMPP MySQL password is empty
   ```

### Step 4: Create Required Directories
Create the following directories in the backend folder:
```
backend/
├── uploads/
│   ├── signatures/
│   └── exports/
└── vendor/ (for future Composer dependencies)
```

### Step 5: Test the Installation
1. Open your browser and go to: `http://localhost/office-order-backend/api/templates`
2. You should see a JSON response with the default templates

## 📁 Project Structure

```
backend/
├── api/
│   ├── auth.php           # Authentication endpoints
│   ├── templates.php      # Template management
│   ├── documents.php      # Document generation
│   ├── audit-logs.php     # Audit logging
│   └── employees.php      # Employee data integration
├── config/
│   └── database.php       # Database configuration
├── database/
│   └── schema.sql         # Database schema and sample data
├── uploads/
│   ├── signatures/        # Digital signature uploads
│   └── exports/          # Generated PDF/DOCX files
├── .htaccess             # Apache configuration
└── README.md             # This file
```

## 🔌 API Endpoints

### Authentication
- `POST /api/auth` - User login
- `POST /api/auth` (action: validate) - Token validation

### Templates (Admin only)
- `GET /api/templates` - Get all templates
- `GET /api/templates/{id}` - Get specific template
- `POST /api/templates` - Create new template
- `PUT /api/templates/{id}` - Update template
- `DELETE /api/templates/{id}` - Delete template

### Documents
- `GET /api/documents` - Get user's documents
- `GET /api/documents/{id}` - Get specific document
- `POST /api/documents` - Create new document
- `GET /api/documents/{id}/preview` - Get document preview
- `GET /api/documents/{id}/export/{format}` - Export document (pdf/docx)
- `POST /api/documents/{id}/signature` - Upload digital signature

### Audit Logs (Admin/Approver only)
- `GET /api/audit-logs` - Get audit logs
- `GET /api/audit-logs?action=stats` - Get audit statistics
- `GET /api/audit-logs?action=export` - Export audit logs

### Employees
- `GET /api/employees` - Get all employees
- `GET /api/employees/{id}` - Get specific employee
- `GET /api/employees?action=search&q={term}` - Search employees
- `GET /api/employees?action=departments` - Get departments
- `GET /api/employees?action=designations` - Get designations

## 🔐 Authentication

The API uses token-based authentication. Include the token in the Authorization header:
```
Authorization: Bearer {token}
```

### Default User Accounts
- **Admin**: admin@company.com / password123
- **Encoder**: encoder@company.com / password123
- **Approver**: approver@company.com / password123
- **Viewer**: viewer@company.com / password123

## 🛡️ Security Features

- CORS protection
- SQL injection prevention using prepared statements
- File upload validation
- Role-based access control
- Audit logging for all actions
- Input sanitization and validation

## 📊 Database Schema

### Users Table
- User authentication and role management
- Roles: Admin, Encoder, Approver, Viewer

### Templates Table
- Office order templates with variable placeholders
- Version tracking and audit trail

### Documents Table
- Generated documents with filled data
- Export URLs and digital signatures
- Status tracking (draft, completed, approved)

### Audit Logs Table
- Complete activity logging
- User actions, IP addresses, timestamps
- Document and template change tracking

### Employees Table
- Employee master data
- Department and designation information
- Integration for auto-filling forms

## 🔧 Configuration

### Apache Configuration
Ensure mod_rewrite is enabled in Apache:
1. Open `httpd.conf` in XAMPP
2. Uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
3. Change `AllowOverride None` to `AllowOverride All` for your directory
4. Restart Apache

### PHP Configuration
Recommended PHP settings in `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

## 📝 Frontend Integration

Update your Next.js frontend API configuration:
```typescript
// In src/lib/api.ts
const API_BASE_URL = 'http://localhost/office-order-backend/api'
```

## 🐛 Troubleshooting

### Common Issues

1. **CORS Errors**
   - Ensure `.htaccess` file is properly configured
   - Check that mod_headers is enabled in Apache

2. **Database Connection Failed**
   - Verify MySQL is running in XAMPP
   - Check database credentials in `config/database.php`
   - Ensure database `office_order_generator` exists

3. **File Upload Issues**
   - Check PHP upload limits in `php.ini`
   - Ensure `uploads/` directory has write permissions
   - Verify file size and type restrictions

4. **API Returns 404**
   - Ensure mod_rewrite is enabled
   - Check `.htaccess` file exists and is readable
   - Verify file paths in XAMPP htdocs

### Debug Mode
Enable debug mode by setting in `config/database.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## 📈 Performance Optimization

1. **Database Indexing**
   - Add indexes on frequently queried columns
   - Optimize JOIN queries

2. **File Caching**
   - Implement caching for template data
   - Use file-based caching for static content

3. **Image Optimization**
   - Compress uploaded signature images
   - Implement image resizing

## 🔄 Backup & Maintenance

### Database Backup
```sql
mysqldump -u root -p office_order_generator > backup.sql
```

### Log Rotation
Implement log rotation for audit logs to prevent database bloat.

### File Cleanup
Regularly clean up old export files and signatures.

## 📞 Support

For issues and questions:
1. Check the troubleshooting section
2. Review Apache and PHP error logs
3. Verify database connectivity
4. Test API endpoints individually

## 🚀 Production Deployment

For production deployment:
1. Use proper SSL certificates
2. Implement proper password hashing
3. Set up database backups
4. Configure proper error logging
5. Implement rate limiting
6. Use environment variables for sensitive data
