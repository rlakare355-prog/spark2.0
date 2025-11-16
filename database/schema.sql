-- SPARK Platform Database Schema
-- Created for Sanjivani University's AI, Research, and Knowledge Club
-- Optimized for MySQL 5.7+ / MariaDB 10.2+

-- Create database with UTF8MB4 support
CREATE DATABASE IF NOT EXISTS spark_platform
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE spark_platform;

-- Students table
CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    prn VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    contact_no VARCHAR(15) NOT NULL,
    department ENUM('CSE', 'CY', 'AIML', 'ALDS', 'Integrated-B.tech') NOT NULL,
    year ENUM('FY', 'SY', 'TY', 'FINAL YEAR') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    role ENUM('student', 'event_coordinator', 'research_coordinator', 'domain_lead', 'management_head', 'accountant', 'super_admin') DEFAULT 'student',
    is_email_verified TINYINT(1) DEFAULT 0,
    email_verification_token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_prn (prn),
    UNIQUE KEY uniq_email (email),
    INDEX idx_department (department),
    INDEX idx_year (year),
    INDEX idx_role (role),
    INDEX idx_is_email_verified (is_email_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events table
CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    banner_image VARCHAR(255) DEFAULT NULL,
    event_date DATETIME NOT NULL,
    registration_deadline DATETIME DEFAULT NULL,
    fee DECIMAL(10,2) DEFAULT 0.00,
    max_participants INT UNSIGNED DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_event_date (event_date),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event registrations table
CREATE TABLE event_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_id VARCHAR(100) DEFAULT NULL,
    razorpay_order_id VARCHAR(100) DEFAULT NULL,
    amount_paid DECIMAL(10,2) DEFAULT NULL,
    attendance_status ENUM('registered', 'present', 'absent') DEFAULT 'registered',
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_event_student (event_id, student_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_attendance_status (attendance_status),
    INDEX idx_event_id (event_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_id VARCHAR(100) NOT NULL,
    order_id VARCHAR(100) NOT NULL,
    status ENUM('created', 'authorized', 'captured', 'refunded', 'failed') NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_student_id (student_id),
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificates table
CREATE TABLE certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    certificate_id VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    verification_link VARCHAR(255) DEFAULT NULL,
    template_type VARCHAR(50) DEFAULT 'participation',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES students(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_certificate_id (certificate_id),
    UNIQUE KEY uniq_verification_link (verification_link),
    INDEX idx_student_id (student_id),
    INDEX idx_event_id (event_id),
    INDEX idx_issue_date (issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Research projects table
CREATE TABLE research_projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    tech_stack JSON DEFAULT NULL,
    coordinator_id INT UNSIGNED NOT NULL,
    domain_lead_id INT UNSIGNED DEFAULT NULL,
    status ENUM('active', 'completed', 'on_hold') DEFAULT 'active',
    max_members INT UNSIGNED DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (coordinator_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_lead_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_coordinator_id (coordinator_id),
    INDEX idx_domain_lead_id (domain_lead_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project members table
CREATE TABLE project_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    role ENUM('member', 'lead') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (project_id) REFERENCES research_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_project_student (project_id, student_id),
    INDEX idx_status (status),
    INDEX idx_project_id (project_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opportunities table
CREATE TABLE opportunities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type ENUM('internship', 'hackathon', 'event', 'research', 'job') NOT NULL,
    organizer VARCHAR(255) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    apply_link VARCHAR(500) DEFAULT NULL,
    tech_stack JSON DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_end_date (end_date),
    INDEX idx_is_featured (is_featured),
    INDEX idx_start_date (start_date),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance table
CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    qr_token VARCHAR(100) DEFAULT NULL,
    status ENUM('present', 'absent') DEFAULT 'present',
    marked_by ENUM('qr_scan', 'manual') DEFAULT 'qr_scan',
    marked_by_admin INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by_admin) REFERENCES students(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_event_student_attendance (event_id, student_id),
    UNIQUE KEY uniq_qr_token (qr_token),
    INDEX idx_qr_token (qr_token),
    INDEX idx_scan_time (scan_time),
    INDEX idx_event_id (event_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team members table
CREATE TABLE team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    role VARCHAR(100) NOT NULL,
    category ENUM('Core Leadership', 'Management Team', 'Technical Division', 'Non-Technical & Creative Division', 'General Members') NOT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    github_url VARCHAR(255) DEFAULT NULL,
    display_email VARCHAR(255) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery table
CREATE TABLE gallery (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    tags JSON DEFAULT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact messages table
CREATE TABLE contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    reply_text TEXT DEFAULT NULL,
    replied_by INT UNSIGNED DEFAULT NULL,
    replied_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (replied_by) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_replied_by (replied_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homepage content table
CREATE TABLE homepage_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(100) NOT NULL,
    content_key VARCHAR(100) NOT NULL,
    content_value TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES students(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_section_key (section, content_key),
    INDEX idx_section (section),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue table
CREATE TABLE email_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT UNSIGNED DEFAULT 0,
    sent_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_to_email (to_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES students(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_title', 'SPARK - Sanjivani Platform for AI, Research & Knowledge', 'Website title'),
('site_description', 'Dynamic platform for Sanjivani University\'s AI, Research, and Knowledge Club', 'Site description'),
('razorpay_key_id', '', 'Razorpay API Key ID'),
('razorpay_key_secret', '', 'Razorpay API Key Secret'),
('mailjet_api_key', '', 'Mailjet API Key'),
('mailjet_api_secret', '', 'Mailjet API Secret'),
('admin_email', 'admin@sanjivani.edu', 'Default admin email for notifications'),
('registration_enabled', '1', 'Enable/disable student registration'),
('email_verification_required', '1', 'Require email verification for new accounts'),
('max_upload_size', '5242880', 'Maximum file upload size in bytes (default: 5MB)');

-- Email logs table
CREATE TABLE email_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email TEXT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('send', 'success', 'error') NOT NULL,
    error_message TEXT DEFAULT NULL,
    message_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_message_id (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;