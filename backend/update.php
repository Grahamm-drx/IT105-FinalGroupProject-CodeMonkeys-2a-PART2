<?php
require_once 'db_connect.php';

if (!isset($_SESSION['role'])) {
    die("Unauthorized");
}

$role = $_SESSION['role'];
$table = $_POST['table'] ?? '';

if ($role !== 'admin' && $table !== 'enrollments') {
    die("Permission denied.");
}

function updateRecord($conn, $table, $idCol, $idVal, $updateData)
{
    $setClause = '';
    $params = [];
    $types = '';
    foreach ($updateData as $col => $val) {
        $setClause .= "$col=?, ";
        $params[] = $val;
        if (is_int($val)) $types .= 'i';
        elseif (is_float($val)) $types .= 'd';
        else $types .= 's';
    }
    $setClause = rtrim($setClause, ', ');
    $types .= 'i';
    $params[] = $idVal;

    $sql = "UPDATE $table SET $setClause WHERE $idCol = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

// For transaction procedure call we need changed_by (user id)
$changedBy = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : null;

$id = (int)($_POST['id'] ?? 0);
$result = false;

switch ($table) {
    case 'students':
        $data = [
            'firstName' => $_POST['firstName'],
            'lastName'  => $_POST['lastName'],
            'email'     => $_POST['email']
        ];
        $result = updateRecord($conn, 'students', 'studentID', $id, $data);
        break;
    case 'instructors':
        $data = [
            'name'  => $_POST['name'],
            'email' => $_POST['email']
        ];
        $result = updateRecord($conn, 'instructors', 'instructorID', $id, $data);
        break;
    case 'courses':
        $data = [
            'courseName'   => $_POST['courseName'],
            'instructorID' => (int)$_POST['instructorID'],
            'description'  => $_POST['description'] ?? null,
            'credits'      => (int)($_POST['credits'] ?? 3)
        ];
        $result = updateRecord($conn, 'courses', 'courseID', $id, $data);
        break;

    case 'enrollments':
        // Exercise transaction procedure `update_student_grade` for grade updates.
        // stored procedure signature: update_student_grade(p_enrollment_id, p_new_grade, p_changed_by)
        $newGrade = $_POST['grade'] ?? null;
        $newStatus = $_POST['status'] ?? 'active';

        if ($changedBy === null) {
            die("Unauthorized");
        }

        // 1) Update grade transactionally (will also write into grades_log)
        $proc = $conn->prepare('CALL update_student_grade(?, ?, ?)');
        if (!$proc) {
            die('Failed to prepare procedure call: ' . $conn->error);
        }
        $proc->bind_param('isi', $id, $newGrade, $changedBy);

        // Execute procedure. Note: need to fetch results to clear the connection for next queries.
        $execOk = $proc->execute();
        if ($execOk) {
            // Clear result sets (required in MySQL for CALL when using mysqli)
            while ($proc->more_results() && $proc->next_result()) {
                // no-op
            }
            $proc->close();

            // 2) Update status (not part of the transaction procedure in current DB script)
            // This keeps current UI behavior, while still ensuring transaction procedure is exercised.
            $data = [
                'status' => $newStatus
            ];
            $result = updateRecord($conn, 'enrollments', 'enrollment_id', $id, $data);
        } else {
            // Procedure failed
            $result = false;
            // Try to close properly
            $proc->close();
        }
        break;

    default:
        die("Invalid table.");
}

if ($result) {
    header('Location: ../frontend/dashboard.php?msg=Record updated successfully');
} else {
    header('Location: ../frontend/dashboard.php?msg=Error updating record: ' . $conn->error);
}
exit();

