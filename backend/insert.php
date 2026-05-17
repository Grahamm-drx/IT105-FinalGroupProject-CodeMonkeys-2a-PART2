<?php
require_once 'db_connect.php';

// Only logged-in users can insert
if (!isset($_SESSION['role'])) {
    die("Unauthorized");
}

$role = $_SESSION['role'];
$table = $_POST['table'] ?? '';

// Admin can insert into any table; staff can only insert enrollments
if ($role !== 'admin' && $table !== 'enrollments') {
    die("Permission denied.");
}

// Helper to build INSERT with prepared statement
function insertRecord($conn, $table, $data)
{
    $columns = implode(',', array_keys($data));
    $placeholders = implode(',', array_fill(0, count($data), '?'));
    $types = '';
    foreach ($data as $val) {
        if (is_int($val)) $types .= 'i';
        elseif (is_float($val)) $types .= 'd';
        else $types .= 's';
    }
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param($types, ...array_values($data));
    return $stmt->execute();
}

// Gather data based on table
$result = false;
switch ($table) {
    case 'students':
        $data = [
            'firstName' => $_POST['firstName'] ?? '',
            'lastName'  => $_POST['lastName'] ?? '',
            'email'     => $_POST['email'] ?? ''
        ];
        $result = insertRecord($conn, 'students', $data);
        break;

    case 'instructors':
        $data = [
            'name'  => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        $result = insertRecord($conn, 'instructors', $data);
        break;

    case 'courses':
        $data = [
            'courseName'   => $_POST['courseName'] ?? '',
            'instructorID' => (int)($_POST['instructorID'] ?? 0),
            'description'  => $_POST['description'] ?? '',
            'credits'      => (int)($_POST['credits'] ?? 3)
        ];
        $result = insertRecord($conn, 'courses', $data);
        break;

    case 'enrollments':
        $data = [
            'studentID'      => (int)($_POST['studentID'] ?? 0),
            'courseID'       => (int)($_POST['courseID'] ?? 0),
            'enrollmentDate' => $_POST['enrollmentDate'] ?? date('Y-m-d'),
            'grade'          => $_POST['grade'] ?? '',
            'status'         => $_POST['status'] ?? 'active'
        ];
        $result = insertRecord($conn, 'enrollments', $data);
        break;

    default:
        die("Invalid table.");
}

if ($result) {
    header('Location: ../frontend/dashboard.php?msg=Record added successfully');
} else {
    header('Location: ../frontend/dashboard.php?msg=Error adding record: ' . $conn->error);
}
exit();
