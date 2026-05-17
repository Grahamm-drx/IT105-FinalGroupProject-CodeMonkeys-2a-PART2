USE student_management_systemDB;

DELIMITER $$

-- === AUDIT TRIGGERS ===
-- These triggers demonstrate how database operations can be automatically logged

-- Audit trigger for INSERT on enrollments
CREATE TRIGGER trg_enrollment_audit_insert
AFTER INSERT ON enrollments
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, details)
    VALUES ('INSERT', 'enrollments', CONCAT('Student ID: ', NEW.studentID, ', Course ID: ', NEW.courseID));
END$$

-- Audit trigger for UPDATE on enrollments
CREATE TRIGGER trg_enrollment_audit_update
AFTER UPDATE ON enrollments
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, details)
    VALUES ('UPDATE', 'enrollments', CONCAT('Enrollment ID: ', NEW.enrollment_id, ', Grade: ', NEW.grade));
END$$

-- Audit trigger for DELETE from enrollments
CREATE TRIGGER trg_enrollment_audit_delete
AFTER DELETE ON enrollments
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, details)
    VALUES ('DELETE', 'enrollments', CONCAT('Enrollment ID: ', OLD.enrollment_id, ' deleted'));
END$$

-- Audit trigger for DELETE on students
-- When a student is deleted, related enrollments (if any) may be deleted via FK cascade.
-- This trigger ensures we always get an audit row for the student DELETE itself.
CREATE TRIGGER trg_student_audit_delete
AFTER DELETE ON students
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, details)
    VALUES ('DELETE', 'students', CONCAT('Student ID: ', OLD.studentID, ', Name: ', OLD.firstName, ' ', OLD.lastName));
END$$


-- === VALIDATION TRIGGERS ===
-- These trigger perform data validation before insertion

-- Validation trigger: prevent invalid grades
CREATE TRIGGER trg_validate_grade_on_insert
BEFORE INSERT ON enrollments
FOR EACH ROW
BEGIN
    DECLARE valid_grades VARCHAR(100);
    SET valid_grades = 'A+,A,A-,B+,B,B-,C+,C,C-,D+,D,F';
    
    -- If grade is provided and not in valid list, set to NULL
    IF NEW.grade IS NOT NULL AND FIND_IN_SET(NEW.grade, valid_grades) = 0 THEN
        SET NEW.grade = NULL;
    END IF;
END$$

-- Validation trigger: prevent future enrollment dates
CREATE TRIGGER trg_validate_enrollment_date
BEFORE INSERT ON enrollments
FOR EACH ROW
BEGIN
    IF NEW.enrollmentDate > CURDATE() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment date cannot be in the future';
    END IF;
END$$

-- === CONSTRAINT TRIGGERS ===
-- Prevent enrollment of same student to same course twice

CREATE TRIGGER trg_prevent_duplicate_enrollment
BEFORE INSERT ON enrollments
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM enrollments 
               WHERE studentID = NEW.studentID 
               AND courseID = NEW.courseID) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student is already enrolled in this course';
    END IF;
END$$

-- Update timestamp on student modifications
CREATE TRIGGER trg_update_student_timestamp
BEFORE UPDATE ON students
FOR EACH ROW
BEGIN
    SET NEW.created_at = NOW();
END$$

-- Cascade: Auto-log when course is deleted
CREATE TRIGGER trg_log_course_deletion
BEFORE DELETE ON courses
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, details)
    VALUES ('DELETE', 'courses', CONCAT('Course: ', OLD.courseName, ' (ID: ', OLD.courseID, ')'));
END$$

DELIMITER ;
