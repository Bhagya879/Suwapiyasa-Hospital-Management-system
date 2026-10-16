<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();


$message = "";
$messageType = "";

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

function validateAppointmentSchedule($conn, $scheduleId, $doctorId, $appointmentDate, $appointmentTime)
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
            AND status != 'Cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $doctorId, $appointmentDate, $appointmentTime);
    $stmt->execute();
    $bookedCount = (int) ($stmt->get_result()->fetch_assoc()["booked_count"] ?? 0);
    $maxPatients = (int) $schedule["max_patients"];
    if ($bookedCount >= $maxPatients) return ["valid" => false, "message" => "This appointment time is already full."];
    return ["valid" => true];
}

/* =================== BOOK APPOINTMENT =================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "book") {
    $doctorId        = (int) ($_POST["doctor_id"] ?? 0);
    $scheduleId      = (int) ($_POST["schedule_id"] ?? 0);
    $appointmentDate = $_POST["appointment_date"] ?? "";
    $appointmentTime = $_POST["appointment_time"] ?? "";
    $reason          = trim($_POST["reason"] ?? "");

    if ($doctorId === 0 || $scheduleId === 0 || $appointmentDate === "" || $appointmentTime === "" || $reason === "") {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif ($appointmentDate <= date("Y-m-d")) {
        $message = "Appointment date must be a future date (today is not allowed).";
        $messageType = "error";
    } elseif (date("Y-m", strtotime($appointmentDate)) !== date("Y-m")) {
        $message = "Appointment date must be within the current month.";
        $messageType = "error";
    } else {
        // Verify doctor is active
        $docCheck = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
        $docCheck->bind_param("i", $doctorId);
        $docCheck->execute();
        if ($docCheck->get_result()->num_rows === 0) {
            $message = "Selected doctor is not available.";
            $messageType = "error";
        } else {
            $scheduleValidation = validateAppointmentSchedule($conn, $scheduleId, $doctorId, $appointmentDate, $appointmentTime);
            if (!$scheduleValidation["valid"]) {
                $message = $scheduleValidation["message"];
                $messageType = "error";
            } else {
                $appointmentNumber = generateAppointmentNumber($conn);
                $stmt = $conn->prepare("INSERT INTO appointments (appointment_number, patient_id, doctor_id, schedule_id, appointment_date, appointment_time, reason, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', '')");
                $stmt->bind_param("siiisss", $appointmentNumber, $patientId, $doctorId, $scheduleId, $appointmentDate, $appointmentTime, $reason);
                if ($stmt->execute()) {
                    $message = "Appointment booked successfully. Appointment Number: " . $appointmentNumber;
                    $messageType = "success";
                } else {
                    $message = "Failed to book appointment.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* ---------- Load data for form ---------- */
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
            <h2 class="text-2xl font-bold text-slate-900">Book Appointment</h2>
            <p class="text-sm text-slate-500 mt-1">Choose a doctor, date and time that suits you.</p>
        </div>
        <a href="index.php?page=my_appointments"
            class="inline-flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="calendar-check" class="w-5 h-5"></i>
            My Appointments
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
        <form method="POST" class="p-6" id="appointmentForm">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="schedule_id" id="scheduleIdField" value="">

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
                                <?php while ($doctor = $doctors->fetch_assoc()): ?>
                                    <option value="<?php echo $doctor["id"]; ?>">
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
                                value="<?php echo date("Y-m-d", strtotime("+1 day")); ?>"
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
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <a href="index.php?page=dashboard"
                    class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                    Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                    Book Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const schedulesData = <?php echo json_encode($schedulesData); ?>;
    const bookedData = <?php echo json_encode($bookedData); ?>;
    const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

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