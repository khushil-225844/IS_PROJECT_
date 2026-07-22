<?php
session_start();
require 'db_connect.php';

// Security Check: Ensure the user is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$admin_id = $_SESSION['user_id']; // For the Audit Log

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action 1: Add a Brand New User (ENTERPRISE UPGRADE)
    if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
        $username = trim($_POST['username']);
        $raw_password = trim($_POST['password']);
        $role_id = intval($_POST['role_id']);
        $department_id = intval($_POST['department_id']);

        // First, check if this username is already registered
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Cannot create account: That username is already registered.</div>";
        } else {
            // Securely hash the password and insert the new user using IDs
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            // Note: If you aren't using password hashing yet, change this to $raw_password for testing, but hashing is best practice!
            // I'm keeping your original hashing logic intact.
            
            $insert_sql = "INSERT INTO users (role_id, department_id, username, password) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiss", $role_id, $department_id, $username, $hashed_password);

            if ($insert_stmt->execute()) {
                $new_user_id = $conn->insert_id;
                
                // Security Tracking
                $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'USER_ADDED', ?)";
                $audit_stmt = $conn->prepare($audit_sql);
                $action_details = "Created new account for username: {$username} (Role ID: {$role_id})";
                $audit_stmt->bind_param("is", $admin_id, $action_details);
                $audit_stmt->execute();

                $message = "<div class='alert alert-success'>Successfully created new account for <b>{$username}</b>!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error creating account: " . $conn->error . "</div>";
            }
        }
    }

    // Action 2: Update User Role (ENTERPRISE UPGRADE)
    if (isset($_POST['action']) && $_POST['action'] == 'update_role') {
        $target_user_id = intval($_POST['user_id']);
        $new_role_id = intval($_POST['new_role_id']);
        
        if ($target_user_id == $_SESSION['user_id']) {
            $message = "<div class='alert alert-danger'>You cannot change your own role.</div>";
        } else {
            $sql = "UPDATE users SET role_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_role_id, $target_user_id);
            if ($stmt->execute()) {
                
                // Security Tracking
                $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'USER_ROLE_UPDATED', ?)";
                $audit_stmt = $conn->prepare($audit_sql);
                $action_details = "Updated user ID {$target_user_id} to Role ID {$new_role_id}";
                $audit_stmt->bind_param("is", $admin_id, $action_details);
                $audit_stmt->execute();

                $message = "<div class='alert alert-success'>User role updated successfully!</div>";
            }
        }
    }
    
    // Action 3: Reset Password to Default
    if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $target_user_id = intval($_POST['user_id']);
        $default_password = "Strathmore2026!";
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $target_user_id);
        if ($stmt->execute()) {
            
            // Security Tracking
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'PASSWORD_RESET', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Reset password for user ID {$target_user_id}";
            $audit_stmt->bind_param("is", $admin_id, $action_details);
            $audit_stmt->execute();

            $message = "<div class='alert alert-success'>Password reset successfully! The temporary password is: <b>{$default_password}</b></div>";
        }
    }

    // Action 4: Delete User
    if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
        $target_user_id = intval($_POST['user_id']);
        
        if ($target_user_id == $_SESSION['user_id']) {
            $message = "<div class='alert alert-danger'>You cannot delete your own active admin account.</div>";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $target_user_id);
            if ($stmt->execute()) {
                
                // Security Tracking
                $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'USER_DELETED', ?)";
                $audit_stmt = $conn->prepare($audit_sql);
                $action_details = "Permanently deleted user ID {$target_user_id}";
                $audit_stmt->bind_param("is", $admin_id, $action_details);
                $audit_stmt->execute();

                $message = "<div class='alert alert-success'>User account permanently deleted.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error deleting user. They likely have active bookings preventing deletion.</div>";
            }
        }
    }
}

