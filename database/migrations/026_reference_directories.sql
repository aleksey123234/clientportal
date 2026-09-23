-- Shared normalized directories for Canadian/US courts, SBC and LPRC locations.

CREATE TABLE IF NOT EXISTS geographic_regions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_code CHAR(2) NOT NULL,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(100) NOT NULL,
    region_type ENUM('province','territory','state','district','commonwealth') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_region_country_code (country_code, code),
    KEY idx_region_country_name (country_code, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS geographic_cities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    region_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    normalized_name VARCHAR(160) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_city_region_normalized (region_id, normalized_name),
    KEY idx_city_name (name),
    CONSTRAINT fk_city_region FOREIGN KEY (region_id) REFERENCES geographic_regions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reference_locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    directory_type ENUM('canada_court','usa_court','sbc','lprc') NOT NULL,
    region_id INT UNSIGNED NOT NULL,
    city_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    address_line VARCHAR(500) NULL,
    postal_code VARCHAR(30) NULL,
    phone VARCHAR(100) NULL,
    alternate_phone VARCHAR(100) NULL,
    fax VARCHAR(100) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(1000) NULL,
    contact_name VARCHAR(255) NULL,
    contact_phone VARCHAR(100) NULL,
    contact_email VARCHAR(255) NULL,
    manager_name VARCHAR(255) NULL,
    manager_contact VARCHAR(255) NULL,
    mailing_type VARCHAR(100) NULL,
    no_fee TINYINT(1) NOT NULL DEFAULT 0,
    payable_to VARCHAR(255) NULL,
    payment_type VARCHAR(100) NULL,
    fee_text VARCHAR(500) NULL,
    additional_information TEXT NULL,
    special_instructions TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    needs_review TINYINT(1) NOT NULL DEFAULT 0,
    review_reason VARCHAR(500) NULL,
    source_key VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reference_source (directory_type, source_key),
    KEY idx_reference_list (directory_type, region_id, city_id, is_active),
    KEY idx_reference_review (directory_type, needs_review),
    KEY idx_reference_name (name),
    CONSTRAINT fk_reference_region FOREIGN KEY (region_id) REFERENCES geographic_regions(id),
    CONSTRAINT fk_reference_city FOREIGN KEY (city_id) REFERENCES geographic_cities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reference_location_cities (
    location_id BIGINT UNSIGNED NOT NULL,
    city_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (location_id, city_id),
    KEY idx_covered_city_location (city_id, location_id),
    CONSTRAINT fk_covered_location FOREIGN KEY (location_id) REFERENCES reference_locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_covered_city FOREIGN KEY (city_id) REFERENCES geographic_cities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sbc_request_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id BIGINT UNSIGNED NOT NULL,
    method_type ENUM('fingerprint_by_mail','online_name_based','mail_name_based','livescan_in_person','fax_or_email','other') NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    processing_time VARCHAR(255) NULL,
    required_documents TEXT NULL,
    results_return_to VARCHAR(255) NULL,
    restrictions TEXT NULL,
    website VARCHAR(1000) NULL,
    no_fee TINYINT(1) NOT NULL DEFAULT 0,
    payable_to VARCHAR(255) NULL,
    payment_type VARCHAR(100) NULL,
    fee_text VARCHAR(255) NULL,
    special_instructions TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sbc_location_method (location_id, method_type),
    KEY idx_sbc_method (method_type, is_active),
    CONSTRAINT fk_sbc_method_location FOREIGN KEY (location_id) REFERENCES reference_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
