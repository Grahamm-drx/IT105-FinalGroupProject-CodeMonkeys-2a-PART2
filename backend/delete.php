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

        $deleteEnrollments = $conn->prepare(
            "DELETE FROM enrollments WHERE studentID = ?"
        );

        $deleteEnrollments->bind_param('i', $id);
        $deleteEnrollments->execute();
    }

    // Now delete the main record
    $sql = "DELETE FROM $table WHERE $idCol = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param('i', $id);
    $stmt->execute();

    header('Location: ../frontend/dashboard.php?msg=Record deleted');

} catch (mysqli_sql_exception $e) {

    header('Location: ../frontend/dashboard.php?msg=' . urlencode($e->getMessage()));
}

exit();
?>