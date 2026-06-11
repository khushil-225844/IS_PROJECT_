<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $room_id = $_POST['room_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Basic conflict check: Ensure the room isn't already booked at that time
    $check_sql = "SELECT id FROM bookings WHERE room_id = ? AND booking_date = ? 
                  AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?)) AND status = 'Confirmed'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("isssss", $room_id, $booking_date, $start_time, $start_time, $end_time, $end_time);
    $check_stmt->execute();
    $conflict_result = $check_stmt->get_result();

    if ($conflict_result->num_rows > 0) {
        echo "<script>alert('This room is already booked during that time slot. Please choose another time.'); window.history.back();</script>";
        exit();
    }

   // Insert the new booking
    $insert_sql = "INSERT INTO bookings (user_id, room_id, booking_date, start_time, end_time) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iisss", $user_id, $room_id, $booking_date, $start_time, $end_time);

    if ($stmt->execute()) {
        // Grab the ID of the booking that was just created
        $booking_id = $conn->insert_id; 
        
        // Include the QR Code library
        require_once(__DIR__ . '/libs/phpqrcode/qrlib.php');
        
        // Define the data embedded in the QR code (used later for check-in validation)
        $qr_data = "STR-BK-" . $booking_id . "-USR-" . $user_id;
        
        // Define the file path where the image will be saved
        $qr_file_path = "assets/qrcodes/booking_" . $booking_id . ".png";
        
        // Generate and save the QR code image
        // Parameters: (Data, Filepath, ErrorCorrectionLevel, PixelSize)
// Generate and save the QR code image
        QRcode::png($qr_data, $qr_file_path, QR_ECLEVEL_L, 5);
        
        // --- THIS IS THE CRITICAL PART ---
        // Update the specific booking record with the image path
        $update_sql = "UPDATE bookings SET qr_code_path = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $qr_file_path, $booking_id);
        $update_stmt->execute();
        // ---------------------------------

        // Redirect to the My Bookings page
        echo "<script>alert('Booking Confirmed! QR Code generated.'); window.location.href='my_bookings.php';</script>";
    } else {
        echo "<script>alert('Error processing booking.'); window.history.back();</script>";
    }
} else {
    header("Location: rooms.php");
    exit();
}
?>