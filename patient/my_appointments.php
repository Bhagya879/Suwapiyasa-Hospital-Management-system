<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();

$message = "";
$messageType = "";
$editAppointment = null;
$viewAppointment = null;

$userId = $_SESSION["user_id"] ?? 0;

// Get the patient record for the logged-in user
$patientStmt = $conn->prepare("SELECT id, patient_number, full_name FROM patients WHERE user_id = ? AND status = 'Active' LIMIT 1");
$patientStmt->bind_param("i", $userId);
$patientStmt->execute();
$patient = $patientStmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient record not found. Please complete your registration first.");
}

$patientId = (int) $patient["id"];

function getDayName($date)
{
    return date("l", strtotime($date));
}

function validateAppointmentSchedule($conn, $scheduleId, $doctorId, $appointmentDate, $appointmentTime, $excludeId = 0)
{
    $dayOfWeek = getDayName($appointmentDate);
    $sql = "SELECT s.id, s.max_patients FROM doctor_schedules s
            INNER JOIN doctor_schedule_days sd ON s.id = sd.schedule_id
            WHERE s.id = ? AND s.doctor_id = ? AND sd.schedule_day = ?
            AND s.status = 'Active' AND ? >= s.start_time AND ? < s.end_time LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $scheduleId, $doctorId, $dayOfWeek, $appointmentTime, $appointmentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return ["valid" => false, "message" => "Doctor is not available at the selected date and time."];
    $schedule = $result->fetch_assoc();

    $sql = "SELECT COUNT(*) AS booked_count FROM appointments
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
            AND status != 'Cancelled'" . ($excludeId ? " AND id != ?" : "");
    $stmt = $conn->prepare($sql);
    if ($excludeId) {
        $stmt->bind_param("issi", $doctorId, $appointmentDate, $appointmentTime, $excludeId);
    } else {
        $stmt->bind_param("iss", $doctorId, $appointmentDate, $appointmentTime);
    }
    $stmt->execute();
    $bookedCount = (int) ($stmt->get_result()->fetch_assoc()["booked_count"] ?? 0);
    $maxPatients = (int) $schedule["max_patients"];
    if ($bookedCount >= $maxPatients) return ["valid" => false, "message" => "This appointment time is already full."];
    return ["valid" => true];
}

