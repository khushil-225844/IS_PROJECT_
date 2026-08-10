<?php
session_start();
require 'db_connect.php';

// DEFENSE NOTE: Admin-Only Routing
// Why? Facility management is a high-privilege action. This ensures no students or lecturers can accidentally or maliciously add, update, or delete physical campus resources.
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$admin_id = $_SESSION['user_id']; // Captured specifically for the Audit Log

// DEFENSE NOTE: Multi-Action Controller
// Why? Instead of creating three separate PHP files (add_room.php, update_room.php, delete_room.php), we use a hidden 'action' input in our HTML forms. This single PHP block acts as a controller, detecting which form was submitted and executing the corresponding CRUD operation.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- CREATE (C) PROCESS ---
    if (isset($_POST['action']) && $_POST['action'] == 'add_room') {
        $room_name = trim($_POST['room_name']);
        $capacity = intval($_POST['capacity']);
        $room_type = $_POST['room_type'];
        $equipment = $_POST['equipment']; 
        
        // Step 1: Insert into the core 'rooms' table
        $sql = "INSERT INTO rooms (room_name, capacity, room_type, status) VALUES (?, ?, ?, 'Available')";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("sis", $room_name, $capacity, $room_type);
            if ($stmt->execute()) {
                
                // DEFENSE NOTE: Multi-Table Transaction
                // Why? We use $conn->insert_id to immediately grab the auto-generated ID of the room we just created. We need this ID to link the hardware in our normalized database structure.
                $new_room_id = $conn->insert_id; 
                
                // Step 2: Insert into 'equipment_inventory' table 
                if ($equipment !== 'None') {
                    $eq_sql = "INSERT INTO equipment_inventory (room_id, asset_name, status) VALUES (?, ?, 'Functional')";
                    $eq_stmt = $conn->prepare($eq_sql);
                    $eq_stmt->bind_param("is", $new_room_id, $equipment);
                    $eq_stmt->execute();
                }

                // Step 3: Write to the immutable 'audit_logs' table for security tracking
                $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'ROOM_ADDED', ?)";
                $audit_stmt = $conn->prepare($audit_sql);
                $action_details = "Created {$room_type}: {$room_name} (Capacity: {$capacity})";
                $audit_stmt->bind_param("is", $admin_id, $action_details);
                $audit_stmt->execute();

                $message = "<div class='alert alert-success'>Successfully added {$room_name} with {$equipment}!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error adding room: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Database error: " . $conn->error . "</div>";
        }
    }
    
    // --- UPDATE (U) PROCESS ---
    if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
        $room_id = intval($_POST['room_id']);
        $new_status = $_POST['new_status'];
        
        $sql = "UPDATE rooms SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $room_id);
        
        if ($stmt->execute()) {
            // Write to the immutable 'audit_logs' table for security tracking
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'ROOM_STATUS_UPDATE', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Updated room ID {$room_id} status to '{$new_status}'";
            $audit_stmt->bind_param("is", $admin_id, $action_details);
            $audit_stmt->execute();

            $message = "<div class='alert alert-success'>Room status updated successfully!</div>";
        }
    }

    // --- DELETE (D) PROCESS ---
    if (isset($_POST['action']) && $_POST['action'] == 'delete_room') {
        $room_id = intval($_POST['room_id']);
        
        // DEFENSE NOTE: Foreign Key Cascade Deletion
        // Why? We only have to delete the room from the `rooms` table. Because we set up `ON DELETE CASCADE` in our MySQL schema, the database will automatically hunt down and delete any associated hardware in the `equipment_inventory` table or logs in the `bookings` table. This prevents "orphaned data" and maintains perfect referential integrity.
        $sql = "DELETE FROM rooms WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        
        if ($stmt->execute()) {
            // Write to the immutable 'audit_logs' table for security tracking
            $audit_sql = "INSERT INTO audit_logs (user_id, action_type, action_details) VALUES (?, 'ROOM_DELETED', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action_details = "Permanently deleted room ID {$room_id} and its associated hardware.";
            $audit_stmt->bind_param("is", $admin_id, $action_details);
            $audit_stmt->execute();

            $message = "<div class='alert alert-success'>Room and equipment permanently deleted from the system!</div>";
        }
    }
}

// --- READ (R) PROCESS ---
// DEFENSE NOTE: Relational Data Stitching (Enterprise JOIN)
// Why? To display a user-friendly table, we must query the `rooms` table and JOIN it with the `equipment_inventory` table. We use GROUP_CONCAT so that if a room has 3 pieces of hardware, it displays as one neat, comma-separated string rather than generating 3 duplicate rows in our HTML table.
$rooms_sql = "SELECT rooms.*, 
              IFNULL(GROUP_CONCAT(equipment_inventory.asset_name SEPARATOR ', '), 'None') as equipment_list 
              FROM rooms 
              LEFT JOIN equipment_inventory ON rooms.id = equipment_inventory.room_id 
              GROUP BY rooms.id 
              ORDER BY rooms.room_type ASC, rooms.room_name ASC";
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
    <!-- Admin Navigation -->
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
                        <a class="nav-link text-white px-3 fw-bold" href="manage_rooms.php">Manage Rooms</a>
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
            <!-- DEFENSE NOTE: The Create Form -->
            <!-- Why? This panel captures data to build the physical constraints of our campus (Capacity, Hardware, Type). This data acts as the boundary rules for the student booking engine later on. -->
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

            <!-- DEFENSE NOTE: The Master Directory Table -->
            <!-- Why? This table dynamically renders the results of our READ query. It also houses the UPDATE and DELETE forms for each specific room row. -->
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
                                            
                                            // DEFENSE NOTE: UX Formatting
                                            // Why? We use str_replace to turn raw database strings ('Television') into visually appealing icons ('📺 TV') for the end-user interface without altering the actual data stored on the server.
                                            $eq_display = "<span class='text-muted'>None</span>";
                                            if ($room['equipment_list'] !== 'None') {
                                                $display_str = str_replace('Television', '📺 TV', $room['equipment_list']);
                                                $display_str = str_replace('Projector', '📽️ Projector', $display_str);
                                                $eq_display = "<strong>{$display_str}</strong>";
                                            }

                                            echo "<tr>
                                                    <td><strong>{$room['room_name']}</strong></td>
                                                    <td><span class='badge bg-secondary'>{$room['room_type']}</span></td>
                                                    <td>{$eq_display}</td>
                                                    <td>{$room['capacity']} seats</td>
                                                    <td><span class='badge {$badge}'>{$room['status']}</span></td>
                                                    <td class='text-end'>
                                                        <div class='d-flex justify-content-end gap-2'>
                                                            
                                                            <!-- DEFENSE NOTE: Inline UPDATE Form -->
                                                            <form method='POST' class='d-flex gap-1'>
                                                                <input type='hidden' name='action' value='update_status'>
                                                                <input type='hidden' name='room_id' value='{$room['id']}'>
                                                                <select name='new_status' class='form-select form-select-sm' style='width: 110px;'>
                                                                    <option value='Available' ".($room['status']=='Available' ? 'selected' : '').">Available</option>
                                                                    <option value='Maintenance' ".($room['status']=='Maintenance' ? 'selected' : '').">Maintenance</option>
                                                                </select>
                                                                <button type='submit' class='btn btn-sm btn-outline-dark'>Update</button>
                                                            </form>

                                                            <!-- DEFENSE NOTE: Inline DELETE Form (With Boundary Validation) -->
                                                            <!-- Why? We use a client-side JavaScript 'onsubmit' confirm dialog to prevent accidental deletion of critical infrastructure. -->
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