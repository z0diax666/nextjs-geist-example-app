# Office Order Generator - Complete Setup Guide

This guide will help you set up the complete Office Order Document Generator system with XAMPP backend and Next.js frontend.

## 🎯 System Overview

- **Frontend**: Next.js 15 with TypeScript, Tailwind CSS, and shadcn/ui
- **Backend**: PHP with MySQL (XAMPP)
- **Database**: MySQL with comprehensive schema
- **Features**: Template management, document generation, role-based access, audit logging

## 📋 Prerequisites

1. **XAMPP** (Apache + MySQL + PHP)
   - Download from: https://www.apachefriends.org/
   - PHP 7.4 or higher required

2. **Node.js** (for frontend)
   - Download from: https://nodejs.org/
   - Version 18 or higher recommended

3. **Git** (optional, for version control)

## 🚀 Step-by-Step Installation

### Step 1: Install XAMPP

1. Download and install XAMPP
2. Start XAMPP Control Panel
3. Start **Apache** and **MySQL** services
4. Verify installation by visiting `http://localhost`

### Step 2: Setup Database

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create a new database named `office_order_generator`
3. Import the database schema:
   - Click on the database name
   - Go to "Import" tab
   - Select `backend/database/schema.sql`
   - Click "Go" to execute

### Step 3: Setup Backend (PHP)

1. Copy the `backend` folder to XAMPP's htdocs directory:
   ```
   C:\xampp\htdocs\office-order-backend\
   ```

2. Rename `backend/htaccess.txt` to `backend/.htaccess`

3. Create required directories:
   ```
   backend/uploads/signatures/
   backend/uploads/exports/
   ```

4. Set proper permissions (if on Linux/Mac):
   ```bash
   chmod 755 backend/uploads/
   chmod 755 backend/uploads/signatures/
   chmod 755 backend/uploads/exports/
   ```

5. Test backend installation:
   - Visit: `http://localhost/office-order-backend/api/templates`
   - You should see JSON response with default templates

### Step 4: Setup Frontend (Next.js)

1. Navigate to the project root directory
2. Install dependencies:
   ```bash
   npm install --legacy-peer-deps
   ```

3. Start the development server:
   ```bash
   npm run dev
   ```

4. Open your browser and visit: `http://localhost:8000`

## 🔐 Default User Accounts

The system comes with pre-configured user accounts:

| Role | Email | Password | Permissions |
|------|-------|----------|-------------|
| Admin | admin@company.com | password123 | Full access to all features |
| Encoder | encoder@company.com | password123 | Create and edit documents |
| Approver | approver@company.com | password123 | Approve documents, view audit logs |
| Viewer | viewer@company.com | password123 | Read-only access |

## 🧪 Testing the System

### 1. Test Authentication
1. Go to `http://localhost:8000`
2. You should be redirected to the login page
3. Login with admin@company.com / password123
4. You should see the main dashboard

### 2. Test Template Management
1. Click on "Template Manager" tab
2. You should see existing templates
3. Try creating a new template with variables like `{{employee_name}}`

### 3. Test Document Generation
1. Click on "Document Generator" tab
2. Select a template from the dropdown
3. Fill in the form fields
4. Check the live preview updates in real-time

### 4. Test Export Functionality
1. After filling a document form
2. Click "Export as PDF" or "Export as Word"
3. Files should be generated in `backend/uploads/exports/`

## 🔧 Configuration

### Backend Configuration

Edit `backend/config/database.php` if needed:
```php
private $host = 'localhost';
private $db_name = 'office_order_generator';
private $username = 'root';
private $password = ''; // Default XAMPP password is empty
```

### Frontend Configuration

The frontend is already configured to work with the PHP backend. The API base URL is set to:
```typescript
const API_BASE_URL = 'http://localhost/office-order-backend/api'
```

## 📁 Project Structure

