<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Check if a room was actually selected
if (!isset($_GET['room_id'])) {
    echo "<script>alert('Please select a room first.'); window.location.href='rooms.php';</script>";
    exit();
}

$room_id = intval($_GET['room_id']);

// Fetch the specific room details
$sql = "SELECT * FROM rooms WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Room not found.'); window.location.href='rooms.php';</script>";
    exit();
}

$room = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room - Strathmore Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm p-4">
                    <h3 class="mb-3 text-primary">Book <?php echo htmlspecialchars($room['room_name']); ?></h3>
                    <p class="text-muted">Type: <?php echo htmlspecialchars($room['room_type']); ?> | Capacity: <?php echo htmlspecialchars($room['capacity']); ?></p>
                    
                    <form action="submit_booking.php" method="POST">
                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="booking_date" class="form-label fw-bold">Date</label>
                            <input type="date" class="form-control" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label fw-bold">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label fw-bold">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="rooms.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Confirm Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>