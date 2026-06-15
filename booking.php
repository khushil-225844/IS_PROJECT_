<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['student', 'lecturer'])) {
    header("Location: index.php");
    exit();
}

$user_role = $_SESSION['role']; // NEW: Grab the role to customize the UI
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

$room_sql = "SELECT room_name, capacity, equipment FROM rooms WHERE id = ?";
$stmt = $conn->prepare($room_sql);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    die("Room not found. Please select a room from the dashboard.");
}

$capacity = $room['capacity'];
$room_equipment = $room['equipment']; 
$occupied_seats = [];
$date_selected = false;
$room_locked_for_lecture = false;
$equipment_taken = false; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_time'])) {
    $date_selected = true;
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $check_sql = "SELECT seat_number, equipment FROM bookings 
                  WHERE room_id = ? AND booking_date = ? AND status = 'Confirmed' 
                  AND (start_time < ? AND end_time > ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("isss", $room_id, $booking_date, $end_time, $start_time);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['seat_number'] == 0) {
            $room_locked_for_lecture = true;
        }
        $occupied_seats[] = $row['seat_number'];
        
        if ($row['equipment'] !== 'None') {
            $equipment_taken = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Seat - <?php echo $room['room_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .cinema-screen { background: #adb5bd; color: white; text-align: center; padding: 10px; border-radius: 50% 50% 0 0 / 20px 20px 0 0; margin-bottom: 30px; font-weight: bold; letter-spacing: 5px; }
        .seat-grid { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; max-width: 600px; margin: 0 auto; }
        .seat { width: 45px; height: 45px; border-radius: 8px 8px 4px 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; transition: all 0.2s; }
        .seat.available { background-color: #d4edda; border: 2px solid #28a745; color: #155724; }
        .seat.available:hover { transform: scale(1.1); }
        .seat.occupied { background-color: #f8d7da; border: 2px solid #dc3545; color: #721c24; cursor: not-allowed; opacity: 0.6; }
        .seat.selected { background-color: #0d6efd; border: 2px solid #0a58ca; color: white; transform: scale(1.1); }
        .eq-btn { height: 60px; font-size: 1.1rem; border-radius: 12px; width: 100%; max-width: 400px; margin: 0 auto; }
    </style>
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
        <h2 class="text-center mb-1">Book <?php echo $room['room_name']; ?></h2>
        <p class="text-muted text-center mb-5">Total Capacity: <?php echo $capacity; ?> Seats</p>
        <div class="card shadow-sm border-0 mb-4 mx-auto" style="max-width: 600px;">
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="check_time" value="1">
                    <div class="row g-3">
                        <div class="col-md-4"><input type="date" name="booking_date" class="form-control" value="<?php echo isset($booking_date) ? $booking_date : date('Y-m-d'); ?>" required></div>
                        <div class="col-md-4"><input type="time" name="start_time" class="form-control" value="<?php echo isset($start_time) ? $start_time : '08:00'; ?>" required></div>
                        <div class="col-md-4"><input type="time" name="end_time" class="form-control" value="<?php echo isset($end_time) ? $end_time : '10:00'; ?>" required></div>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 mt-3 fw-bold">Check Availability</button>
                </form>
            </div>
        </div>

        <?php if ($date_selected): ?>
        <div class="card shadow-sm border-0 mx-auto" style="max-width: 800px;">
            <div class="card-body p-5">
                
                <?php if ($room_locked_for_lecture): ?>
                    <div class="alert alert-danger text-center p-4"><h4>🔒 Room Unavailable</h4><p>This entire room has been reserved for a lecture.</p></div>
                <?php else: ?>
                    
                    <?php if ($user_role === 'lecturer'): ?>
                        <div class="alert alert-info text-center p-4 mb-5 border-info">
                            <h4 class="fw-bold text-info-emphasis mb-3">🎓 Lecturer Access</h4>
                            <p class="text-dark">You have authorization to bypass individual seat selection and lock down this entire space.</p>
                            <button type="button" class="btn btn-primary btn-lg fw-bold px-5 py-3 shadow-sm" id="bookEntireRoomBtn" onclick="selectEntireRoom()">
                                Reserve Entire Room
                            </button>
                        </div>
                    <?php else: ?>
                        <h5 class="card-title fw-bold mb-4 text-center">Select Your Seat(s)</h5>
                        <div class="cinema-screen">FRONT OF ROOM / BOARD</div>
                        
                        <div class="seat-grid mb-5">
                            <?php
                            for ($i = 1; $i <= $capacity; $i++) {
                                if (in_array($i, $occupied_seats)) {
                                    echo "<div class='seat occupied'>{$i}</div>";
                                } else {
                                    echo "<div class='seat available' onclick='selectSeat({$i}, this)'>{$i}</div>";
                                }
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <hr class="mb-4">

                    <?php if ($room_equipment !== 'None'): ?>
                        <div class="text-center mb-5">
                            <h5 class="fw-bold mb-3 text-dark">Shared Room Resources</h5>
                            <?php if ($equipment_taken): ?>
                                <div class="seat occupied eq-btn mx-auto">
                                    <?php echo ($room_equipment == 'Television') ? '📺' : '📽️'; ?> <?php echo $room_equipment; ?> (Already Reserved)
                                </div>
                            <?php else: ?>
                                <div class="seat available eq-btn mx-auto" id="eqButton" onclick="toggleEquipment(this, '<?php echo $room_equipment; ?>')">
                                    Click to Add <?php echo ($room_equipment == 'Television') ? '📺 Television' : '📽️ Projector'; ?>
                                </div>
                            <?php endif; ?>
                            <div class="form-text mt-2 small">Claim this hardware for your session.</div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="submit_booking.php" id="finalBookingForm">
                        <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                        <input type="hidden" name="booking_date" value="<?php echo $booking_date; ?>">
                        <input type="hidden" name="start_time" value="<?php echo $start_time; ?>">
                        <input type="hidden" name="end_time" value="<?php echo $end_time; ?>">
                        <input type="hidden" name="seat_number" id="selected_seat_input" required>
                        <input type="hidden" name="equipment" id="selected_equipment_input" value="None">
                        
                        <button type="submit" class="btn btn-success btn-lg w-100 fw-bold" id="confirmBtn" disabled>
                            Choose a Seat to Confirm Booking
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let selectedSeats = [];
        const MAX_SEATS = 10;
        let equipmentSelected = 'None';

        // Student Multi-Seat Logic
        function selectSeat(seatNumber, element) {
            const index = selectedSeats.indexOf(seatNumber);
            if (index > -1) {
                selectedSeats.splice(index, 1);
                element.classList.remove('selected');
            } else {
                if (selectedSeats.length >= MAX_SEATS) {
                    alert('Maximum 10 seats allowed.');
                    return;
                }
                selectedSeats.push(seatNumber);
                element.classList.add('selected');
            }
            
            document.getElementById('selected_seat_input').value = selectedSeats.join(',');
            
            let btn = document.getElementById('confirmBtn');
            if (selectedSeats.length > 0) {
                btn.disabled = false;
                btn.innerHTML = "Confirm Booking for " + selectedSeats.length + " Seat(s)";
            } else {
                btn.disabled = true;
                btn.innerHTML = "Choose a Seat to Confirm Booking";
            }
        }

        // NEW: Lecturer Full Room Logic
        function selectEntireRoom() {
            document.getElementById('selected_seat_input').value = "0";
            
            let btn = document.getElementById('confirmBtn');
            btn.disabled = false;
            btn.innerHTML = "Confirm Full Room Lock";
            
            let bigBtn = document.getElementById('bookEntireRoomBtn');
            bigBtn.classList.replace('btn-primary', 'btn-success');
            bigBtn.innerHTML = "✅ Entire Room Selected";
        }

        // Equipment Toggle Logic
        function toggleEquipment(element, eqName) {
            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                equipmentSelected = 'None';
                element.innerHTML = "Click to Add " + (eqName == 'Television' ? '📺 Television' : '📽️ Projector');
            } else {
                element.classList.add('selected');
                equipmentSelected = eqName;
                element.innerHTML = "✅ " + eqName + " Added to Booking";
            }
            document.getElementById('selected_equipment_input').value = equipmentSelected;
        }
    </script>
</body>
</html>