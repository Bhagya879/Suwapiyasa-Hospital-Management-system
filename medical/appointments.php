<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireMedicalRole();

$message = "";
$messageType = "";
$editAppointment = null;
$viewAppointment = null;
$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "User";

function generateAppointmentNumber($conn)
{
    $result = $conn->query("SELECT appointment_number FROM appointments ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $number = (int) preg_replace('/[^0-9]/', '', $row["appointment_number"]);
        return "APT" . str_pad($number + 1, 6, "0", STR_PAD_LEFT);
    }
    return "APT000001";
}

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

function validateAppointmentInput($data, $conn, $isEdit = false)
{
    $required = ["patient_id", "doctor_id", "schedule_id", "appointment_date", "appointment_time", "reason"];
    foreach ($required as $field) {
        if (empty($data[$field])) return ["valid" => false, "message" => "Please fill in all required fields."];
    }
    if (!in_array($data["status"], ["Pending", "Confirmed", "Completed", "Cancelled"])) {
        return ["valid" => false, "message" => "Invalid appointment status."];
    }
    if ($data["appointment_date"] <= date("Y-m-d")) {
        return ["valid" => false, "message" => "Appointment date must be a future date (today is not allowed)."];
    }
    if (date("Y-m", strtotime($data["appointment_date"])) !== date("Y-m")) {
        return ["valid" => false, "message" => "Appointment date must be within the current month."];
    }
    if ($isEdit) {
        $stmt = $conn->prepare("SELECT id FROM appointments WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $data["appointment_id"]);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) return ["valid" => false, "message" => "Appointment not found."];
    }
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param("i", $data["patient_id"]);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) return ["valid" => false, "message" => "Selected patient is not available."];

    $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param("i", $data["doctor_id"]);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) return ["valid" => false, "message" => "Selected doctor is not available."];

    return ["valid" => true];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add" || $action === "update") {
        $data = [
            "patient_id" => (int) ($_POST["patient_id"] ?? 0),
            "doctor_id" => (int) ($_POST["doctor_id"] ?? 0),
            "schedule_id" => (int) ($_POST["schedule_id"] ?? 0),
            "appointment_date" => $_POST["appointment_date"] ?? "",
            "appointment_time" => $_POST["appointment_time"] ?? "",
            "reason" => trim($_POST["reason"] ?? ""),
            "status" => $_POST["status"] ?? "Pending",
            "notes" => trim($_POST["notes"] ?? ""),
        ];
        if ($action === "update") $data["appointment_id"] = (int) ($_POST["appointment_id"] ?? 0);

        $validation = validateAppointmentInput($data, $conn, $action === "update");
        if (!$validation["valid"]) {
            $message = $validation["message"];
            $messageType = "error";
        } else {
            $scheduleValidation = validateAppointmentSchedule($conn, $data["schedule_id"], $data["doctor_id"], $data["appointment_date"], $data["appointment_time"], $action === "update" ? $data["appointment_id"] : 0);
            if (!$scheduleValidation["valid"]) {
                $message = $scheduleValidation["message"];
                $messageType = "error";
            } else if ($action === "add") {
                $appointmentNumber = generateAppointmentNumber($conn);
                $stmt = $conn->prepare("INSERT INTO appointments (appointment_number, patient_id, doctor_id, schedule_id, appointment_date, appointment_time, reason, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiisssss", $appointmentNumber, $data["patient_id"], $data["doctor_id"], $data["schedule_id"], $data["appointment_date"], $data["appointment_time"], $data["reason"], $data["status"], $data["notes"]);
                if ($stmt->execute()) {
                    $message = "Appointment created successfully. Appointment Number: " . $appointmentNumber;
                    $messageType = "success";
                } else {
                    $message = "Failed to create appointment.";
                    $messageType = "error";
                }
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET patient_id = ?, doctor_id = ?, schedule_id = ?, appointment_date = ?, appointment_time = ?, reason = ?, status = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("iiisssssi", $data["patient_id"], $data["doctor_id"], $data["schedule_id"], $data["appointment_date"], $data["appointment_time"], $data["reason"], $data["status"], $data["notes"], $data["appointment_id"]);
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
    if ($action === "delete") {
        $message = "Medical staff cannot delete appointments.";
        $messageType = "error";
    }
}

if (isset($_GET["view"])) {
    $appointmentId = (int) $_GET["view"];
    $stmt = $conn->prepare("SELECT a.*, p.patient_number, p.full_name AS patient_name, p.phone AS patient_phone, d.doctor_number, d.full_name AS doctor_name, d.specialization FROM appointments a INNER JOIN patients p ON a.patient_id = p.id INNER JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? LIMIT 1");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $viewAppointment = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET["edit"])) {
    $appointmentId = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT a.*, p.patient_number, p.full_name AS patient_name, d.doctor_number, d.full_name AS doctor_name, d.specialization FROM appointments a INNER JOIN patients p ON a.patient_id = p.id INNER JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? LIMIT 1");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $editAppointment = $stmt->get_result()->fetch_assoc();
}

