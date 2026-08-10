<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Role-Based Access Control (RBAC)
// Why? Analytics and system-wide metrics are strictly for administrators. This prevents students or lecturers from viewing sensitive facility utilization data or audit logs.
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// DEFENSE NOTE: Aggregate SQL Functions (Performance Optimization NFR)
// Why? We use the COUNT(*) function directly inside the SQL queries. If we instead used 'SELECT *' and counted the rows using PHP's count() array function, it would pull thousands of records into the server's RAM, slowing the system down. SQL COUNT() is incredibly fast and memory-efficient, ensuring system scalability.
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];

$today = date('Y-m-d');

// DEFENSE NOTE: Business Logic & Data Accuracy
// Why? A raw count of today's bookings would include 'Cancelled' passes and 'No-Shows', which would artificially inflate our occupancy metrics. By explicitly filtering the status using the SQL `IN` operator, we ensure management sees a 100% accurate picture of actual campus utilization.
$bookings_today = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date = '$today' AND status IN ('Confirmed', 'Checked-In')")->fetch_assoc()['count'];

// Count total security events logged by the system
$audit_events = $conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count'];

// Fetch base room data to generate the progress bars below
$rooms_sql = "SELECT id, room_name, capacity, status FROM rooms ORDER BY room_name ASC";
$rooms_result = $conn->query($rooms_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard-admin.php">Strathmore Admin</a>
            <div class="collapse navbar-collapse justify-content-end">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link text-white" href="dashboard-admin.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="manage_users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="manage_rooms.php">Rooms</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Facility Utilization Reports</h2>
                <p class="text-muted">Live occupancy tracking and historical data export.</p>
            </div>
            <div>
                <!-- DEFENSE NOTE: Data Portability (Decision Support) -->
                <!-- Why? Web dashboards are great, but university management often requires data in external tools for budget planning. This CTA triggers a script that compiles our relational data into a portable CSV format for Microsoft Excel. -->
                <a href="export_report.php" class="btn btn-success fw-bold shadow-sm">
                    📥 Export Full Data (CSV)
                </a>
            </div>
        </div>

        <!-- System-Wide KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-primary text-white text-center py-3 h-100">
                    <h2 class="fw-bold mb-0"><?php echo $total_users; ?></h2>
                    <span class="small text-uppercase">Registered Users</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-dark text-white text-center py-3 h-100">
                    <h2 class="fw-bold mb-0"><?php echo $total_bookings; ?></h2>
                    <span class="small text-uppercase">All-Time Bookings</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-warning text-dark text-center py-3 h-100">
                    <h2 class="fw-bold mb-0"><?php echo $bookings_today; ?></h2>
                    <span class="small text-uppercase">Active Today</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-danger text-white text-center py-3 h-100">
                    <h2 class="fw-bold mb-0"><?php echo $audit_events; ?></h2>
                    <span class="small text-uppercase">Security Events</span>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-danger fw-bold">Live Room Occupancy (Today: <?php echo date('M j, Y'); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // DEFENSE NOTE: Dynamic Real-Time Data Visualization
                    if ($rooms_result->num_rows > 0) {
                        while($room = $rooms_result->fetch_assoc()) {
                            $r_id = $room['id'];
                            $capacity = $room['capacity'];
                            
                            // 1. Calculate how many individual seats are currently occupied by students today
                            // We explicitly ignore cancelled passes and seat '0' (which represents a lecturer lock)
                            $seat_sql = "SELECT COUNT(DISTINCT seat_number) as seats_taken FROM bookings WHERE room_id = $r_id AND booking_date = '$today' AND seat_number > 0 AND status IN ('Confirmed', 'Checked-In')";
                            $seats_taken = $conn->query($seat_sql)->fetch_assoc()['seats_taken'];
                            
                            // 2. Check if a Lecturer has invoked an override lock (seat_number = 0)
                            $lock_sql = "SELECT COUNT(*) as locked FROM bookings WHERE room_id = $r_id AND booking_date = '$today' AND seat_number = 0 AND status IN ('Confirmed', 'Checked-In')";
                            $is_locked = $conn->query($lock_sql)->fetch_assoc()['locked'] > 0;

                            // DEFENSE NOTE: Mathematical Translation to UI Elements
                            // Why? We use PHP to translate raw numbers into a percentage, which is then injected directly into Bootstrap's CSS 'width' property to create a live progress bar.
                            if ($is_locked) {
                                // If a lecturer locked it, instantly force the UI to 100% full capacity (Red)
                                $percent = 100;
                                $display_text = "ROOM LOCKED (LECTURE)";
                                $bar_color = "bg-danger";
                            } else {
                                // Calculate standard student seat percentage (preventing Division by Zero errors)
                                $percent = ($capacity > 0) ? round(($seats_taken / $capacity) * 100) : 0;
                                $display_text = "{$seats_taken} / {$capacity} Seats Booked Today";
                                
                                // Dynamic Color Coding based on thresholds
                                if ($percent < 50) $bar_color = "bg-success";         // Green for empty
                                elseif ($percent < 90) $bar_color = "bg-warning";     // Yellow for filling up
                                else $bar_color = "bg-danger";                        // Red for full
                            }
                            ?>
                            
                            <div class="col-md-6 mb-4">
                                <div class="p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold mb-0"><?php echo $room['room_name']; ?></h6>
                                        <span class="badge bg-light text-dark border"><?php echo $percent; ?>% Full</span>
                                    </div>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <!-- The calculated variables are injected right here into the CSS class and inline styles -->
                                        <div class="progress-bar <?php echo $bar_color; ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <div class="small text-muted text-end fw-bold"><?php echo $display_text; ?></div>
                                </div>
                            </div>
                            
                            <?php
                        }
                    } else {
                        echo "<p class='text-muted'>No rooms configured in the system.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>