<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Role-Based Access Control (RBAC)
// Why? We restrict this script so that only logged-in students and lecturers can access it. If an unauthenticated user tries to run this file directly in their browser, the system kicks them out to the index page.
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['student', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

// DEFENSE NOTE: Parameter Validation
// Why? We use isset() to ensure a booking ID was actually passed in the URL (e.g., cancel_booking.php?id=5). We then use intval() to cast it as an integer, preventing SQL injection via the URL.
if (isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // DEFENSE NOTE: IDOR (Insecure Direct Object Reference) Prevention 
    // Why? This is a critical security feature! Notice the "AND user_id = ?" in the SQL query. If we only updated based on the booking_id, a malicious student could guess another student's booking ID and cancel it. By binding the session's user_id, we guarantee a user can ONLY cancel their own bookings.
    $sql = "UPDATE bookings SET status = 'Cancelled' WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);

    if ($stmt->execute()) {
        
        // DEFENSE NOTE: Logical Verification
        // Why? We check affected_rows to see if the database actually changed. If a user clicks "cancel" on a booking that is ALREADY cancelled, affected_rows will be 0, preventing us from logging duplicate cancellation events.
        if ($stmt->affected_rows > 0) {
            
            // DEFENSE NOTE: Enterprise Audit Log (Non-Functional Requirement - Security)
            // Why? To ensure full system accountability, the exact moment a student successfully cancels a booking, it is recorded in the audit_logs table. If a student later claims "the system deleted my booking," the Admin has immutable proof that the student clicked the cancel button manually.
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'BOOKING_CANCELLED', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "User manually cancelled Booking ID: {$booking_id}";
            $audit_stmt->bind_param("is", $user_id, $action_details);
            $audit_stmt->execute();
            // -------------------------------------------------------

            // Redirect with a success message
            echo "<script>alert('Your booking has been successfully cancelled.'); window.location.href='my_bookings.php';</script>";
        } else {
            // This happens if the ID doesn't exist, is already cancelled, or belongs to someone else
            echo "<script>alert('Error: Unable to cancel this booking.'); window.location.href='my_bookings.php';</script>";
        }
    } else {
        echo "<script>alert('Database connection error.'); window.location.href='my_bookings.php';</script>";
    }
} else {
    // If someone accesses the file without an ID, send them back safely
    header("Location: my_bookings.php");
}
exit();
?>