-- ============================================================================
-- AI Education App — Database Schema
-- ============================================================================
-- Run this file against your MySQL / MariaDB instance to create all tables.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. Schools
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schools` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(255) NOT NULL,
  `domain`      VARCHAR(255) DEFAULT NULL,
  `settings`    JSON DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 2. Users (students, teachers, admins)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `school_id`     INT UNSIGNED DEFAULT NULL,
  `name`          VARCHAR(255) NOT NULL,
  `email`         VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `profile`       JSON DEFAULT NULL COMMENT 'Adaptive learning profile data',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 3. Classes
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `school_id`   INT UNSIGNED DEFAULT NULL,
  `teacher_id`  INT UNSIGNED NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `settings`    JSON DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`school_id`)  REFERENCES `schools`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 4. Enrollments (students ↔ classes)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `class_id`    INT UNSIGNED NOT NULL,
  `student_id`  INT UNSIGNED NOT NULL,
  `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_enrollment` (`class_id`, `student_id`),
  FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 5. Assignments
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `class_id`      INT UNSIGNED NOT NULL,
  `teacher_id`    INT UNSIGNED NOT NULL,
  `title`         VARCHAR(255) NOT NULL,
  `instructions`  TEXT NOT NULL,
  `due_date`      DATETIME DEFAULT NULL,
  `ai_rules`      JSON DEFAULT NULL COMMENT 'Per-assignment AI policy overrides',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 6. Documents
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED NOT NULL,
  `assignment_id` INT UNSIGNED DEFAULT NULL,
  `title`         VARCHAR(255) NOT NULL DEFAULT 'Untitled',
  `content`       LONGTEXT DEFAULT NULL,
  `word_count`    INT UNSIGNED DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 7. Document versions (revision history)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_versions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id` INT UNSIGNED NOT NULL,
  `content`     LONGTEXT NOT NULL,
  `word_count`  INT UNSIGNED DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 8. Assignment submissions
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `student_id`    INT UNSIGNED NOT NULL,
  `document_id`   INT UNSIGNED DEFAULT NULL,
  `status`        ENUM('draft','submitted','graded','returned') NOT NULL DEFAULT 'draft',
  `grade`         VARCHAR(20) DEFAULT NULL,
  `feedback`      TEXT DEFAULT NULL,
  `submitted_at`  DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`)    REFERENCES `users`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`document_id`)   REFERENCES `documents`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 9. AI conversations (chat history per document)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `mode`        VARCHAR(50) NOT NULL COMMENT 'e.g. interpreter, planner, brainstorm, outline, draft_coach, reasoning, reflection',
  `role`        ENUM('user','assistant','system') NOT NULL,
  `content`     TEXT NOT NULL,
  `tokens_used` INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 10. AI events (request log for audit)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_events` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED NOT NULL,
  `document_id`   INT UNSIGNED DEFAULT NULL,
  `event_type`    VARCHAR(50) NOT NULL COMMENT 'request, response, refusal, error',
  `mode`          VARCHAR(50) DEFAULT NULL,
  `request_text`  TEXT DEFAULT NULL,
  `response_text` TEXT DEFAULT NULL,
  `tokens_used`   INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 11. Policy rules
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_rules` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `scope`       ENUM('school','class','assignment') NOT NULL,
  `scope_id`    INT UNSIGNED NOT NULL COMMENT 'References schools.id / classes.id / assignments.id',
  `rule_key`    VARCHAR(100) NOT NULL COMMENT 'e.g. enforcement_level, block_mode, allow_only_modes',
  `rule_value`  VARCHAR(255) NOT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 12. Policy violations (integrity log)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_violations` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`           INT UNSIGNED NOT NULL,
  `document_id`       INT UNSIGNED DEFAULT NULL,
  `flagged_request`   TEXT NOT NULL,
  `policy_triggered`  VARCHAR(100) NOT NULL,
  `severity`          ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `teacher_id`        INT UNSIGNED DEFAULT NULL,
  `resolution_status` ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  `resolved_by`       INT UNSIGNED DEFAULT NULL,
  `resolved_at`       DATETIME DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`teacher_id`)  REFERENCES `users`(`id`)     ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 13. Comments (teacher ↔ student on documents)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `content`     TEXT NOT NULL,
  `selection`   TEXT DEFAULT NULL COMMENT 'The highlighted text range the comment refers to',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------
-- 14. Notifications
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `type`         VARCHAR(50) NOT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `message`      TEXT NOT NULL,
  `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
