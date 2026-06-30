-- Create Database
CREATE DATABASE IF NOT EXISTS spedalm_db;
USE spedalm_db;

-- Create Accounts Table
CREATE TABLE IF NOT EXISTS admin_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_email VARCHAR(255) NOT NULL UNIQUE,
  admin_password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  school_name VARCHAR(255),
  phone_number VARCHAR(20),
  role VARCHAR(50) DEFAULT 'admin',
  condition_info VARCHAR(255),
  status VARCHAR(20) DEFAULT 'active',
  password_changed BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT chk_admin_email_domain CHECK (admin_email LIKE '%@spedalm.edu.ph')
);

-- Insert Admin Account (Password: Admin@123)
-- Note: Password is stored as plain text. For production, use hashed passwords with bcrypt or similar.
INSERT INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, status) 
VALUES ('admin@spedalm.edu.ph', 'Admin@123', 'Admin', 'User', 'Mamatid Elementary School', 'admin', 'active');

-- Optional: Create an index for faster email lookups
CREATE INDEX idx_admin_email ON admin_accounts(admin_email);

-- Create Admin Activities Log Table
CREATE TABLE IF NOT EXISTS admin_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_type VARCHAR(50) NOT NULL,
  user_type VARCHAR(50),
  user_name VARCHAR(100),
  user_email VARCHAR(255),
  action_detail VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at (created_at)
);

-- Create Teacher Accounts Table
CREATE TABLE IF NOT EXISTS teacher_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_email VARCHAR(255) NOT NULL UNIQUE,
  teacher_password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  school_name VARCHAR(255),
  phone_number VARCHAR(20),
  specialization VARCHAR(100),
  status VARCHAR(20) DEFAULT 'active',
  password_changed BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_teacher_email_domain CHECK (teacher_email LIKE '%@spedalm.edu.ph')
);

-- Create Learner Profiles Table
CREATE TABLE IF NOT EXISTS learner_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  learner_name VARCHAR(100) NOT NULL,
  learner_age INT,
  grade_level VARCHAR(50),
  disability_type VARCHAR(100),
  learning_style VARCHAR(50),
  strengths TEXT,
  challenges TEXT,
  parent_name VARCHAR(100),
  parent_contact VARCHAR(20),
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE
);

-- Create Learner Activities Table
CREATE TABLE IF NOT EXISTS learner_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  learner_id INT NOT NULL,
  activity_name VARCHAR(255) NOT NULL,
  activity_type VARCHAR(50),
  description TEXT,
  materials TEXT,
  duration_minutes INT,
  frequency VARCHAR(50),
  date_assigned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_completed TIMESTAMP NULL,
  status VARCHAR(20) DEFAULT 'assigned',
  FOREIGN KEY (learner_id) REFERENCES learner_profiles(id) ON DELETE CASCADE
);

-- Create Learner Progress Table
CREATE TABLE IF NOT EXISTS learner_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  learner_id INT NOT NULL,
  activity_id INT,
  progress_date DATE NOT NULL,
  completion_percentage INT DEFAULT 0,
  observations TEXT,
  behavior_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (learner_id) REFERENCES learner_profiles(id) ON DELETE CASCADE,
  FOREIGN KEY (activity_id) REFERENCES learner_activities(id) ON DELETE SET NULL
);

-- Create IEP Materials Table
CREATE TABLE IF NOT EXISTS iep_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  learner_id INT,
  template_name VARCHAR(255),
  content TEXT,
  category VARCHAR(100),
  date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (learner_id) REFERENCES learner_profiles(id) ON DELETE SET NULL
);

-- Create Learner Reports Table
CREATE TABLE IF NOT EXISTS learner_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  learner_id INT NOT NULL,
  teacher_id INT NOT NULL,
  report_type VARCHAR(50),
  period_start DATE,
  period_end DATE,
  summary TEXT,
  achievements TEXT,
  areas_for_improvement TEXT,
  recommendations TEXT,
  date_generated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (learner_id) REFERENCES learner_profiles(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE
);

-- Create Notification Table for Teacher
CREATE TABLE IF NOT EXISTS teacher_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  notification_type VARCHAR(50),
  message TEXT NOT NULL,
  related_learner_id INT,
  related_activity_id INT,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (related_learner_id) REFERENCES learner_profiles(id) ON DELETE SET NULL,
  FOREIGN KEY (related_activity_id) REFERENCES learner_activities(id) ON DELETE SET NULL
);

-- Create indexes for faster queries
CREATE INDEX idx_teacher_email ON teacher_accounts(teacher_email);
CREATE INDEX idx_learner_teacher ON learner_profiles(teacher_id);
CREATE INDEX idx_activity_learner ON learner_activities(learner_id);
CREATE INDEX idx_progress_learner ON learner_progress(learner_id);
CREATE INDEX idx_iep_teacher ON iep_materials(teacher_id);
CREATE INDEX idx_report_learner ON learner_reports(learner_id);
CREATE INDEX idx_notif_teacher ON teacher_notifications(teacher_id);
