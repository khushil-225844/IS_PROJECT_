<?php
// DEFENSE NOTE: Session Management & Privilege Escalation Prevention
// Why? We resume the session and immediately check if the user is explicitly an 'admin'. If a 'student' or 'lecturer' tries to type "dashboard-admin.php" in their URL bar, the system forcefully redirects them. This fulfills the Non-Functional Requirement (NFR) for Access Control.
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require 'db_connect.php';

// --- BULLETPROOF AUTO-CANCELLATION ENGINE ---
// DEFENSE NOTE: Time Synchronization
// Why? We lock the PHP script to 'Africa/Nairobi' so that the server time matches Strathmore's physical timezone perfectly.
date_default_timezone_set('Africa/Nairobi'); 

$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// DEFENSE NOTE: Automated State Management (The 15-Minute Rule)
// Why? This is a core functional requirement. Instead of relying on admins to manually cancel no-shows, this SQL query automatically sweeps the database. 
// Logic: It targets 'Confirmed' bookings where either the date is in the past (< ?) OR the date is today but the start time plus 15 minutes is earlier than right now (ADDTIME < ?). This frees up abandoned campus resources dynamically.
$cleanup_sql = "UPDATE bookings 
                SET status = 'Cancelled (No-Show)' 
                WHERE status = 'Confirmed' 
                AND (
                    booking_date < ? 
                    OR 
                    (booking_date = ? AND ADDTIME(start_time, '00:15:00') < ?)
                )";
                
$stmt = $conn->prepare($cleanup_sql);
if ($stmt) {
    $stmt->bind_param("sss", $current_date, $current_date, $current_time);
    $stmt->execute();
}
// ------------------------------------------------

// --- Analytics Queries ---

// DEFENSE NOTE: SQL Aggregate Functions
// Why? We use the COUNT(*) function to quickly aggregate massive amounts of data without pulling every single row into PHP's memory. This is highly efficient and scalable.
$user_query = $conn->query("SELECT COUNT(*) as count FROM users");
$total_users = $user_query->fetch_assoc()['count'];

$room_query = $conn->query("SELECT COUNT(*) as count FROM rooms");
$total_rooms = $room_query->fetch_assoc()['count'];

$booking_query = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Confirmed'");
$total_bookings = $booking_query->fetch_assoc()['count'];

// DEFENSE NOTE: 3NF Database Normalization & 4-Table JOIN
// Why? To prove our database is in Third Normal Form (3NF), we do not store redundant data (like usernames or role names) inside the bookings table. 
// Instead, we use a complex relational JOIN query. We pull the raw booking data, join the 'rooms' table for the room name, join the 'users' table for the username, and join the 'roles' table for the role name. This ensures perfect data integrity.
$recent_sql = "SELECT b.id, b.booking_date, b.start_time, b.status, r.room_name, u.username, ro.role_name 
               FROM bookings b
               JOIN rooms r ON b.room_id = r.id
               JOIN users u ON b.user_id = u.id
               JOIN roles ro ON u.role_id = ro.id
               ORDER BY b.id DESC LIMIT 5";
$recent_bookings = $conn->query($recent_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Strathmore Room Booking</title>
    <!-- DEFENSE NOTE: Bootstrap 5 CDN -->
    <!-- Why? We use a Content Delivery Network (CDN) to pull in Bootstrap for a responsive, mobile-first design without bloating our local server storage. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar Section -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard-admin.php">Strathmore Admin</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="adminNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="dashboard-admin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="manage_rooms.php">Manage Rooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="scan.php">QR Scanner</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-dark btn-sm fw-bold px-3 py-2" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2>Facility Administration</h2>
                <p class="text-muted">System overview and master booking logs.</p>
            </div>
        </div>

        <!-- Dashboard Stat Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-primary text-white h-100">
                    <div class="card-body text-center py-4">
                        <h1 class="display-4 fw-bold"><?php echo $total_bookings; ?></h1>
                        <h5 class="mb-0">Active Bookings</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-dark text-white h-100">
                    <div class="card-body text-center py-4">
                        <h1 class="display-4 fw-bold"><?php echo $total_rooms; ?></h1>
                        <h5 class="mb-0">Total Rooms</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-secondary text-white h-100">
                    <div class="card-body text-center py-4">
                        <h1 class="display-4 fw-bold"><?php echo $total_users; ?></h1>
                        <h5 class="mb-0">Registered Users</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-danger fw-bold">Recent Booking Activity</h5>
                <div>
                    <!-- DEFENSE NOTE: Navigation Routing -->
                    <a href="scan.php" class="btn btn-sm btn-success shadow-sm fw-bold me-2">📷 QR Scanner</a>
                    <a href="system_reports.php" class="btn btn-sm btn-dark shadow-sm fw-bold me-2">📊 Analytics</a>
                    <a href="manage_users.php" class="btn btn-sm btn-outline-dark shadow-sm fw-bold me-2">Users</a>
                    <a href="manage_rooms.php" class="btn btn-sm btn-danger shadow-sm fw-bold me-2">Rooms</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking ID</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Room</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // DEFENSE NOTE: Data Rendering and Conditional Formatting
                            if ($recent_bookings && $recent_bookings->num_rows > 0) {
                                while($row = $recent_bookings->fetch_assoc()) {
                                    
                                    // DEFENSE NOTE: String Search Function (stripos)
                                    // Why? Instead of hardcoding every possible cancellation status ('Cancelled', 'Cancelled (No-Show)'), we use PHP's 'stripos' to check if the word "Cancel" exists anywhere in the status string. If it does, we dynamically turn the UI badge red (bg-danger).
                                    $badge = 'bg-success';
                                    if (stripos($row['status'], 'Cancel') !== false) $badge = 'bg-danger';
                                    
                                    // Format the date/time into a human-readable format
                                    $time_str = date("M j, Y", strtotime($row['booking_date'])) . " @ " . date("g:i A", strtotime($row['start_time']));
                                    
                                    echo "<tr>
                                            <td><small class='text-muted'>#STR-{$row['id']}</small></td>
                                            <td>{$row['username']}</td>
                                            <td><span class='badge bg-secondary text-uppercase'>{$row['role_name']}</span></td>
                                            <td><strong>{$row['room_name']}</strong></td>
                                            <td>{$time_str}</td>
                                            <td><span class='badge {$badge}'>{$row['status']}</span></td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center py-4 text-muted'>No recent activity found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>