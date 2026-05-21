-- Create and use the main database
CREATE DATABASE IF NOT EXISTS student_management_systemdb;
USE student_management_systemdb;

-- 1. USERS (for login / role-based access)
CREATE TABLE users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- 2. STUDENTS (Dimension Table)
CREATE TABLE students (
    studentID INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lastName (lastName),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

-- 3. INSTRUCTORS (Dimension Table)
CREATE TABLE instructors (
    instructorID INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_email (email)
);

-- 4. COURSES (Dimension Table - FK to instructors)
CREATE TABLE courses (
    courseID INT AUTO_INCREMENT PRIMARY KEY,
    courseName VARCHAR(100) NOT NULL,
    instructorID INT NOT NULL,
    description TEXT,
    credits INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructorID) REFERENCES instructors(instructorID) ON DELETE RESTRICT,
    INDEX idx_courseName (courseName),
    INDEX idx_instructorID (instructorID),
    INDEX idx_created_at (created_at)
);

-- 5. ENROLLMENTS (Fact Table: many-to-many between students & courses)
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    studentID INT NOT NULL,
    courseID INT NOT NULL,
    enrollmentDate DATE NOT NULL,
    grade VARCHAR(5),
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (studentID) REFERENCES students(studentID) ON DELETE CASCADE,
    FOREIGN KEY (courseID) REFERENCES courses(courseID) ON DELETE RESTRICT,
    UNIQUE KEY unique_enrollment (studentID, courseID),
    INDEX idx_studentID (studentID),
    INDEX idx_courseID (courseID),
    INDEX idx_enrollmentDate (enrollmentDate),
    INDEX idx_grade (grade),
    INDEX idx_status (status)
);

-- 6. AUDIT LOGS (for tracking all DML operations)
CREATE TABLE audit_logs (
    logID INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(20),
    tableName VARCHAR(50),
    row_id INT,
    old_value JSON NULL,
    new_value JSON NULL,
    actionTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. GRADES_LOG (for tracking grade changes - transaction example)
CREATE TABLE grades_log (
    grade_log_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    old_grade VARCHAR(5),
    new_grade VARCHAR(5),
    changed_by INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    INDEX idx_enrollment_id (enrollment_id),
    INDEX idx_changed_at (changed_at)
);
