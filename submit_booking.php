<?php
session_start();
require 'db_connect.php';

// Adjust this line if needed based on the library folder setup
require 'libs/phpqrcode/qrlib.php';

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['student', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $room_id = intval($_POST['room_id']);
    $seat_input = $_POST['seat_number']; 
    $booking_date = trim($_POST['booking_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $equipment = isset($_POST['equipment']) ? $_POST['equipment'] : 'None';

    // ==========================================
    // PRE-CHECK: EQUIPMENT CONFLICT
    // ==========================================
    if ($equipment !== 'None') {
        $eq_check_sql = "SELECT id FROM bookings WHERE room_id = ? AND equipment = ? AND booking_date = ? AND status = 'Confirmed' AND (start_time < ? AND end_time > ?)";
        $eq_check_stmt = $conn->prepare($eq_check_sql);
        $eq_check_stmt->bind_param("issss", $room_id, $equipment, $booking_date, $end_time, $start_time);
        $eq_check_stmt->execute();
        
        if ($eq_check_stmt->get_result()->num_rows > 0) {
            echo "<script>alert('Error: The {$equipment} in this room is already reserved by another student during this time slot! Please choose \"Just the seat\" or pick another time.'); window.history.back();</script>";
            exit();
        }
    }

    // --- SCENARIO A: LECTURER LOCKING THE WHOLE ROOM (Seat 0) ---
    if ($seat_input === "0") {
        if ($_SESSION['role'] !== 'lecturer') {
            die("Security Error: Only lecturers can book entire rooms.");
        }

        $check_sql = "SELECT id FROM bookings WHERE room_id = ? AND booking_date = ? AND status = 'Confirmed' AND (start_time < ? AND end_time > ?)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isss", $room_id, $booking_date, $end_time, $start_time);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            echo "<script>alert('Error: Cannot book the entire room because some seats are already reserved!'); window.history.back();</script>";
            exit();
        }

        $insert_sql = "INSERT INTO bookings (user_id, room_id, seat_number, booking_date, start_time, end_time, status, equipment) VALUES (?, ?, 0, ?, ?, ?, 'Confirmed', ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissss", $user_id, $room_id, $booking_date, $start_time, $end_time, $equipment);
        $insert_stmt->execute();
        $booking_id = $conn->insert_id;
        
        $qr_dir = "qrcodes/";
        if (!is_dir($qr_dir)) { mkdir($qr_dir, 0777, true); }
        $qr_file_path = $qr_dir . "booking_" . $booking_id . ".png";
        
        $qr_data = "Booking ID: " . $booking_id . "\nRoom: " . $room_id . "\nSeat: ENTIRE ROOM\nEquipment: " . $equipment . "\nDate: " . $booking_date;
        QRcode::png($qr_data, $qr_file_path, QR_ECLEVEL_L, 5);
        
        $conn->query("UPDATE bookings SET qr_code_path = '{$qr_file_path}' WHERE id = {$booking_id}");
        echo "<script>alert('Success! Room locked for lecture. Equipment Secured: {$equipment}'); window.location.href='my_bookings.php';</script>";
        exit();
    } 
    
    // --- SCENARIO B: STUDENT MULTI-SEAT GROUP BOOKING ---
    else {
        $seat_array = array_map('intval', explode(',', $seat_input));
        
        if (count($seat_array) > 10) {
            echo "<script>alert('Security Error: Maximum 10 seats allowed per booking.'); window.history.back();</script>";
            exit();
        }

        // SEAT CONFLICT CHECK
        foreach ($seat_array as $seat_num) {
            $check_sql = "SELECT id FROM bookings WHERE room_id = ? AND (seat_number = ? OR seat_number = 0) AND booking_date = ? AND status = 'Confirmed' AND (start_time < ? AND end_time > ?)";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iisss", $room_id, $seat_num, $booking_date, $end_time, $start_time);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                echo "<script>alert('Error: Seat {$seat_num} was just taken by someone else! Please reselect.'); window.history.back();</script>";
                exit();
            }
        }

        $qr_dir = "qrcodes/";
        if (!is_dir($qr_dir)) { mkdir($qr_dir, 0777, true); }
        $group_identifier = time() . rand(100, 999); 
        $qr_file_path = $qr_dir . "group_booking_" . $group_identifier . ".png";
        
        $qr_data = "Group Booking\nRoom: " . $room_id . "\nSeats: " . implode(', ', $seat_array) . "\nEquipment: " . $equipment . "\nDate: " . $booking_date . "\nStart: " . $start_time;
        QRcode::png($qr_data, $qr_file_path, QR_ECLEVEL_L, 5);

        $insert_sql = "INSERT INTO bookings (user_id, room_id, seat_number, booking_date, start_time, end_time, status, qr_code_path, equipment) VALUES (?, ?, ?, ?, ?, ?, 'Confirmed', ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        foreach ($seat_array as $seat_num) {
            $insert_stmt->bind_param("iiisssss", $user_id, $room_id, $seat_num, $booking_date, $start_time, $end_time, $qr_file_path, $equipment);
            $insert_stmt->execute();
        }

        $seat_count = count($seat_array);
        echo "<script>alert('Success! {$seat_count} seats reserved. Equipment Secured: {$equipment}'); window.location.href='my_bookings.php';</script>";
    }
}
?>