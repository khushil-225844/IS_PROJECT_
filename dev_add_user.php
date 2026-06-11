<?php
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // Securely hash the custom password before saving
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (email, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $hashed_password, $role);

    if ($stmt->execute()) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
                Success! Account created for <b>{$email}</b>. They can now log in.
              </div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
                Error: " . $conn->error . "
              </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dev Tool - Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="card shadow-sm p-4 mx-auto" style="max-width: 500px;">
        <h3 class="mb-4 text-primary">Developer Tool: Add User</h3>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="e.g. test1@strathmore.edu" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Custom Password</label>
                <input type="text" name="password" class="form-control" placeholder="Type password here..." required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Role</label>
                <select name="role" class="form-select">
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100">Create Account</button>
        </form>
    </div>
</body>
</html>