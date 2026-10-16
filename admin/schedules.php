<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();

$message = "";
$messageType = "";
$editSchedule = null;
$viewSchedule = null;

// Return day name from date
function getDayName($date)
{
    return date("l", strtotime($date));
}

// Format date for display
function formatScheduleDate($date)
{
    return date("d M Y", strtotime($date));
}

$allDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

// Handle POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // Add new schedule
    if ($action === "add") {
        $doctorId = (int) ($_POST["doctor_id"] ?? 0);
        $scheduleDays = $_POST["schedule_days"] ?? [];
        $startTime = $_POST["start_time"] ?? "";
        $endTime = $_POST["end_time"] ?? "";
        $scheduleType = $_POST["schedule_type"] ?? "Consultation";
        $roomNumber = trim($_POST["room_number"] ?? "");
        $maxPatients = (int) ($_POST["max_patients"] ?? 20);
        $status = $_POST["status"] ?? "Active";
        $notes = trim($_POST["notes"] ?? "");

        // Normalize days array
        if (!is_array($scheduleDays)) $scheduleDays = [];
        $scheduleDays = array_values(array_unique(array_filter(array_map("trim", $scheduleDays))));
        $scheduleDays = array_values(array_intersect($scheduleDays, $allDays));

        // Validate required fields
        if ($doctorId <= 0 || empty($scheduleDays) || $startTime === "" || $endTime === "") {
            $message = "Please select a doctor, at least one day, start time and end time.";
            $messageType = "error";
        } elseif ($endTime <= $startTime) {
            $message = "End time must be later than start time.";
            $messageType = "error";
        } elseif ($maxPatients <= 0) {
            $message = "Maximum patients must be greater than 0.";
            $messageType = "error";
        } else {
            // Verify doctor is active
            $doctorCheck = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
            $doctorCheck->bind_param("i", $doctorId);
            $doctorCheck->execute();
            if ($doctorCheck->get_result()->num_rows === 0) {
                $message = "Selected doctor is not available.";
                $messageType = "error";
            } else {
                // Check for duplicate day+time conflict
                $conflictFound = false;
                foreach ($scheduleDays as $day) {
                    $duplicate = $conn->prepare("SELECT s.id FROM doctor_schedules s INNER JOIN doctor_schedule_days sd ON s.id = sd.schedule_id WHERE s.doctor_id = ? AND sd.schedule_day = ? AND s.start_time = ? AND s.end_time = ? LIMIT 1");
                    $duplicate->bind_param("isss", $doctorId, $day, $startTime, $endTime);
                    $duplicate->execute();
                    if ($duplicate->get_result()->num_rows > 0) {
                        $conflictFound = true;
                        break;
                    }
                }
                if ($conflictFound) {
                    $message = "One or more selected days already have the same doctor schedule.";
                    $messageType = "error";
                } else {
                    // Save schedule and days in transaction
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, start_time, end_time, schedule_type, room_number, max_patients, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("issssiss", $doctorId, $startTime, $endTime, $scheduleType, $roomNumber, $maxPatients, $status, $notes);
                        if (!$stmt->execute()) throw new Exception("Failed to insert schedule.");
                        $scheduleId = $conn->insert_id;
                        $dayStmt = $conn->prepare("INSERT INTO doctor_schedule_days (schedule_id, schedule_day) VALUES (?, ?)");
                        foreach ($scheduleDays as $day) {
                            $dayStmt->bind_param("is", $scheduleId, $day);
                            if (!$dayStmt->execute()) throw new Exception("Failed to save schedule days.");
                        }
                        $conn->commit();
                        $message = "Doctor schedule and selected days added successfully.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Failed to add doctor schedule.";
                        $messageType = "error";
                    }
                }
            }
        }
    }

    // Update existing schedule
    if ($action === "update") {
        $scheduleId = (int) ($_POST["schedule_id"] ?? 0);
        $doctorId = (int) ($_POST["doctor_id"] ?? 0);
        $scheduleDays = $_POST["schedule_days"] ?? [];
        $startTime = $_POST["start_time"] ?? "";
        $endTime = $_POST["end_time"] ?? "";
        $scheduleType = $_POST["schedule_type"] ?? "Consultation";
        $roomNumber = trim($_POST["room_number"] ?? "");
        $maxPatients = (int) ($_POST["max_patients"] ?? 20);
        $status = $_POST["status"] ?? "Active";
        $notes = trim($_POST["notes"] ?? "");

        // Normalize days array
        if (!is_array($scheduleDays)) $scheduleDays = [];
        $scheduleDays = array_values(array_unique(array_filter(array_map("trim", $scheduleDays))));
        $scheduleDays = array_values(array_intersect($scheduleDays, $allDays));

        // Validate required fields
        if ($scheduleId <= 0 || $doctorId <= 0 || empty($scheduleDays) || $startTime === "" || $endTime === "") {
            $message = "Please select a doctor, at least one day, start time and end time.";
            $messageType = "error";
        } elseif ($endTime <= $startTime) {
            $message = "End time must be later than start time.";
            $messageType = "error";
        } elseif ($maxPatients <= 0) {
            $message = "Maximum patients must be greater than 0.";
            $messageType = "error";
        } else {
            // Check for duplicate day+time conflict excluding current
            $conflictFound = false;
            foreach ($scheduleDays as $day) {
                $duplicate = $conn->prepare("SELECT s.id FROM doctor_schedules s INNER JOIN doctor_schedule_days sd ON s.id = sd.schedule_id WHERE s.doctor_id = ? AND sd.schedule_day = ? AND s.start_time = ? AND s.end_time = ? AND s.id != ? LIMIT 1");
                $duplicate->bind_param("isssi", $doctorId, $day, $startTime, $endTime, $scheduleId);
                $duplicate->execute();
                if ($duplicate->get_result()->num_rows > 0) {
                    $conflictFound = true;
                    break;
                }
            }
            if ($conflictFound) {
                $message = "One or more selected days already have the same doctor schedule.";
                $messageType = "error";
            } else {
                // Update schedule and replace days in transaction
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE doctor_schedules SET doctor_id = ?, start_time = ?, end_time = ?, schedule_type = ?, room_number = ?, max_patients = ?, status = ?, notes = ? WHERE id = ?");
                    $stmt->bind_param("issssissi", $doctorId, $startTime, $endTime, $scheduleType, $roomNumber, $maxPatients, $status, $notes, $scheduleId);
                    if (!$stmt->execute()) throw new Exception("Failed to update schedule.");
                    $deleteDays = $conn->prepare("DELETE FROM doctor_schedule_days WHERE schedule_id = ?");
                    $deleteDays->bind_param("i", $scheduleId);
                    if (!$deleteDays->execute()) throw new Exception("Failed to update schedule days.");
                    $dayStmt = $conn->prepare("INSERT INTO doctor_schedule_days (schedule_id, schedule_day) VALUES (?, ?)");
                    foreach ($scheduleDays as $day) {
                        $dayStmt->bind_param("is", $scheduleId, $day);
                        if (!$dayStmt->execute()) throw new Exception("Failed to save schedule days.");
                    }
                    $conn->commit();
                    $message = "Doctor schedule updated successfully.";
                    $messageType = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Failed to update doctor schedule.";
                    $messageType = "error";
                }
            }
        }
    }

    // Delete schedule
    if ($action === "delete") {
        $scheduleId = (int) ($_POST["schedule_id"] ?? 0);
        if ($scheduleId > 0) {
            $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ?");
            $stmt->bind_param("i", $scheduleId);
            if ($stmt->execute()) {
                $message = "Doctor schedule deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to delete doctor schedule.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid schedule.";
            $messageType = "error";
        }
    }
}

