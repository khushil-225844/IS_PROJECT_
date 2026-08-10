<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Admin-Only Routing (RBAC)
// Why? User management is the most sensitive part of the system. This blocks students and lecturers from accessing the user directory or escalating their own privileges.
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$admin_id = $_SESSION['user_id']; // For the Audit Log

// Handle Form Submissions via a Single Controller
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action 1: Add a Brand New User (ENTERPRISE UPGRADE)
    if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
        $username = trim($_POST['username']);
        $raw_password = trim($_POST['password']);
        $role_id = intval($_POST['role_id']);
        $department_id = intval($_POST['department_id']);

        // DEFENSE NOTE: Duplicate Username Prevention
        // Why? We query the database first to ensure no two users have the same username. This prevents login conflicts.
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Cannot create account: That username is already registered.</div>";
        } else {
            // DEFENSE NOTE: Password Hashing (Crucial Security NFR)
            // Why? We NEVER store plaintext passwords in the database. password_hash() uses the bcrypt algorithm to generate a one-way mathematical hash. Even if a hacker steals the database, they cannot read the passwords.
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (role_id, department_id, username, password) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiss", $role_id, $department_id, $username, $hashed_password);

            if ($insert_stmt->execute()) {
                $new_user_id = $conn->insert_id;
                
                // Security Tracking (Audit Log)
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

    // Action 2: Approve or reject a public signup request.
    if (isset($_POST['action']) && $_POST['action'] == 'approve_signup') {
        $request_id = intval($_POST['request_id']);
        $request_sql = "SELECT id, username, password_hash, requested_role_id, department_id
                        FROM signup_requests WHERE id = ? AND status = 'Pending'";
        $request_stmt = $conn->prepare($request_sql);
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request = $request_stmt->get_result()->fetch_assoc();

        if (!$request) {
            $message = "<div class='alert alert-danger'>That signup request is no longer pending.</div>";
        } else {
            $duplicate_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $duplicate_stmt->bind_param("s", $request['username']);
            $duplicate_stmt->execute();

            if ($duplicate_stmt->get_result()->num_rows > 0) {
                $message = "<div class='alert alert-danger'>Cannot approve this request because the username already exists.</div>";
            } else {
                
                // DEFENSE NOTE: ACID-Compliant Database Transactions
                // Why? This is a massive Enterprise feature! Approving a user requires TWO actions: 1) Inserting them into the users table. 2) Updating the request status to 'Approved'. 
                // By using begin_transaction(), we ensure that if step 2 fails, step 1 is "rolled back" (undone). This prevents partial data corruption (like creating a user but leaving the request pending forever).
                $conn->begin_transaction();
                
                $insert_sql = "INSERT INTO users (role_id, department_id, username, password) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiss", $request['requested_role_id'], $request['department_id'], $request['username'], $request['password_hash']);

                $review_sql = "UPDATE signup_requests SET status = 'Approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = 'Pending'";
                $review_stmt = $conn->prepare($review_sql);
                $review_stmt->bind_param("ii", $admin_id, $request_id);

                $user_created = $insert_stmt->execute();
                $new_user_id = $conn->insert_id;

                if ($user_created && $review_stmt->execute() && $review_stmt->affected_rows === 1) {
                    $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'SIGNUP_APPROVED', ?)";
                    $audit_stmt = $conn->prepare($audit_sql);
                    $action_details = "Approved signup request for username: {$request['username']} (new user ID: {$new_user_id})";
                    $audit_stmt->bind_param("is", $admin_id, $action_details);

                    if ($audit_stmt->execute()) {
                        $conn->commit(); // SUCCESS: Commit both actions to the database!
                        $safe_username = htmlspecialchars($request['username']);
                        $message = "<div class='alert alert-success'>Approved <b>{$safe_username}</b>. They can now log in.</div>";
                    } else {
                        $conn->rollback(); // FAIL: Undo the transaction.
                        $message = "<div class='alert alert-danger'>The approval could not be completed.</div>";
                    }
                } else {
                    $conn->rollback(); // FAIL: Undo the transaction.
                    $message = "<div class='alert alert-danger'>The approval could not be completed.</div>";
                }
            }
        }
    }

    // Reject Signup Request
    if (isset($_POST['action']) && $_POST['action'] == 'reject_signup') {
        $request_id = intval($_POST['request_id']);
        $reject_sql = "UPDATE signup_requests SET status = 'Rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = 'Pending'";
        $reject_stmt = $conn->prepare($reject_sql);
        $reject_stmt->bind_param("ii", $admin_id, $request_id);

        if ($reject_stmt->execute() && $reject_stmt->affected_rows === 1) {
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'SIGNUP_REJECTED', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Rejected signup request ID: {$request_id}";
            $audit_stmt->bind_param("is", $admin_id, $action_details);
            $audit_stmt->execute();
            $message = "<div class='alert alert-secondary'>Signup request rejected.</div>";
        } else {
            $message = "<div class='alert alert-danger'>That signup request is no longer pending.</div>";
        }
    }

    // Action 3: Update User Role (ENTERPRISE UPGRADE)
    if (isset($_POST['action']) && $_POST['action'] == 'update_role') {
        $target_user_id = intval($_POST['user_id']);
        $new_role_id = intval($_POST['new_role_id']);
        
        // DEFENSE NOTE: Boundary Testing / Logical Constraint
        // Why? We prevent the logged-in admin from accidentally downgrading their own role to a student, which would lock them out of the admin panel forever!
        if ($target_user_id == $_SESSION['user_id']) {
            $message = "<div class='alert alert-danger'>You cannot change your own role.</div>";
        } else {
            $sql = "UPDATE users SET role_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_role_id, $target_user_id);
            if ($stmt->execute()) {
                
                $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'USER_ROLE_UPDATED', ?)";
                $audit_stmt = $conn->prepare($audit_sql);
                $action_details = "Updated user ID {$target_user_id} to Role ID {$new_role_id}";
                $audit_stmt->bind_param("is", $admin_id, $action_details);
                $audit_stmt->execute();

                $message = "<div class='alert alert-success'>User role updated successfully!</div>";
            }
        }
    }
    
    // Action 4: Reset Password to Default
    if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $target_user_id = intval($_POST['user_id']);
        $default_password = "Strathmore2026!"; // Hardcoded temporary default password
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $target_user_id);
        if ($stmt->execute()) {
            
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'PASSWORD_RESET', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Reset password for user ID {$target_user_id}";
            $audit_stmt->bind_param("is", $admin_id, $action_details);
            $audit_stmt->execute();

            $message = "<div class='alert alert-success'>Password reset successfully! The temporary password is: <b>{$default_password}</b></div>";
        }
    }

    // Action 5: Delete User
    if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
        $target_user_id = intval($_POST['user_id']);
        
        // Boundary Validation to prevent self-deletion
        if ($target_user_id == $_SESSION['user_id']) {
            $message = "<div class='alert alert-danger'>You cannot delete your own active admin account.</div>";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $target_user_id);
            if ($stmt->execute()) {
                
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

// --- READ QUERIES (Data Fetching) ---

// DEFENSE NOTE: 3-Table Relational JOIN for Users
// Why? The `users` table only stores IDs (role_id, department_id) to maintain 3NF normalization. We use JOINs to fetch the human-readable 'role_name' and 'department_name' from their respective tables.
$users_sql = "SELECT users.id, users.username, roles.role_name, departments.department_name 
              FROM users 
              JOIN roles ON users.role_id = roles.id 
              LEFT JOIN departments ON users.department_id = departments.id
              ORDER BY roles.access_level DESC, users.username ASC";
$users_result = $conn->query($users_sql);

// JOIN query for Pending Signup Requests
$requests_sql = "SELECT sr.id, sr.username, sr.created_at, r.role_name, d.department_name
                 FROM signup_requests sr
                 JOIN roles r ON sr.requested_role_id = r.id
                 JOIN departments d ON sr.department_id = d.id
                 WHERE sr.status = 'Pending'
                 ORDER BY sr.created_at ASC";
$requests_result = $conn->query($requests_sql);
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

    <!-- Navbar -->
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
            
            <!-- Pending Signups Table -->
            <div class="col-12 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-warning-subtle py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Pending Signup Requests</h5>
                        <span class="badge bg-warning text-dark"><?php echo $requests_result->num_rows; ?> pending</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($requests_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>Username</th><th>Department</th><th>Requested role</th><th>Requested</th><th class="text-end">Decision</th></tr></thead>
                                    <tbody>
                                        <?php while ($request = $requests_result->fetch_assoc()): ?>
                                            <tr>
                                                <!-- DEFENSE NOTE: XSS Protection -->
                                                <!-- Why? htmlspecialchars() is used here because this data was typed by an external, untrusted user during the signup process. It sanitizes the string before rendering it. -->
                                                <td><strong><?php echo htmlspecialchars($request['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($request['department_name']); ?></td>
                                                <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($request['role_name']); ?></span></td>
                                                <td class="text-muted small"><?php echo htmlspecialchars($request['created_at']); ?></td>
                                                <td class="text-end">
                                                    
                                                    <!-- Approval Form -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="approve_signup">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                                    </form>
                                                    
                                                    <!-- Rejection Form -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Reject this signup request?');">
                                                        <input type="hidden" name="action" value="reject_signup">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center my-4">There are no pending signup requests.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Manual User Creation Form -->
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

            <!-- Master User Directory Table -->
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
                                            
                                            // Dynamic UI Badges based on role
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
                                                            <!-- Role Update Form -->
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
                                                            
                                                            <!-- Password Reset Form -->
                                                            <form method='POST' onsubmit=\"return confirm('Reset password to Strathmore2026!?');\">
                                                                <input type='hidden' name='action' value='reset_password'>
                                                                <input type='hidden' name='user_id' value='{$user['id']}'>
                                                                <button type='submit' class='btn btn-sm btn-outline-warning text-dark'>Reset</button>
                                                            </form>

                                                            <!-- Delete User Form -->
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