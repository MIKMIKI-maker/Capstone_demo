-- ============================================================
-- Migration: Fix learner_progress table
-- Run this in phpMyAdmin > spedalm_db > SQL tab
--
-- The old database.sql created learner_progress with:
--   - learner_id NOT NULL (FK → learner_activities — wrong table)
-- The new app inserts activity_id values from teacher_activities,
-- so every INSERT was rejected by the FK constraint.
-- This script drops and recreates the table with the correct schema.
-- Safe to run: the old table has no valid data (all inserts failed).
-- ============================================================

USE spedalm_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS learner_progress;

CREATE TABLE learner_progress (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id       INT NOT NULL,
    student_id       INT NOT NULL,
    activity_id      INT,
    score            INT,
    notes            TEXT,
    assessment_date  DATE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id)  REFERENCES teacher_accounts(id)   ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES students(id)           ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES teacher_activities(id) ON DELETE SET NULL,
    INDEX idx_teacher_student (teacher_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'learner_progress table fixed successfully.' AS result;
