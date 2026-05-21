<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['userID'])) {
    header('Location: index.html');
    exit();
}
require_once '../backend/db_connect.php';
$role = $_SESSION['role'];
$uname = $_SESSION['username'];
$userID = $_SESSION['userID'];

$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;
$view = $_GET['view'] ?? 'students';

// DATASET COUNTER: total records across all main tables
$total_records_all = 0;
$tables = ['students', 'instructors', 'courses', 'enrollments', 'audit_logs'];

$check_users = $conn->query("SHOW TABLES LIKE 'users'");
if ($check_users && $check_users->num_rows > 0) {
    $tables[] = 'users';
}
foreach ($tables as $table) {
    $count_res = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
    if ($count_res) {
        $total_records_all += $count_res->fetch_assoc()['cnt'];
    }
}

// Helper for pagination
function show_pagination($total_records, $records_per_page, $page, $view, $extra_params = '')
{
    $total_pages = ceil($total_records / $records_per_page);
    if ($total_pages <= 1) return;

    $range = 4;
    $start = max(1, $page - $range);
    $end = min($total_pages, $page + $range);

    echo '<div class="pagination">';
    if ($page > 1) {
        echo '<a href="?view=' . $view . '&page=1' . $extra_params . '">First</a>';
        echo '<a href="?view=' . $view . '&page=' . ($page - 1) . $extra_params . '">Previous</a>';
    }

    if ($start > 1) {
        echo '<span>…</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            echo '<span class="current">' . $i . '</span>';
        } else {
            echo '<a href="?view=' . $view . '&page=' . $i . $extra_params . '">' . $i . '</a>';
        }
    }

    if ($end < $total_pages) {
        echo '<span>…</span>';
    }

    if ($page < $total_pages) {
        echo '<a href="?view=' . $view . '&page=' . ($page + 1) . $extra_params . '">Next</a>';
        echo '<a href="?view=' . $view . '&page=' . $total_pages . $extra_params . '">Last</a>';
    }
    echo '</div>';
}

// Ensure index on students(lastName) exists for fair benchmarking
$check_index = $conn->query("SHOW INDEX FROM students WHERE Key_name = 'idx_lastname'");
if ($check_index->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD INDEX idx_lastname (lastName)");
}

