<?php
session_start();
require 'db_connect.php'; 

// Security Check: Ensure the user is logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// Fetch all rooms from the database
$sql = "SELECT * FROM rooms";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Rooms - Strathmore Booking</title>
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
        <h2 class="mb-4">Room Availability</h2>
        
        <div class="row g-4">
            <?php
            // Check if there are rooms in the database
            if ($result->num_rows > 0) {
                // Loop through each room and create a Bootstrap card
                while($room = $result->fetch_assoc()) {
                    
                    // Determine the badge color based on status
                    $badgeColor = 'bg-success';
                    if ($room['status'] == 'Booked') {
                        $badgeColor = 'bg-danger';
                    } elseif ($room['status'] == 'Maintenance') {
                        $badgeColor = 'bg-warning text-dark';
                    }

                    echo "
                    <div class='col-md-4'>
                        <div class='card shadow-sm h-100'>
                            <div class='card-body'>
                                <div class='d-flex justify-content-between align-items-center mb-2'>
                                    <h5 class='card-title mb-0'>{$room['room_name']}</h5>
                                    <span class='badge {$badgeColor}'>{$room['status']}</span>
                                </div>
                                <p class='card-text text-muted mb-1'><strong>Type:</strong> {$room['room_type']}</p>
                                <p class='card-text text-muted mb-3'><strong>Capacity:</strong> {$room['capacity']} people</p>";
                                
                    // Only show the "Book Now" button if the room is actually available
                    if ($room['status'] == 'Available') {
                        echo "<a href='booking.php?room_id={$room['id']}' class='btn btn-outline-primary w-100'>Book Now</a>";
                    } else {
                        echo "<button class='btn btn-secondary w-100' disabled>Unavailable</button>";
                    }

                    echo "
                            </div>
                        </div>
                    </div>";
                }
            } else {
                echo "<div class='col-12'><p>No rooms found in the system.</p></div>";
            }
            ?>
        </div>
    </div>

</body>
</html>