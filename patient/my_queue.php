<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();

$today = date("Y-m-d");

$message = "";
$messageType = "success";

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

function generateQueueNumber($conn, $date)
{
    $stmt = $conn->prepare("SELECT queue_number FROM queue WHERE queue_date = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $lastNumber = intval(substr($row["queue_number"], 1));
        return "A" . str_pad($lastNumber + 1, 3, "0", STR_PAD_LEFT);
    }
    return "A001";
}

function getQueueStatusClass($status)
{
    switch ($status) {
        case "Waiting":
            return "bg-amber-100 text-amber-700";
        case "Serving":
            return "bg-blue-100 text-blue-700";
        case "Skipped":
            return "bg-red-100 text-red-700";
        case "Completed":
            return "bg-emerald-100 text-emerald-700";
        default:
            return "bg-slate-100 text-slate-700";
    }
}

/* ---------- Self check-in for today's appointment ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["check_in"])) {
    $appointmentId = (int) $_POST["appointment_id"];

    // Verify the appointment belongs to this patient, is today, and not cancelled
    $stmt = $conn->prepare("
        SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status
        FROM appointments a
        WHERE a.id = ? AND a.patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $appointmentId, $patientId);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();

    if (!$appointment) {
        $message = "Appointment not found.";
        $messageType = "error";
    } elseif ($appointment["appointment_date"] !== $today) {
        $message = "Only today's appointments can be checked in.";
        $messageType = "error";
    } elseif ($appointment["status"] === "Cancelled") {
        $message = "Cancelled appointment cannot be checked in.";
        $messageType = "error";
    } else {
        $check = $conn->prepare("SELECT id FROM queue WHERE appointment_id = ? LIMIT 1");
        $check->bind_param("i", $appointmentId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        if ($existing) {
            $message = "You are already checked in for this appointment.";
            $messageType = "error";
        } else {
            $queueNumber = generateQueueNumber($conn, $today);
            $insert = $conn->prepare("
                INSERT INTO queue
                (queue_number, appointment_id, patient_id, doctor_id, queue_date, status)
                VALUES (?, ?, ?, ?, ?, 'Waiting')
            ");
            $insert->bind_param(
                "siiis",
                $queueNumber,
                $appointmentId,
                $patientId,
                $appointment["doctor_id"],
                $today
            );
            if ($insert->execute()) {
                $message = "Checked in successfully. Your token: " . $queueNumber;
                $messageType = "success";
            } else {
                $message = "Unable to check in. Please try again.";
                $messageType = "error";
            }
        }
    }
}

/* ---------- Today's appointments available for self check-in ---------- */
$appointmentStmt = $conn->prepare("
    SELECT a.id,
           a.appointment_number,
           a.appointment_time,
           d.full_name AS doctor_name,
           d.specialization
    FROM appointments a
    INNER JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN queue q ON a.id = q.appointment_id
    WHERE a.appointment_date = ?
    AND a.patient_id = ?
    AND a.status IN ('Pending','Confirmed')
    AND q.id IS NULL
    ORDER BY a.appointment_time ASC
");
$appointmentStmt->bind_param("si", $today, $patientId);
$appointmentStmt->execute();
$appointmentResult = $appointmentStmt->get_result();

/* ---------- My queue entries (today) ---------- */
$queueStmt = $conn->prepare("
    SELECT q.id,
           q.queue_number,
           q.queue_date,
           q.check_in_time,
           q.called_time,
           q.completed_time,
           q.status,
           d.doctor_number,
           d.full_name AS doctor_name,
           d.specialization,
           a.appointment_number,
           a.appointment_time,
           a.reason
    FROM queue q
    INNER JOIN doctors d ON q.doctor_id = d.id
    INNER JOIN appointments a ON q.appointment_id = a.id
    WHERE q.queue_date = ?
    AND q.patient_id = ?
    ORDER BY q.id DESC
");
$queueStmt->bind_param("si", $today, $patientId);
$queueStmt->execute();
$queueResult = $queueStmt->get_result();

/* ---------- My queue history (previous days) ---------- */
$historyStmt = $conn->prepare("
    SELECT q.id,
           q.queue_number,
           q.queue_date,
           q.check_in_time,
           q.called_time,
           q.completed_time,
           q.status,
           d.doctor_number,
           d.full_name AS doctor_name,
           d.specialization,
           a.appointment_number,
           a.appointment_time,
           a.reason
    FROM queue q
    INNER JOIN doctors d ON q.doctor_id = d.id
    INNER JOIN appointments a ON q.appointment_id = a.id
    WHERE q.patient_id = ?
    AND q.queue_date < ?
    ORDER BY q.queue_date DESC, q.id DESC
    LIMIT 20
");
$historyStmt->bind_param("is", $patientId, $today);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="list-ordered" class="w-7 h-7 text-blue-600"></i>
                My Queue
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                View your token status and check in for today's appointment.
            </p>
        </div>

        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
            <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
            <span class="text-sm font-medium text-slate-600">
                <?= date("d M Y") ?>
            </span>
        </div>
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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- Left: Check-in -->
        <div class="xl:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center gap-2 mb-5">
                <i data-lucide="log-in" class="w-5 h-5 text-blue-600"></i>
                <h2 class="text-lg font-bold text-slate-800">Check-in</h2>
            </div>

            <?php if ($appointmentResult->num_rows > 0): ?>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Today's Appointment
                        </label>
                        <select name="appointment_id" required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Select Appointment</option>
                            <?php while ($appointment = $appointmentResult->fetch_assoc()): ?>
                                <option value="<?= (int) $appointment["id"] ?>">
                                    <?= htmlspecialchars($appointment["appointment_number"]) ?>
                                    · Dr. <?= htmlspecialchars($appointment["doctor_name"]) ?>
                                    · <?= date("h:i A", strtotime($appointment["appointment_time"])) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <button type="submit" name="check_in"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2 transition">
                        <i data-lucide="log-in" class="w-5 h-5"></i>
                        Check-in & Get Token
                    </button>
                </form>
            <?php else: ?>
                <div class="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                        <i data-lucide="calendar-x" class="h-6 w-6 text-slate-400"></i>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">
                        No Appointment Today
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        You have no pending or confirmed appointments for today.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Today's Queue -->
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-800">Today's Queue</h2>
                <p class="text-sm text-slate-500">Your token status for today.</p>
            </div>

            <?php if ($queueResult->num_rows > 0): ?>
                <div class="divide-y divide-slate-100">
                    <?php while ($row = $queueResult->fetch_assoc()): ?>
                        <div class="p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-blue-50">
                                    <span class="text-lg font-bold text-blue-600">
                                        <?= htmlspecialchars($row["queue_number"]) ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800">
                                        Dr. <?= htmlspecialchars($row["doctor_name"]) ?>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        <?= htmlspecialchars($row["specialization"]) ?>
                                        · <?= htmlspecialchars($row["appointment_number"]) ?>
                                        · <?= date("h:i A", strtotime($row["appointment_time"])) ?>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Checked in: <?= date("h:i A", strtotime($row["check_in_time"])) ?>
                                        <?php if (!empty($row["called_time"])): ?>
                                            · Called: <?= date("h:i A", strtotime($row["called_time"])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold <?= getQueueStatusClass($row["status"]) ?>">
                                <?= htmlspecialchars($row["status"]) ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                        <i data-lucide="inbox" class="h-7 w-7 text-slate-400"></i>
                    </div>
                    <h3 class="mt-4 font-semibold text-slate-700">Not in Queue</h3>
                    <p class="mt-1 text-sm text-slate-400">
                        You are not currently in today's queue.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- History -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-800">Queue History</h2>
                <p class="text-xs text-slate-500">Your previous queue entries.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                <i data-lucide="history" class="h-3.5 w-3.5"></i>
                Last 20
            </span>
        </div>

        <?php if ($historyResult->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200">
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Token</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Doctor</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Appointment</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($row = $historyResult->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-bold text-blue-600">
                                    <?= htmlspecialchars($row["queue_number"]) ?>
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    <?= date("d M Y", strtotime($row["queue_date"])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-800">Dr. <?= htmlspecialchars($row["doctor_name"]) ?></p>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($row["specialization"]) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-slate-700"><?= htmlspecialchars($row["appointment_number"]) ?></p>
                                    <p class="text-xs text-slate-400"><?= date("h:i A", strtotime($row["appointment_time"])) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= getQueueStatusClass($row["status"]) ?>">
                                        <?= htmlspecialchars($row["status"]) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                    <i data-lucide="history" class="h-7 w-7 text-slate-400"></i>
                </div>
                <h3 class="mt-4 font-semibold text-slate-700">No History</h3>
                <p class="mt-1 text-sm text-slate-400">
                    You don't have any previous queue entries.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    <?php if ($message !== ""): ?>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if ($messageType === "success"): ?>
                toastr.success(<?php echo json_encode($message); ?>, "Success");
            <?php else: ?>
                toastr.error(<?php echo json_encode($message); ?>, "Error");
            <?php endif; ?>
        });
    <?php endif; ?>
</script>