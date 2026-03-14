CREATE DATABASE IF NOT EXISTS core_inventory;
USE core_inventory;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'staff') NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1
);

-- OTP verification table
CREATE TABLE otp_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('registration', 'password_reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    verified TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    category VARCHAR(50),
    unit_of_measure VARCHAR(20),
    description TEXT,
    reorder_level INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Warehouses table
CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    short_code VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    type VARCHAR(50),
    area DECIMAL(10,2),
    capacity INT,
    description TEXT
);

-- Locations table
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT,
    name VARCHAR(100),
    short_code VARCHAR(20),
    type VARCHAR(50),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

-- Stock table
CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    warehouse_id INT,
    location_id INT,
    quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 5,
    max_quantity INT DEFAULT 1000,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Receipts (Incoming)
CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE,
    supplier VARCHAR(100),
    product_id INT,
    quantity INT,
    received_date DATE,
    received_time TIME,
    status ENUM('draft', 'waiting', 'ready', 'done', 'canceled') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Delivery Orders
CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_number VARCHAR(50) UNIQUE,
    customer VARCHAR(100),
    product_id INT,
    quantity INT,
    delivery_date DATE,
    delivery_time TIME,
    status ENUM('draft', 'waiting', 'ready', 'done', 'canceled') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Internal Transfers
CREATE TABLE transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) UNIQUE,
    from_warehouse INT,
    to_warehouse INT,
    from_location INT,
    to_location INT,
    product_id INT,
    quantity INT,
    transfer_date DATE,
    status ENUM('draft', 'waiting', 'ready', 'done', 'canceled') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (from_warehouse) REFERENCES warehouses(id),
    FOREIGN KEY (to_warehouse) REFERENCES warehouses(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Damage Products
CREATE TABLE damage_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    warehouse_id INT,
    location_id INT,
    quantity INT,
    damage_date DATE,
    reason TEXT,
    status ENUM('reported', 'inspected', 'replaced', 'disposed') DEFAULT 'reported',
    reported_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

-- Move History
CREATE TABLE move_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    from_warehouse INT,
    to_warehouse INT,
    from_location INT,
    to_location INT,
    quantity INT,
    move_type ENUM('receipt', 'delivery', 'transfer', 'adjustment', 'damage'),
    reference_number VARCHAR(50),
    moved_by INT,
    moved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (moved_by) REFERENCES users(id)
);

-- Insert sample data
INSERT INTO users (username, email, password, role, full_name) VALUES
('admin', 'admin@core.com', MD5('admin123'), 'admin', 'System Admin'),
('manager1', 'manager@core.com', MD5('manager123'), 'manager', 'John Manager'),
('staff1', 'staff@core.com', MD5('staff123'), 'staff', 'Jane Staff');

INSERT INTO products (name, sku, category, unit_of_measure) VALUES
('Steel Rods', 'SR001', 'Raw Material', 'kg'),
('Wood Planks', 'WP001', 'Raw Material', 'pieces'),
('Screws Pack', 'SC001', 'Hardware', 'box'),
('Paint Can', 'PC001', 'Finishing', 'gallon');

INSERT INTO warehouses (name, short_code, address, type) VALUES
('Main Warehouse', 'MW', '123 Main St', 'primary'),
('Production Warehouse', 'PW', '456 Factory Rd', 'production');

INSERT INTO locations (warehouse_id, name, short_code) VALUES
(1, 'Rack A-1', 'A1'),
(1, 'Rack A-2', 'A2'),
(2, 'Production Rack', 'PR1');

INSERT INTO stock (product_id, warehouse_id, location_id, quantity) VALUES
(1, 1, 1, 500),
(2, 1, 2, 200),
(3, 2, 3, 100),
(4, 2, 3, 50);