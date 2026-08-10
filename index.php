<?php
// DEFENSE NOTE: Session Initialization & Flash Messages
// Why? We start the session immediately to check if process_login.php sent us back here with an error (e.g., "Invalid password"). 
// We use $_SESSION to pass this error instead of putting it in the URL (e.g., index.php?error=invalid), which is cleaner and more secure.
session_start();

$login_error = $_SESSION['login_error'] ?? null;
$username = $_SESSION['login_username'] ?? '';

// DEFENSE NOTE: Memory Management
// Why? Once we grab the error and username from the session, we instantly unset (destroy) them. This ensures that if the user hits "Refresh" on their browser, the error message disappears instead of being stuck on the screen forever.
unset($_SESSION['login_error'], $_SESSION['login_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room and Resource Booking System</title>
    
    <!-- DEFENSE NOTE: Front-End Framework (Bootstrap 5) -->
    <!-- Why? We use Bootstrap 5 via CDN to fulfill the Non-Functional Requirement (NFR) for a responsive UI. It scales perfectly on mobile devices for students booking on the go. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin-top: 10vh;
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center align-items-center">
        <div class="card p-4 shadow-sm login-container w-100">
            <h3 class="text-center mb-4">System Login</h3>

            <?php if ($login_error): ?>
                <!-- DEFENSE NOTE: XSS (Cross-Site Scripting) Prevention -->
                <!-- Why? If a hacker tries to input a malicious JavaScript payload as their username, htmlspecialchars() converts the code into harmless text (HTML entities) before it is rendered on the screen. ENT_QUOTES ensures both single and double quotes are neutralized. -->
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <!-- DEFENSE NOTE: Form Method (POST vs GET) -->
            <!-- Why? We strictly use the POST method because it sends data in the HTTP request body. If we used GET, the user's password would be visible in plaintext in the browser's URL bar and server logs! -->
            <form action="process_login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label text-secondary fw-bold">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="e.g. StudentJohn" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label text-secondary fw-bold">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <!-- DEFENSE NOTE: Database Normalization & Security Upgrade -->
                <!-- If the panel asks: "Why don't users select if they are a student or lecturer here?" -->
                <!-- Answer: "In an earlier prototype, we used a dropdown. However, that was a security risk because a student could select 'Lecturer' to try and gain elevated privileges. By upgrading our database to a normalized 7-table Enterprise Architecture, the system now automatically looks up the user's exact Role ID from the database during process_login.php. The user cannot spoof their role." -->
                
                <button type="submit" class="btn btn-primary w-100 fw-bold">Login</button>
            
                <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-decoration-none small text-muted">Forgot your password?</a>
                </div>            
            </form>

            <hr class="my-4">
            <p class="text-center small text-muted mb-2">New to the system?</p>
            <a href="signup.php" class="btn btn-outline-primary w-100">Request an Account</a>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>