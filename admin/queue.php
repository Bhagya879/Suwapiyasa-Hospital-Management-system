<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();
$today = date("Y-m-d");
$message = "";
$messageType = "success";
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
/* Check-in appointment */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["check_in"])) {
    $appointmentId = (int)$_POST["appointment_id"];
    $stmt = $conn->prepare("
        SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status,
               p.full_name AS patient_name,
               d.full_name AS doctor_name
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.id
        INNER JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $appointmentId);
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
            $message = "This appointment is already in the queue.";
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
                $appointment["patient_id"],
                $appointment["doctor_id"],
                $today
            );
            if ($insert->execute()) {
                $message = "Patient checked in successfully. Token: " . $queueNumber;
                $messageType = "success";
            } else {
                $message = "Unable to add patient to queue.";
                $messageType = "error";
            }
        }
    }
}
/* Call next */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["call_next"])) {
    $doctorId = (int)$_POST["doctor_id"];
    $checkServing = $conn->prepare("
        SELECT id
        FROM queue
        WHERE doctor_id = ?
        AND queue_date = ?
        AND status = 'Serving'
        LIMIT 1
    ");
    $checkServing->bind_param("is", $doctorId, $today);
    $checkServing->execute();
    $serving = $checkServing->get_result()->fetch_assoc();
    if ($serving) {
        $message = "This doctor already has a patient being served.";
        $messageType = "error";
    } else {
        $next = $conn->prepare("
            SELECT id
            FROM queue
            WHERE doctor_id = ?
            AND queue_date = ?
            AND status = 'Waiting'
            ORDER BY id ASC
            LIMIT 1
        ");
        $next->bind_param("is", $doctorId, $today);
        $next->execute();
        $nextQueue = $next->get_result()->fetch_assoc();
        if (!$nextQueue) {
            $message = "No waiting patients for this doctor.";
            $messageType = "error";
        } else {
            $update = $conn->prepare("
                UPDATE queue
                SET status = 'Serving',
                    called_time = NOW()
                WHERE id = ?
            ");
            $update->bind_param("i", $nextQueue["id"]);
            if ($update->execute()) {
                $message = "Next patient has been called.";
                $messageType = "success";
            } else {
                $message = "Unable to call next patient.";
                $messageType = "error";
            }
        }
    }
}
/* Skip patient */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["skip"])) {
    $queueId = (int)$_POST["queue_id"];
    $update = $conn->prepare("
        UPDATE queue
        SET status = 'Skipped'
        WHERE id = ?
        AND status IN ('Waiting','Serving')
    ");
    $update->bind_param("i", $queueId);
    if ($update->execute()) {
        $message = "Patient skipped successfully.";
        $messageType = "success";
    } else {
        $message = "Unable to skip patient.";
        $messageType = "error";
    }
}
/* Complete patient */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["complete"])) {
    $queueId = (int)$_POST["queue_id"];
    $update = $conn->prepare("
        UPDATE queue
        SET status = 'Completed',
            completed_time = NOW()
        WHERE id = ?
        AND status = 'Serving'
    ");
    $update->bind_param("i", $queueId);
    if ($update->execute()) {
        $message = "Patient consultation completed.";
        $messageType = "success";
    } else {
        $message = "Unable to complete queue record.";
        $messageType = "error";
    }
}
/* Search */
$search = trim($_GET["search"] ?? "");
$searchLike = "%" . $search . "%";
/* Today's queue */
$stmt = $conn->prepare("
    SELECT q.id,
           q.queue_number,
           q.queue_date,
           q.check_in_time,
           q.called_time,
           q.completed_time,
           q.status,
           p.patient_number,
           p.full_name AS patient_name,
           d.doctor_number,
           d.full_name AS doctor_name,
           a.appointment_number,
           a.appointment_time,
           a.reason
    FROM queue q
    INNER JOIN patients p ON q.patient_id = p.id
    INNER JOIN doctors d ON q.doctor_id = d.id
    INNER JOIN appointments a ON q.appointment_id = a.id
    WHERE q.queue_date = ?
    AND (
        q.queue_number LIKE ?
        OR p.patient_number LIKE ?
        OR p.full_name LIKE ?
        OR d.full_name LIKE ?
    )
    ORDER BY
        CASE
            WHEN q.status = 'Serving' THEN 1
            WHEN q.status = 'Waiting' THEN 2
            WHEN q.status = 'Skipped' THEN 3
            WHEN q.status = 'Completed' THEN 4
            ELSE 5
        END,
        q.id ASC
");
$stmt->bind_param("sssss", $today, $searchLike, $searchLike, $searchLike, $searchLike);
$stmt->execute();
$queueResult = $stmt->get_result();
/* Today's appointments available for check-in */
$appointmentStmt = $conn->prepare("
    SELECT a.id,
           a.appointment_number,
           a.appointment_time,
           p.patient_number,
           p.full_name AS patient_name,
           d.full_name AS doctor_name
    FROM appointments a
    INNER JOIN patients p ON a.patient_id = p.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN queue q ON a.id = q.appointment_id
    WHERE a.appointment_date = ?
    AND a.status IN ('Pending','Confirmed')
    AND q.id IS NULL
    ORDER BY a.appointment_time ASC
");
$appointmentStmt->bind_param("s", $today);
$appointmentStmt->execute();
$appointmentResult = $appointmentStmt->get_result();
/* Active doctors */
$doctorResult = $conn->query("
    SELECT id, doctor_number, full_name
    FROM doctors
    WHERE status = 'Active'
    ORDER BY full_name ASC
");
/* Queue counts */
$countStmt = $conn->prepare("
    SELECT
        SUM(status = 'Waiting') AS waiting_count,
        SUM(status = 'Serving') AS serving_count,
        SUM(status = 'Skipped') AS skipped_count,
        SUM(status = 'Completed') AS completed_count
    FROM queue
    WHERE queue_date = ?
");
$countStmt->bind_param("s", $today);
$countStmt->execute();
$counts = $countStmt->get_result()->fetch_assoc();
$waitingCount = (int)($counts["waiting_count"] ?? 0);
$servingCount = (int)($counts["serving_count"] ?? 0);
$skippedCount = (int)($counts["skipped_count"] ?? 0);
$completedCount = (int)($counts["completed_count"] ?? 0);
?>
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="list-ordered" class="w-7 h-7 text-blue-600"></i>
                Queue Management
            </h1>
            <p class="text-sm text-slate-500 mt-1">Manage today's patient check-ins and consultation queue.</p>
        </div>
        <div class="bg-blue-50 text-blue-700 px-4 py-2 rounded-xl font-medium">
            <?= date("d M Y") ?>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Waiting</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1"><?= $waitingCount ?></p>
                </div>
                <div class="bg-amber-100 p-3 rounded-xl">
                    <i data-lucide="clock-3" class="w-6 h-6 text-amber-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Serving</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1"><?= $servingCount ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-xl">
                    <i data-lucide="user-check" class="w-6 h-6 text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Completed</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1"><?= $completedCount ?></p>
                </div>
                <div class="bg-emerald-100 p-3 rounded-xl">
                    <i data-lucide="circle-check" class="w-6 h-6 text-emerald-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Skipped</p>
                    <p class="text-2xl font-bold text-red-600 mt-1"><?= $skippedCount ?></p>
                </div>
                <div class="bg-red-100 p-3 rounded-xl">
                    <i data-lucide="skip-forward" class="w-6 h-6 text-red-600"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center gap-2 mb-5">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i>
                <h2 class="text-lg font-bold text-slate-800">Patient Check-in</h2>
            </div>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Today's Appointment</label>
                    <select name="appointment_id" required class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Appointment</option>
                        <?php while ($appointment = $appointmentResult->fetch_assoc()): ?>
                            <option value="<?= $appointment["id"] ?>">
                                <?= htmlspecialchars($appointment["appointment_number"]) ?> -
                                <?= htmlspecialchars($appointment["patient_name"]) ?> -
                                <?= htmlspecialchars($appointment["doctor_name"]) ?>
                                (<?= date("h:i A", strtotime($appointment["appointment_time"])) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="check_in" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-5 h-5"></i>
                    Check-in & Generate Token
                </button>
            </form>
            <div class="mt-6 pt-5 border-t border-slate-100">
                <h3 class="font-semibold text-slate-800 mb-3">Call Next Patient</h3>
                <form method="POST" class="space-y-3">
                    <select name="doctor_id" required class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Doctor</option>
                        <?php while ($doctor = $doctorResult->fetch_assoc()): ?>
                            <option value="<?= $doctor["id"] ?>">
                                <?= htmlspecialchars($doctor["doctor_number"]) ?> - <?= htmlspecialchars($doctor["full_name"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" name="call_next" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
                        <i data-lucide="megaphone" class="w-5 h-5"></i>
                        Call Next
                    </button>
                </form>
            </div>
        </div>
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Current Queue</h2>
                        <p class="text-sm text-slate-500">Today's patient queue</p>
                    </div>
                    <form method="GET" class="flex gap-2">
                        <input type="hidden" name="page" value="queue">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search token, patient or doctor..." class="rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-4 rounded-xl">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Token</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Patient</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Doctor</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Time</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                            <th class="text-right px-6 py-4 font-semibold text-slate-600">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($queueResult->num_rows > 0): ?>
                            <?php while ($row = $queueResult->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-blue-600"><?= htmlspecialchars($row["queue_number"]) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-800"><?= htmlspecialchars($row["patient_name"]) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($row["patient_number"]) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-700"><?= htmlspecialchars($row["doctor_name"]) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($row["doctor_number"]) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        <?= date("h:i A", strtotime($row["appointment_time"])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= getQueueStatusClass($row["status"]) ?>">
                                            <?= htmlspecialchars($row["status"]) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <?php if ($row["status"] === "Serving"): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="queue_id" value="<?= $row["id"] ?>">
                                                    <button type="submit" name="complete" title="Complete" class="p-2 rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                                                        <i data-lucide="check" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($row["status"] === "Waiting" || $row["status"] === "Serving"): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="queue_id" value="<?= $row["id"] ?>">
                                                    <button type="submit" name="skip" title="Skip" class="p-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200">
                                                        <i data-lucide="skip-forward" class="w-4 h-4"></i>
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
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto text-slate-300"></i>
                                    <p class="mt-3 text-slate-500">No patients in today's queue.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    lucide.createIcons();
    <?php if ($message): ?>
        toastr.<?= $messageType ?>("<?= htmlspecialchars($message, ENT_QUOTES) ?>");
    <?php endif; ?>
</script>