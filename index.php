<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room and Resource Booking System</title>
    
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
            
            <form action="process_login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label text-secondary fw-bold">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="e.g. StudentJohn" required>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label text-secondary fw-bold">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <!-- Notice we deleted the Role Dropdown. The Database handles this now! -->
                
                <button type="submit" class="btn btn-primary w-100 fw-bold">Login</button>
            
                <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-decoration-none small text-muted">Forgot your password?</a>
                </div>            
            </form>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>