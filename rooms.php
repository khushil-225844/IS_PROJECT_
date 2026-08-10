<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Filter Capture
// Why? We check the URL using the GET method to see if the user selected a specific room filter from the dropdown (e.g., rooms.php?type=Study+Room). If they didn't, we default the filter to 'All'.
$current_filter = isset($_GET['type']) ? $_GET['type'] : 'All';

// DEFENSE NOTE: Dynamic SQL Execution & Data Normalization
// Why? We use an IF/ELSE block to construct the SQL query dynamically based on the user's filter.
if ($current_filter == 'All') {
    // If 'All', fetch every room that isn't under maintenance.
    // Notice the LEFT JOIN with GROUP_CONCAT: This is how we grab the room's details AND combine all its hardware from the equipment table into a single, neat comma-separated string (e.g., "Projector, Television").
    $sql = "SELECT rooms.*, 
            IFNULL(GROUP_CONCAT(equipment_inventory.asset_name SEPARATOR ', '), 'None') as equipment_list 
            FROM rooms 
            LEFT JOIN equipment_inventory ON rooms.id = equipment_inventory.room_id 
            WHERE rooms.status = 'Available' 
            GROUP BY rooms.id 
            ORDER BY rooms.room_name ASC";
    $stmt = $conn->prepare($sql);
} else {
    // DEFENSE NOTE: SQL Injection Prevention with Filters
    // Why? If the user selects a specific filter (like "Computer Room"), we MUST use a prepared statement. A hacker could try to manipulate the URL (?type='; DROP TABLE rooms;). The question mark (?) and bind_param() ensure the database strictly treats the filter as text, protecting the system.
    $sql = "SELECT rooms.*, 
            IFNULL(GROUP_CONCAT(equipment_inventory.asset_name SEPARATOR ', '), 'None') as equipment_list 
            FROM rooms 
            LEFT JOIN equipment_inventory ON rooms.id = equipment_inventory.room_id 
            WHERE rooms.status = 'Available' AND rooms.room_type = ? 
            GROUP BY rooms.id 
            ORDER BY rooms.room_name ASC";
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
    <!-- DEFENSE NOTE: Bootstrap 5 Responsive Grid System -->
    <!-- Why? To ensure the room cards scale perfectly on mobile devices, tablets, and desktop computers. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

    <?php
        // DEFENSE NOTE: Dynamic Theming
        // Why? This dynamically changes the navbar from blue (student) to dark (lecturer) depending on the session data without having to duplicate code into two separate files.
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
                        <a class="nav-link text-white px-3 fw-bold" href="rooms.php">Reserve Space</a>
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
                <!-- DEFENSE NOTE: Interactive Filtering Interface -->
                <!-- Why? Instead of a standard submit button, we use JavaScript's `onchange="this.form.submit()"` on the select tag. The moment the user picks a new category, the form instantly submits via the GET method, refreshing the page with the filtered data. -->
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
            // DEFENSE NOTE: Dynamic Card Rendering (The READ process)
            // Why? We use a PHP while loop to iterate through every row returned from our database query. 
            if ($rooms_result->num_rows > 0) {
                while($room = $rooms_result->fetch_assoc()) {
                    
                    // DEFENSE NOTE: UI/UX Enhancement
                    // Assign an emoji icon based on the raw database string for better visual UI design
                    $icon = "🏫";
                    if ($room['room_type'] == 'Computer Room') $icon = "💻";
                    if ($room['room_type'] == 'Discussion Room') $icon = "🗣️";
                    if ($room['room_type'] == 'Study Room') $icon = "📚";

                    // DEFENSE NOTE: Data Binding to HTML
                    // Why? We bind the specific $room['id'] to the URL of the "Select & Book" button. When the user clicks it, it passes that exact ID to booking.php so the system knows exactly which room layout to render.
                    echo "
                    <div class='col-md-4 mb-4'>
                        <div class='card shadow-sm border-0 h-100 hover-zoom'>
                            <div class='card-body text-center p-4'>
                                <div class='fs-1 mb-3'>{$icon}</div>
                                <h4 class='fw-bold mb-1'>{$room['room_name']}</h4>
                                <span class='badge bg-primary mb-3'>{$room['room_type']}</span>
                                <p class='text-muted small mb-4'>
                                    Capacity: <strong>{$room['capacity']} Seats</strong><br>
                                    <span class='text-secondary'>Equipment: <strong>{$room['equipment_list']}</strong></span>
                                </p>
                                <!-- The button routes to booking.php and passes the ID via the GET method -->
                                <a href='booking.php?room_id={$room['id']}' class='btn btn-outline-primary w-100 fw-bold'>Select & Book</a>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                // DEFENSE NOTE: Graceful Fallback
                // Why? If a filter returns no results (e.g., they search for Computer Rooms but all of them are under maintenance), we don't just show a blank white page. We show a graceful error message and a link to clear the filter.
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