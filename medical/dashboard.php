<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";

$role   = $_SESSION["role"] ?? "";
$userId = (int) ($_SESSION["user_id"] ?? 0);
$today  = date("Y-m-d");

// Only doctor or nurse may access this dashboard
if (!in_array($role, ["doctor", "nurse"], true)) {
    header("Location: ../index.php");
    exit;
}

/* =========================================================
   DOCTOR DASHBOARD DATA
   ========================================================= */
$doctor = null;
$doctorRows = [];
$doctorTotalToday = 0;
$doctorWaiting = 0;
$doctorCompleted = 0;

if ($role === "doctor") {
    $stmt = $conn->prepare("SELECT id, full_name FROM doctors WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        exit("Doctor profile not found.");
    }
    $doctorId = (int) $doctor["id"];

    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_number, a.appointment_time, a.status,
               p.full_name AS patient_name, p.patient_number,
               q.queue_number, q.status AS queue_status
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        LEFT JOIN queue q ON q.appointment_id = a.id
        WHERE a.doctor_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC
    ");
    $stmt->bind_param("is", $doctorId, $today);
    $stmt->execute();
    $doctorResult = $stmt->get_result();

    while ($row = $doctorResult->fetch_assoc()) {
        $doctorRows[] = $row;
        $doctorTotalToday++;
        if ($row["queue_status"] === "Waiting" || $row["queue_status"] === "Serving") $doctorWaiting++;
        if ($row["queue_status"] === "Completed" || $row["status"] === "Completed") $doctorCompleted++;
    }
}

/* =========================================================
   NURSE DASHBOARD DATA
   ========================================================= */
$nurse = null;
$nurseRows = [];
$nurseTotalQueue = 0;
$nurseWaiting = 0;
$nurseServing = 0;
$nurseCompleted = 0;
$pendingVitals = 0;

