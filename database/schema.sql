/* Maintenance Hub database schema */

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(20)  DEFAULT NULL,
    role          ENUM('customer', 'worker', 'admin') NOT NULL DEFAULT 'customer',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon        VARCHAR(10)  NOT NULL DEFAULT '🔧',
    base_price  DECIMAL(10,2) NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id    INT UNSIGNED NOT NULL,
    property_type ENUM('home', 'office') NOT NULL,
    size          ENUM('small', 'medium', 'large') NOT NULL,
    price         DECIMAL(10,2) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_package (service_id, property_type, size),
    CONSTRAINT fk_packages_service FOREIGN KEY (service_id) REFERENCES services (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id  INT UNSIGNED NOT NULL,
    package_id   INT UNSIGNED NOT NULL,
    worker_id    INT UNSIGNED DEFAULT NULL,
    booking_date DATE NOT NULL,
    slot_start   TIME NOT NULL,
    address      VARCHAR(255) NOT NULL,
    notes        VARCHAR(500) DEFAULT NULL,
    price        DECIMAL(10,2) NOT NULL,
    status       ENUM('requested','confirmed','in_progress','completed','cancelled')
                 NOT NULL DEFAULT 'requested',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bookings_slot (booking_date, slot_start),
    CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES users (id),
    CONSTRAINT fk_bookings_package  FOREIGN KEY (package_id)  REFERENCES packages (id),
    CONSTRAINT fk_bookings_worker   FOREIGN KEY (worker_id)   REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id  INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    worker_id   INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL,
    comment     VARCHAR(500) DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_booking (booking_id),
    CONSTRAINT fk_reviews_booking  FOREIGN KEY (booking_id)  REFERENCES bookings (id),
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users (id),
    CONSTRAINT fk_reviews_worker   FOREIGN KEY (worker_id)   REFERENCES users (id),
    CONSTRAINT chk_reviews_rating  CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
