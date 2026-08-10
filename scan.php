<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Admin-Only Routing (RBAC)
// Why? Security verification at the physical door should only be performed by authorized facility administrators. This blocks students from accessing the scanner page and "checking themselves in" from their dorm rooms.
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Handle the scan submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['booking_id'])) {
    // We cast the input to an integer to prevent SQL injection payloads in the input field.
    $booking_id = intval($_POST['booking_id']);
    
    // DEFENSE NOTE: Time-Bound Validation Constraint
    // Why? The 'CURDATE()' function is a massive security feature here. It forces the database to only return a match if the booking is scheduled for TODAY. If a student tries to scan a valid QR code for a booking scheduled for next week, this query treats it as invalid, preventing early access.
    $check_sql = "SELECT * FROM bookings WHERE id = ? AND booking_date = CURDATE()";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        
        // DEFENSE NOTE: State Machine Logic & Double-Scan Prevention
        // Why? A booking has a strict lifecycle (e.g., Confirmed -> Checked-In). 
        if ($booking['status'] == 'Confirmed') {
            
            // Step 1: If it's Confirmed, safely Update status to Checked-In
            $update_sql = "UPDATE bookings SET status = 'Checked-In' WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $booking_id);
            $update_stmt->execute();
            
            // DEFENSE NOTE: Enterprise Audit Log (Traceability)
            // Why? We record exactly which admin checked the student in. If equipment goes missing from that room, the university knows exactly who authorized the door access and when.
            $admin_id = $_SESSION['user_id'];
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'CHECK_IN', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Admin successfully verified and checked in Booking ID: {$booking_id}";
            $audit_stmt->bind_param("is", $admin_id, $action_details);
            $audit_stmt->execute();
            
            $message = "<div class='alert alert-success fw-bold p-4'>✅ Check-In Successful!<br><small class='fw-normal'>Booking ID #{$booking_id} has been verified.</small></div>";
        } elseif ($booking['status'] == 'Checked-In') {
            // Step 2: Handle Double-Scans gracefully
            $message = "<div class='alert alert-warning fw-bold p-4'>⚠️ Already Checked In.<br><small class='fw-normal'>This pass was already scanned.</small></div>";
        } else {
            // Step 3: Handle Invalid States (e.g., Cancelled passes)
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
                                
                                <!-- DEFENSE NOTE: Hardware Integration (UX Design) -->
                                <!-- Why? Physical QR code scanners act like fast keyboards. They scan the code, type the number into the active input field, and simulate pressing the 'Enter' key. By adding the 'autofocus' attribute here, the admin doesn't even need to touch the mouse. They can just point the scanner at a student's phone and the form submits instantly. -->
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