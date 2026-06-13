<?php
session_start();
require 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Check if the email exists in the database
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
// Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Let MySQL handle the exact expiration time so clocks never mismatch
        $update_sql = "UPDATE users SET reset_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE email = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $token, $email);
        $update_stmt->execute();
        
        // Create the reset link
        $reset_link = "http://localhost/IS_PROJECT/reset_password.php?token=" . $token;
        
        // Simulate sending an email (For local testing)
        $message = "
        <div class='alert alert-success mt-3'>
            <p class='mb-1'>If that email exists in our system, a reset link has been sent.</p>
            <hr>
            <p class='mb-1 text-muted small'><strong>[Local Server Dev Mode] Simulated Email:</strong></p>
            <a href='{$reset_link}' class='fw-bold'>Click here to reset your password</a>
        </div>";
    } else {
        // Security best practice: Don't reveal if an email exists or not to hackers
        $message = "<div class='alert alert-success mt-3'>If that email exists in our system, a reset link has been sent.</div>";
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
        <p class="text-muted text-center small mb-4">Enter your Strathmore email address and we will send you a secure link to reset your password.</p>
        
        <form method="POST">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="user@strathmore.edu" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">Send Reset Link</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none small text-muted">Back to Login</a>
        </div>

        <?php echo $message; ?>
    </div>
</body>
</html>