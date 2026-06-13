<?php
// 1. Resume the session
session_start();

// 2. Security Check: Ensure the user is logged in AND is an 'admin'
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require 'db_connect.php';

// --- AUTO-CANCELLATION ENGINE ---
// Find any booking for today that is still 'Confirmed', where the start time was more than 15 minutes ago, and change it to 'Cancelled (No-Show)'
// --- AUTO-CANCELLATION ENGINE (UPGRADED) ---
// Find any 'Confirmed' booking that is either from a past date, OR from today but more than 15 minutes old.
$cleanup_sql = "UPDATE bookings 
                SET status = 'Cancelled (No-Show)' 
                WHERE status = 'Confirmed' 
                AND (
                    booking_date < CURDATE() 
                    OR 
                    (booking_date = CURDATE() AND start_time < SUBTIME(CURTIME(), '00:15:00'))
                )";
$conn->query($cleanup_sql);
// ------------------------------------

// --- Analytics Queries ---

// Query A: Count total registered users
$user_query = $conn->query("SELECT COUNT(*) as count FROM users");
$total_users = $user_query->fetch_assoc()['count'];

// Query B: Count total rooms in the database
$room_query = $conn->query("SELECT COUNT(*) as count FROM rooms");
$total_rooms = $room_query->fetch_assoc()['count'];

// Query C: Count total confirmed bookings
$booking_query = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Confirmed'");
$total_bookings = $booking_query->fetch_assoc()['count'];

// Query D: Fetch the 5 most recent bookings across the entire university
// Uses JOINs to pull the human-readable room name and user email
$recent_sql = "SELECT b.id, b.booking_date, b.start_time, b.status, r.room_name, u.email, u.role 
               FROM bookings b
               JOIN rooms r ON b.room_id = r.id
               JOIN users u ON b.user_id = u.id
               ORDER BY b.created_at DESC LIMIT 5";
$recent_bookings = $conn->query($recent_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Strathmore Room Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">Strathmore Admin</a>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-bold" href="logout.php">Logout</a>
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


<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-danger fw-bold">Recent Booking Activity</h5>
                <div>
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
                            if ($recent_bookings->num_rows > 0) {
                                while($row = $recent_bookings->fetch_assoc()) {
                                    // Set badge color based on status
                                    $badge = 'bg-success';
                                    if ($row['status'] == 'Cancelled') $badge = 'bg-danger';
                                    
                                    // Format the date/time
                                    $time_str = date("M j, Y", strtotime($row['booking_date'])) . " @ " . date("g:i A", strtotime($row['start_time']));
                                    
                                    echo "<tr>
                                            <td><small class='text-muted'>#STR-{$row['id']}</small></td>
                                            <td>{$row['email']}</td>
                                            <td><span class='badge bg-secondary text-uppercase'>{$row['role']}</span></td>
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