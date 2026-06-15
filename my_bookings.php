<?php
session_start();
require 'db_connect.php';

// THE NEW, UPGRADED BOUNCER
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['student', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
        // Determine user role for dynamic styling and links
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
                        <a class="nav-link text-white px-3" href="my_bookings.php">My History</a>
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

        <div class="row">
            <?php
            if (isset($result) && $result->num_rows > 0) {
                while($booking = $result->fetch_assoc()) {
                    
                    // --- STATUS LOGIC ---
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
                    } elseif (stripos($raw_status, 'Cancel') !== false) {
                        $badge_color = "bg-danger";
                        $display_status = "❌ Cancelled (Time Expired)";
                        $show_qr = false; 
                    }
                    
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

                    if ($show_qr && !empty($booking['qr_code_path'])) {
                        echo "
                                <div class='text-center ms-3 border p-2 rounded bg-white shadow-sm'>
                                    <img src='{$booking['qr_code_path']}' alt='QR Code' style='width: 100px; height: 100px;'>
                                    <div class='small text-muted mt-1 fw-bold'>ID: {$booking['id']}</div>
                                </div>";
                    }

                    echo "
                            </div>
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
</body>
</html>