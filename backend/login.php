<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];   // plain‑text for demo

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $res = $conn->query($sql);

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
        $_SESSION['userID']   = $user['userID'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        header('Location: ../frontend/dashboard.php');
        exit();
    } else {
        header('Location: ../frontend/index.html?error=1');
        exit();
    }
} else {
    header('Location: ../frontend/index.html');
    exit();
}
