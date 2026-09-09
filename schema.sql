-- =============================================================
--  FreeDNS Registration & Management Panel - MySQL Schema
--  Import via:  mysql -u root -p < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS freedns CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freedns;

-- -------------------------------------------------------------
-- Users (account holders)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Domains (the specific free domains offered, e.g. "fr.to")
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS domains (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(190) NOT NULL,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255)          DEFAULT NULL,
    category    VARCHAR(60)           DEFAULT 'General',
    featured    TINYINT(1)   NOT NULL DEFAULT 0,
    available   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_domains_name (name),
    KEY idx_domains_available (available)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Registrations (a user's subdomain under a parent domain)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registrations (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    domain_id     INT UNSIGNED NOT NULL,
    subdomain     VARCHAR(190) NOT NULL,
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registrations_sub (domain_id, subdomain),
    KEY idx_registrations_user (user_id),
    CONSTRAINT fk_reg_domain FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- DNS records attached to a registration
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dns_records (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    registration_id INT UNSIGNED NOT NULL,
    type            ENUM('A','AAAA','CNAME','MX','TXT','NS') NOT NULL DEFAULT 'A',
    name            VARCHAR(190) NOT NULL DEFAULT '@',
    value           VARCHAR(190) NOT NULL,
    priority        INT UNSIGNED NOT NULL DEFAULT 0,
    ttl             INT UNSIGNED NOT NULL DEFAULT 3600,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dns_registration (registration_id),
    CONSTRAINT fk_dns_reg FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Settings (app configuration stored in DB)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         NOT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('dns_server_type', 'none'),
('dns_server_installed', '0'),
('dns_server_ip', ''),
('dns_server_domain', ''),
('freedns_api_key', ''),
('freedns_username', ''),
('site_name', 'FreeDNS'),
('install_complete', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- -------------------------------------------------------------
-- Default admin account (change the password after first login!)
--   username: admin    password: Welcome@123
-- -------------------------------------------------------------
INSERT INTO users (username, email, password_hash, is_admin)
SELECT 'admin', 'admin@example.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFxBLJXBZBAfD3WnBOL5ZgJ2J2bs92B6', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- -------------------------------------------------------------
-- A small starter set of domains (manage/delete via Admin panel)
-- -------------------------------------------------------------
INSERT IGNORE INTO domains (name, price, description, category, featured) VALUES
('fr.to',         0.00, 'Short & memorable free subdomain', 'Short',    1),
('us.to',         0.00, 'Classic free subdomain',          'Short',    1),
('uk.to',         0.00, 'UK flavour free subdomain',       'Short',    1),
('hs.vc',         0.00, 'Minimal 5-char subdomain',        'Short',    1),
('ix.tc',         0.00, 'Tiny tech-friendly domain',       'Short',    0),
('jk.al',         0.00, 'Clean and easy to remember',      'Short',    0),
('42.ar',         0.00, 'Numeric short domain',            'Numbers',  0),
('69.mu',         0.00, 'Playful short domain',            'Numbers',  0),
('acme.si',       0.00, 'Business-friendly subdomain',     'General',  0),
('apps.dj',       0.00, 'Great for app projects',          'General',  1);

-- -------------------------------------------------------------
-- Premium short domains (.com, .org, .co, .io, .net)
-- -------------------------------------------------------------
INSERT IGNORE INTO domains (name, price, description, category, featured) VALUES
('coolo.com',     0.00, 'Premium short .com domain',       'Premium',  1),
('ezspot.com',    0.00, 'Easy to remember .com',           'Premium',  1),
('flynet.com',    0.00, 'Flying fast .com domain',          'Premium',  1),
('gohere.com',    0.00, 'Direct and simple .com',           'Premium',  1),
('hublab.com',    0.00, 'Lab-grade .com domain',            'Premium',  1),
('myhost.com',    0.00, 'Personal hosting .com',            'Premium',  0),
('coolvpn.com',   0.00, 'VPN-friendly .com',                'Premium',  0),
('instant.org',   0.00, 'Instant free .org subdomain',      'Premium',  1),
('mycloud.org',   0.00, 'Cloud-ready .org domain',          'Premium',  1),
('fastweb.org',   0.00, 'Fast website .org',                'Premium',  1),
('prosite.org',   0.00, 'Professional site .org',           'Premium',  0),
('upzone.co',     0.00, 'Short & punchy .co domain',        'Premium',  1),
('onspot.co',     0.00, 'Spot-on .co domain',               'Premium',  1),
('fastmail.co',   0.00, 'Quick mail .co',                   'Premium',  0),
('webstack.co',   0.00, 'Web technology stack .co',         'Premium',  0),
('short.io',      0.00, 'Ultra-short .io domain',           'Premium',  1),
('devhq.io',      0.00, 'Developer HQ .io',                 'Premium',  1),
('codebase.io',   0.00, 'Code storage .io domain',          'Premium',  1),
('techbit.io',    0.00, 'Tech bites .io',                   'Premium',  0),
('netzone.net',   0.00, 'Network zone .net',                'Premium',  0),
('fastlink.net',  0.00, 'Quick connection .net',            'Premium',  0);
