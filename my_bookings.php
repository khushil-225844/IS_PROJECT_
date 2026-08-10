<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Strict Role-Based Access Control (RBAC)
// Why? We explicitly block 'admin' accounts or unauthenticated users from accessing this page. Admins have a global view in 'dashboard-admin.php', while this page is strictly isolated for individual students and lecturers to manage their own personal schedules.
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['student', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// DEFENSE NOTE: Real-Time Event Polling (Notifications)
// Why? This implements a critical Functional Requirement. If a Lecturer uses their elevated privileges to lock down a room that a Student previously booked, the system generates a displacement notice. This query fetches those unread alerts so the student is immediately informed upon checking their history.
$notification_sql = "SELECT id, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
$notification_stmt = $conn->prepare($notification_sql);
$notification_stmt->bind_param("i", $user_id);
$notification_stmt->execute();
$notifications = $notification_stmt->get_result();

// DEFENSE NOTE: Automated State Management (The 15-Minute Rule)
// Why? We run the Auto-Cancellation Sweep right here on the user's page load. By forcing the server to check Nairobi time against the database before rendering the HTML, we guarantee the student is looking at 100% accurate, real-time data. If they load this page 16 minutes after their booking started and they haven't scanned in, they will watch their pass automatically switch to 'Cancelled (No-Show)'.
date_default_timezone_set('Africa/Nairobi'); 
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

$cleanup_sql = "UPDATE bookings 
                SET status = 'Cancelled (No-Show)' 
                WHERE status = 'Confirmed' 
                AND (
                    booking_date < ? 
                    OR 
                    (booking_date = ? AND ADDTIME(start_time, '00:15:00') < ?)
                )";
                
$cleanup_stmt = $conn->prepare($cleanup_sql);
if ($cleanup_stmt) {
    $cleanup_stmt->bind_param("sss", $current_date, $current_date, $current_time);
    $cleanup_stmt->execute();
}

// DEFENSE NOTE: Enterprise Relational JOIN (Read Process)
// Why? To maintain 3NF (Third Normal Form) database integrity, the `bookings` table only stores the `room_id`. We use a SQL JOIN to dynamically pull the human-readable `room_name` from the `rooms` table. 
$sql = "SELECT b.id, r.room_name, b.seat_number, b.booking_date, b.start_time, b.end_time, b.status, b.equipment, b.qr_code_path 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.user_id = ? 
        ORDER BY b.booking_date DESC, b.start_time DESC";
        
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("<div class='alert alert-danger m-5'>Database Error: " . $conn->error . "</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - Strathmore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

    <?php
        // DEFENSE NOTE: Modular User Interface
        // Why? This dynamically adjusts the navigation bar color based on the session role, providing clear visual feedback on the user's current privilege level without needing duplicate HTML files.
        $nav_bg = ($_SESSION['role'] === 'lecturer') ? 'bg-dark' : 'bg-primary';
        $dash_link = ($_SESSION['role'] === 'lecturer') ? 'dashboard-lecturer.php' : 'dashboard-student.php';
        $brand_text = ($_SESSION['role'] === 'lecturer') ? 'Strathmore Faculty' : 'Strathmore Booking';
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $nav_bg; ?> shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $dash_link; ?>"><?php echo $brand_text; ?></a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="mainNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="<?php echo $dash_link; ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="rooms.php">Reserve Space</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3 fw-bold" href="my_bookings.php">My History</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-danger btn-sm fw-bold px-3 py-2" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="fw-bold mb-4">My Booking History</h2>

        <!-- Render Notifications (if any exist) -->
        <?php while ($notification = $notifications->fetch_assoc()): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Booking update:</strong> <?php echo htmlspecialchars($notification['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endwhile; ?>

        <div class="row">
            <?php
            if (isset($result) && $result->num_rows > 0) {
                while($booking = $result->fetch_assoc()) {
                    
                    // DEFENSE NOTE: Dynamic Status Parsing & UI Logic
                    // Why? We read the raw database string and use `stripos` (case-insensitive string position check) to determine the exact state of the booking. This determines the color of the UI badge, the text shown to the user, and most importantly, whether the QR code is displayed.
                    $raw_status = isset($booking['status']) ? $booking['status'] : 'Unknown';
                    
                    $badge_color = "bg-secondary";
                    $display_status = "Status: " . $raw_status; 
                    $show_qr = false;

                    if (stripos($raw_status, 'Confirm') !== false) {
                        $badge_color = "bg-warning text-dark";
                        $display_status = "⏳ Awaiting Check-In";
                        $show_qr = true; 
                    } elseif (stripos($raw_status, 'Check') !== false) {
                        $badge_color = "bg-success";
                        $display_status = "✅ Booking Verified";
                        $show_qr = true; 
                    } elseif (stripos($raw_status, 'Lecturer Priority') !== false) {
                        // NFR handling for Lecturer Overrides
                        $badge_color = "bg-danger";
                        $display_status = "Cancelled (Lecturer Priority)";
                        $show_qr = false;
                    } elseif (stripos($raw_status, 'Cancel') !== false) {
                        // Catch-all for No-Shows and User Cancellations
                        $badge_color = "bg-danger";
                        $display_status = "❌ Cancelled (Time Expired)";
                        $show_qr = false; 
                    }
                    
                    // Display formatting for Lecturer entire-room lockouts (seat '0')
                    $seat_display = ($booking['seat_number'] == 0) ? "Entire Room" : "Seat " . $booking['seat_number'];

                    echo "
                    <div class='col-md-6 mb-4'>
                        <div class='card shadow-sm border-0 h-100'>
                            <div class='card-header bg-white py-3 d-flex justify-content-between align-items-center' style='border-bottom: 2px solid #f8f9fa;'>
                                <h4 class='mb-0 fw-bold text-primary'>{$booking['room_name']}</h4>
                                <span class='badge {$badge_color} px-3 py-2 fs-6 shadow-sm'>{$display_status}</span>
                            </div>
                            <div class='card-body d-flex align-items-center justify-content-between'>
                                <div>
                                    <p class='mb-1'><strong>Date:</strong> {$booking['booking_date']}</p>
                                    <p class='mb-1'><strong>Time:</strong> {$booking['start_time']} - {$booking['end_time']}</p>
                                    <p class='mb-1'><strong>Reserved:</strong> {$seat_display}</p>
                                    <p class='mb-0'><strong>Equipment:</strong> {$booking['equipment']}</p>
                                </div>";

                    // DEFENSE NOTE: The Digital-to-Physical Bridge (Module 4)
                    // Why? If the booking is active ($show_qr is true) AND a path exists, we render the image. This is how the student physically verifies their digital reservation at the room door. If the pass is cancelled, we hide the QR code so they can't maliciously screenshot an old pass to trick the scanner.
                    if ($show_qr && !empty($booking['qr_code_path'])) {
                        echo "
                                <div class='text-center ms-3 border p-2 rounded bg-white shadow-sm'>
                                    <img src='{$booking['qr_code_path']}' alt='QR Code' style='width: 100px; height: 100px;'>
                                    <div class='small text-muted mt-1 fw-bold'>ID: {$booking['id']}</div>
                                </div>";
                    }

                    echo "
                            </div>";

                    // DEFENSE NOTE: Logical Button Rendering
                    // Why? The cancel button is ONLY shown if the booking is 'Confirmed' (Awaiting Check-in). If a student has already checked into the physical room (Verified) or the booking is already cancelled, we remove the cancel button to prevent database logic errors.
                    if (stripos($raw_status, 'Confirm') !== false) {
                        echo "
                            <div class='card-footer bg-white border-0 pb-3 px-3'>
                                <!-- Note: This routes to cancel_booking.php, which contains our IDOR security check to ensure users can only delete their own data. -->
                                <a href='cancel_booking.php?id={$booking['id']}'
                                   class='btn btn-outline-danger btn-sm w-100 fw-bold'
                                   onclick=\"return confirm('Are you sure you want to cancel this booking?');\">
                                   ❌ Cancel Booking
                                </a>
                            </div>";
                    }

                    echo "
                        </div>
                    </div>";
                }
            } else {
                echo "
                <div class='col-12 text-center py-5'>
                    <h4 class='text-muted'>No booking history found.</h4>
                    <p class='text-muted'>You haven't reserved any spaces yet.</p>
                    <a href='rooms.php' class='btn btn-primary mt-3 fw-bold px-4 py-2'>Find a Space</a>
                </div>";
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>