<?php
// Resume the session
session_start();

// Security Check: Ensure the user is logged in AND has the 'lecturer' role
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Strathmore Room Booking</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">Strathmore Faculty</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-bold" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-1">Faculty Dashboard</h2>
                <p class="text-muted">Manage lecture spaces, computer labs, and office hour bookings.</p>
            </div>
        </div>

        <div class="row g-4 mt-3">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-5">
                        <h4 class="card-title mb-3 text-dark">Reserve a Space</h4>
                        <p class="card-text text-secondary mb-4">Book discussion rooms for office hours or larger halls for makeup classes and tutorials.</p>
                        <a href="rooms.php" class="btn btn-dark btn-lg w-100">View Available Rooms</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-5">
                        <h4 class="card-title mb-3 text-dark">My Schedule</h4>
                        <p class="card-text text-secondary mb-4">View upcoming faculty reservations, access physical check-in QR codes, or cancel unneeded slots.</p>
                        <a href="my_bookings.php" class="btn btn-outline-dark btn-lg w-100">Manage Bookings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>