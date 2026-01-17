-- Dietetic: esquema mínimo para login
-- Ejecutar en phpMyAdmin (Hostinger) sobre tu DB.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by INT UNSIGNED NOT NULL,
  customer_name VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(40) NULL,
  customer_email VARCHAR(190) NOT NULL DEFAULT '',
  customer_dni VARCHAR(32) NULL,
  customer_address VARCHAR(255) NULL,
  detail TEXT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ARS',
  total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invoices_created_by (created_by),
  KEY idx_invoices_created_by_created_at (created_by, created_at),
  KEY idx_invoices_customer_dni (customer_dni),
  CONSTRAINT fk_invoices_users
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si ya tenías la tabla creada, podés agregar la columna con:
-- ALTER TABLE invoices ADD COLUMN customer_address VARCHAR(255) NULL AFTER customer_dni;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit VARCHAR(8) NULL,
  unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  line_total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_invoice_items_invoice_id (invoice_id),
  CONSTRAINT fk_invoice_items_invoices
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos públicos (lista de precios + encargo para retirar en el local)
CREATE TABLE IF NOT EXISTS customer_orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by INT UNSIGNED NOT NULL,
  customer_name VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(40) NULL,
  customer_address VARCHAR(255) NULL,
  notes TEXT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ARS',
  total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('new','confirmed','cancelled','fulfilled') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_customer_orders_created_by (created_by),
  KEY idx_customer_orders_created_by_status_created_at (created_by, status, created_at),
  CONSTRAINT fk_customer_orders_users
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  line_total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_customer_order_items_order_id (order_id),
  KEY idx_customer_order_items_product_id (product_id),
  CONSTRAINT fk_customer_order_items_orders
    FOREIGN KEY (order_id) REFERENCES customer_orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE
  -- product_id no tiene FK porque el catálogo es por usuario y puede cambiar con el tiempo.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Finanzas: ingresos/egresos manuales (no reemplaza ventas por facturas; es complementario)
CREATE TABLE IF NOT EXISTS finance_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by INT UNSIGNED NOT NULL,
  entry_type ENUM('income','expense') NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'ARS',
  entry_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_finance_entries_created_by_date (created_by, entry_date),
  KEY idx_finance_entries_type (entry_type),
  CONSTRAINT fk_finance_entries_users
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock: items con cantidad actual (ajustes manuales)
CREATE TABLE IF NOT EXISTS stock_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  sku VARCHAR(64) NULL,
  unit VARCHAR(24) NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_items_user_sku (created_by, sku),
  KEY idx_stock_items_created_by (created_by),
  CONSTRAINT fk_stock_items_users
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo: lista de precios de productos
CREATE TABLE IF NOT EXISTS catalog_products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'ARS',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_products_user_name (created_by, name),
  KEY idx_catalog_products_created_by (created_by),
  CONSTRAINT fk_catalog_products_users
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si ya tenías la tabla creada, podés agregar la columna con:
-- ALTER TABLE catalog_products ADD COLUMN description VARCHAR(255) NULL AFTER name;
