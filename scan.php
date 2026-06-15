<?php
session_start();
require 'db_connect.php';

// Security Check: Ensure the user is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Handle the scan submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if the booking exists, is confirmed, and is for TODAY
    $check_sql = "SELECT * FROM bookings WHERE id = ? AND booking_date = CURDATE()";
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
            
            $message = "<div class='alert alert-success fw-bold p-4'>✅ Check-In Successful!<br><small class='fw-normal'>Booking ID #{$booking_id} has been verified.</small></div>";
        } elseif ($booking['status'] == 'Checked-In') {
            $message = "<div class='alert alert-warning fw-bold p-4'>⚠️ Already Checked In.<br><small class='fw-normal'>This pass was already scanned.</small></div>";
        } else {
            $message = "<div class='alert alert-danger fw-bold p-4'>❌ Cannot check in.<br><small class='fw-normal'>Current status: {$booking['status']}</small></div>";
        }
    } else {
        $message = "<div class='alert alert-danger fw-bold p-4'>❌ Invalid Booking ID.<br><small class='fw-normal'>Pass not found or not scheduled for today.</small></div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Scanner - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark">

    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm mb-5">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard-admin.php">Strathmore Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="adminNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a class="nav-link text-white px-3" href="dashboard-admin.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white px-3" href="manage_rooms.php">Manage Rooms</a></li>
                    <li class="nav-item"><a class="nav-link text-white px-3 fw-bold" href="scan.php">QR Scanner</a></li>
                    <li class="nav-item ms-lg-3"><a class="btn btn-dark btn-sm fw-bold px-3 py-2" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                
                <h2 class="text-danger fw-bold mb-4">Strathmore Access Control</h2>
                
                <div class="card shadow border-0" style="border-radius: 15px;">
                    <div class="card-body p-5">
                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted text-uppercase tracking-wide">Enter Booking ID</label>
                                <input type="number" name="booking_id" class="form-control form-control-lg text-center fw-bold fs-3" placeholder="e.g. 42" required autofocus>
                            </div>
                            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow-sm">VERIFY & CHECK-IN</button>
                        </form>
                    </div>
                </div>

                <div class="mt-4">
                    <?php echo $message; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>