-- ============================================================
--  Jemelo E-Commerce System Database
--  Roles: admin | staff | customer | rider
-- ============================================================



-- Disable FK checks so we can drop tables in any order
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversations;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS deliveries;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE users (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    first_name  VARCHAR(60)   NOT NULL DEFAULT '',
    middle_name VARCHAR(60)   NOT NULL DEFAULT '',
    last_name   VARCHAR(60)   NOT NULL DEFAULT '',
    username    VARCHAR(80)   DEFAULT NULL,
    birthdate   DATE          DEFAULT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('admin','staff','customer','rider') NOT NULL DEFAULT 'customer',
    phone       VARCHAR(25),
    address     TEXT,
    avatar      VARCHAR(255)  DEFAULT 'default.png',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categories ───────────────────────────────────────────────
CREATE TABLE categories (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL UNIQUE,
    description TEXT,
    image       VARCHAR(255),
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Products ─────────────────────────────────────────────────
CREATE TABLE products (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED   DEFAULT NULL,
    name         VARCHAR(200)   NOT NULL,
    description  TEXT,
    price        DECIMAL(10,2)  NOT NULL,
    sale_price   DECIMAL(10,2)  DEFAULT NULL,
    stock        INT            NOT NULL DEFAULT 0,
    image        VARCHAR(255)   DEFAULT 'no-image.png',
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    featured     TINYINT(1)     NOT NULL DEFAULT 0,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cart ─────────────────────────────────────────────────────
CREATE TABLE cart (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    quantity    INT           NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Orders ───────────────────────────────────────────────────
CREATE TABLE orders (
    id               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED   NOT NULL,
    rider_id         INT UNSIGNED   DEFAULT NULL,
    order_number     VARCHAR(25)    NOT NULL UNIQUE,
    total_amount     DECIMAL(10,2)  NOT NULL,
    status           ENUM('pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled')
                                   NOT NULL DEFAULT 'pending',
    shipping_name    VARCHAR(100),
    shipping_phone   VARCHAR(25),
    shipping_address TEXT,
    payment_method   ENUM('cod','gcash','bank_transfer') NOT NULL DEFAULT 'cod',
    payment_status   ENUM('pending','unpaid','paid','failed') NOT NULL DEFAULT 'pending',
    notes            TEXT,
    delivery_notes   TEXT,
    created_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id),
    FOREIGN KEY (rider_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Order Items ──────────────────────────────────────────────
CREATE TABLE order_items (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED   NOT NULL,
    product_id    INT UNSIGNED   DEFAULT NULL,
    product_name  VARCHAR(200)   NOT NULL,
    product_image VARCHAR(255),
    price         DECIMAL(10,2)  NOT NULL,
    quantity      INT            NOT NULL,
    subtotal      DECIMAL(10,2)  NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Deliveries ───────────────────────────────────────────────
CREATE TABLE deliveries (
    id                  INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    order_id            INT UNSIGNED  NOT NULL,
    rider_id            INT UNSIGNED  NOT NULL,
    status              ENUM('pending','picked_up','out_for_delivery','delivered','failed')
                                     NOT NULL DEFAULT 'pending',
    notes               TEXT          DEFAULT NULL,
    assigned_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    picked_up_at        DATETIME      DEFAULT NULL,
    out_for_delivery_at DATETIME      DEFAULT NULL,
    delivered_at        DATETIME      DEFAULT NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order  (order_id),
    INDEX idx_rider  (rider_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reviews ──────────────────────────────────────────────────
CREATE TABLE reviews (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    order_id    INT UNSIGNED  DEFAULT NULL,
    rating      TINYINT       NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ────────────────────────────────────────────
CREATE TABLE notifications (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NOT NULL,
    type       VARCHAR(50)   NOT NULL,
    title      VARCHAR(200)  NOT NULL,
    message    TEXT,
    link       VARCHAR(255),
    is_read    TINYINT(1)    NOT NULL DEFAULT 0,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Chat Support ─────────────────────────────────────────────
CREATE TABLE chat_conversations (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED  NOT NULL,
    subject     VARCHAR(200)  NOT NULL,
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_messages (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED  NOT NULL,
    sender_id       INT UNSIGNED  NOT NULL,
    sender_role     ENUM('admin','staff','customer','rider') NOT NULL,
    message         TEXT          NOT NULL,
    is_read         TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SEED DATA
-- ============================================================

-- ── Admin account  (password: Admin@123) ─────────────────────
INSERT IGNORE INTO users (name, first_name, last_name, email, password, role) VALUES
('Administrator', 'Admin', 'User', 'admin@shop.com',
 '$2y$10$XQ0WMKfcl1nFbkI4Z0Ud9.oaY/oNnEn3iajISg.TvELeNWuu6VqIa',
 'admin');

-- ── Categories ───────────────────────────────────────────────
INSERT IGNORE INTO categories (name, description) VALUES
('Electronics',      'Gadgets, phones, laptops and more'),
('Clothing',         'Fashion apparel for men and women'),
('Books',            'Bestselling books across all genres'),
('Sports & Fitness', 'Equipment for an active lifestyle'),
('Home & Garden',    'Decor, tools, and living essentials');

-- ── Sample products ──────────────────────────────────────────
INSERT IGNORE INTO products (category_id, name, description, price, sale_price, stock, featured) VALUES
(1, 'Wireless Headphones Pro',    'Premium over-ear headphones with active noise cancellation and 30-hour battery.', 2999.00, 2499.00, 50, 1),
(1, 'Smart Watch Series 5',       '1.4-inch AMOLED display, heart rate monitor, sleep tracking, 7-day battery.',     4599.00, NULL,    30, 1),
(1, 'Mechanical Keyboard RGB',    'Tactile switches, per-key RGB lighting, aluminium frame, USB-C.',                  1799.00, 1499.00, 75, 0),
(1, 'Portable Bluetooth Speaker', '360° surround sound, waterproof IPX7, 20-hour playtime.',                         1299.00, NULL,    60, 1),
(2, 'Classic Oxford Shirt',       '100% cotton button-down shirt, slim fit, machine washable.',                       599.00,  499.00,  100,1),
(2, 'Slim Fit Chino Pants',       'Stretch fabric, tapered leg, five pockets, modern fit.',                          799.00,  NULL,    80, 0),
(2, 'Running Jacket Lite',        'Lightweight windbreaker, reflective strips, zip pockets.',                         1199.00, 999.00,  45, 0),
(3, 'The Art of Clean Code',      'A guide to writing readable, maintainable software — 400 pages.',                  450.00,  NULL,    200,0),
(3, 'Deep Work',                  'Rules for focused success in a distracted world by Cal Newport.',                  380.00,  320.00,  150,1),
(4, 'Adjustable Dumbbell Set',    '5–52.5 lbs per dumbbell, 15 weight settings, compact design.',                    3499.00, NULL,    25, 1),
(4, 'Resistance Band Kit',        'Set of 5 bands (10–50 lbs), door anchor, handles & ankle straps.',                699.00,  549.00,  90, 0),
(5, 'Ceramic Plant Pot Set',      'Set of 3 minimalist matte pots with drainage holes & saucers.',                   549.00,  NULL,    70, 0);