$search = trim($_GET["search"] ?? "");
if ($search !== "") {
    $stmt = $conn->prepare("SELECT a.*, p.patient_number, p.full_name AS patient_name, d.doctor_number, d.full_name AS doctor_name, d.specialization FROM appointments a INNER JOIN patients p ON a.patient_id = p.id INNER JOIN doctors d ON a.doctor_id = d.id WHERE a.appointment_number LIKE ? OR p.patient_number LIKE ? OR p.full_name LIKE ? OR d.doctor_number LIKE ? OR d.full_name LIKE ? OR a.appointment_date LIKE ? OR a.status LIKE ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $searchValue = "%" . $search . "%";
    $stmt->bind_param("sssssss", $searchValue, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
    $stmt->execute();
    $appointments = $stmt->get_result();
} else {
    $appointments = $conn->query("SELECT a.*, p.patient_number, p.full_name AS patient_name, d.doctor_number, d.full_name AS doctor_name, d.specialization FROM appointments a INNER JOIN patients p ON a.patient_id = p.id INNER JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC, a.appointment_time DESC");
}

$patients = $conn->query("SELECT id, patient_number, full_name FROM patients WHERE status = 'Active' ORDER BY full_name ASC");
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
            <h2 class="text-2xl font-bold text-slate-900">Appointment Management</h2>
            <p class="text-sm text-slate-500 mt-1">Book and manage patient appointments with doctors.</p>
        </div>
        <button onclick="openAppointmentModal()" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="calendar-plus" class="w-5 h-5"></i> New Appointment
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="page" value="appointments">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by appointment, patient, doctor, date or status..." class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit" class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-medium transition">Search</button>
            <?php if ($search !== ""): ?>
                <a href="index.php?page=appointments" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition text-center">Clear</a>
            <?php endif; ?>
        </form>
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
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Patient</th>
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
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($appointment["patient_name"]); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($appointment["patient_number"]); ?></p>
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
                                        <a href="index.php?page=appointments&view=<?php echo $appointment["id"]; ?>" class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="View"><i data-lucide="eye" class="w-4 h-4"></i></a>
                                        <a href="index.php?page=appointments&edit=<?php echo $appointment["id"]; ?>" class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mb-3"><i data-lucide="calendar-x" class="w-7 h-7 text-slate-400"></i></div>
                                    <h3 class="font-semibold text-slate-700">No appointments found</h3>
                                    <p class="text-sm text-slate-400 mt-1">Create an appointment to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($viewAppointment): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Appointment Details</h3>
                    <p class="text-sm text-blue-600"><?php echo htmlspecialchars($viewAppointment["appointment_number"]); ?></p>
                </div>
                <a href="index.php?page=appointments" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></a>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Patient</p>
                        <p class="mt-1 font-medium text-slate-800"><?php echo htmlspecialchars($viewAppointment["patient_name"]); ?></p>
                        <p class="text-xs text-blue-600"><?php echo htmlspecialchars($viewAppointment["patient_number"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Doctor</p>
                        <p class="mt-1 font-medium text-slate-800"><?php echo htmlspecialchars($viewAppointment["doctor_name"]); ?></p>
                        <p class="text-xs text-slate-400"><?php echo htmlspecialchars($viewAppointment["specialization"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Appointment Date</p>
                        <p class="mt-1 text-slate-700"><?php echo date("d M Y", strtotime($viewAppointment["appointment_date"])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Appointment Time</p>
                        <p class="mt-1 text-slate-700"><?php echo date("h:i A", strtotime($viewAppointment["appointment_time"])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Status</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewAppointment["status"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Patient Phone</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewAppointment["patient_phone"]); ?></p>
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
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=appointments&edit=<?php echo $viewAppointment["id"]; ?>" class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Appointment</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="appointmentModal" class="<?php echo $editAppointment ? 'flex' : 'hidden'; ?> fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div>
                <h3 class="text-xl font-bold text-slate-900"><?php echo $editAppointment ? "Edit Appointment" : "New Appointment"; ?></h3>
                <p class="text-sm text-slate-500"><?php echo $editAppointment ? "Update appointment information" : "Book an appointment for a patient"; ?></p>
            </div>
            <button type="button" onclick="closeAppointmentModal()" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" class="p-6" id="appointmentForm">
            <input type="hidden" name="action" value="<?php echo $editAppointment ? 'update' : 'add'; ?>">
            <?php if ($editAppointment): ?><input type="hidden" name="appointment_id" value="<?php echo $editAppointment["id"]; ?>"><?php endif; ?>
            <input type="hidden" name="schedule_id" id="scheduleIdField" value="<?php echo htmlspecialchars($editAppointment["schedule_id"] ?? ""); ?>">
            <div class="space-y-7">
                <div>
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="user-round" class="w-5 h-5 text-blue-600"></i> Patient & Doctor</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Patient <span class="text-red-500">*</span></label>
                            <select name="patient_id" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Patient</option>
                                <?php if ($patients): while ($patient = $patients->fetch_assoc()): ?>
                                        <option value="<?php echo $patient["id"]; ?>" <?php echo ((int) ($editAppointment["patient_id"] ?? 0) === (int) $patient["id"]) ? "selected" : ""; ?>><?php echo htmlspecialchars($patient["full_name"]); ?> - <?php echo htmlspecialchars($patient["patient_number"]); ?></option>
                                <?php endwhile;
                                endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Doctor <span class="text-red-500">*</span></label>
                            <select name="doctor_id" id="doctorSelect" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Doctor</option>
                                <?php $doctors->data_seek(0);
                                while ($doctor = $doctors->fetch_assoc()): ?>
                                    <option value="<?php echo $doctor["id"]; ?>" <?php echo ((int) ($editAppointment["doctor_id"] ?? 0) === (int) $doctor["id"]) ? "selected" : ""; ?>><?php echo htmlspecialchars($doctor["full_name"]); ?> - <?php echo htmlspecialchars($doctor["specialization"]); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="calendar-clock" class="w-5 h-5 text-blue-600"></i> Appointment Date & Time</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="appointment_date" id="appointmentDate" required min="<?php echo date("Y-m-d", strtotime("+1 day")); ?>" max="<?php echo date("Y-m-t"); ?>" value="<?php echo htmlspecialchars($editAppointment["appointment_date"] ?? date("Y-m-d", strtotime("+1 day"))); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <p class="text-xs text-slate-400 mt-1">Only future dates within this month.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Time <span class="text-red-500">*</span></label>
                            <select name="appointment_time" id="appointmentTime" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select doctor and date first</option>
                            </select>
                        </div>
                    </div>
                    <div id="slotInfo" class="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-xl hidden">
                        <div class="flex items-start gap-3"><i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                            <p class="text-sm text-blue-700" id="slotInfoText"></p>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="clipboard-list" class="w-5 h-5 text-blue-600"></i> Appointment Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Reason <span class="text-red-500">*</span></label>
                            <textarea name="reason" required rows="3" placeholder="Reason for appointment..." class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"><?php echo htmlspecialchars($editAppointment["reason"] ?? ""); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes..." class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"><?php echo htmlspecialchars($editAppointment["notes"] ?? ""); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="circle-check" class="w-5 h-5 text-blue-600"></i> Appointment Status</h4>
                    <div class="max-w-sm">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <?php foreach (["Pending", "Confirmed", "Completed", "Cancelled"] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo (($editAppointment["status"] ?? "Pending") === $s) ? "selected" : ""; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <button type="button" onclick="closeAppointmentModal()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="<?php echo $editAppointment ? 'save' : 'calendar-plus'; ?>" class="w-5 h-5"></i>
                    <?php echo $editAppointment ? "Update Appointment" : "Create Appointment"; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const schedulesData = <?php echo json_encode($schedulesData); ?>;
    const bookedData = <?php echo json_encode($bookedData); ?>;
    const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

    function openAppointmentModal() {
        document.getElementById("appointmentModal").classList.remove("hidden");
        document.getElementById("appointmentModal").classList.add("flex");
    }

    function closeAppointmentModal() {
        window.location.href = "index.php?page=appointments";
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
        if (doctorSelect.value && appointmentDate.value) {
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