<?php
session_start();
require 'db_connect.php';

// Security Check: Ensure the user is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action 1: Add a Brand New User
    if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
        $email = trim($_POST['email']);
        $raw_password = trim($_POST['password']);
        $role = $_POST['role'];

        // First, check if this email is already registered
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Cannot create account: That email is already registered.</div>";
        } else {
            // Securely hash the password and insert the new user
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (email, password, role) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sss", $email, $hashed_password, $role);

            if ($insert_stmt->execute()) {
                $message = "<div class='alert alert-success'>Successfully created new {$role} account for <b>{$email}</b>!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error creating account: " . $conn->error . "</div>";
            }
        }
    }

    // Action 2: Update User Role
    if (isset($_POST['action']) && $_POST['action'] == 'update_role') {
        $target_user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'];
        
        if ($target_user_id == $_SESSION['user_id']) {
            $message = "<div class='alert alert-danger'>You cannot change your own role.</div>";
        } else {
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_role, $target_user_id);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>User role updated to '{$new_role}' successfully!</div>";
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
                $message = "<div class='alert alert-success'>User account permanently deleted.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error deleting user. They likely have active bookings preventing deletion.</div>";
            }
        }
    }
}

// Fetch all registered users for the table
$users_sql = "SELECT id, email, role, created_at FROM users ORDER BY role ASC, email ASC";
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
                <p class="text-muted">Onboard new accounts, upgrade roles, or reset passwords.</p>
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
                                <label class="form-label fw-bold">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="user@strathmore.edu" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Temporary Password</label>
                                <input type="text" name="password" class="form-control" placeholder="Enter password" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Account Role</label>
                                <select name="role" class="form-select">
                                    <option value="student">Student</option>
                                    <option value="lecturer">Lecturer</option>
                                    <option value="admin">Admin</option>
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
                                        <th>Email Address</th>
                                        <th>Role</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($users_result->num_rows > 0) {
                                        while($user = $users_result->fetch_assoc()) {
                                            
                                            $role_badge = 'bg-secondary';
                                            if ($user['role'] == 'admin') $role_badge = 'bg-danger';
                                            if ($user['role'] == 'lecturer') $role_badge = 'bg-dark';
                                            if ($user['role'] == 'student') $role_badge = 'bg-primary';

                                            $is_self = ($user['id'] == $_SESSION['user_id']);

                                            echo "<tr>
                                                    <td><strong>{$user['email']}</strong>" . ($is_self ? " <span class='badge bg-success ms-1'>You</span>" : "") . "</td>
                                                    <td><span class='badge {$role_badge} text-uppercase'>{$user['role']}</span></td>
                                                    <td class='text-end'>
                                                        ".(!$is_self ? "
                                                        <div class='d-flex justify-content-end gap-2'>
                                                            <form method='POST' class='d-flex gap-1'>
                                                                <input type='hidden' name='action' value='update_role'>
                                                                <input type='hidden' name='user_id' value='{$user['id']}'>
                                                                <select name='new_role' class='form-select form-select-sm' style='width: 100px;'>
                                                                    <option value='student' ".($user['role']=='student'?'selected':'').">Student</option>
                                                                    <option value='lecturer' ".($user['role']=='lecturer'?'selected':'').">Lecturer</option>
                                                                    <option value='admin' ".($user['role']=='admin'?'selected':'').">Admin</option>
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
                                        echo "<tr><td colspan='3' class='text-center py-4'>No users found.</td></tr>";
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