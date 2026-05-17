USE student_management_systemdb;

DELIMITER $$

-- TRANSACTION 1: Enroll student with error handling and rollback capability
-- Demonstrates ACID properties (Atomicity, Consistency, Isolation, Durability)
CREATE PROCEDURE enroll_student(
    IN p_studentID INT,
    IN p_courseID INT,
    IN p_enrollmentDate DATE,
    IN p_grade VARCHAR(5)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Check if student exists
    IF NOT EXISTS (SELECT 1 FROM students WHERE studentID = p_studentID) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student not found';
    END IF;

    -- Check if course exists
    IF NOT EXISTS (SELECT 1 FROM courses WHERE courseID = p_courseID) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Course not found';
    END IF;

    -- Check for duplicate enrollment
    IF EXISTS (SELECT 1 FROM enrollments WHERE studentID = p_studentID AND courseID = p_courseID) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student already enrolled in this course';
    END IF;

    -- Insert enrollment record
    INSERT INTO enrollments (studentID, courseID, enrollmentDate, grade, status)
    VALUES (p_studentID, p_courseID, p_enrollmentDate, p_grade, 'active');

    -- Optional: Update course statistics (if needed)
    -- ...

    COMMIT;
END$$

-- TRANSACTION 2: Grade Update with History Tracking
-- Demonstrates transaction-based critical operation with audit trail
CREATE PROCEDURE update_student_grade(
    IN p_enrollment_id INT,
    IN p_new_grade VARCHAR(5),
    IN p_changed_by INT
)
BEGIN
    DECLARE v_old_grade VARCHAR(5);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Get the current grade
    SELECT grade INTO v_old_grade FROM enrollments WHERE enrollment_id = p_enrollment_id;

    -- If no record found
    IF v_old_grade IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment record not found';
    END IF;

    -- Update the grade
    UPDATE enrollments 
    SET grade = p_new_grade 
    WHERE enrollment_id = p_enrollment_id;

    -- Log the grade change
    INSERT INTO grades_log (enrollment_id, old_grade, new_grade, changed_by)
    VALUES (p_enrollment_id, v_old_grade, p_new_grade, p_changed_by);

    COMMIT;
END$$

-- TRANSACTION 3: Bulk Enrollment with Rollback on Failure
-- Demonstrates atomicity - all succeeds or all fails
CREATE PROCEDURE bulk_enroll_students(
    IN p_course_id INT,
    IN p_start_student_id INT,
    IN p_end_student_id INT,
    IN p_enrollment_date DATE
)
BEGIN
    DECLARE v_counter INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SET v_counter = p_start_student_id;
    
    WHILE v_counter <= p_end_student_id DO
        -- Check if student exists
        IF EXISTS (SELECT 1 FROM students WHERE studentID = v_counter) THEN
            -- Only insert if not already enrolled
            IF NOT EXISTS (SELECT 1 FROM enrollments WHERE studentID = v_counter AND courseID = p_course_id) THEN
                INSERT INTO enrollments (studentID, courseID, enrollmentDate, grade, status)
                VALUES (v_counter, p_course_id, p_enrollment_date, NULL, 'active');
            END IF;
        END IF;
        
        SET v_counter = v_counter + 1;
    END WHILE;

    COMMIT;
END$$

DELIMITER ;
