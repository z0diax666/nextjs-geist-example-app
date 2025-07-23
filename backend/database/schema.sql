-- Office Order Generator Database Schema
-- Run this in phpMyAdmin or MySQL command line

CREATE DATABASE IF NOT EXISTS office_order_generator;
USE office_order_generator;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Encoder', 'Approver', 'Viewer') NOT NULL DEFAULT 'Viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Templates table
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    variables JSON,
    version INT DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Documents table
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    filled_data JSON NOT NULL,
    export_pdf_url VARCHAR(500),
    export_docx_url VARCHAR(500),
    digital_signature VARCHAR(500),
    version INT DEFAULT 1,
    status ENUM('draft', 'completed', 'approved') DEFAULT 'draft',
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NULL,
    template_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
);

-- Employee data table (optional for integration)
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    designation VARCHAR(255),
    department VARCHAR(255),
    manager_id VARCHAR(50),
    salary DECIMAL(10,2),
    hire_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: password123)
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'),
('Encoder User', 'encoder@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Encoder'),
('Approver User', 'approver@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Approver'),
('Viewer User', 'viewer@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Viewer');

-- Insert default templates
INSERT INTO templates (name, content, variables, created_by) VALUES 
(
    'Employee Designation Order',
    'OFFICE ORDER\n\nDate: {{date}}\nOrder No: {{order_number}}\n\nSubject: Designation of {{employee_name}} as {{designation}}\n\nThis is to inform that {{employee_name}} (Employee ID: {{employee_id}}) has been designated as {{designation}} in the {{department}} department, effective from {{effective_date}}.\n\nThe terms and conditions of employment remain unchanged.\n\nAuthorized Signatory\n{{approver_name}}\n{{approver_designation}}',
    '["date", "order_number", "employee_name", "designation", "employee_id", "department", "effective_date", "approver_name", "approver_designation"]',
    1
),
(
    'Travel Authorization Order',
    'OFFICE ORDER - TRAVEL AUTHORIZATION\n\nDate: {{date}}\nOrder No: {{order_number}}\n\nSubject: Authorization for Official Travel\n\n{{employee_name}} (Employee ID: {{employee_id}}) is hereby authorized to travel to {{destination}} for {{purpose}} from {{start_date}} to {{end_date}}.\n\nTravel expenses will be reimbursed as per company policy.\n\nApproved by:\n{{approver_name}}\n{{approver_designation}}',
    '["date", "order_number", "employee_name", "employee_id", "destination", "purpose", "start_date", "end_date", "approver_name", "approver_designation"]',
    1
),
(
    'Promotion Order',
    'OFFICE ORDER - PROMOTION\n\nDate: {{date}}\nOrder No: {{order_number}}\n\nSubject: Promotion of {{employee_name}}\n\nWe are pleased to inform that {{employee_name}} (Employee ID: {{employee_id}}) has been promoted from {{current_designation}} to {{new_designation}} in the {{department}} department.\n\nThis promotion is effective from {{effective_date}}.\n\nNew salary: {{new_salary}}\nReporting to: {{reporting_manager}}\n\nCongratulations on your well-deserved promotion.\n\nHR Department\n{{hr_manager_name}}\n{{hr_manager_designation}}',
    '["date", "order_number", "employee_name", "employee_id", "current_designation", "new_designation", "department", "effective_date", "new_salary", "reporting_manager", "hr_manager_name", "hr_manager_designation"]',
    1
);

-- Insert sample employee data
INSERT INTO employees (employee_id, name, email, designation, department, salary) VALUES 
('EMP001', 'John Smith', 'john.smith@company.com', 'Senior Developer', 'IT', 75000.00),
('EMP002', 'Jane Doe', 'jane.doe@company.com', 'Marketing Manager', 'Marketing', 65000.00),
('EMP003', 'Mike Johnson', 'mike.johnson@company.com', 'HR Specialist', 'Human Resources', 55000.00);

-- Insert sample document
INSERT INTO documents (template_id, template_name, filled_data, status, created_by) VALUES 
(1, 'Employee Designation Order', '{"employee_name": "John Smith", "employee_id": "EMP001", "designation": "Senior Developer", "department": "IT", "effective_date": "2024-01-15"}', 'completed', 1);

-- Insert sample audit log
INSERT INTO audit_logs (user_id, document_id, action, details) VALUES 
(1, 1, 'Document Created', 'Created Employee Designation Order for John Smith');
