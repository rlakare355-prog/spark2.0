# 🚀 SPARK Platform - Complete Setup Guide

## 📋 Table of Contents
1. [Environment Requirements](#environment-requirements)
2. [Installation Steps](#installation-steps)
3. [Database Configuration](#database-configuration)
4. [API Keys Setup](#api-keys-setup)
5. [Library Installation](#library-installation)
6. [Configuration Files](#configuration-files)
7. [Testing & Verification](#testing--verification)
8. [Production Deployment](#production-deployment)

## 🔧 Environment Requirements

### Minimum Requirements
- **PHP**: 8.0+ with required extensions
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx 1.8+
- **SSL/TLS**: Valid certificate for production
- **Memory**: Minimum 256MB, Recommended 512MB+

### Required PHP Extensions
```bash
# Check extensions
php -m | grep -E "(curl|json|mbstring|gd|pdo_mysql)"

# Install extensions (Ubuntu/Debian)
sudo apt update
sudo apt install php8.1-curl php8.1-json php8.1-mbstring php8.1-gd php8.1-pdo-mysql

# Install extensions (CentOS/RHEL)
sudo yum install php81-curl php81-json php81-mbstring php81-gd php81-pdo-mysql
```

### Web Server Requirements
```apache
# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl
sudo systemctl restart apache2
```

```nginx
# Nginx configuration example
server {
    listen 443 ssl http2;
    server_name spark.sanjivani.edu;
    root /var/www/spark;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;

    # Security Headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🚀 Installation Steps

### Step 1: Download Files
```bash
# Clone repository (if using Git)
git clone <repository-url> spark
cd spark

# Or download and extract ZIP
unzip spark.zip
cd spark
```

### Step 2: Set Permissions
```bash
# Set proper ownership and permissions
sudo chown -R www-data:www-data /var/www/spark
sudo chmod -R 755 /var/www/spark
sudo chmod -R 777 /var/www/spark/assets/
sudo chmod -R 777 /var/www/spark/assets/images/
sudo chmod -R 777 /var/www/spark/assets/qrcodes/
```

### Step 3: Create Required Directories
```bash
mkdir -p assets/images/profiles
mkdir -p assets/qrcodes
mkdir -p logs
chmod 755 assets/images/profiles
chmod 755 assets/qrcodes
chmod 755 logs
```

## 🗄 Database Configuration

### Step 1: Create Database
```sql
-- Connect to MySQL
mysql -u root -p

-- Create database
CREATE DATABASE spark_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create database user
CREATE USER 'spark_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON spark_platform.* TO 'spark_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 2: Import Schema
```bash
# Import the complete schema
mysql -u spark_user -p spark_platform < database/schema.sql

# Verify import
mysql -u spark_user -p spark_platform -e "SHOW TABLES;"
```

### Step 3: Verify Tables
```sql
-- Should show these tables:
DESCRIBE students;
DESCRIBE events;
DESCRIBE payments;
DESCRIBE certificates;
-- ... (16 tables total)
```

## 🔑 API Keys Setup

### Mailjet Configuration
1. **Create Account**
   - Visit [mailjet.com](https://www.mailjet.com)
   - Sign up for free account
   - Verify email address

2. **Generate API Keys**
   - Go to Account Settings → REST API
   - Click "Create an API Key"
   - Copy API Key and Secret

3. **Configure in Platform**
   - Option 1: Via Admin Panel → Email Settings
   - Option 2: Direct config update
   - Option 3: Use UPDATE_KEYS.php utility

### Razorpay Configuration
1. **Create Account**
   - Visit [razorpay.com](https://razorpay.com)
   - Sign up for business account
   - Complete KYC verification

2. **Generate API Keys**
   - Go to Settings → API Keys
   - Click "Generate Key"
   - Copy Key ID and Key Secret

3. **Configure in Platform**
   - Option 1: Via Admin Panel → Payment Settings
   - Option 2: Direct config update
   - Option 3: Use UPDATE_KEYS.php utility

## 📦 Library Installation

### Step 1: Install Composer
```bash
# Download Composer
curl -sS https://getcomposer.org/installer | php

# Move to global path
sudo mv composer.phar /usr/local/bin/composer

# Verify installation
composer --version
```

### Step 2: Install Dependencies
```bash
# Navigate to project directory
cd /var/www/spark

# Install required packages
composer require mailjet/mailjet-apiv3-php
composer require razorkit/razorpay
composer require endroid/qr-code

# Install development dependencies (optional)
composer require --dev phpunit/phpunit
composer require --dev phpmd/phpmd
```

### Step 3: Verify Installation
```bash
# Check composer.json
cat composer.json

# Check vendor directory
ls -la vendor/

# Test autoloading
php -r "echo 'Testing autoloading...';" && vendor/autoload.php
```

## ⚙️ Configuration Files

### Method 1: Configuration Wizard
```bash
# Run the interactive setup
php INSTALL.php

# Follow the step-by-step guide
# Check each step for completion
```

### Method 2: Manual Configuration
Create `includes/config.php`:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'spark_platform');
define('DB_USER', 'spark_user');
define('DB_PASS', 'your_actual_password_here');

// Site Configuration
define('SITE_URL', 'https://yourdomain.com/spark');
define('SITE_NAME', 'SPARK - Sanjivani Platform for AI, Research & Knowledge');

// Security Configuration
define('HASH_COST', 12);
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// API Keys (replace with actual keys)
define('MAILJET_API_KEY', 'your_actual_mailjet_key_here');
define('MAILJET_API_SECRET', 'your_actual_mailjet_secret_here');
define('MAILJET_TEST_MODE', false); // Set to true for testing

define('RAZORPAY_KEY_ID', 'your_actual_razorpay_key_id_here');
define('RAZORPAY_KEY_SECRET', 'your_actual_razorpay_secret_here');

// Email Configuration
define('FROM_EMAIL', 'noreply@sanjivani.edu');
define('FROM_NAME', 'SPARK Platform');

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_PATH', __DIR__ . '/../assets/images/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Start session
session_start();
?>
```

### Method 3: Environment Variables
```bash
# Set environment variables
export DB_HOST=localhost
export DB_NAME=spark_platform
export DB_USER=spark_user
export DB_PASS=your_password
export MAILJET_API_KEY=your_mailjet_key
export MAILJET_API_SECRET=your_mailjet_secret
export RAZORPAY_KEY_ID=your_razorpay_key
export RAZORPAY_KEY_SECRET=your_razorpay_secret

# Use in configuration
php -d "echo 'DB_HOST: ' . DB_HOST;" # Test environment variables
```

## 🧪 Testing & Verification

### Step 1: Basic Functionality Test
```bash
# Test configuration
php -r "require_once 'includes/config.php'; echo 'Configuration loaded successfully';"

# Test database connection
php deploy.php

# Test file permissions
php -r "echo is_writable('assets/images/'): ' . (is_writable('assets/images/') ? 'YES' : 'NO');"
```

### Step 2: API Testing
```bash
# Test Mailjet configuration
curl -X POST "http://yourdomain.com/spark/admin/email_settings.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"test_email","test_email":"test@example.com"}'

# Test Razorpay configuration
curl -X POST "http://yourdomain.com/spark/api/payment.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"test_connection"}'
```

### Step 3: Frontend Testing
```bash
# Test homepage
curl -I http://yourdomain.com/spark/student/index.php

# Test admin panel
curl -I http://yourdomain.com/spark/admin/login.php

# Test API endpoints
curl -X POST http://yourdomain.com/spark/api/payment.php \
  -H "Content-Type: application/json" \
  -d '{"action":"test_connection"}'
```

## 🚀 Production Deployment

### Step 1: Production Configuration
```bash
# Set production environment
export APP_ENV=production

# Optimize autoloader
composer dump-autoload --optimize --no-dev

# Clear caches
composer clear-cache
```

### Step 2: Web Server Configuration
```apache
# Apache .htaccess for production
<Files ~ "^\.">
    Require all denied
</Files>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self' 'unsafe-inline' 'unsafe-eval'; script-src 'self' 'unsafe-inline'"
</IfModule>

# PHP settings for production
<IfModule mod_php.c>
    php_flag display_errors off
    php_value error_reporting E_ALL & ~E_DEPRECATED & ~E_STRICT
    php_value error_log /var/log/spark/php_errors.log
    php_value max_execution_time 30
    php_value memory_limit 256M
    php_value upload_max_filesize 5M
    php_value post_max_size 6M
</IfModule>
```

### Step 3: SSL/TLS Configuration
```bash
# Generate SSL certificate (Let's Encrypt)
sudo certbot --apache -d spark.sanjivani.edu

# Or create self-signed for development
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/spark.key \
  -out /etc/ssl/certs/spark.crt
```

### Step 4: Performance Optimization
```bash
# Enable OPcache
sudo php -d "opcache.enable=1" -d "opcache.memory_consumption=128" -d "opcache.revalidate_freq=60"

# Configure MySQL for performance
# In my.cnf
[mysqld]
innodb_buffer_pool_size=256M
innodb_log_file_size=64M
query_cache_size=64M
query_cache_type=1
```

## 🔍 Verification Checklist

### Pre-Launch Checklist
- [ ] PHP version 8.0+ installed
- [ ] All required extensions loaded
- [ ] Composer dependencies installed
- [ ] Database created and schema imported
- [ ] API keys configured and tested
- [ ] File permissions set correctly
- [ ] SSL certificate installed
- [ ] Web server configured
- [ ] Error logging enabled
- [ ] Email notifications tested
- [ ] Payment gateway tested
- [ ] QR code generation tested

### Post-Launch Checklist
- [ ] User registration working
- [ ] Email verification sent/received
- [ ] Event registration with payments
- [ ] QR attendance scanning
- [ ] Admin panel accessible
- [ ] All API endpoints responding
- [ ] Mobile responsiveness verified
- [ ] Performance acceptable
- [ ] Error monitoring active

## 📊 Troubleshooting

### Common Issues & Solutions

#### 1. Database Connection Error
```bash
# Check MySQL service
sudo systemctl status mysql

# Check connection
mysql -u spark_user -p -e "SELECT 1;"

# Check credentials
grep DB_USER includes/config.php
```

#### 2. Permission Denied Error
```bash
# Check file ownership
ls -la /var/www/spark

# Fix permissions
sudo chown -R www-data:www-data /var/www/spark
sudo chmod -R 755 /var/www/spark
```

#### 3. Email Not Sending
```php
// Test Mailjet connection
$mailjet = new MailjetService();
$result = $mailjet->sendEmail('test@example.com', 'Test', 'Test message');
var_dump($result);
```

#### 4. Payment Gateway Error
```bash
# Test Razorpay connection
curl -X GET "https://api.razorpay.com/v1/payments" \
  -u "key_id:key_secret"

# Check logs
tail -f /var/log/spark/php_errors.log
```

#### 5. QR Code Not Generating
```bash
# Check QR library
php -r "echo class_exists('Endroid\QrCode\Builder\Builder'): " . (class_exists('Endroid\QrCode\Builder\Builder') ? 'YES' : 'NO');"

# Install missing library
composer require endroid/qr-code
```

## 🎯 Final Deployment Commands

```bash
# Complete deployment sequence
cd /var/www/spark

# 1. Optimize for production
composer dump-autoload --optimize --no-dev
composer clear-cache

# 2. Set final permissions
sudo chown -R www-data:www-data /var/www/spark
sudo chmod -R 755 /var/www/spark
sudo chmod -R 777 /var/www/spark/assets/

# 3. Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql

# 4. Verify deployment
curl -I http://spark.sanjivani.edu/student/
curl -I http://spark.sanjivani.edu/admin/

# 5. Monitor initial access
tail -f /var/log/apache2/access.log
tail -f /var/log/spark/php_errors.log
```

## 📞 Support & Documentation

### Useful Commands
```bash
# Check PHP version and modules
php -v
php -m

# Test database connection
php -r "require_once 'includes/config.php'; echo 'DB Test: ' . (testDatabaseConnection() ? 'SUCCESS' : 'FAILED');"

# Clear caches
composer clear-cache
php artisan cache:clear  # If using framework

# Monitor logs
tail -f logs/error.log
tail -f /var/log/apache2/error.log

# Check file permissions
find . -type f -name "*.php" -exec ls -la {} \;
```

### Important Files Reference
- `composer.json` - Package dependencies
- `includes/config.php` - Main configuration
- `database/schema.sql` - Database structure
- `deploy.php` - Deployment testing script
- `INSTALL.php` - Installation wizard
- `UPDATE_KEYS.php` - API key update utility

---

## 🎉 Ready to Launch!

Following this complete setup guide will give you a fully functional SPARK platform with:

✅ **All integrations working** (Mailjet, Razorpay, QR codes)
✅ **Production-ready security** and configurations
✅ **Optimized performance** and caching
✅ **Complete monitoring** and logging
✅ **Professional black theme** with 3D animations

**Your SPARK platform will be ready to empower students at Sanjivani College of Engineering!** 🚀

---

**For additional support, refer to:**
- Installation Checklist: `INSTALLATION_CHECKLIST.md`
- API Documentation: Check `api/` directory
- Database Schema: `database/schema.sql`
- Troubleshooting Guide: Check `logs/` directory
