<?php
session_start();
require 'db_connect.php';

$message = "";
$valid_token = false;
$user_id = null;

// Check if a token is in the URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Look for the token in the database and make sure it hasn't expired
    $sql = "SELECT id FROM users WHERE reset_token = ? AND token_expiry > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
        $user_row = $result->fetch_assoc();
        $user_id = $user_row['id'];
    } else {
        $message = "<div class='alert alert-danger'>This password reset link is invalid or has expired. Please request a new one.</div>";
    }
} else {
    header("Location: index.php");
    exit();
}

// Process the new password submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = "<div class='alert alert-danger'>Passwords do not match. Try again.</div>";
    } elseif (strlen($new_password) < 6) {
        $message = "<div class='alert alert-danger'>Password must be at least 6 characters long.</div>";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password and instantly DESTROY the token so it can't be used twice
        $update_sql = "UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            echo "<script>alert('Password updated successfully! You can now log in.'); window.location.href='index.php';</script>";
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Database error. Could not update password.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow-sm p-4" style="width: 100%; max-width: 400px;">
        <h3 class="mb-4 text-center text-primary">Create New Password</h3>
        
        <?php echo $message; ?>

        <?php if ($valid_token): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold small">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold small">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100 fw-bold">Update Password</button>
        </form>
        <?php endif; ?>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none small text-muted">Return to Login</a>
        </div>
    </div>
</body>
</html>