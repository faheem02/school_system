-- =====================================================
-- SCHOOL MANAGEMENT SYSTEM - DATABASE
-- Run this file in phpMyAdmin to create everything
-- =====================================================

CREATE DATABASE IF NOT EXISTS school_system;
USE school_system;

-- -----------------------------------------------------
-- TABLE: roles
-- Stores the 4 user types
-- -----------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,         -- e.g. "Super Admin"
    slug VARCHAR(50) NOT NULL UNIQUE,         -- e.g. "super_admin"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- TABLE: users
-- All login accounts (super admin creates these)
-- -----------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,           -- hashed with password_hash()
    role_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,           -- 1=active, 0=disabled
    created_by INT DEFAULT NULL,              -- which admin created this user
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- -----------------------------------------------------
-- TABLE: permissions
-- Stores what each role can do in each module
-- -----------------------------------------------------
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,              -- e.g. "students", "invoices"
    can_view TINYINT(1) DEFAULT 0,
    can_add TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    UNIQUE KEY unique_role_module (role_id, module)
);

-- -----------------------------------------------------
-- TABLE: students
-- -----------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE,            -- e.g. "STU-001"
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    class VARCHAR(50),
    date_of_birth DATE,
    address TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- TABLE: teachers
-- -----------------------------------------------------
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(20) UNIQUE,            -- e.g. "TCH-001"
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    subject VARCHAR(100),
    qualification VARCHAR(100),
    salary DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- TABLE: staff
-- -----------------------------------------------------
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    department VARCHAR(100),
    position VARCHAR(100),
    salary DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- TABLE: invoices (fees)
-- -----------------------------------------------------
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE,        -- e.g. "INV-2024-001"
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    due_date DATE,
    status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
    paid_amount DECIMAL(10,2) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- -----------------------------------------------------
-- TABLE: activity_logs
-- Records every action taken in the system
-- -----------------------------------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),                      -- e.g. "Added student"
    module VARCHAR(50),                       -- e.g. "students"
    record_id INT,                            -- which record was affected
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =====================================================
-- DEFAULT DATA - Insert roles
-- =====================================================
INSERT INTO roles (name, slug) VALUES
('Super Admin', 'super_admin'),
('Teacher',     'teacher'),
('Accountant',  'accountant'),
('Staff',       'staff');

-- =====================================================
-- DEFAULT DATA - Insert Super Admin user
-- Password is: Admin@123
-- (generated with password_hash('Admin@123', PASSWORD_DEFAULT))
-- =====================================================
INSERT INTO users (name, email, password, role_id) VALUES
('Super Admin', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- =====================================================
-- DEFAULT DATA - Permissions for each role
-- Modules: students, teachers, invoices, staff
-- =====================================================

-- Super Admin: full access to everything
INSERT INTO permissions (role_id, module, can_view, can_add, can_edit, can_delete) VALUES
(1, 'students', 1, 1, 1, 1),
(1, 'teachers', 1, 1, 1, 1),
(1, 'invoices', 1, 1, 1, 1),
(1, 'staff',    1, 1, 1, 1);

-- Teacher: can view and add students only
INSERT INTO permissions (role_id, module, can_view, can_add, can_edit, can_delete) VALUES
(2, 'students', 1, 1, 1, 1),
(2, 'teachers', 1, 0, 0, 0),
(2, 'invoices', 0, 0, 0, 0),
(2, 'staff',    1, 0, 0, 0);

-- Accountant: full access to invoices only
INSERT INTO permissions (role_id, module, can_view, can_add, can_edit, can_delete) VALUES
(3, 'students', 1, 0, 0, 0),
(3, 'teachers', 1, 0, 0, 0),
(3, 'invoices', 1, 1, 1, 1),
(3, 'staff',    0, 0, 0, 0);

-- Staff: view only on students
INSERT INTO permissions (role_id, module, can_view, can_add, can_edit, can_delete) VALUES
(4, 'students', 1, 0, 0, 0),
(4, 'teachers', 0, 0, 0, 0),
(4, 'invoices', 0, 0, 0, 0),
(4, 'staff',    1, 0, 0, 0);
