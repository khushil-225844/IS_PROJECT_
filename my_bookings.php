<?php
session_start();
require 'db_connect.php';

// Security Check: Ensure the user is a logged-in student
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the student's bookings and join with the rooms table to get the room name
$sql = "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.qr_code_path, r.room_name 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.user_id = ? 
        ORDER BY b.booking_date DESC, b.start_time DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Strathmore Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard-student.php">Strathmore Booking</a>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard-student.php">Back to Dashboard</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Bookings</h2>
            <a href="rooms.php" class="btn btn-primary">Book Another Room</a>
        </div>
        
        <div class="row g-4">
            <?php
            // Check if the student has any bookings
            if ($result->num_rows > 0) {
                while($booking = $result->fetch_assoc()) {
                    
                    // Determine badge color based on booking status
                    $badgeColor = 'bg-primary';
                    if ($booking['status'] == 'Cancelled' || $booking['status'] == 'No-Show') {
                        $badgeColor = 'bg-danger';
                    } elseif ($booking['status'] == 'Completed') {
                        $badgeColor = 'bg-success';
                    }

                    // Format times for better readability
                    $formatted_date = date("F j, Y", strtotime($booking['booking_date']));
                    $formatted_start = date("g:i A", strtotime($booking['start_time']));
                    $formatted_end = date("g:i A", strtotime($booking['end_time']));

                    echo "
                    <div class='col-md-6'>
                        <div class='card shadow-sm border-0 mb-3'>
                            <div class='card-body'>
                                <div class='d-flex justify-content-between align-items-center mb-3'>
                                    <h5 class='card-title mb-0 text-primary'>{$booking['room_name']}</h5>
                                    <span class='badge {$badgeColor}'>{$booking['status']}</span>
                                </div>
                                <hr>
                                <div class='mb-3'>
                                    <p class='mb-1'><strong>Date:</strong> {$formatted_date}</p>
                                    <p class='mb-1'><strong>Time:</strong> {$formatted_start} - {$formatted_end}</p>
                                    <p class='mb-0 text-muted small'>Booking ID: #STR-{$booking['id']}</p>
                                </div>";

                    // Display actionable buttons and the QR Code if the booking is still active
                    // Display actionable buttons and the QR Code if the booking is still active
                    if ($booking['status'] == 'Confirmed') {
                        echo "<div class='text-center my-4 p-3 bg-light rounded'>";
                        
                        // Check if the QR code path exists and display it using PHP string syntax
                        if (!empty($booking['qr_code_path'])) {
                            echo "<img src='{$booking['qr_code_path']}' alt='Check-in QR Code' class='img-fluid shadow-sm border' style='max-width: 150px;'>";
                            echo "<p class='text-muted small mt-2 mb-0 fw-bold'>Scan at door to check in</p>";
                        } else {
                            echo "<span class='text-danger small fw-bold'>QR Code Generation Failed. Please contact admin.</span>";
                        }
                        
                        echo "</div>
                                <div>
                                    <button class='btn btn-outline-danger w-100' onclick=\"alert('Cancellation feature in development.')\">Cancel Booking</button>
                                </div>";
                    }

                    echo "
                            </div>
                        </div>
                    </div>";
                }
            } else {
                echo "
                <div class='col-12'>
                    <div class='alert alert-info text-center' role='alert'>
                        You have no upcoming or past bookings.
                    </div>
                </div>";
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>