// Fetch all registered users utilizing Enterprise JOINs
$users_sql = "SELECT users.id, users.username, roles.role_name, departments.department_name 
              FROM users 
              JOIN roles ON users.role_id = roles.id 
              LEFT JOIN departments ON users.department_id = departments.id
              ORDER BY roles.access_level DESC, users.username ASC";
$users_result = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard-admin.php">Strathmore Admin</a>
            <div class="collapse navbar-collapse justify-content-end">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard-admin.php">Back to Dashboard</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2>User Management</h2>
                <p class="text-muted">Onboard new accounts, assign departments, or upgrade roles.</p>
                <?php echo $message; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        + Add New Account
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_user">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" name="username" class="form-control" placeholder="e.g. StudentJohn" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Department / Faculty</label>
                                <select name="department_id" class="form-select" required>
                                    <option value="1">School of Computing</option>
                                    <option value="2">Business School</option>
                                    <option value="3">Law School</option>
                                    <option value="4">Admin Operations</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Temporary Password</label>
                                <input type="text" name="password" class="form-control" placeholder="Enter password" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Account Role</label>
                                <select name="role_id" class="form-select" required>
                                    <option value="1">Student</option>
                                    <option value="2">Lecturer</option>
                                    <option value="3">Admin</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 fw-bold">Create Account</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-danger fw-bold">Master User Directory</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Username</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($users_result->num_rows > 0) {
                                        while($user = $users_result->fetch_assoc()) {
                                            
                                            $role_badge = 'bg-secondary';
                                            if ($user['role_name'] == 'admin') $role_badge = 'bg-danger';
                                            if ($user['role_name'] == 'lecturer') $role_badge = 'bg-dark';
                                            if ($user['role_name'] == 'student') $role_badge = 'bg-primary';

                                            $is_self = ($user['id'] == $_SESSION['user_id']);
                                            $dept_name = $user['department_name'] ? $user['department_name'] : 'Unassigned';

                                            echo "<tr>
                                                    <td><strong>{$user['username']}</strong>" . ($is_self ? " <span class='badge bg-success ms-1'>You</span>" : "") . "</td>
                                                    <td><span class='text-muted small'>{$dept_name}</span></td>
                                                    <td><span class='badge {$role_badge} text-uppercase'>{$user['role_name']}</span></td>
                                                    <td class='text-end'>
                                                        ".(!$is_self ? "
                                                        <div class='d-flex justify-content-end gap-2'>
                                                            <form method='POST' class='d-flex gap-1'>
                                                                <input type='hidden' name='action' value='update_role'>
                                                                <input type='hidden' name='user_id' value='{$user['id']}'>
                                                                <select name='new_role_id' class='form-select form-select-sm' style='width: 100px;'>
                                                                    <option value='1' ".($user['role_name']=='student'?'selected':'').">Student</option>
                                                                    <option value='2' ".($user['role_name']=='lecturer'?'selected':'').">Lecturer</option>
                                                                    <option value='3' ".($user['role_name']=='admin'?'selected':'').">Admin</option>
                                                                </select>
                                                                <button type='submit' class='btn btn-sm btn-outline-dark'>Save</button>
                                                            </form>
                                                            
                                                            <form method='POST' onsubmit=\"return confirm('Reset password to Strathmore2026!?');\">
                                                                <input type='hidden' name='action' value='reset_password'>
                                                                <input type='hidden' name='user_id' value='{$user['id']}'>
                                                                <button type='submit' class='btn btn-sm btn-outline-warning text-dark'>Reset</button>
                                                            </form>

                                                            <form method='POST' onsubmit=\"return confirm('Permanently delete this user?');\">
                                                                <input type='hidden' name='action' value='delete_user'>
                                                                <input type='hidden' name='user_id' value='{$user['id']}'>
                                                                <button type='submit' class='btn btn-sm btn-danger'>Delete</button>
                                                            </form>
                                                        </div>
                                                        " : "<span class='text-muted small'>Restricted (Current Session)</span>")."
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-4'>No users found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>