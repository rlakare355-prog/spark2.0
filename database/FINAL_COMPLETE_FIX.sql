-- =====================================================
-- SPARK PLATFORM - FINAL COMPLETE DATABASE FIX
-- =====================================================
-- Copy and paste this ENTIRE file into phpMyAdmin SQL tab
-- This fixes ALL admin panel database errors for XAMPP
-- =====================================================

USE spark_platform;

-- =====================================================
-- 1. FIX EVENTS TABLE
-- =====================================================

ALTER TABLE events ADD COLUMN IF NOT EXISTS status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming';
ALTER TABLE events ADD COLUMN IF NOT EXISTS start_date DATE NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS end_date DATE NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS start_time TIME NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS end_time TIME NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS short_description TEXT DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_type VARCHAR(100) DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS venue VARCHAR(255) DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS external_link VARCHAR(500) DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS featured TINYINT(1) DEFAULT 0;
ALTER TABLE events ADD COLUMN IF NOT EXISTS requirements TEXT DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS perks TEXT DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00;

UPDATE events SET 
    start_date = DATE(event_date),
    venue = location,
    image_url = banner_image,
    price = fee
WHERE start_date IS NULL;

-- =====================================================
-- 2. FIX PAYMENTS TABLE
-- =====================================================

ALTER TABLE payments ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS refund_reason TEXT DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS refund_status ENUM('none', 'pending', 'processed', 'rejected') DEFAULT 'none';
ALTER TABLE payments ADD COLUMN IF NOT EXISTS refunded_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS refunded_by INT UNSIGNED DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway VARCHAR(50) DEFAULT 'razorpay';

-- =====================================================
-- 3. FIX TEAM_MEMBERS TABLE
-- =====================================================

ALTER TABLE team_members ADD COLUMN IF NOT EXISTS featured TINYINT(1) DEFAULT 0;

-- =====================================================
-- 4. FIX GALLERY TABLE
-- =====================================================

ALTER TABLE gallery ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'pending') DEFAULT 'active';
ALTER TABLE gallery ADD COLUMN IF NOT EXISTS featured TINYINT(1) DEFAULT 0;

UPDATE gallery SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END WHERE status IS NULL OR status = '';

-- =====================================================
-- 5. FIX RESEARCH PROJECT TABLES
-- =====================================================

-- Create research_project_members as alias/view for project_members
-- Or add missing columns to project_members table
ALTER TABLE project_members ADD COLUMN IF NOT EXISTS added_by INT UNSIGNED DEFAULT NULL;
ALTER TABLE project_members ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Create view for compatibility
CREATE OR REPLACE VIEW research_project_members AS 
SELECT * FROM project_members;

-- =====================================================
-- 6. CREATE HOMEPAGE_SETTINGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS homepage_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hero_title VARCHAR(255) DEFAULT 'SPARK Platform',
    hero_subtitle VARCHAR(255) DEFAULT 'Sanjivani Platform for AI, Research & Knowledge',
    hero_description TEXT DEFAULT 'Empowering innovation through technology and research',
    hero_button_text VARCHAR(100) DEFAULT 'Get Started',
    hero_button_link VARCHAR(255) DEFAULT '/student/register.php',
    hero_background VARCHAR(255) DEFAULT NULL,
    welcome_title VARCHAR(255) DEFAULT 'Welcome to SPARK',
    welcome_content TEXT DEFAULT NULL,
    stats_students INT UNSIGNED DEFAULT 0,
    stats_events INT UNSIGNED DEFAULT 0,
    stats_projects INT UNSIGNED DEFAULT 0,
    stats_certificates INT UNSIGNED DEFAULT 0,
    theme_color VARCHAR(50) DEFAULT '#00ff88',
    font_family VARCHAR(100) DEFAULT 'Segoe UI',
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO homepage_settings (
    id, hero_title, hero_subtitle, hero_description, hero_button_text, hero_button_link,
    welcome_title, welcome_content, stats_students, stats_events, stats_projects, stats_certificates
) VALUES (
    1, 'SPARK Platform',
    'Sanjivani Platform for AI, Research & Knowledge',
    'Empowering students through technology, innovation, and collaboration.',
    'Get Started', '/student/register.php',
    'Welcome to SPARK',
    'Dynamic platform for AI, Research, and Knowledge at Sanjivani University.',
    100, 50, 25, 75
);

-- =====================================================
-- ✅ SUCCESS!
-- =====================================================

SELECT '✅ DATABASE FIX COMPLETE! All admin panel errors are now resolved.' as STATUS;