// Load schedule for viewing
if (isset($_GET["view"])) {
    $scheduleId = (int) $_GET["view"];
    $stmt = $conn->prepare("SELECT s.*, d.doctor_number, d.full_name, d.specialization FROM doctor_schedules s INNER JOIN doctors d ON s.doctor_id = d.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $scheduleId);
    $stmt->execute();
    $viewSchedule = $stmt->get_result()->fetch_assoc();
    if ($viewSchedule) {
        $dayStmt = $conn->prepare("SELECT schedule_day FROM doctor_schedule_days WHERE schedule_id = ? ORDER BY FIELD(schedule_day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
        $dayStmt->bind_param("i", $scheduleId);
        $dayStmt->execute();
        $viewScheduleDays = $dayStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $viewScheduleDays = [];
    }
}

// Load schedule for editing
if (isset($_GET["edit"])) {
    $scheduleId = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT s.*, d.doctor_number, d.full_name, d.specialization FROM doctor_schedules s INNER JOIN doctors d ON s.doctor_id = d.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $scheduleId);
    $stmt->execute();
    $editSchedule = $stmt->get_result()->fetch_assoc();
    if ($editSchedule) {
        $dayStmt = $conn->prepare("SELECT schedule_day FROM doctor_schedule_days WHERE schedule_id = ?");
        $dayStmt->bind_param("i", $scheduleId);
        $dayStmt->execute();
        $editScheduleDaysResult = $dayStmt->get_result();
        $editScheduleDays = [];
        while ($dayRow = $editScheduleDaysResult->fetch_assoc()) {
            $editScheduleDays[] = $dayRow["schedule_day"];
        }
    } else {
        $editScheduleDays = [];
    }
}

// Search or list all schedules
$search = trim($_GET["search"] ?? "");
if ($search !== "") {
    $stmt = $conn->prepare("SELECT s.*, d.doctor_number, d.full_name, d.specialization, GROUP_CONCAT(sd.schedule_day ORDER BY FIELD(sd.schedule_day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') SEPARATOR ',') AS schedule_days FROM doctor_schedules s INNER JOIN doctors d ON s.doctor_id = d.id LEFT JOIN doctor_schedule_days sd ON s.id = sd.schedule_id WHERE d.doctor_number LIKE ? OR d.full_name LIKE ? OR d.specialization LIKE ? OR s.room_number LIKE ? OR s.schedule_type LIKE ? OR sd.schedule_day LIKE ? GROUP BY s.id ORDER BY s.id DESC");
    $searchValue = "%" . $search . "%";
    $stmt->bind_param("ssssss", $searchValue, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
    $stmt->execute();
    $schedules = $stmt->get_result();
} else {
    $schedules = $conn->query("SELECT s.*, d.doctor_number, d.full_name, d.specialization, GROUP_CONCAT(sd.schedule_day ORDER BY FIELD(sd.schedule_day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') SEPARATOR ',') AS schedule_days FROM doctor_schedules s INNER JOIN doctors d ON s.doctor_id = d.id LEFT JOIN doctor_schedule_days sd ON s.id = sd.schedule_id GROUP BY s.id ORDER BY s.id DESC");
}

// Load active doctors for dropdown
$doctors = $conn->query("SELECT id, doctor_number, full_name, specialization FROM doctors WHERE status = 'Active' ORDER BY full_name ASC");
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Doctor Schedule Management</h2>
            <p class="text-sm text-slate-500 mt-1">Create and manage doctor schedules with one or more consultation days.</p>
        </div>
        <button onclick="openScheduleModal()" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="calendar-plus" class="w-5 h-5"></i>
            Add Schedule
        </button>
    </div>
    <!-- Search -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="page" value="schedules">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by doctor, specialization, day, type or room..." class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit" class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-medium transition">Search</button>
            <?php if ($search !== ""): ?>
                <a href="index.php?page=schedules" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition text-center">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <!-- Schedule Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200">
            <h3 class="font-bold text-lg text-slate-900">Doctor Schedules</h3>
            <p class="text-sm text-slate-500">Total records: <?php echo $schedules ? $schedules->num_rows : 0; ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Doctor</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Days</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Time</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Type</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Room</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Patients</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($schedules && $schedules->num_rows > 0): ?>
                        <?php while ($schedule = $schedules->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <!-- Doctor -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                            <i data-lucide="stethoscope" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($schedule["full_name"]); ?></p>
                                            <p class="text-xs text-blue-600 font-medium"><?php echo htmlspecialchars($schedule["doctor_number"]); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Days -->
                                <td class="px-6 py-4">
                                    <?php $days = !empty($schedule["schedule_days"]) ? explode(",", $schedule["schedule_days"]) : []; ?>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach ($days as $day): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                                <i data-lucide="calendar" class="w-3 h-3"></i>
                                                <?php echo htmlspecialchars($day); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <!-- Time -->
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-slate-700">
                                        <?php echo date("h:i A", strtotime($schedule["start_time"])); ?> - <?php echo date("h:i A", strtotime($schedule["end_time"])); ?>
                                    </p>
                                </td>
                                <!-- Type -->
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                        <?php echo htmlspecialchars($schedule["schedule_type"]); ?>
                                    </span>
                                </td>
                                <!-- Room -->
                                <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($schedule["room_number"] ?: "-"); ?></td>
                                <!-- Patients -->
                                <td class="px-6 py-4 text-sm text-slate-700"><?php echo (int) $schedule["max_patients"]; ?></td>
                                <!-- Status -->
                                <td class="px-6 py-4">
                                    <?php if ($schedule["status"] === "Active"): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-green-50 text-green-700 text-xs font-semibold">Active</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-semibold">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Actions -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="index.php?page=schedules&view=<?php echo $schedule["id"]; ?>" class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="View">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <a href="index.php?page=schedules&edit=<?php echo $schedule["id"]; ?>" class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center" title="Edit">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this schedule and all its days?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule["id"]; ?>">
                                            <button type="submit" class="w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center" title="Delete">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                                        <i data-lucide="calendar-x" class="w-7 h-7 text-slate-400"></i>
                                    </div>
                                    <h3 class="font-semibold text-slate-700">No schedules found</h3>
                                    <p class="text-sm text-slate-400 mt-1">Add a doctor schedule to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- VIEW MODAL -->
<?php if ($viewSchedule): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Schedule Details</h3>
                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($viewSchedule["doctor_number"]); ?></p>
                </div>
                <a href="index.php?page=schedules" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Doctor</p>
                        <p class="mt-1 font-medium text-slate-800"><?php echo htmlspecialchars($viewSchedule["full_name"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Specialization</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewSchedule["specialization"]); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Schedule Days</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php foreach ($viewScheduleDays as $dayRow): ?>
                                <span class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 text-blue-700 text-sm font-semibold">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <?php echo htmlspecialchars($dayRow["schedule_day"]); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Time</p>
                        <p class="mt-1 text-slate-700"><?php echo date("h:i A", strtotime($viewSchedule["start_time"])); ?> - <?php echo date("h:i A", strtotime($viewSchedule["end_time"])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Schedule Type</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewSchedule["schedule_type"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Room Number</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewSchedule["room_number"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Maximum Patients</p>
                        <p class="mt-1 text-slate-700"><?php echo (int) $viewSchedule["max_patients"]; ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Status</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewSchedule["status"]); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Notes</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewSchedule["notes"] ?: "-"); ?></p>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=schedules&edit=<?php echo $viewSchedule["id"]; ?>" class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        Edit Schedule
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<!-- ADD / EDIT MODAL -->
<div id="scheduleModal" class="<?php echo $editSchedule ? 'flex' : 'hidden'; ?> fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div>
                <h3 class="text-xl font-bold text-slate-900"><?php echo $editSchedule ? "Edit Schedule" : "Add Doctor Schedule"; ?></h3>
                <p class="text-sm text-slate-500"><?php echo $editSchedule ? "Update doctor schedule and days" : "Create a doctor schedule with one or more days"; ?></p>
            </div>
            <button type="button" onclick="closeScheduleModal()" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-6" id="scheduleForm">
            <input type="hidden" name="action" value="<?php echo $editSchedule ? 'update' : 'add'; ?>">
            <?php if ($editSchedule): ?>
                <input type="hidden" name="schedule_id" value="<?php echo $editSchedule["id"]; ?>">
            <?php endif; ?>
            <div class="space-y-7">
                <!-- DOCTOR -->
                <div>
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="stethoscope" class="w-5 h-5 text-blue-600"></i>
                        Doctor
                    </h4>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Select Doctor <span class="text-red-500">*</span></label>
                    <select name="doctor_id" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <option value="">Select Doctor</option>
                        <?php if ($doctors): ?>
                            <?php while ($doctor = $doctors->fetch_assoc()): ?>
                                <option value="<?php echo $doctor["id"]; ?>" <?php echo ((int)($editSchedule["doctor_id"] ?? 0) === (int)$doctor["id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($doctor["full_name"]); ?> - <?php echo htmlspecialchars($doctor["specialization"]); ?> (<?php echo htmlspecialchars($doctor["doctor_number"]); ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <!-- DAYS -->
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="calendar-days" class="w-5 h-5 text-blue-600"></i>
                        Schedule Days
                    </h4>
                    <p class="text-sm text-slate-500 mb-4">Select one or more days for this doctor schedule.</p>
                    <div id="dayContainer" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php foreach ($allDays as $day): ?>
                            <label class="flex items-center gap-3 px-4 py-3 border border-slate-300 rounded-xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:text-blue-700">
                                <input type="checkbox" name="schedule_days[]" value="<?php echo $day; ?>" <?php echo ($editSchedule && in_array($day, $editScheduleDays)) ? "checked" : ""; ?> class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                                <span class="font-medium text-sm"><?php echo $day; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- TIME -->
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="clock" class="w-5 h-5 text-blue-600"></i>
                        Schedule Time
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Start Time <span class="text-red-500">*</span></label>
                            <input type="time" name="start_time" required value="<?php echo htmlspecialchars($editSchedule["start_time"] ?? "08:00"); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">End Time <span class="text-red-500">*</span></label>
                            <input type="time" name="end_time" required value="<?php echo htmlspecialchars($editSchedule["end_time"] ?? "12:00"); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <!-- DETAILS -->
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-600"></i>
                        Schedule Details
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Schedule Type</label>
                            <select name="schedule_type" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <?php foreach (["Consultation", "Clinic", "Emergency", "Follow-up"] as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo (($editSchedule["schedule_type"] ?? "Consultation") === $type) ? "selected" : ""; ?>><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Room Number</label>
                            <input type="text" name="room_number" value="<?php echo htmlspecialchars($editSchedule["room_number"] ?? ""); ?>" placeholder="e.g. Room 101" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Maximum Patients</label>
                            <input type="number" name="max_patients" min="1" max="500" value="<?php echo htmlspecialchars($editSchedule["max_patients"] ?? "20"); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <!-- ADDITIONAL -->
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>
                        Additional Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                            <select name="status" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="Active" <?php echo (($editSchedule["status"] ?? "Active") === "Active") ? "selected" : ""; ?>>Active</option>
                                <option value="Inactive" <?php echo (($editSchedule["status"] ?? "") === "Inactive") ? "selected" : ""; ?>>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Notes</label>
                            <input type="text" name="notes" value="<?php echo htmlspecialchars($editSchedule["notes"] ?? ""); ?>" placeholder="Additional schedule information" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <button type="button" onclick="closeScheduleModal()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="<?php echo $editSchedule ? 'save' : 'calendar-plus'; ?>" class="w-5 h-5"></i>
                    <?php echo $editSchedule ? "Update Schedule" : "Add Schedule"; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    // Open schedule modal
    function openScheduleModal() {
        document.getElementById("scheduleModal").classList.remove("hidden");
        document.getElementById("scheduleModal").classList.add("flex");
    }
    // Close modal and reload page
    function closeScheduleModal() {
        window.location.href = "index.php?page=schedules";
    }
    // Initialize Lucide icons
    lucide.createIcons();
</script>
<?php if ($message !== ""): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if ($messageType === "success"): ?>
                toastr.success(<?php echo json_encode($message); ?>, "Success");
            <?php else: ?>
                toastr.error(<?php echo json_encode($message); ?>, "Error");
            <?php endif; ?>
        });
    </script>
<?php endif; ?>