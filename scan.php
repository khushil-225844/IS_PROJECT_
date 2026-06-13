<?php
session_start();
require 'db_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Security: Only admins or lecturers should be able to scan/check-in students
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['admin', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if the booking exists, is for TODAY, and is still 'Confirmed'
    $check_sql = "SELECT b.id, u.email, r.room_name, b.start_time, b.status 
                  FROM bookings b 
                  JOIN users u ON b.user_id = u.id 
                  JOIN rooms r ON b.room_id = r.id 
                  WHERE b.id = ? AND b.booking_date = CURDATE()";
                  
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        
        if ($booking['status'] == 'Confirmed') {
            // Update status to Checked-In
            $update_sql = "UPDATE bookings SET status = 'Checked-In' WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $booking_id);
            $update_stmt->execute();
            
            $message = "<div class='alert alert-success text-center py-4'>
                            <h4 class='fw-bold mb-2'>✅ Check-In Successful</h4>
                            <p class='mb-0'><strong>Student:</strong> {$booking['email']}</p>
                            <p class='mb-0'><strong>Room:</strong> {$booking['room_name']}</p>
                            <p class='mb-0'><strong>Time:</strong> {$booking['start_time']}</p>
                        </div>";
        } elseif ($booking['status'] == 'Checked-In') {
            $message = "<div class='alert alert-warning text-center'>⚠️ This booking has already been checked in.</div>";
        } else {
            $message = "<div class='alert alert-danger text-center'>❌ Cannot check in. Current status: {$booking['status']}</div>";
        }
    } else {
        $message = "<div class='alert alert-danger text-center'>❌ Invalid Booking ID or the booking is not scheduled for today.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    
    <div class="container" style="max-width: 500px;">
        <div class="text-center mb-4">
            <h2 class="text-danger fw-bold">Strathmore Access Control</h2>
            <p class="text-muted">Enter the Booking ID from the student's QR pass.</p>
        </div>

        <?php echo $message; ?>

        <div class="card shadow-lg border-0 bg-light text-dark mt-4">
            <div class="card-body p-5">
                <form method="POST">
                    <div class="mb-4 text-center">
                        <label class="form-label fw-bold text-uppercase text-muted tracking-wide">Booking ID</label>
                        <input type="number" name="booking_id" class="form-control form-control-lg text-center fw-bold fs-3" placeholder="e.g. 42" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow-sm">VERIFY & CHECK-IN</button>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="dashboard-admin.php" class="text-decoration-none text-muted small">Return to Dashboard</a>
        </div>
    </div>

</body>
</html>