```
office-order-generator/
├── frontend/
│   ├── src/
│   │   ├── app/
│   │   │   ├── page.tsx          # Main dashboard
│   │   │   ├── login/page.tsx    # Login page
│   │   │   └── layout.tsx        # Root layout
│   │   ├── components/
│   │   │   ├── Dashboard.tsx     # Main app interface
│   │   │   └── ProtectedRoute.tsx # Auth wrapper
│   │   ├── hooks/
│   │   │   └── useAuth.tsx       # Authentication hook
│   │   └── lib/
│   │       ├── api.ts            # API client
│   │       └── types.ts          # TypeScript types
│   └── package.json
├── backend/
│   ├── api/
│   │   ├── auth.php              # Authentication
│   │   ├── templates.php         # Template management
│   │   ├── documents.php         # Document generation
│   │   ├── audit-logs.php        # Audit logging
│   │   └── employees.php         # Employee data
│   ├── config/
│   │   └── database.php          # Database config
│   ├── database/
│   │   └── schema.sql            # Database schema
│   ├── uploads/
│   │   ├── signatures/           # Digital signatures
│   │   └── exports/             # Generated files
│   └── .htaccess                # Apache config
└── SETUP_GUIDE.md               # This file
```

## 🛠️ Troubleshooting

### Common Issues

#### 1. CORS Errors
**Problem**: Frontend can't connect to backend
**Solution**: 
- Ensure `.htaccess` file exists in backend folder
- Check that Apache mod_headers is enabled
- Verify CORS headers in `.htaccess`

#### 2. Database Connection Failed
**Problem**: Backend can't connect to MySQL
**Solution**:
- Ensure MySQL is running in XAMPP
- Check database name and credentials
- Verify database `office_order_generator` exists

#### 3. 404 Errors on API Calls
**Problem**: API endpoints return 404
**Solution**:
- Ensure mod_rewrite is enabled in Apache
- Check `.htaccess` file is properly configured
- Verify backend folder is in correct location

#### 4. Login Not Working
**Problem**: Authentication fails
**Solution**:
- Check browser console for errors
- Verify backend API is accessible
- Ensure database has user records

#### 5. File Upload Issues
**Problem**: Digital signature upload fails
**Solution**:
- Check PHP upload limits in `php.ini`
- Ensure uploads directory has write permissions
- Verify file size and type restrictions

### Debug Steps

1. **Check Apache Error Logs**:
   - Location: `xampp/apache/logs/error.log`

2. **Check PHP Errors**:
   - Enable error reporting in `config/database.php`

3. **Test API Endpoints**:
   - Use browser or Postman to test individual endpoints

4. **Check Database**:
   - Verify tables exist in phpMyAdmin
   - Check sample data is present

## 🔒 Security Considerations

### For Development
- Default passwords are simple for testing
- Error reporting is enabled for debugging
- CORS is open for localhost

### For Production
- Change all default passwords
- Implement proper password hashing
- Set up SSL certificates
- Configure proper CORS policies
- Disable error reporting
- Set up database backups
- Implement rate limiting

## 📈 Performance Optimization

1. **Database Optimization**:
   - Add indexes on frequently queried columns
   - Optimize JOIN queries
   - Implement query caching

2. **File Management**:
   - Implement file cleanup for old exports
   - Compress uploaded images
   - Use CDN for static assets

3. **Caching**:
   - Implement template caching
   - Use browser caching for static assets
   - Consider Redis for session management

## 🚀 Deployment

### Development Environment
- Use XAMPP for local development
- Keep error reporting enabled
- Use localhost URLs

### Production Environment
- Use proper web server (Apache/Nginx)
- Set up SSL certificates
- Configure environment variables
- Implement proper logging
- Set up automated backups

## 📞 Support

If you encounter issues:

1. Check this troubleshooting guide
2. Review Apache and PHP error logs
3. Test individual API endpoints
4. Verify database connectivity
5. Check file permissions

## 🎉 Success!

If everything is working correctly, you should be able to:

✅ Login with different user roles
✅ Create and manage templates (as Admin)
✅ Generate documents with live preview
✅ Export documents as PDF/Word
✅ Upload digital signatures
✅ View document history and audit logs
✅ Search and integrate employee data

The system is now ready for use by HR staff and administrators to generate standardized office orders efficiently!
