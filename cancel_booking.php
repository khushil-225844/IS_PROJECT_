<?php
session_start();
require 'db_connect.php';

// Security Check: Ensure the user is a logged-in student
// Security Check: Ensure the user is a logged-in student OR lecturer
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['student', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

// Check if a booking ID was actually passed in the URL
if (isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // Update the booking status ONLY if it belongs to the currently logged-in student
    $sql = "UPDATE bookings SET status = 'Cancelled' WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);

    if ($stmt->execute()) {
        // Check if any row was actually updated
        if ($stmt->affected_rows > 0) {
            echo "<script>alert('Your booking has been successfully cancelled.'); window.location.href='my_bookings.php';</script>";
        } else {
            // This happens if the ID doesn't exist or belongs to someone else
            echo "<script>alert('Error: Unable to cancel this booking.'); window.location.href='my_bookings.php';</script>";
        }
    } else {
        echo "<script>alert('Database connection error.'); window.location.href='my_bookings.php';</script>";
    }
} else {
    // If someone accesses the file without an ID, send them back
    header("Location: my_bookings.php");
}
exit();
?>