<?php
session_start();
require 'db_connect.php'; // Ensure your database connection file is named correctly

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 1. We use a JOIN to combine the users table with the roles table
    $sql = "SELECT users.*, roles.role_name 
            FROM users 
            JOIN roles ON users.role_id = roles.id 
            WHERE users.username = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // 2. If the user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 3. Verify password
        // Support legacy plain-text records while using secure hashes for all new accounts.
        if (password_verify($password, $user['password']) || hash_equals($user['password'], $password)) {
            // Set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_name']; // Grabs 'admin', 'student', etc.
            
            // 4. Audit Log: Securely record the successful login
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'LOGIN', 'User successfully logged into the system')";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("i", $user['id']);
            $audit_stmt->execute();

            // 5. Smart Routing: Send them to their specific dashboard
            if ($_SESSION['role'] === 'admin') {
                header("Location: dashboard-admin.php");
            } elseif ($_SESSION['role'] === 'lecturer') {
                header("Location: dashboard-lecturer.php");
            } else {
                header("Location: dashboard-student.php");
            }
            exit();
        } else {
            // Show the failure on the login page after redirecting.
            $_SESSION['login_error'] = 'Invalid username or password.';
            $_SESSION['login_username'] = $username;
            header('Location: index.php');
            exit();
        }
    } else {
        // Use the same message so usernames cannot be guessed.
        $_SESSION['login_error'] = 'Invalid username or password.';
        $_SESSION['login_username'] = $username;
        header('Location: index.php');
        exit();
    }
} else {
    // If someone tries to access this page directly without submitting the form
    header("Location: index.php");
    exit();
}
?>
