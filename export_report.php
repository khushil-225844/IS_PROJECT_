<?php
session_start();
require 'db_connect.php';

// Security: Only admins can download the raw data
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Tell the browser to expect a CSV file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Strathmore_Facility_Report_' . date('Y-m-d') . '.csv');

// Open the output stream
$output = fopen('php://output', 'w');

// Write the upgraded column headers for the spreadsheet
fputcsv($output, array('Booking ID', 'Username', 'Department', 'Account Role', 'Room Name', 'Seat Number', 'Hardware Booked', 'Date', 'Start Time', 'End Time', 'Status'));

// Fetch every booking, joining all 4 relevant tables to get the readable names
$sql = "SELECT 
            b.id, 
            u.username, 
            d.department_name,
            ro.role_name, 
            r.room_name, 
            b.seat_number, 
            b.equipment,
            b.booking_date, 
            b.start_time, 
            b.end_time, 
            b.status 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN roles ro ON u.role_id = ro.id
        LEFT JOIN departments d ON u.department_id = d.id
        JOIN rooms r ON b.room_id = r.id 
        ORDER BY b.booking_date DESC, b.start_time ASC";
        
$result = $conn->query($sql);

// Loop through the data and write it row by row
while ($row = $result->fetch_assoc()) {
    // Translate Seat 0 into readable text for the report
    $seat_display = ($row['seat_number'] == 0) ? 'ENTIRE ROOM (LECTURE)' : $row['seat_number'];
    $dept_display = $row['department_name'] ? $row['department_name'] : 'Unassigned';
    
    fputcsv($output, array(
        $row['id'], 
        $row['username'], 
        $dept_display,
        strtoupper($row['role_name']), 
        $row['room_name'], 
        $seat_display, 
        $row['equipment'],
        $row['booking_date'], 
        $row['start_time'], 
        $row['end_time'], 
        $row['status']
    ));
}

fclose($output);
exit();
?>