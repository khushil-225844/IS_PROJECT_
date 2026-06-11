<?php
// 1. Start the session to keep the user logged in across pages
session_start();

// 2. Include the database connection
require 'db_connect.php'; 

// 3. Check if the form was actually submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Grab the data from the form
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // 4. Look up the user in the database
    // Using prepared statements (?) prevents SQL Injection attacks
    $sql = "SELECT id, password, role FROM users WHERE email = ? AND role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    // 5. Check if exactly one user was found
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 6. Verify the typed password against the hashed password
        if (password_verify($password, $user['password'])) {
            
            // Success! Create the session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // 7. Redirect to the correct PHP dashboard based on role
            if ($role === 'student') {
                header("Location: dashboard-student.php");
            } elseif ($role === 'lecturer') {
                header("Location: dashboard-lecturer.php");
            } else {
                header("Location: dashboard-admin.php");
            }
            exit(); // Stop script execution after redirect
            
        } else {
            // Wrong password: show a clean JavaScript alert and send them back
            echo "<script>alert('Incorrect password.'); window.location.href='index.php';</script>";
        }
    } else {
        // User not found for that specific role/email combination
        echo "<script>alert('No account found with that email and role.'); window.location.href='index.php';</script>";
    }
} else {
    // If someone tries to type 'process_login.php' directly into the URL, kick them back to login
    header("Location: index.php");
    exit();
}
?>