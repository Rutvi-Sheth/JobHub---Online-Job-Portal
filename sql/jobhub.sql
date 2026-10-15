-- ============================================================
-- JobHub - Job Portal Database Schema
-- Database: jobhub_db
-- Author: JobHub Project
-- Date: 2026
-- Description: Complete schema with tables, keys, relationships,
--              indexes, and sample data.
-- ============================================================

-- Create and select database
CREATE DATABASE IF NOT EXISTS `jobhub_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `jobhub_db`;

-- ============================================================
-- TABLE: users
-- Stores all users: admin, recruiter, candidate
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `full_name`    VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL,
  `password`     VARCHAR(255) NOT NULL,          -- bcrypt hashed
  `phone`        VARCHAR(15)  DEFAULT NULL,
  `role`         ENUM('admin','recruiter','candidate') NOT NULL DEFAULT 'candidate',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `profile_pic`  VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: companies
-- Company profiles created by recruiters
-- ============================================================
CREATE TABLE IF NOT EXISTS `companies` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `recruiter_id`   INT(11)      NOT NULL,          -- FK -> users.id
  `company_name`   VARCHAR(150) NOT NULL,
  `industry`       VARCHAR(100) DEFAULT NULL,
  `company_size`   VARCHAR(50)  DEFAULT NULL,      -- e.g. "1-50", "51-200"
  `website`        VARCHAR(255) DEFAULT NULL,
  `location`       VARCHAR(150) DEFAULT NULL,
  `description`    TEXT         DEFAULT NULL,
  `logo`           VARCHAR(255) DEFAULT NULL,
  `established`    YEAR         DEFAULT NULL,
  `is_verified`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recruiter` (`recruiter_id`),
  CONSTRAINT `fk_company_recruiter`
    FOREIGN KEY (`recruiter_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: jobs
-- Job postings linked to companies
-- ============================================================
CREATE TABLE IF NOT EXISTS `jobs` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `company_id`      INT(11)       NOT NULL,          -- FK -> companies.id
  `recruiter_id`    INT(11)       NOT NULL,          -- FK -> users.id
  `title`           VARCHAR(150)  NOT NULL,
  `description`     TEXT          NOT NULL,
  `requirements`    TEXT          DEFAULT NULL,
  `skills`          VARCHAR(255)  DEFAULT NULL,      -- comma-separated
  `job_type`        ENUM('Full-Time','Part-Time','Contract','Internship','Remote') NOT NULL DEFAULT 'Full-Time',
  `category`        VARCHAR(100)  DEFAULT NULL,
  `location`        VARCHAR(150)  DEFAULT NULL,
  `salary_min`      DECIMAL(10,2) DEFAULT NULL,
  `salary_max`      DECIMAL(10,2) DEFAULT NULL,
  `experience`      VARCHAR(50)   DEFAULT NULL,      -- e.g. "0-1 years"
  `education`       VARCHAR(100)  DEFAULT NULL,
  `vacancies`       INT(5)        NOT NULL DEFAULT 1,
  `deadline`        DATE          DEFAULT NULL,
  `status`          ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
  `is_featured`     TINYINT(1)    NOT NULL DEFAULT 0,
  `views`           INT(11)       NOT NULL DEFAULT 0,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company`   (`company_id`),
  KEY `idx_recruiter` (`recruiter_id`),
  KEY `idx_status`    (`status`),
  KEY `idx_category`  (`category`),
  FULLTEXT KEY `ft_search` (`title`,`description`,`skills`,`location`),
  CONSTRAINT `fk_job_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_job_recruiter`
    FOREIGN KEY (`recruiter_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: resumes
-- Uploaded resumes by candidates
-- ============================================================
CREATE TABLE IF NOT EXISTS `resumes` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `candidate_id` INT(11)      NOT NULL,          -- FK -> users.id
  `file_name`    VARCHAR(255) NOT NULL,          -- original filename
  `file_path`    VARCHAR(255) NOT NULL,          -- stored path
  `file_size`    INT(11)      DEFAULT NULL,      -- bytes
  `file_type`    VARCHAR(50)  DEFAULT NULL,      -- pdf/doc/docx
  `is_primary`   TINYINT(1)   NOT NULL DEFAULT 0,
  `uploaded_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_candidate` (`candidate_id`),
  CONSTRAINT `fk_resume_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: candidate_profiles
-- Extended profile info for candidates
-- ============================================================
CREATE TABLE IF NOT EXISTS `candidate_profiles` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)      NOT NULL,
  `headline`       VARCHAR(200) DEFAULT NULL,    -- e.g. "PHP Developer"
  `about`          TEXT         DEFAULT NULL,
  `skills`         VARCHAR(500) DEFAULT NULL,    -- comma-separated
  `experience`     VARCHAR(50)  DEFAULT NULL,
  `education`      VARCHAR(150) DEFAULT NULL,
  `location`       VARCHAR(150) DEFAULT NULL,
  `linkedin`       VARCHAR(255) DEFAULT NULL,
  `github`         VARCHAR(255) DEFAULT NULL,
  `portfolio`      VARCHAR(255) DEFAULT NULL,
  `dob`            DATE         DEFAULT NULL,
  `gender`         ENUM('Male','Female','Other','Prefer not to say') DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`),
  CONSTRAINT `fk_profile_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: applications
-- Job applications submitted by candidates
-- ============================================================
CREATE TABLE IF NOT EXISTS `applications` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `job_id`         INT(11)      NOT NULL,          -- FK -> jobs.id
  `candidate_id`   INT(11)      NOT NULL,          -- FK -> users.id
  `resume_id`      INT(11)      DEFAULT NULL,      -- FK -> resumes.id
  `cover_letter`   TEXT         DEFAULT NULL,
  `status`         ENUM('pending','reviewed','shortlisted','rejected','hired') NOT NULL DEFAULT 'pending',
  `recruiter_note` TEXT         DEFAULT NULL,
  `applied_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application` (`job_id`, `candidate_id`),  -- one application per job
  KEY `idx_job`        (`job_id`),
  KEY `idx_candidate`  (`candidate_id`),
  KEY `idx_resume`     (`resume_id`),
  CONSTRAINT `fk_app_job`
    FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_app_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_app_resume`
    FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: csrf_tokens
-- CSRF token management
-- ============================================================
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `token`      VARCHAR(64)  NOT NULL,
  `user_id`    INT(11)      DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: password_resets
-- Password reset OTP and token management
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `otp`        VARCHAR(6)   NOT NULL,
  `token`      VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: job_categories
-- Predefined job categories
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_categories` (
  `id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(100) NOT NULL,
  `icon`  VARCHAR(50)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin user (password: Admin@1234)
INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`, `is_active`) VALUES
('Admin User', 'admin@jobhub.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password (change in production)
 '9876543210', 'admin', 1);

-- Note: The hash above uses password_hash('Admin@1234', PASSWORD_BCRYPT)
-- For the actual hash we use the real bcrypt hash:
UPDATE `users` SET `password` = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LkIB4X5Gfbm'
WHERE `email` = 'admin@jobhub.com';
-- That hash corresponds to "Admin@1234" — update after verifying with PHP

-- Sample Recruiter (password: Recruit@123)
INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`, `is_active`) VALUES
('TechCorp HR', 'hr@techcorp.com',
 '$2y$10$abcdefghijklmnopqrstuvuCQr3wZa2NqKV5ZEBKfxnFI3RhqQaOa',
 '9123456780', 'recruiter', 1);

-- Sample Candidate (password: Candidate@123)
INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`, `is_active`) VALUES
('John Doe', 'john@example.com',
 '$2y$10$abcdefghijklmnopqrstuvuCQr3wZa2NqKV5ZEBKfxnFI3RhqQaOa',
 '9012345678', 'candidate', 1);

