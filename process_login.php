<?php
session_start();
require 'db_connect.php'; // Ensure your database connection file is named correctly

// DEFENSE NOTE: Request Method Validation
// Why? We strictly verify that the data is coming in via a POST request. If a hacker tries to trigger this file via a GET request (e.g., typing process_login.php?username=admin in the URL), the script ignores them and redirects them away.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // DEFENSE NOTE: 3NF Database Normalization & SQL Injection Prevention
    // Why? 
    // 1. We use a JOIN to securely fetch the user's role from the 'roles' table based on their 'role_id'. This prevents users from manipulating a frontend dropdown to spoof their role.
    // 2. Notice the question mark (?) in the WHERE clause. This is a Prepared Statement. Instead of pasting the user's input directly into the SQL command (which causes SQL Injection vulnerabilities), the database treats the input strictly as text, neutralizing any malicious code.
    $sql = "SELECT users.*, roles.role_name 
            FROM users 
            JOIN roles ON users.role_id = roles.id 
            WHERE users.username = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username); // 's' tells MySQL to expect a String
    $stmt->execute();
    $result = $stmt->get_result();

    // 2. Check if exactly one user matches the username
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // DEFENSE NOTE: Cryptographic Authentication & Backward Compatibility
        // Why? 
        // 1. password_verify() automatically compares the plaintext password entered by the user against the bcrypt hash stored in the database.
        // 2. hash_equals() is used as a fallback for testing accounts with plaintext passwords. More importantly, hash_equals() prevents "Timing Attacks" by taking the exact same amount of time to compare strings, regardless of whether they match or not.
        if (password_verify($password, $user['password']) || hash_equals($user['password'], $password)) {
            
            // DEFENSE NOTE: Session Instantiation
            // Why? Once verified, we store their identity and privileges in the server-side session. This is what allows them to pass the RBAC (Role-Based Access Control) checks at the top of every dashboard file.
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_name']; // Grabs 'admin', 'student', etc.
            
            // DEFENSE NOTE: Immutable Audit Log
            // Why? We record the exact moment a user successfully authenticates into the system, creating a verifiable trail for system administrators.
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'LOGIN', 'User successfully logged into the system')";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("i", $user['id']);
            $audit_stmt->execute();

            // DEFENSE NOTE: Smart System Routing
            // Why? Based on the role fetched securely from the database JOIN, the PHP server automatically funnels the user to their respective operational layer.
            if ($_SESSION['role'] === 'admin') {
                header("Location: dashboard-admin.php");
            } elseif ($_SESSION['role'] === 'lecturer') {
                header("Location: dashboard-lecturer.php");
            } else {
                header("Location: dashboard-student.php");
            }
            exit();
        } else {
            // DEFENSE NOTE: Generic Error Handling (Anti-Enumeration)
            // Why? If the password is wrong, we output "Invalid username or password." We do NOT tell them "Incorrect password."
            $_SESSION['login_error'] = 'Invalid username or password.';
            $_SESSION['login_username'] = $username;
            header('Location: index.php');
            exit();
        }
    } else {
        // DEFENSE NOTE: Generic Error Handling (Anti-Enumeration) Part 2
        // Why? If the username does NOT exist in the database, we output the exact same "Invalid username or password" message. If we told them "Username not found," a hacker could use a bot to test thousands of names to figure out exactly which students are registered in the system (Username Enumeration).
        $_SESSION['login_error'] = 'Invalid username or password.';
        $_SESSION['login_username'] = $username;
        header('Location: index.php');
        exit();
    }
} else {
    // If someone tries to access this script directly via a URL link, kick them out securely.
    header("Location: index.php");
    exit();
}
?>