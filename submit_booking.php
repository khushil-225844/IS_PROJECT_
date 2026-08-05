<?php
session_start();
require 'db_connect.php';
date_default_timezone_set('Africa/Nairobi');

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

    // Do not rely on the browser's date restriction: requests can be forged.
    $requested_start = DateTime::createFromFormat('Y-m-d H:i', "$booking_date $start_time");
    $requested_end = DateTime::createFromFormat('Y-m-d H:i', "$booking_date $end_time");

    if (!$requested_start || !$requested_end || $requested_end <= $requested_start) {
        echo "<script>alert('Please choose a valid booking time range.'); window.history.back();</script>";
        exit();
    }

    if ($start_time < '08:00' || $end_time > '20:00') {
        echo "<script>alert('Bookings are available only between 08:00 and 20:00.'); window.history.back();</script>";
        exit();
    }

    if ($requested_start < new DateTime('now')) {
        echo "<script>alert('Bookings cannot be made for a time that has already passed.'); window.history.back();</script>";
        exit();
    }

    // ==========================================
    // PRE-CHECK: EQUIPMENT CONFLICT
    // ==========================================
    if ($equipment !== 'None' && !($_SESSION['role'] === 'lecturer' && $seat_input === '0')) {
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

        $conn->begin_transaction();

        try {
            // A lecturer may take priority over students, but never over another lecturer.
            $lecturer_conflict_sql = "SELECT b.id
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN roles ro ON u.role_id = ro.id
                WHERE b.room_id = ? AND b.booking_date = ? AND b.status = 'Confirmed'
                AND ro.role_name = 'lecturer' AND (b.start_time < ? AND b.end_time > ?)
                FOR UPDATE";
            $lecturer_conflict_stmt = $conn->prepare($lecturer_conflict_sql);
            $lecturer_conflict_stmt->bind_param("isss", $room_id, $booking_date, $end_time, $start_time);
            $lecturer_conflict_stmt->execute();

            if ($lecturer_conflict_stmt->get_result()->num_rows > 0) {
                $conn->rollback();
                echo "<script>alert('This room is already reserved by another lecturer for that time.'); window.history.back();</script>";
                exit();
            }

            $affected_students_sql = "SELECT DISTINCT b.user_id, r.room_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN roles ro ON u.role_id = ro.id
                JOIN rooms r ON b.room_id = r.id
                WHERE b.room_id = ? AND b.booking_date = ? AND b.status = 'Confirmed'
                AND ro.role_name = 'student' AND (b.start_time < ? AND b.end_time > ?)
                FOR UPDATE";
            $affected_students_stmt = $conn->prepare($affected_students_sql);
            $affected_students_stmt->bind_param("isss", $room_id, $booking_date, $end_time, $start_time);
            $affected_students_stmt->execute();
            $affected_students = $affected_students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $cancel_students_sql = "UPDATE bookings b
                JOIN users u ON b.user_id = u.id
                JOIN roles ro ON u.role_id = ro.id
                SET b.status = 'Cancelled (Lecturer Priority)'
                WHERE b.room_id = ? AND b.booking_date = ? AND b.status = 'Confirmed'
                AND ro.role_name = 'student' AND (b.start_time < ? AND b.end_time > ?)";
            $cancel_students_stmt = $conn->prepare($cancel_students_sql);
            $cancel_students_stmt->bind_param("isss", $room_id, $booking_date, $end_time, $start_time);
            $cancel_students_stmt->execute();

            $notification_sql = "INSERT INTO notifications (user_id, message) VALUES (?, ?)";
            $notification_stmt = $conn->prepare($notification_sql);
            foreach ($affected_students as $student) {
                $notification_message = "Your {$student['room_name']} booking on {$booking_date} from {$start_time} to {$end_time} was cancelled because the room was reserved for a lecturer. Please book another room.";
                $notification_stmt->bind_param("is", $student['user_id'], $notification_message);
                $notification_stmt->execute();
            }

            $insert_sql = "INSERT INTO bookings (user_id, room_id, seat_number, booking_date, start_time, end_time, status, equipment) VALUES (?, ?, 0, ?, ?, ?, 'Confirmed', ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissss", $user_id, $room_id, $booking_date, $start_time, $end_time, $equipment);
            $insert_stmt->execute();
            $booking_id = $conn->insert_id;

            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'ROOM_LOCKED', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Lecturer locked entire room ID: {$room_id} on {$booking_date}; displaced " . count($affected_students) . " student(s)";
            $audit_stmt->bind_param("is", $user_id, $action_details);
            $audit_stmt->execute();

            $conn->commit();
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Lecturer-priority booking failed: ' . $exception->getMessage());
            echo "<script>alert('The booking could not be completed. Please try again.'); window.history.back();</script>";
            exit();
        }
        
        $qr_dir = "qrcodes/";
        if (!is_dir($qr_dir)) { mkdir($qr_dir, 0777, true); }
        $qr_file_path = $qr_dir . "booking_" . $booking_id . ".png";
        
        $qr_data = "Booking ID: " . $booking_id . "\nRoom: " . $room_id . "\nSeat: ENTIRE ROOM\nEquipment: " . $equipment . "\nDate: " . $booking_date;
        QRcode::png($qr_data, $qr_file_path, QR_ECLEVEL_L, 5);
        
        $conn->query("UPDATE bookings SET qr_code_path = '{$qr_file_path}' WHERE id = {$booking_id}");

        $displaced_count = count($affected_students);
        $notice = $displaced_count > 0 ? " {$displaced_count} student booking(s) were cancelled and notified." : '';
        echo "<script>alert('Success! Room locked for lecture. Equipment Secured: {$equipment}.{$notice}'); window.location.href='my_bookings.php';</script>";
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

        // --- NEW: ENTERPRISE AUDIT LOG (STUDENT) ---
        $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'BOOKING_CREATED', ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $action_details = "Student booked {$seat_count} seat(s) in room ID: {$room_id} on {$booking_date}";
        $audit_stmt->bind_param("is", $user_id, $action_details);
        $audit_stmt->execute();
        // -------------------------------------------

        echo "<script>alert('Success! {$seat_count} seats reserved. Equipment Secured: {$equipment}'); window.location.href='my_bookings.php';</script>";
    }
}
?>
