<?php
require_once 'db_connect.php';

if (!isset($_SESSION['role'])) {
    die("Unauthorized");
}

$role = $_SESSION['role'];
$table = $_GET['table'] ?? '';
$id = (int)$_GET['id'];

if ($role !== 'admin') {
    die("Permission denied. Only admins can delete records.");
}

$allowedTables = ['students', 'instructors', 'courses', 'enrollments'];

if (!in_array($table, $allowedTables)) {
    die("Invalid table.");
}

$idCols = [
    'students'    => 'studentID',
    'instructors' => 'instructorID',
    'courses'     => 'courseID',
    'enrollments' => 'enrollment_id'
];

$idCol = $idCols[$table];

try {
    // If deleting a student, delete related enrollments first
    if ($table === 'students') {
        $deleteEnrollments = $conn->prepare("DELETE FROM enrollments WHERE studentID = ?");
        $deleteEnrollments->bind_param('i', $id);
        $deleteEnrollments->execute();
    }

    // Delete the main record
    $sql = "DELETE FROM $table WHERE $idCol = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    // CRITICAL: Force fresh load with timestamp AND keep the same view (students)
    $timestamp = time();
    $view = ($table === 'enrollments') ? 'enrollments' : 'students';
    header("Location: ../frontend/dashboard.php?view=$view&msg=Record+deleted&_=$timestamp");

} catch (mysqli_sql_exception $e) {
    $timestamp = time();
    header("Location: ../frontend/dashboard.php?msg=" . urlencode($e->getMessage()) . "&_=$timestamp");
}

exit();
?>