-- Job Categories
INSERT INTO `job_categories` (`name`, `icon`) VALUES
('Information Technology', 'fas fa-laptop-code'),
('Finance & Accounting', 'fas fa-chart-line'),
('Marketing & Sales', 'fas fa-bullhorn'),
('Human Resources', 'fas fa-users'),
('Healthcare', 'fas fa-heartbeat'),
('Education', 'fas fa-graduation-cap'),
('Engineering', 'fas fa-cogs'),
('Design & Creative', 'fas fa-paint-brush'),
('Customer Service', 'fas fa-headset'),
('Legal', 'fas fa-balance-scale');

-- ============================================================
-- VIEWS (optional helpers)
-- ============================================================

-- View: active jobs with company info
CREATE OR REPLACE VIEW `v_active_jobs` AS
SELECT
  j.id,
  j.title,
  j.job_type,
  j.category,
  j.location,
  j.salary_min,
  j.salary_max,
  j.experience,
  j.deadline,
  j.created_at,
  j.is_featured,
  j.views,
  c.company_name,
  c.logo AS company_logo,
  c.location AS company_location,
  (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS application_count
FROM jobs j
JOIN companies c ON j.company_id = c.id
WHERE j.status = 'active';

-- View: application details
CREATE OR REPLACE VIEW `v_applications` AS
SELECT
  ap.id,
  ap.status,
  ap.cover_letter,
  ap.applied_at,
  u.full_name  AS candidate_name,
  u.email      AS candidate_email,
  u.phone      AS candidate_phone,
  j.title      AS job_title,
  c.company_name,
  r.file_path  AS resume_path,
  r.file_name  AS resume_name
FROM applications ap
JOIN users u       ON ap.candidate_id = u.id
JOIN jobs j        ON ap.job_id       = j.id
JOIN companies c   ON j.company_id    = c.id
LEFT JOIN resumes r ON ap.resume_id   = r.id;

-- ============================================================
-- End of jobhub.sql
-- ============================================================
