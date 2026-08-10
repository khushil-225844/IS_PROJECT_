<?php
require 'db_connect.php';

$message = '';

// DEFENSE NOTE: Dynamic Form Population
// Why? Instead of hardcoding the departments in HTML, we fetch them dynamically from the database. If the university adds a new faculty next year, the Admin only updates the database, and this dropdown automatically updates without touching the code.
$departments = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");

// Check if the form was submitted via POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // We cast these to integers immediately to prevent SQL injection or type-juggling attacks
    $role_id = (int) ($_POST['role_id'] ?? 0);
    $department_id = (int) ($_POST['department_id'] ?? 0);

    // DEFENSE NOTE: Server-Side Input Validation & Boundary Testing
    // Why? Client-side HTML validation (like 'required' or 'minlength') can easily be bypassed by a hacker using tools like Postman or modifying the browser's DOM. 
    // Here, we use a Regular Expression (preg_match) to ensure the username only contains safe characters, and we enforce strict length boundaries (3 to 50 chars).
    if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
        $message = '<div class="alert alert-danger">Use a username with 3-50 letters, numbers, dots, hyphens, or underscores.</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="alert alert-danger">Your password must be at least 6 characters long.</div>';
    } elseif (!in_array($role_id, [1, 2], true)) {
        // Prevents someone from manipulating the form to request 'Admin' (Role ID 3) access
        $message = '<div class="alert alert-danger">Please choose Student or Lecturer.</div>';
    } else {
        
        // DEFENSE NOTE: Duplicate Username Check (Data Integrity)
        $user_check = $conn->prepare('SELECT id FROM users WHERE username = ?');
        
        if (!$user_check) {
            // DEFENSE NOTE: Enterprise Security Error Handling
            // Why? Notice how we use error_log() here. Instead of printing $conn->error to the screen (which could reveal our database structure to a hacker), we log the actual error silently on the server and show a generic, friendly "temporarily unavailable" message to the user.
            error_log('Signup request lookup failed: ' . $conn->error);
            $message = '<div class="alert alert-danger">Account requests are temporarily unavailable. Please try again shortly.</div>';
        } else {
            $user_check->bind_param('s', $username);
            $user_check->execute();
            $username_exists = $user_check->get_result()->num_rows > 0;
            $user_check->close();

            if ($username_exists) {
                $message = '<div class="alert alert-danger">That username is already in use. Please choose another one.</div>';
            } else {
                
                // Read the first query result before preparing another statement on this connection.
                // DEFENSE NOTE: Staging Area Check
                // Why? We also need to check if they already have a pending request. This stops a user from spamming the "Submit" button and flooding the Admin dashboard with duplicate requests.
                $request_check = $conn->prepare("SELECT id FROM signup_requests WHERE username = ? AND `status` = 'Pending'");

                if (!$request_check) {
                    error_log('Signup request lookup failed: ' . $conn->error);
                    $message = '<div class="alert alert-danger">Account requests are temporarily unavailable. Please try again shortly.</div>';
                } else {
                    $request_check->bind_param('s', $username);
                    $request_check->execute();
                    $request_pending = $request_check->get_result()->num_rows > 0;
                    $request_check->close();

                    if ($request_pending) {
                        $message = '<div class="alert alert-info">A request for that username is already waiting for administrator approval.</div>';
                    } else {
                        
                        // DEFENSE NOTE: Cryptographic Hashing at the Entry Point
                        // Why? We hash the password BEFORE it even goes into the pending requests table. Even if a request is rejected or sitting in pending status, the plaintext password is never exposed or saved.
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // DEFENSE NOTE: Approval Workflow (Staging Table)
                        // Why? Notice we are inserting into 'signup_requests', NOT 'users'. This fulfills the system requirement that accounts must be vetted by an Admin before gaining access to campus resources.
                        $insert = $conn->prepare('INSERT INTO signup_requests (username, password_hash, requested_role_id, department_id) VALUES (?, ?, ?, ?)');

                        if ($insert) {
                            $insert->bind_param('ssii', $username, $password_hash, $role_id, $department_id);
                        }

                        if ($insert && $insert->execute()) {
                            $message = '<div class="alert alert-success">Your signup request was sent. You can log in once an administrator approves it.</div>';
                        } else {
                            error_log('Signup request insert failed: ' . $conn->error);
                            $message = '<div class="alert alert-danger">Your request could not be submitted. Please try again.</div>';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request an Account - Strathmore Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center py-5">
        <div class="card shadow-sm border-0 w-100" style="max-width: 500px;">
            <div class="card-body p-4 p-md-5">
                <h2 class="h3 text-center mb-2">Request an Account</h2>
                <p class="text-muted text-center mb-4">An administrator must approve your request before you can log in.</p>
                
                <?php echo $message; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="username">Username</label>
                        
                        <!-- DEFENSE NOTE: XSS Protection and Sticky Forms -->
                        <!-- Why? If a user makes an error (like a password that's too short), we repopulate the username field so they don't have to type it again. We use htmlspecialchars() to prevent Cross-Site Scripting (XSS) in case they typed malicious code into the box. -->
                        <input class="form-control" id="username" name="username" required maxlength="50" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="password">Password</label>
                        <input class="form-control" id="password" type="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="role_id">I am a</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <!-- Notice Admin is explicitly excluded from public signup -->
                            <option value="1">Student</option>
                            <option value="2">Lecturer</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold" for="department_id">Department / Faculty</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            
                            <!-- DEFENSE NOTE: Dynamic Rendering -->
                            <?php while ($department = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                            <?php endwhile; ?>
                            
                        </select>
                    </div>
                    <button class="btn btn-primary w-100 fw-bold" type="submit">Send Approval Request</button>
                </form>
                <div class="text-center mt-3"><a class="text-decoration-none" href="index.php">Back to login</a></div>
            </div>
        </div>
    </div>
</body>
</html>