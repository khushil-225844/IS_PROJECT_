<?php
// DEFENSE NOTE: Session Initialization
// Why? We must resume the active session before checking the user's identity. Session variables are stored on the server side, making them much more secure than client-side cookies.
session_start();

// DEFENSE NOTE: Strict Role-Based Access Control (RBAC) & Dashboard Isolation
// Why? This enforces strict dashboard isolation. If an unauthenticated user, or even a Lecturer/Admin, attempts to access the student dashboard directly via the URL, this logic blocks them. It ensures that users only interact with the system layer designed specifically for their privilege level.
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    // If not a logged-in student, forcefully redirect back to the login page
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Strathmore Room Booking</title>
    
    <!-- DEFENSE NOTE: Front-End Framework & NFR (Usability) -->
    <!-- Why? Bootstrap 5 is used to ensure the application meets the Non-Functional Requirement for usability and responsiveness. It ensures the dashboard looks perfect on both laptops and student mobile phones. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php
        // DEFENSE NOTE: Modular Code Design (DRY Principle - Don't Repeat Yourself)
        // Why? Even though this file is protected for students only, we use a dynamic variable structure for the navigation bar. If we ever combine dashboards or include this navigation block as a separate header file, the system will dynamically render the correct colors (primary for students, dark for lecturers) without breaking.
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

    <!-- DEFENSE NOTE: Solving the Core Problem -->
    <!-- Why? During the problem statement presentation, we highlighted that students waste time manually searching the physical buildings for open spaces. This dashboard solves that by immediately offering clear, centralized CTAs (Calls to Action) to view live availability. -->
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-1">Welcome to the Student Dashboard</h2>
                <p class="text-muted">Manage academic spaces during free periods.</p>
            </div>
        </div>

        <div class="row g-4 mt-3">
            <div class="col-md-6">
                <!-- Data Capture / Create Entry Point -->
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-5">
                        <h4 class="card-title mb-3 text-primary">Find a Room</h4>
                        <p class="card-text text-secondary mb-4">Search for available discussion rooms, study halls, or computer labs without having to search the physical building.</p>
                        <a href="rooms.php" class="btn btn-primary btn-lg w-100">View Available Rooms</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Data Management / Update & Delete Entry Point -->
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-5">
                        <h4 class="card-title mb-3 text-primary">My Bookings</h4>
                        <p class="card-text text-secondary mb-4">View confirmed reservations, scan your QR code for physical check-in, or cancel unneeded slots.</p>
                        <a href="my_bookings.php" class="btn btn-outline-primary btn-lg w-100">Manage Bookings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>