// Handle index benchmark
if (isset($_GET['benchmark']) && isset($_GET['search_name'])) {
    $search_term = trim($_GET['search_name']);
    if ($search_term === '') {
        header("Location: dashboard.php?view=students&msg=" . urlencode("Please enter a last name to search."));
        exit();
    }
    $like = "$search_term%";
    $use_index = $_GET['benchmark'] === 'with_index';
    
    $start = microtime(true);
    if ($use_index) {
        $sql = "SELECT * FROM students FORCE INDEX (idx_lastname) WHERE lastName LIKE ?";
    } else {
        $sql = "SELECT * FROM students IGNORE INDEX (idx_lastname) WHERE lastName LIKE ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->num_rows;
    $end = microtime(true);
    $time_ms = round(($end - $start) * 1000, 2);
    
    $explain_sql = "EXPLAIN " . $sql;
    $explain_stmt = $conn->prepare($explain_sql);
    $explain_stmt->bind_param('s', $like);
    $explain_stmt->execute();
    $explain_res = $explain_stmt->get_result();
    $explain_row = $explain_res->fetch_assoc();
    $key_used = $explain_row['key'] ?? 'NONE';
    
    $msg = "🔍 Search for last name containing '{$search_term}' | Records: {$count} | Time: {$time_ms} ms | Index used: " . ($key_used ?: 'NO INDEX');
    header("Location: dashboard.php?view=students&msg=" . urlencode($msg));
    exit();
}

// Handle transaction demo (atomic enrollment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_enroll'])) {
    $studentID = (int)$_POST['student_id'];
    $courseID  = (int)$_POST['course_id'];
    $grade      = trim($_POST['grade']);
    
    $conn->begin_transaction();
    try {
        $chk_student = $conn->query("SELECT studentID FROM students WHERE studentID = $studentID");
        if ($chk_student->num_rows == 0) throw new Exception("Student ID not found.");
        
        $chk_course = $conn->query("SELECT courseID FROM courses WHERE courseID = $courseID");
        if ($chk_course->num_rows == 0) throw new Exception("Course ID not found.");
        
        $chk_enroll = $conn->query("SELECT enrollment_id FROM enrollments WHERE studentID = $studentID AND courseID = $courseID");
        if ($chk_enroll->num_rows > 0) throw new Exception("Student already enrolled in this course.");
        
        $enroll_date = date('Y-m-d');
        $status = 'active';
        $insert = $conn->prepare("INSERT INTO enrollments (studentID, courseID, enrollmentDate, grade, status) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iisss", $studentID, $courseID, $enroll_date, $grade, $status);
        if (!$insert->execute()) throw new Exception("Failed to insert enrollment: " . $insert->error);
        
        $conn->commit();
        $msg = "✅ Transaction successful: Student $studentID enrolled into course $courseID with grade $grade.";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "❌ Transaction failed: " . $e->getMessage();
    }
    header("Location: dashboard.php?view=students&msg=" . urlencode($msg));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard – Student Management System</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ----- Styles ----- */
        .nav-tabs { display: flex; gap: 5px; margin: 15px 0; border-bottom: 2px solid #ddd; flex-wrap: wrap; }
        .nav-tab { padding: 10px 15px; background: #f0f0f0; text-decoration: none; border-radius: 3px; font-size: 14px; }
        .nav-tab.active { background: #007bff; color: white; }
        .nav-tab:hover { background: #0056b3; color: white; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; font-weight: bold; }
        .action-btn { display: inline-block; margin: 2px; padding: 4px 8px; font-size: 11px; text-decoration: none; border-radius: 3px; background: #28a745; color: white; }
        .action-btn:hover { background: #218838; }
        .action-btn.delete { background: #dc3545; }
        .action-btn.delete:hover { background: #c82333; }
        .edit-form { background: #f9f9f9; border: 2px solid #007bff; border-radius: 8px; padding: 20px; margin: 15px 0; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 4px; }
        .form-group input, .form-group select { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .alert { padding: 12px; border-radius: 4px; margin: 10px 0; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .pagination { display: flex; gap: 5px; justify-content: center; margin: 25px 0; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; font-weight: 600; background: white; }
        .pagination a:hover { background: #f58220; color: white; }
        .pagination .current { background: #005baa; color: white; border-color: #005baa; }
        .search-box { display: flex; gap: 10px; margin: 15px 0; flex-wrap: wrap; }
        .search-box input, .search-box select { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .search-box button { padding: 8px 15px; }
        .query-result-header { margin: 20px 0; padding: 10px; background: #d4edda; border-left: 4px solid #155724; color: #155724; font-weight: bold; }
        .demo-section { background: #f8f9fc; border: 1px solid #cce5ff; border-radius: 8px; padding: 15px 20px; margin: 20px 0; display: flex; flex-wrap: wrap; gap: 25px; align-items: flex-start; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .demo-card { background: white; border-radius: 8px; padding: 12px 18px; flex: 1; min-width: 240px; border-left: 4px solid #007bff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .demo-card h4 { margin: 0 0 10px 0; color: #0056b3; }
        .demo-card form, .demo-card div { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .demo-card input, .demo-card select { padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; }
        .demo-btn { background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 13px; }
        .demo-btn:hover { background: #0056b3; }
        small { font-size: 11px; color: #555; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Header with Dataset Counter -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
        <h2 style="margin: 0;">Dashboard</h2>
        <div>
            <strong><?= htmlspecialchars($uname) ?></strong> (<?= $role ?>) |
            <a href="../backend/db_connect.php?logout=1">Logout</a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): 
        $msg_class = (strpos($_GET['msg'], '✅') !== false || strpos($_GET['msg'], 'Search') !== false) ? 'alert-success' : 'alert-error'; ?>
        <div class='alert <?= $msg_class ?>'><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <!-- Demo Section: Index Optimization & Transaction -->
    <div class="demo-section">
        <div class="demo-card" style="background: #e9ecef; text-align: center;">
            <h4>📈 Total Dataset Size</h4>
            <span style="font-size: 28px; font-weight: bold;"><?= number_format($total_records_all) ?></span>
            <small>records across all tables</small>
        </div>
        <div class="demo-card">
            <h4>Index Optimization Demo</h4>
            <form method="get" action="dashboard.php" style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                <input type="hidden" name="view" value="students">
                <input type="text" name="search_name" placeholder="Last name starts with (e.g., Smit)" required style="flex: 1;">
                <button type="submit" name="benchmark" value="without_index" class="demo-btn" style="background:#6c757d;">Without Index</button>
                <button type="submit" name="benchmark" value="with_index" class="demo-btn">With Index</button>
            </form>
            <small>Searches with/without index on `lastName`. Shows execution time & EXPLAIN key used.</small>
        </div>
        <div class="demo-card">
            <h4>Transaction Demo (Atomic Enrollment)</h4>
            <form method="post" action="dashboard.php" style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                <input type="hidden" name="view" value="students">
                <input type="number" name="student_id" placeholder="Student ID" required style="width:90px;">
                <input type="number" name="course_id" placeholder="Course ID" required style="width:90px;">
                <select name="grade" required style="width:80px;">
                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="F">F</option>
                </select>
                <button type="submit" name="transaction_enroll" class="demo-btn">➕ Enroll (Transaction)</button>
            </form>
            <small>✅ Uses BEGIN/COMMIT/ROLLBACK – fails if student/course missing or already enrolled.</small>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <a href="?view=students" class="nav-tab <?= $view === 'students' ? 'active' : '' ?>">📚 Students</a>
        <a href="?view=courses" class="nav-tab <?= $view === 'courses' ? 'active' : '' ?>">🎓 Courses</a>
        <a href="?view=instructors" class="nav-tab <?= $view === 'instructors' ? 'active' : '' ?>">👨‍🏫 Instructors</a>
        <a href="?view=enrollments" class="nav-tab <?= $view === 'enrollments' ? 'active' : '' ?>">📝 Enrollments</a>
        <a href="?view=audit" class="nav-tab <?= $view === 'audit' ? 'active' : '' ?>">📋 Audit</a>
        <a href="form.html" class="nav-tab">➕ Add</a>
    </div>

    <?php
    // ========================= STUDENTS TAB =========================
    if ($view === 'students'):
        // Edit form
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])):
            $id = (int)$_GET['id'];
            $res = $conn->query("SELECT * FROM students WHERE studentID = $id");
            if ($res && $res->num_rows === 1):
                $stud = $res->fetch_assoc(); ?>
                <div class='edit-form'>
                    <h3>✏️ Edit Student (ID: <?= $id ?>)</h3>
                    <form method='post' action='../backend/update.php'>
                        <input type='hidden' name='table' value='students'>
                        <input type='hidden' name='id' value='<?= $id ?>'>
                        <div class='form-group'><label>First Name:</label><input type='text' name='firstName' value='<?= htmlspecialchars($stud['firstName']) ?>' required></div>
                        <div class='form-group'><label>Last Name:</label><input type='text' name='lastName' value='<?= htmlspecialchars($stud['lastName']) ?>' required></div>
                        <div class='form-group'><label>Email:</label><input type='email' name='email' value='<?= htmlspecialchars($stud['email']) ?>' required></div>
                        <button type='submit'>Update Student</button>
                        <a href='?view=students' class='action-btn' style='padding:8px 15px; background:#6c757d;'>Cancel</a>
                    </form>
                </div>
            <?php else: ?>
                <div class='alert alert-error'>Student not found.</div>
            <?php endif;
        endif;

        // Search
        $search = $_GET['search'] ?? '';
        echo "<div class='search-box'>
              <form method='get' style='display: flex; gap: 10px; flex-wrap: wrap;'>
                <input type='hidden' name='view' value='students'>
                <input type='text' name='search' placeholder='Search by Last Name (indexed)' value='" . htmlspecialchars($search) . "'>
                <button type='submit'>Search</button>
                <a href='?view=students' class='action-btn' style='background: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px;'>Clear</a>
              </form>
              </div>";

        if ($search) {
            $like = "%$search%";
            $total_records = $conn->query("SELECT COUNT(*) as count FROM students WHERE lastName LIKE '%$search%'")->fetch_assoc()['count'];
            $stmt = $conn->prepare("SELECT * FROM students WHERE lastName LIKE ? LIMIT ? OFFSET ?");
            $stmt->bind_param('sii', $like, $records_per_page, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            echo "<div class='query-result-header'>🔍 Query Results: <em>" . htmlspecialchars($search) . "</em> ($total_records records found)</div>";
        } else {
            $total_records = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
            $result = $conn->query("SELECT * FROM students LIMIT $records_per_page OFFSET $offset");
            echo "<h3>Students (Total: $total_records)</h3>";
        }

        if ($result && $result->num_rows > 0): ?>
            <table>
                <tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Created</th><th>Actions</th></tr>
                <?php while ($row = $result->fetch_assoc()): $created = $row['created_at'] ?? 'N/A'; ?>
                    <tr>
                        <td><?= $row['studentID'] ?></td>
                        <td><?= $row['firstName'] ?></td>
                        <td><?= htmlspecialchars($row['lastName']) ?></td>
                        <td><?= $row['email'] ?></td>
                        <td><?= $created ?></td>
                        <td>
                            <a class='action-btn' href='?view=students&action=edit&id=<?= $row['studentID'] ?>'>Edit</a>
                            <?php if ($role === 'admin'): ?>
                                <a class='action-btn delete' href='../backend/delete.php?table=students&id=<?= $row['studentID'] ?>' onclick="return confirm('Delete this student?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php show_pagination($total_records, $records_per_page, $page, 'students'); ?>
        <?php else: echo "<p>No students found.</p>"; endif;

    // ========================= COURSES TAB =========================
    elseif ($view === 'courses'):
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])):
            $id = (int)$_GET['id'];
            $res = $conn->query("SELECT * FROM courses WHERE courseID = $id");
            if ($res && $res->num_rows === 1):
                $co = $res->fetch_assoc(); ?>
                <div class='edit-form'>
                    <h3>✏️ Edit Course (ID: <?= $id ?>)</h3>
                    <form method='post' action='../backend/update.php'>
                        <input type='hidden' name='table' value='courses'>
                        <input type='hidden' name='id' value='<?= $id ?>'>
                        <div class='form-group'><label>Course Name:</label><input type='text' name='courseName' value='<?= htmlspecialchars($co['courseName']) ?>' required></div>
                        <div class='form-group'><label>Instructor ID:</label><input type='number' name='instructorID' value='<?= htmlspecialchars($co['instructorID']) ?>' required></div>
                        <div class='form-group'><label>Description:</label><input type='text' name='description' value='<?= htmlspecialchars($co['description'] ?? '') ?>'></div>
                        <div class='form-group'><label>Credits:</label><input type='number' name='credits' value='<?= htmlspecialchars($co['credits'] ?? 3) ?>'></div>
                        <button type='submit'>Update Course</button>
                        <a href='?view=courses' class='action-btn' style='padding:8px 15px; background:#6c757d;'>Cancel</a>
                    </form>
                </div>
            <?php else: echo "<div class='alert alert-error'>Course not found.</div>"; endif;
        endif;

        $total_records = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
        $result = $conn->query("SELECT c.*, i.name as instructor_name FROM courses c LEFT JOIN instructors i ON c.instructorID = i.instructorID LIMIT $records_per_page OFFSET $offset");
        echo "<h3>Courses (Total: $total_records)</h3>";
        if ($result && $result->num_rows > 0): ?>
            <table>
                <tr><th>ID</th><th>Course Name</th><th>Instructor</th><th>Credits</th><th>Created</th><th>Actions</th></tr>
                <?php while ($row = $result->fetch_assoc()): $instructor = $row['instructor_name'] ?? 'N/A'; $credits = $row['credits'] ?? 3; $created = $row['created_at'] ?? 'N/A'; ?>
                    <tr>
                        <td><?= $row['courseID'] ?></td>
                        <td><?= $row['courseName'] ?></td>
                        <td><?= $instructor ?></td>
                        <td><?= $credits ?></td>
                        <td><?= $created ?></td>
                        <td>
                            <a class='action-btn' href='?view=courses&action=edit&id=<?= $row['courseID'] ?>'>Edit</a>
                            <?php if ($role === 'admin'): ?>
                                <a class='action-btn delete' href='../backend/delete.php?table=courses&id=<?= $row['courseID'] ?>' onclick="return confirm('Delete?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php show_pagination($total_records, $records_per_page, $page, 'courses'); ?>
        <?php else: echo "<p>No courses found.</p>"; endif;

    // ========================= INSTRUCTORS TAB =========================
    elseif ($view === 'instructors'):
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])):
            $id = (int)$_GET['id'];
            $res = $conn->query("SELECT * FROM instructors WHERE instructorID = $id");
            if ($res && $res->num_rows === 1):
                $inst = $res->fetch_assoc(); ?>
                <div class='edit-form'>
                    <h3>✏️ Edit Instructor (ID: <?= $id ?>)</h3>
                    <form method='post' action='../backend/update.php'>
                        <input type='hidden' name='table' value='instructors'>
                        <input type='hidden' name='id' value='<?= $id ?>'>
                        <div class='form-group'><label>Name:</label><input type='text' name='name' value='<?= htmlspecialchars($inst['name']) ?>' required></div>
                        <div class='form-group'><label>Email:</label><input type='email' name='email' value='<?= htmlspecialchars($inst['email']) ?>' required></div>
                        <button type='submit'>Update Instructor</button>
                        <a href='?view=instructors' class='action-btn' style='padding:8px 15px; background:#6c757d;'>Cancel</a>
                    </form>
                </div>
            <?php else: echo "<div class='alert alert-error'>Instructor not found.</div>"; endif;
        endif;

        $total_records = $conn->query("SELECT COUNT(*) as count FROM instructors")->fetch_assoc()['count'];
        $result = $conn->query("SELECT * FROM instructors LIMIT $records_per_page OFFSET $offset");
        echo "<h3>Instructors (Total: $total_records)</h3>";
        if ($result && $result->num_rows > 0): ?>
            <table>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Created</th><th>Actions</th></tr>
                <?php while ($row = $result->fetch_assoc()): $created = $row['created_at'] ?? 'N/A'; ?>
                    <tr>
                        <td><?= $row['instructorID'] ?></td>
                        <td><?= $row['name'] ?></td>
                        <td><?= $row['email'] ?></td>
                        <td><?= $created ?></td>
                        <td>
                            <?php if ($role === 'admin'): ?>
                                <a class='action-btn' href='?view=instructors&action=edit&id=<?= $row['instructorID'] ?>'>Edit</a>
                                <a class='action-btn delete' href='../backend/delete.php?table=instructors&id=<?= $row['instructorID'] ?>' onclick="return confirm('Delete?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php show_pagination($total_records, $records_per_page, $page, 'instructors'); ?>
        <?php else: echo "<p>No instructors found.</p>"; endif;

    // ========================= ENROLLMENTS TAB =========================
    elseif ($view === 'enrollments'):
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])):
            $id = (int)$_GET['id'];
            $res = $conn->query("SELECT * FROM enrollments WHERE enrollment_id = $id");
            if ($res && $res->num_rows === 1):
                $enr = $res->fetch_assoc(); ?>
                <div class='edit-form'>
                    <h3>✏️ Edit Enrollment (ID: <?= $id ?>)</h3>
                    <form method='post' action='../backend/update.php'>
                        <input type='hidden' name='table' value='enrollments'>
                        <input type='hidden' name='id' value='<?= $id ?>'>
                        <div class='form-group'>
                            <label>Grade:</label>
                            <select name='grade' required>
                                <option value='A+' <?= ($enr['grade'] == 'A+') ? 'selected' : '' ?>>A+</option>
                                <option value='A' <?= ($enr['grade'] == 'A') ? 'selected' : '' ?>>A</option>
                                <option value='B+' <?= ($enr['grade'] == 'B+') ? 'selected' : '' ?>>B+</option>
                                <option value='B' <?= ($enr['grade'] == 'B') ? 'selected' : '' ?>>B</option>
                                <option value='C+' <?= ($enr['grade'] == 'C+') ? 'selected' : '' ?>>C+</option>
                                <option value='C' <?= ($enr['grade'] == 'C') ? 'selected' : '' ?>>C</option>
                                <option value='F' <?= ($enr['grade'] == 'F') ? 'selected' : '' ?>>F</option>
                            </select>
                        </div>
                        <div class='form-group'>
                            <label>Status:</label>
                            <select name='status'>
                                <option value='active' <?= (($enr['status'] ?? 'active') == 'active') ? 'selected' : '' ?>>Active</option>
                                <option value='completed' <?= (($enr['status'] ?? '') == 'completed') ? 'selected' : '' ?>>Completed</option>
                                <option value='dropped' <?= (($enr['status'] ?? '') == 'dropped') ? 'selected' : '' ?>>Dropped</option>
                            </select>
                        </div>
                        <button type='submit'>Update Enrollment</button>
                        <a href='?view=enrollments' class='action-btn' style='padding:8px 15px; background:#6c757d;'>Cancel</a>
                    </form>
                </div>
            <?php else: echo "<div class='alert alert-error'>Enrollment not found.</div>"; endif;
        endif;

        $search_grade = $_GET['grade'] ?? '';
        echo "<div class='search-box'>
              <form method='get' style='display: flex; gap: 10px; flex-wrap: wrap;'>
                <input type='hidden' name='view' value='enrollments'>
                <select name='grade'>
                    <option value=''>All Grades</option>
                    <option value='A+'>A+</option><option value='A'>A</option><option value='A-'>A-</option>
                    <option value='B+'>B+</option><option value='B'>B</option><option value='B-'>B-</option>
                    <option value='C+'>C+</option><option value='C'>C</option><option value='C-'>C-</option>
                    <option value='F'>F</option>
                </select>
                <button type='submit'>Filter</button>
                <a href='?view=enrollments' class='action-btn' style='background: #6c757d; color: white; padding: 8px 15px;'>Clear</a>
              </form>
              </div>";

        if ($search_grade) {
            $total_records = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE grade = '$search_grade'")->fetch_assoc()['count'];
            $result = $conn->query("SELECT e.*, s.firstName, s.lastName, c.courseName FROM enrollments e JOIN students s ON e.studentID = s.studentID JOIN courses c ON e.courseID = c.courseID WHERE e.grade = '$search_grade' LIMIT $records_per_page OFFSET $offset");
            echo "<div class='query-result-header'>🔍 Query Results: Grade <em>$search_grade</em> ($total_records records found)</div>";
        } else {
            $total_records = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
            $result = $conn->query("SELECT e.*, s.firstName, s.lastName, c.courseName FROM enrollments e JOIN students s ON e.studentID = s.studentID JOIN courses c ON e.courseID = c.courseID LIMIT $records_per_page OFFSET $offset");
            echo "<h3>Enrollments (Total: $total_records)</h3>";
        }

        if ($result && $result->num_rows > 0): ?>
            <table>
                <tr><th>ID</th><th>Student</th><th>Course</th><th>Date</th><th>Grade</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                <?php while ($row = $result->fetch_assoc()): $fullname = ($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''); $status = $row['status'] ?? 'active'; $created = $row['created_at'] ?? 'N/A'; ?>
                    <tr>
                        <td><?= $row['enrollment_id'] ?></td>
                        <td><?= htmlspecialchars($fullname) ?></td>
                        <td><?= $row['courseName'] ?></td>
                        <td><?= $row['enrollmentDate'] ?></td>
                        <td><strong><?= $row['grade'] ?? 'N/A' ?></strong></td>
                        <td><span style='background:#e9ecef;padding:3px 6px;border-radius:3px;'><?= $status ?></span></td>
                        <td><?= $created ?></td>
                        <td>
                            <?php if ($role === 'staff' || $role === 'admin'): ?>
                                <a class='action-btn' href='?view=enrollments&action=edit&id=<?= $row['enrollment_id'] ?>'>Edit</a>
                            <?php endif; ?>
                            <?php if ($role === 'admin'): ?>
                                <a class='action-btn delete' href='../backend/delete.php?table=enrollments&id=<?= $row['enrollment_id'] ?>' onclick="return confirm('Delete?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?php show_pagination($total_records, $records_per_page, $page, 'enrollments', $search_grade ? "&grade=$search_grade" : ''); ?>
        <?php else: echo "<p>No enrollments found.</p>"; endif;

    // ========================= AUDIT LOG TAB =========================
    elseif ($view === 'audit'):
        $total_records = $conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count'];
        $result = $conn->query("SELECT logID, action, tableName, actionTime, row_id, old_value, new_value FROM audit_logs ORDER BY actionTime DESC LIMIT $records_per_page OFFSET $offset");
        echo "<h3>Audit Log (Total: $total_records)</h3>";
        echo "<p style='color: #666; font-size:12px; margin-bottom:15px;'>🔍 Trigger Demonstration: Every INSERT, UPDATE, DELETE on core tables is logged here with full JSON snapshots.</p>";
        if ($result && $result->num_rows > 0): ?>
            <div style='overflow-x:auto;'>
                <table style='min-width:900px;'>
                    <thead><tr><th>Time</th><th>Action</th><th>Table</th><th>Row ID</th><th>Old Value (JSON)</th><th>New Value (JSON)</th></tr></thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $actionClass = '';
                        if ($row['action'] == 'INSERT') $actionClass = 'background:#28a745;';
                        elseif ($row['action'] == 'UPDATE') $actionClass = 'background:#ffc107; color:#333;';
                        elseif ($row['action'] == 'DELETE') $actionClass = 'background:#dc3545;';
                        $oldValueFormatted = '-';
                        if (!is_null($row['old_value'])) {
                            $decoded = json_decode($row['old_value'], true);
                            $oldValueFormatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        }
                        $newValueFormatted = '-';
                        if (!is_null($row['new_value'])) {
                            $decoded = json_decode($row['new_value'], true);
                            $newValueFormatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        }
                        ?>
                        <tr>
                            <td style='white-space:nowrap;'><?= date('Y-m-d H:i:s', strtotime($row['actionTime'])) ?></td>
                            <td><strong style='color:white;padding:3px 8px;border-radius:3px;<?= $actionClass ?>'><?= htmlspecialchars($row['action']) ?></strong></td>
                            <td><?= htmlspecialchars($row['tableName']) ?></td>
                            <td><?= htmlspecialchars($row['row_id']) ?></td>
                            <td><pre style='margin:0;white-space:pre-wrap;font-size:11px;'><?= $oldValueFormatted ?></pre></td>
                            <td><pre style='margin:0;white-space:pre-wrap;font-size:11px;'><?= $newValueFormatted ?></pre></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php show_pagination($total_records, $records_per_page, $page, 'audit'); ?>
        <?php else: echo "<p>No audit log entries found.</p>"; endif;
    endif;

    $conn->close();
    ?>
</div>
</body>
</html>