/* =================== UPDATE (Pending only) =================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update") {
    $appointmentId   = (int) ($_POST["appointment_id"] ?? 0);
    $doctorId        = (int) ($_POST["doctor_id"] ?? 0);
    $scheduleId      = (int) ($_POST["schedule_id"] ?? 0);
    $appointmentDate = $_POST["appointment_date"] ?? "";
    $appointmentTime = $_POST["appointment_time"] ?? "";
    $reason          = trim($_POST["reason"] ?? "");

    // Verify appointment belongs to this patient and is Pending
    $checkStmt = $conn->prepare("SELECT id, status FROM appointments WHERE id = ? AND patient_id = ? LIMIT 1");
    $checkStmt->bind_param("ii", $appointmentId, $patientId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if (!$existing) {
        $message = "Appointment not found.";
        $messageType = "error";
    } elseif ($existing["status"] !== "Pending") {
        $message = "Only pending appointments can be edited.";
        $messageType = "error";
    } elseif ($doctorId === 0 || $scheduleId === 0 || $appointmentDate === "" || $appointmentTime === "" || $reason === "") {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif ($appointmentDate <= date("Y-m-d")) {
        $message = "Appointment date must be a future date (today is not allowed).";
        $messageType = "error";
    } elseif (date("Y-m", strtotime($appointmentDate)) !== date("Y-m")) {
        $message = "Appointment date must be within the current month.";
        $messageType = "error";
    } else {
        $docCheck = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
        $docCheck->bind_param("i", $doctorId);
        $docCheck->execute();
        if ($docCheck->get_result()->num_rows === 0) {
            $message = "Selected doctor is not available.";
            $messageType = "error";
        } else {
            $scheduleValidation = validateAppointmentSchedule($conn, $scheduleId, $doctorId, $appointmentDate, $appointmentTime, $appointmentId);
            if (!$scheduleValidation["valid"]) {
                $message = $scheduleValidation["message"];
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET doctor_id = ?, schedule_id = ?, appointment_date = ?, appointment_time = ?, reason = ? WHERE id = ? AND patient_id = ?");
                $stmt->bind_param("iisssii", $doctorId, $scheduleId, $appointmentDate, $appointmentTime, $reason, $appointmentId, $patientId);
                if ($stmt->execute()) {
                    $message = "Appointment updated successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to update appointment.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* =================== DELETE (Pending only) =================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $appointmentId = (int) ($_POST["appointment_id"] ?? 0);

    $checkStmt = $conn->prepare("SELECT id, status FROM appointments WHERE id = ? AND patient_id = ? LIMIT 1");
    $checkStmt->bind_param("ii", $appointmentId, $patientId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if (!$existing) {
        $message = "Appointment not found.";
        $messageType = "error";
    } elseif ($existing["status"] !== "Pending") {
        $message = "Only pending appointments can be deleted.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND patient_id = ?");
        $stmt->bind_param("ii", $appointmentId, $patientId);
        if ($stmt->execute()) {
            $message = "Appointment deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to delete appointment.";
            $messageType = "error";
        }
    }
}

/* ---------- View single ---------- */
if (isset($_GET["view"])) {
    $appointmentId = (int) $_GET["view"];
    $stmt = $conn->prepare("SELECT a.*, d.doctor_number, d.full_name AS doctor_name, d.specialization
                            FROM appointments a
                            INNER JOIN doctors d ON a.doctor_id = d.id
                            WHERE a.id = ? AND a.patient_id = ? LIMIT 1");
    $stmt->bind_param("ii", $appointmentId, $patientId);
    $stmt->execute();
    $viewAppointment = $stmt->get_result()->fetch_assoc();
}

/* ---------- Edit single ---------- */
if (isset($_GET["edit"])) {
    $appointmentId = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT a.*, d.doctor_number, d.full_name AS doctor_name, d.specialization
                            FROM appointments a
                            INNER JOIN doctors d ON a.doctor_id = d.id
                            WHERE a.id = ? AND a.patient_id = ? LIMIT 1");
    $stmt->bind_param("ii", $appointmentId, $patientId);
    $stmt->execute();
    $editAppointment = $stmt->get_result()->fetch_assoc();
    if ($editAppointment && $editAppointment["status"] !== "Pending") {
        $editAppointment = null;
        $message = "Only pending appointments can be edited.";
        $messageType = "error";
    }
}

/* ---------- List ---------- */
$stmt = $conn->prepare("SELECT a.*, d.doctor_number, d.full_name AS doctor_name, d.specialization
                        FROM appointments a
                        INNER JOIN doctors d ON a.doctor_id = d.id
                        WHERE a.patient_id = ?
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$appointments = $stmt->get_result();

/* ---------- Load data for edit form ---------- */
$doctors = $conn->query("SELECT id, doctor_number, full_name, specialization FROM doctors WHERE status = 'Active' ORDER BY full_name ASC");

$schedulesData = [];
$today = date("Y-m-d");
$currentMonth = date("Y-m");
$schedResult = $conn->query("SELECT s.id, s.doctor_id, s.start_time, s.end_time, s.max_patients, sd.schedule_day FROM doctor_schedules s INNER JOIN doctor_schedule_days sd ON s.id = sd.schedule_id WHERE s.status = 'Active' ORDER BY s.doctor_id, s.start_time");
while ($row = $schedResult->fetch_assoc()) {
    $schedulesData[] = [
        "id" => (int) $row["id"],
        "doctor_id" => (int) $row["doctor_id"],
        "day" => $row["schedule_day"],
        "start" => $row["start_time"],
        "end" => $row["end_time"],
        "max" => (int) $row["max_patients"]
    ];
}

$bookedData = [];
$bookedResult = $conn->query("SELECT doctor_id, appointment_date, appointment_time, COUNT(*) AS cnt FROM appointments WHERE status != 'Cancelled' AND appointment_date > '$today' AND appointment_date LIKE '$currentMonth%' GROUP BY doctor_id, appointment_date, appointment_time");
while ($b = $bookedResult->fetch_assoc()) {
    $bookedData[$b["doctor_id"]][$b["appointment_date"]][$b["appointment_time"]] = (int) $b["cnt"];
}
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">My Appointments</h2>
            <p class="text-sm text-slate-500 mt-1">View, edit or cancel your pending appointments.</p>
        </div>
        <a href="index.php?page=book_appointment"
            class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="calendar-plus" class="w-5 h-5"></i>
            Book Appointment
        </a>
    </div>

    <!-- Patient summary -->
    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center">
            <i data-lucide="user-round" class="w-6 h-6"></i>
        </div>
        <div>
            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($patient["full_name"]); ?></p>
            <p class="text-xs text-blue-600"><?php echo htmlspecialchars($patient["patient_number"]); ?></p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200">
            <h3 class="font-bold text-lg text-slate-900">Appointments</h3>
            <p class="text-sm text-slate-500">Total records: <?php echo $appointments ? $appointments->num_rows : 0; ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Appointment</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Doctor</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Date & Time</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Reason</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($appointments && $appointments->num_rows > 0): ?>
                        <?php while ($appointment = $appointments->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-blue-600"><?php echo htmlspecialchars($appointment["appointment_number"]); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($appointment["doctor_name"]); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($appointment["specialization"]); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-700"><?php echo date("d M Y", strtotime($appointment["appointment_date"])); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo date("h:i A", strtotime($appointment["appointment_time"])); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-slate-700 max-w-xs truncate"><?php echo htmlspecialchars($appointment["reason"]); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <?php $statusClass = match ($appointment["status"]) {
                                        "Pending" => "bg-amber-50 text-amber-700",
                                        "Confirmed" => "bg-blue-50 text-blue-700",
                                        "Completed" => "bg-green-50 text-green-700",
                                        default => "bg-red-50 text-red-600"
                                    }; ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg <?php echo $statusClass; ?> text-xs font-semibold"><?php echo $appointment["status"]; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="index.php?page=my_appointments&view=<?php echo $appointment["id"]; ?>"
                                            class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="View">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <?php if ($appointment["status"] === "Pending"): ?>
                                            <a href="index.php?page=my_appointments&edit=<?php echo $appointment["id"]; ?>"
                                                class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center" title="Edit">
                                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirmDelete(event);">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment["id"]; ?>">
                                                <button type="submit"
                                                    class="w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center" title="Delete">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                                        <i data-lucide="calendar-x" class="w-7 h-7 text-slate-400"></i>
                                    </div>
                                    <h3 class="font-semibold text-slate-700">No appointments found</h3>
                                    <p class="text-sm text-slate-400 mt-1">Book an appointment to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<?php if ($viewAppointment): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Appointment Details</h3>
                    <p class="text-sm text-blue-600"><?php echo htmlspecialchars($viewAppointment["appointment_number"]); ?></p>
                </div>
                <a href="index.php?page=my_appointments" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Doctor</p>
                        <p class="mt-1 font-medium text-slate-800"><?php echo htmlspecialchars($viewAppointment["doctor_name"]); ?></p>
                        <p class="text-xs text-slate-400"><?php echo htmlspecialchars($viewAppointment["specialization"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Status</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewAppointment["status"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Date</p>
                        <p class="mt-1 text-slate-700"><?php echo date("d M Y", strtotime($viewAppointment["appointment_date"])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Time</p>
                        <p class="mt-1 text-slate-700"><?php echo date("h:i A", strtotime($viewAppointment["appointment_time"])); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Reason</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewAppointment["reason"]); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Notes</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewAppointment["notes"] ?: "-"); ?></p>
                    </div>
                </div>
                <?php if ($viewAppointment["status"] === "Pending"): ?>
                    <div class="border-t border-slate-200 pt-5 flex justify-end">
                        <a href="index.php?page=my_appointments&edit=<?php echo $viewAppointment["id"]; ?>"
                            class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
                            <i data-lucide="pencil" class="w-4 h-4"></i> Edit Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Edit Modal -->
<div id="appointmentModal" class="<?php echo $editAppointment ? 'flex' : 'hidden'; ?> fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Edit Appointment</h3>
                <p class="text-sm text-slate-500">Update your pending appointment</p>
            </div>
            <button type="button" onclick="closeAppointmentModal()" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-6" id="appointmentForm">
            <input type="hidden" name="action" value="update">
            <?php if ($editAppointment): ?>
                <input type="hidden" name="appointment_id" value="<?php echo $editAppointment["id"]; ?>">
            <?php endif; ?>
            <input type="hidden" name="schedule_id" id="scheduleIdField" value="<?php echo htmlspecialchars($editAppointment["schedule_id"] ?? ""); ?>">

            <div class="space-y-7">
                <!-- Doctor -->
                <div>
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="stethoscope" class="w-5 h-5 text-blue-600"></i>
                        Select Doctor
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Doctor <span class="text-red-500">*</span>
                            </label>
                            <select name="doctor_id" id="doctorSelect" required
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Doctor</option>
                                <?php $doctors->data_seek(0);
                                while ($doctor = $doctors->fetch_assoc()): ?>
                                    <option value="<?php echo $doctor["id"]; ?>" <?php echo ((int) ($editAppointment["doctor_id"] ?? 0) === (int) $doctor["id"]) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($doctor["full_name"]); ?> - <?php echo htmlspecialchars($doctor["specialization"]); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Date & Time -->
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="calendar-clock" class="w-5 h-5 text-blue-600"></i>
                        Appointment Date & Time
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="appointment_date" id="appointmentDate" required
                                min="<?php echo date("Y-m-d", strtotime("+1 day")); ?>"
                                max="<?php echo date("Y-m-t"); ?>"
                                value="<?php echo htmlspecialchars($editAppointment["appointment_date"] ?? date("Y-m-d", strtotime("+1 day"))); ?>"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <p class="text-xs text-slate-400 mt-1">Only future dates within this month.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Time <span class="text-red-500">*</span>
                            </label>
                            <select name="appointment_time" id="appointmentTime" required
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select doctor and date first</option>
                            </select>
                        </div>
                    </div>
                    <div id="slotInfo" class="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-xl hidden">
                        <div class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                            <p class="text-sm text-blue-700" id="slotInfoText"></p>
                        </div>
                    </div>
                </div>

                <!-- Reason -->
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-600"></i>
                        Reason for Visit
                    </h4>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" required rows="3"
                            placeholder="Briefly describe your symptoms or reason for the appointment..."
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"><?php echo htmlspecialchars($editAppointment["reason"] ?? ""); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <button type="button" onclick="closeAppointmentModal()"
                    class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    Update Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const schedulesData = <?php echo json_encode($schedulesData); ?>;
    const bookedData = <?php echo json_encode($bookedData); ?>;
    const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

    function confirmDelete(e) {
        if (!confirm("Are you sure you want to delete this appointment?")) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    function closeAppointmentModal() {
        window.location.href = "index.php?page=my_appointments";
    }

    function toMinutes(t) {
        const p = t.split(":");
        return parseInt(p[0]) * 60 + parseInt(p[1]);
    }

    function generateSlots(start, end) {
        const slots = [];
        let cur = toMinutes(start);
        const endMin = toMinutes(end);
        while (cur < endMin) {
            slots.push(String(Math.floor(cur / 60)).padStart(2, "0") + ":" + String(cur % 60).padStart(2, "0"));
            cur += 30;
        }
        return slots;
    }

    function formatTime(t) {
        const [h, m] = t.split(":");
        const hour = parseInt(h);
        return (hour % 12 || 12) + ":" + m + " " + (hour >= 12 ? "PM" : "AM");
    }

    function updateTimeSlots() {
        const doctorId = document.getElementById("doctorSelect").value;
        const dateVal = document.getElementById("appointmentDate").value;
        const timeSelect = document.getElementById("appointmentTime");
        const infoBox = document.getElementById("slotInfo");
        const infoText = document.getElementById("slotInfoText");
        const scheduleIdField = document.getElementById("scheduleIdField");

        const currentSelected = timeSelect.value;

        timeSelect.innerHTML = '<option value="">Select doctor and date first</option>';
        scheduleIdField.value = "";
        infoBox.classList.add("hidden");

        if (!doctorId || !dateVal) return;

        const d = new Date(dateVal + "T00:00:00");
        const dayName = dayNames[d.getDay()];
        const matches = schedulesData.filter(s => s.doctor_id == doctorId && s.day === dayName);

        if (matches.length === 0) {
            timeSelect.innerHTML = '<option value="">No schedule available for this day</option>';
            infoBox.classList.remove("hidden");
            infoText.textContent = "This doctor has no schedule on " + dayName + ". Please choose another date.";
            return;
        }

        let totalBooked = 0;
        timeSelect.innerHTML = '<option value="">Select Time</option>';
        const seen = new Set();

        matches.forEach(schedule => {
            generateSlots(schedule.start, schedule.end).forEach(slot => {
                if (seen.has(slot)) return;
                seen.add(slot);

                const booked = (bookedData[doctorId] && bookedData[doctorId][dateVal] && bookedData[doctorId][dateVal][slot]) || 0;
                totalBooked += booked;
                const full = booked >= schedule.max;

                const opt = document.createElement("option");
                opt.value = slot;
                opt.textContent = formatTime(slot) + " (" + booked + "/" + schedule.max + ")" + (full ? " - FULL" : "");
                opt.dataset.scheduleId = schedule.id;
                opt.dataset.booked = booked;
                opt.dataset.max = schedule.max;
                if (full) opt.disabled = true;
                timeSelect.appendChild(opt);
            });
        });

        infoBox.classList.remove("hidden");
        infoText.textContent = dayName + " " + dateVal + " - " + matches.length + " schedule(s), " + totalBooked + " appointment(s) already booked.";
        lucide.createIcons();
    }

    document.getElementById("appointmentTime").addEventListener("change", function() {
        const opt = this.options[this.selectedIndex];
        document.getElementById("scheduleIdField").value = opt.dataset.scheduleId || "";
    });

    document.getElementById("doctorSelect").addEventListener("change", updateTimeSlots);
    document.getElementById("appointmentDate").addEventListener("change", updateTimeSlots);

    document.addEventListener("DOMContentLoaded", function() {
        const doctorSelect = document.getElementById("doctorSelect");
        const appointmentDate = document.getElementById("appointmentDate");
        if (doctorSelect && appointmentDate && doctorSelect.value && appointmentDate.value) {
            updateTimeSlots();
            const editTime = "<?php echo htmlspecialchars($editAppointment["appointment_time"] ?? ""); ?>";
            if (editTime) {
                const timeSelect = document.getElementById("appointmentTime");
                for (let i = 0; i < timeSelect.options.length; i++) {
                    if (timeSelect.options[i].value === editTime) {
                        timeSelect.selectedIndex = i;
                        document.getElementById("scheduleIdField").value = timeSelect.options[i].dataset.scheduleId || "";
                        break;
                    }
                }
            }
        }
        lucide.createIcons();
    });

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