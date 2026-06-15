<?php
session_start();
require 'db_connect.php';

// Security Check: Ensure the user is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action 1: Add a Brand New Room
    if (isset($_POST['action']) && $_POST['action'] == 'add_room') {
        $room_name = trim($_POST['room_name']);
        $capacity = intval($_POST['capacity']);
        $room_type = $_POST['room_type'];
        $equipment = $_POST['equipment']; // Capture the TV or Projector selection!
        
        $sql = "INSERT INTO rooms (room_name, capacity, room_type, equipment, status) VALUES (?, ?, ?, ?, 'Available')";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("siss", $room_name, $capacity, $room_type, $equipment);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Successfully added {$room_name} with a {$equipment}!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error adding room: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Database error: " . $conn->error . "</div>";
        }
    }
    
    // Action 2: Update an Existing Room's Status
    if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
        $room_id = intval($_POST['room_id']);
        $new_status = $_POST['new_status'];
        
        $sql = "UPDATE rooms SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $room_id);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Room status updated successfully!</div>";
        }
    }

    // Action 3: Permanently Delete a Room
    if (isset($_POST['action']) && $_POST['action'] == 'delete_room') {
        $room_id = intval($_POST['room_id']);
        
        $sql = "DELETE FROM rooms WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Room permanently deleted from the system!</div>";
        }
    }
}

// Fetch all current rooms for the table
$rooms_sql = "SELECT * FROM rooms ORDER BY room_type ASC, room_name ASC";
$rooms_result = $conn->query($rooms_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Rooms - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard-admin.php">Strathmore Admin</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="adminNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="dashboard-admin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="manage_rooms.php">Manage Rooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="scan.php">QR Scanner</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-dark btn-sm fw-bold px-3 py-2" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2>Campus Infrastructure</h2>
                <p class="text-muted">Manage spaces, assign hardware, and control room availability.</p>
                <?php echo $message; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white fw-bold py-3">+ Add New Room</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_room">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Room Name/Number</label>
                                <input type="text" name="room_name" class="form-control" placeholder="e.g. STC 101" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Room Category</label>
                                <select name="room_type" class="form-select" required>
                                    <option value="Study Room">Study Room</option>
                                    <option value="Discussion Room">Discussion Room</option>
                                    <option value="Computer Room">Computer Room</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Installed Equipment</label>
                                <select name="equipment" class="form-select" required>
                                    <option value="None">None</option>
                                    <option value="Television">Television</option>
                                    <option value="Projector">Projector</option>
                                </select>
                                <div class="form-text small text-danger">This locks the specific hardware to this room.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Seating Capacity</label>
                                <input type="number" name="capacity" class="form-control" placeholder="e.g. 50" required>
                            </div>
                            <button type="submit" class="btn btn-danger w-100 fw-bold">Create Room</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-danger fw-bold">Master Room Directory</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Room Name</th>
                                        <th>Type</th>
                                        <th>Equipment</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($rooms_result->num_rows > 0) {
                                        while($room = $rooms_result->fetch_assoc()) {
                                            $badge = ($room['status'] == 'Available') ? 'bg-success' : 'bg-warning text-dark';
                                            
                                            // Make the hardware highly visible in the table
                                            $eq_display = "<span class='text-muted'>None</span>";
                                            if ($room['equipment'] == 'Television') $eq_display = "<strong>📺 TV</strong>";
                                            if ($room['equipment'] == 'Projector') $eq_display = "<strong>📽️ Projector</strong>";

                                            echo "<tr>
                                                    <td><strong>{$room['room_name']}</strong></td>
                                                    <td><span class='badge bg-secondary'>{$room['room_type']}</span></td>
                                                    <td>{$eq_display}</td>
                                                    <td>{$room['capacity']} seats</td>
                                                    <td><span class='badge {$badge}'>{$room['status']}</span></td>
                                                    <td class='text-end'>
                                                        <div class='d-flex justify-content-end gap-2'>
                                                            
                                                            <form method='POST' class='d-flex gap-1'>
                                                                <input type='hidden' name='action' value='update_status'>
                                                                <input type='hidden' name='room_id' value='{$room['id']}'>
                                                                <select name='new_status' class='form-select form-select-sm' style='width: 110px;'>
                                                                    <option value='Available' ".($room['status']=='Available' ? 'selected' : '').">Available</option>
                                                                    <option value='Maintenance' ".($room['status']=='Maintenance' ? 'selected' : '').">Maintenance</option>
                                                                </select>
                                                                <button type='submit' class='btn btn-sm btn-outline-dark'>Update</button>
                                                            </form>

                                                            <form method='POST' onsubmit=\"return confirm('WARNING: Are you absolutely sure you want to permanently delete {$room['room_name']}?');\">
                                                                <input type='hidden' name='action' value='delete_room'>
                                                                <input type='hidden' name='room_id' value='{$room['id']}'>
                                                                <button type='submit' class='btn btn-sm btn-danger' title='Delete Room'>🗑️</button>
                                                            </form>
                                                            
                                                        </div>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center py-4'>No rooms in database.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>