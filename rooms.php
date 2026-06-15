<?php
session_start();
require 'db_connect.php';

// Check if a specific filter was applied, otherwise show 'All'
$current_filter = isset($_GET['type']) ? $_GET['type'] : 'All';

// Prepare the SQL based on the filter
if ($current_filter == 'All') {
    $sql = "SELECT * FROM rooms WHERE status = 'Available' ORDER BY room_name ASC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT * FROM rooms WHERE status = 'Available' AND room_type = ? ORDER BY room_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $current_filter);
}

$stmt->execute();
$rooms_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Rooms - Strathmore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<?php
        // Determine user role for dynamic styling and links
        $nav_bg = ($_SESSION['role'] === 'lecturer') ? 'bg-dark' : 'bg-primary';
        $dash_link = ($_SESSION['role'] === 'lecturer') ? 'dashboard-lecturer.php' : 'dashboard-student.php';
        $brand_text = ($_SESSION['role'] === 'lecturer') ? 'Strathmore Faculty' : 'Strathmore Booking';
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $nav_bg; ?> shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo $dash_link; ?>"><?php echo $brand_text; ?></a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="mainNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="<?php echo $dash_link; ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="rooms.php">Reserve Space</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="my_bookings.php">My History</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-danger btn-sm fw-bold px-3 py-2" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold">Available Campus Spaces</h2>
                <p class="text-muted">Select a room type to find the perfect study or discussion area.</p>
            </div>
            
            <div class="col-md-4">
                <form method="GET" action="rooms.php" class="d-flex shadow-sm rounded">
<select name="type" class="form-select border-primary" onchange="this.form.submit()">
    <option value="All" <?php if($current_filter == 'All') echo 'selected'; ?>>Show All Spaces</option>
    <option value="Study Room" <?php if($current_filter == 'Study Room') echo 'selected'; ?>>Study Rooms</option>
    <option value="Discussion Room" <?php if($current_filter == 'Discussion Room') echo 'selected'; ?>>Discussion Rooms</option>
    <option value="Computer Room" <?php if($current_filter == 'Computer Room') echo 'selected'; ?>>Computer Rooms</option>
</select>
                </form>
            </div>
        </div>

        <div class="row">
            <?php
            if ($rooms_result->num_rows > 0) {
                while($room = $rooms_result->fetch_assoc()) {
                    
                    // Assign an icon based on room type for better visual UI
                    $icon = "🏫";
// Assign an icon based on room type for better visual UI
$icon = "🏫";
if ($room['room_type'] == 'Computer Room') $icon = "💻";
if ($room['room_type'] == 'Discussion Room') $icon = "🗣️";
if ($room['room_type'] == 'Study Room') $icon = "📚";

                    echo "
                    <div class='col-md-4 mb-4'>
                        <div class='card shadow-sm border-0 h-100 hover-zoom'>
                            <div class='card-body text-center p-4'>
                                <div class='fs-1 mb-3'>{$icon}</div>
                                <h4 class='fw-bold mb-1'>{$room['room_name']}</h4>
                                <span class='badge bg-primary mb-3'>{$room['room_type']}</span>
                                <p class='text-muted small mb-4'>Capacity: <strong>{$room['capacity']} Seats</strong></p>
                                <a href='booking.php?room_id={$room['id']}' class='btn btn-outline-primary w-100 fw-bold'>Select & Book</a>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                echo "<div class='col-12 text-center py-5'>
                        <h4 class='text-muted'>No spaces found for '{$current_filter}'.</h4>
                        <a href='rooms.php' class='btn btn-link mt-2'>View all rooms</a>
                      </div>";
            }
            ?>
        </div>
    </div>
</body>
</html>