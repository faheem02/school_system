# School Management System

A full-featured School Management System built with PHP, MySQL and Bootstrap 5.

## Features
- Multi-role authentication (Super Admin, Teacher, Accountant, Staff)
- Database-driven permission system (View, Add, Edit, Delete per module)
- Students, Teachers, Invoices, Staff management with full CRUD
- Bootstrap 5 modals for Add, Edit, Delete confirmations
- Search and pagination on all modules
- Activity logging for all user actions
- Financial reports and collection tracking
- My Permissions portal for every user

## Tech Stack
- Backend: PHP 8 with PDO
- Database: MySQL
- Frontend: Bootstrap 5, Bootstrap Icons
- Server: Apache (XAMPP)

## Roles and Permissions
| Role        | Students | Teachers | Invoices | Staff |
|-------------|----------|----------|----------|-------|
| Super Admin | CRUD     | CRUD     | CRUD     | CRUD  |
| Teacher     | CRUD     | View     | No       | View  |
| Accountant  | View     | View     | CRUD     | No    |
| Staff       | View     | No       | No       | View  |

## Setup
1. Import database.sql into phpMyAdmin
2. Configure config/db.php with your credentials
3. Run on XAMPP at http://localhost/school_system
4. Login: admin@school.com / Admin@123