if ($role === "nurse") {
    $stmt = $conn->prepare("SELECT id, full_name FROM nurses WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $nurse = $stmt->get_result()->fetch_assoc();

    if (!$nurse) {
        exit("Nurse profile not found.");
    }
    $nurseId = (int) $nurse["id"];

    $stmt = $conn->prepare("
        SELECT q.id, q.queue_number, q.status, q.check_in_time,
               p.full_name AS patient_name, p.patient_number,
               d.full_name AS doctor_name,
               a.appointment_time
        FROM queue q
        INNER JOIN patients p ON p.id = q.patient_id
        INNER JOIN doctors d ON d.id = q.doctor_id
        INNER JOIN appointments a ON a.id = q.appointment_id
        WHERE q.queue_date = ?
        ORDER BY q.id ASC
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $nurseResult = $stmt->get_result();

    while ($row = $nurseResult->fetch_assoc()) {
        $nurseRows[] = $row;
        $nurseTotalQueue++;
        if ($row["status"] === "Waiting")   $nurseWaiting++;
        if ($row["status"] === "Serving")   $nurseServing++;
        if ($row["status"] === "Completed") $nurseCompleted++;
    }

    // Today's appointments without vitals
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM appointments a
        LEFT JOIN vital_signs v ON v.appointment_id = a.id
        WHERE a.appointment_date = ? AND a.status IN ('Pending','Confirmed') AND v.id IS NULL
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $pendingVitals = (int) $stmt->get_result()->fetch_assoc()["c"];
}
?>
<div class="space-y-6">

    <?php if ($role === "doctor"): ?>

        <!-- ===================== DOCTOR DASHBOARD ===================== -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Doctor Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">Welcome, Dr. <?= htmlspecialchars($doctor["full_name"]) ?>.</p>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
                <span class="text-sm font-medium text-slate-600"><?= date("d M Y") ?></span>
            </div>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Today's Appointments</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1"><?= $doctorTotalToday ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-xl">
                        <i data-lucide="calendar-check" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Waiting Patients</p>
                        <p class="text-3xl font-bold text-amber-600 mt-1"><?= $doctorWaiting ?></p>
                    </div>
                    <div class="bg-amber-100 p-3 rounded-xl">
                        <i data-lucide="clock-3" class="w-6 h-6 text-amber-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Completed Visits</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-1"><?= $doctorCompleted ?></p>
                    </div>
                    <div class="bg-emerald-100 p-3 rounded-xl">
                        <i data-lucide="circle-check" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Patients -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="font-bold text-slate-800">Today's Patients</h2>
                <p class="text-sm text-slate-500">Your appointment schedule for today.</p>
            </div>

            <?php if ($doctorRows): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Token</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Patient</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Time</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                                <th class="text-right px-6 py-4 font-semibold text-slate-600">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($doctorRows as $row): ?>
                                <?php
                                $status = $row["queue_status"] ?: $row["status"];
                                $statusClass = match ($status) {
                                    "Waiting"   => "bg-amber-100 text-amber-700",
                                    "Serving"   => "bg-blue-100 text-blue-700",
                                    "Skipped"   => "bg-red-100 text-red-700",
                                    "Completed" => "bg-emerald-100 text-emerald-700",
                                    default      => "bg-slate-100 text-slate-700"
                                };
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-blue-600">
                                            <?= htmlspecialchars($row["queue_number"] ?: "—") ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-800"><?= htmlspecialchars($row["patient_name"]) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($row["patient_number"]) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        <?= date("h:i A", strtotime($row["appointment_time"])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="index.php?page=medical_records&patient_id=<?= (int) $row['id'] ?>"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            View Patient
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-6 py-12 text-center">
                    <i data-lucide="calendar-x" class="w-10 h-10 mx-auto text-slate-300"></i>
                    <p class="mt-3 text-slate-500">No appointments scheduled for today.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($role === "nurse"): ?>

        <!-- ===================== NURSE DASHBOARD ===================== -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Nurse Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">Welcome, <?= htmlspecialchars($nurse["full_name"]) ?>.</p>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
                <span class="text-sm font-medium text-slate-600"><?= date("d M Y") ?></span>
            </div>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Today's Queue</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1"><?= $nurseTotalQueue ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-xl">
                        <i data-lucide="list-ordered" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Waiting</p>
                        <p class="text-3xl font-bold text-amber-600 mt-1"><?= $nurseWaiting ?></p>
                    </div>
                    <div class="bg-amber-100 p-3 rounded-xl">
                        <i data-lucide="clock-3" class="w-6 h-6 text-amber-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Serving</p>
                        <p class="text-3xl font-bold text-indigo-600 mt-1"><?= $nurseServing ?></p>
                    </div>
                    <div class="bg-indigo-100 p-3 rounded-xl">
                        <i data-lucide="user-check" class="w-6 h-6 text-indigo-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Completed</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-1"><?= $nurseCompleted ?></p>
                    </div>
                    <div class="bg-emerald-100 p-3 rounded-xl">
                        <i data-lucide="circle-check" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vitals Alert -->
        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center">
                <i data-lucide="activity" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="font-semibold text-slate-800">Pending Vital Signs</p>
                <p class="text-sm text-amber-700">
                    <?= $pendingVitals ?> appointment(s) today still need vital signs recorded.
                </p>
            </div>
            <a href="index.php?page=vital_signs"
                class="ml-auto inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Record Vitals
            </a>
        </div>

        <!-- Queue Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="font-bold text-slate-800">Today's Queue</h2>
                <p class="text-sm text-slate-500">All patients currently in today's queue.</p>
            </div>

            <?php if ($nurseRows): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Token</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Patient</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Doctor</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Time</th>
                                <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($nurseRows as $row): ?>
                                <?php
                                $statusClass = match ($row["status"]) {
                                    "Waiting"   => "bg-amber-100 text-amber-700",
                                    "Serving"   => "bg-blue-100 text-blue-700",
                                    "Skipped"   => "bg-red-100 text-red-700",
                                    "Completed" => "bg-emerald-100 text-emerald-700",
                                    default      => "bg-slate-100 text-slate-700"
                                };
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 font-bold text-blue-600">
                                        <?= htmlspecialchars($row["queue_number"]) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-800"><?= htmlspecialchars($row["patient_name"]) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($row["patient_number"]) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700">
                                        Dr. <?= htmlspecialchars($row["doctor_name"]) ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        <?= date("h:i A", strtotime($row["appointment_time"])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                            <?= htmlspecialchars($row["status"]) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-6 py-12 text-center">
                    <i data-lucide="inbox" class="w-10 h-10 mx-auto text-slate-300"></i>
                    <p class="mt-3 text-slate-500">No patients in today's queue.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script>
    lucide.createIcons();
</script>