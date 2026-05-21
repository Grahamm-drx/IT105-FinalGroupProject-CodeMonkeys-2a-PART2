DELIMITER $$

-- ==================== STUDENTS ====================
CREATE TRIGGER trg_students_audit_insert
AFTER INSERT ON students
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, new_value)
    VALUES ('INSERT', 'students', NEW.studentID,
            JSON_OBJECT('studentID', NEW.studentID, 'firstName', NEW.firstName,
                        'lastName', NEW.lastName, 'email', NEW.email));
END$$

CREATE TRIGGER trg_students_audit_update
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value, new_value)
    VALUES ('UPDATE', 'students', NEW.studentID,
            JSON_OBJECT('firstName', OLD.firstName, 'lastName', OLD.lastName, 'email', OLD.email),
            JSON_OBJECT('firstName', NEW.firstName, 'lastName', NEW.lastName, 'email', NEW.email));
END$$

CREATE TRIGGER trg_students_audit_delete
AFTER DELETE ON students
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value)
    VALUES ('DELETE', 'students', OLD.studentID,
            JSON_OBJECT('studentID', OLD.studentID, 'firstName', OLD.firstName,
                        'lastName', OLD.lastName, 'email', OLD.email));
END$$

-- ==================== INSTRUCTORS ====================
CREATE TRIGGER trg_instructors_audit_insert
AFTER INSERT ON instructors
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, new_value)
    VALUES ('INSERT', 'instructors', NEW.instructorID,
            JSON_OBJECT('instructorID', NEW.instructorID, 'name', NEW.name, 'email', NEW.email));
END$$

CREATE TRIGGER trg_instructors_audit_update
AFTER UPDATE ON instructors
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value, new_value)
    VALUES ('UPDATE', 'instructors', NEW.instructorID,
            JSON_OBJECT('name', OLD.name, 'email', OLD.email),
            JSON_OBJECT('name', NEW.name, 'email', NEW.email));
END$$

CREATE TRIGGER trg_instructors_audit_delete
AFTER DELETE ON instructors
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value)
    VALUES ('DELETE', 'instructors', OLD.instructorID,
            JSON_OBJECT('instructorID', OLD.instructorID, 'name', OLD.name, 'email', OLD.email));
END$$

-- ==================== COURSES ====================
CREATE TRIGGER trg_courses_audit_insert
AFTER INSERT ON courses
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, new_value)
    VALUES ('INSERT', 'courses', NEW.courseID,
            JSON_OBJECT('courseID', NEW.courseID, 'courseName', NEW.courseName,
                        'instructorID', NEW.instructorID, 'credits', NEW.credits));
END$$

CREATE TRIGGER trg_courses_audit_update
AFTER UPDATE ON courses
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value, new_value)
    VALUES ('UPDATE', 'courses', NEW.courseID,
            JSON_OBJECT('courseName', OLD.courseName, 'instructorID', OLD.instructorID, 'credits', OLD.credits),
            JSON_OBJECT('courseName', NEW.courseName, 'instructorID', NEW.instructorID, 'credits', NEW.credits));
END$$

CREATE TRIGGER trg_courses_audit_delete
AFTER DELETE ON courses
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value)
    VALUES ('DELETE', 'courses', OLD.courseID,
            JSON_OBJECT('courseID', OLD.courseID, 'courseName', OLD.courseName,
                        'instructorID', OLD.instructorID, 'credits', OLD.credits));
END$$

-- ==================== ENROLLMENTS ====================
CREATE TRIGGER trg_enrollments_audit_insert
AFTER INSERT ON enrollments
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, new_value)
    VALUES ('INSERT', 'enrollments', NEW.enrollment_id,
            JSON_OBJECT('enrollment_id', NEW.enrollment_id, 'studentID', NEW.studentID,
                        'courseID', NEW.courseID, 'grade', NEW.grade, 'status', NEW.status));
END$$

CREATE TRIGGER trg_enrollments_audit_update
AFTER UPDATE ON enrollments
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value, new_value)
    VALUES ('UPDATE', 'enrollments', NEW.enrollment_id,
            JSON_OBJECT('grade', OLD.grade, 'status', OLD.status),
            JSON_OBJECT('grade', NEW.grade, 'status', NEW.status));
END$$

CREATE TRIGGER trg_enrollments_audit_delete
AFTER DELETE ON enrollments
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, tableName, row_id, old_value)
    VALUES ('DELETE', 'enrollments', OLD.enrollment_id,
            JSON_OBJECT('enrollment_id', OLD.enrollment_id, 'studentID', OLD.studentID,
                        'courseID', OLD.courseID, 'grade', OLD.grade));
END$$

DELIMITER ;
