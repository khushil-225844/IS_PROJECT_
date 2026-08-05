<?php
session_start();
require 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    
    // Check if the username exists in the database
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Grab the user ID for our Audit Log
        $user = $result->fetch_assoc();
        $target_user_id = $user['id'];

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Let MySQL handle the exact expiration time so clocks never mismatch
        $update_sql = "UPDATE users SET reset_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE username = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $token, $username);
        $update_stmt->execute();

        // --- NEW: ENTERPRISE AUDIT LOG (PASSWORD RESET REQUEST) ---
        $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'PASSWORD_RESET_REQUESTED', 'User requested a password reset link from the login screen')";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("i", $target_user_id);
        $audit_stmt->execute();
        // ----------------------------------------------------------
        
        // Create the reset link
        $reset_link = "http://localhost/IS_PROJECT/reset_password.php?token=" . $token;
        
        // Simulate sending an email (For local testing)
        $message = "
        <div class='alert alert-success mt-3'>
            <p class='mb-1'>If that username exists in our system, a reset link has been generated.</p>
            <hr>
            <p class='mb-1 text-muted small'><strong>[Local Server Dev Mode] Simulated Link:</strong></p>
            <a href='{$reset_link}' class='fw-bold'>Click here to reset your password</a>
        </div>";
    } else {
        // Security best practice: Don't reveal if a username exists or not to hackers
        $message = "<div class='alert alert-success mt-3'>If that username exists in our system, a reset link has been generated.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Strathmore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow-sm p-4" style="width: 100%; max-width: 400px;">
        <h3 class="mb-3 text-center text-primary">Reset Password</h3>
        <p class="text-muted text-center small mb-4">Enter your system username and we will generate a secure link to reset your password.</p>
        
        <form method="POST">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="e.g. StudentJohn" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">Generate Reset Link</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none small text-muted">Back to Login</a>
        </div>

        <?php echo $message; ?>
    </div>
</body>
</html>