<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();

$userId = $_SESSION["user_id"] ?? 0;
$today = date("Y-m-d");

// Get patient record
$stmt = $conn->prepare("SELECT id, patient_number, full_name FROM patients WHERE user_id = ? AND status = 'Active' LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient record not found. Please complete your registration first.");
}
$patientId = (int) $patient["id"];

// Next upcoming appointment
$stmt = $conn->prepare("
    SELECT a.appointment_number, a.appointment_date, a.appointment_time, a.status,
           d.full_name AS doctor_name, d.specialization
    FROM appointments a
    INNER JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id = ?
      AND a.appointment_date >= ?
      AND a.status IN ('Pending','Confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 1
");
$stmt->bind_param("is", $patientId, $today);
$stmt->execute();
$nextAppointment = $stmt->get_result()->fetch_assoc();

// My queue token for today
$stmt = $conn->prepare("
    SELECT q.queue_number, q.status,
           d.full_name AS doctor_name
    FROM queue q
    INNER JOIN doctors d ON d.id = q.doctor_id
    WHERE q.patient_id = ? AND q.queue_date = ?
    ORDER BY q.id DESC
    LIMIT 1
");
$stmt->bind_param("is", $patientId, $today);
$stmt->execute();
$myQueue = $stmt->get_result()->fetch_assoc();

// Current serving token for the doctor (if any)
$currentToken = null;
if ($myQueue) {
    $stmt = $conn->prepare("
        SELECT q.queue_number
        FROM queue q
        INNER JOIN doctors d ON d.id = q.doctor_id
        WHERE q.queue_date = ?
          AND q.status = 'Serving'
          AND d.full_name = ?
        ORDER BY q.id ASC
        LIMIT 1
    ");
    $stmt->bind_param("ss", $today, $myQueue["doctor_name"]);
    $stmt->execute();
    $currentToken = $stmt->get_result()->fetch_assoc();
}

// Last medical visit
$stmt = $conn->prepare("
    SELECT mr.visit_date, mr.diagnosis,
           d.full_name AS doctor_name
    FROM medical_records mr
    INNER JOIN doctors d ON d.id = mr.doctor_id
    WHERE mr.patient_id = ?
    ORDER BY mr.visit_date DESC, mr.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$lastVisit = $stmt->get_result()->fetch_assoc();
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                Welcome, <?= htmlspecialchars($patient["full_name"]) ?>!
            </h1>
            <p class="mt-1 text-sm text-slate-500">Your health overview at a glance.</p>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
            <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
            <span class="text-sm font-medium text-slate-600"><?= date("d M Y") ?></span>
        </div>
    </div>

    <!-- Patient summary -->
    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center">
            <i data-lucide="user-round" class="w-6 h-6"></i>
        </div>
        <div>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($patient["full_name"]) ?></p>
            <p class="text-xs text-blue-600"><?= htmlspecialchars($patient["patient_number"]) ?></p>
        </div>
    </div>

    <!-- Top Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Next Appointment -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="calendar-clock" class="w-5 h-5 text-blue-600"></i>
                <p class="text-sm font-semibold text-slate-700">Next Appointment</p>
            </div>
            <?php if ($nextAppointment): ?>
                <p class="text-lg font-bold text-slate-800">
                    Dr. <?= htmlspecialchars($nextAppointment["doctor_name"]) ?>
                </p>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($nextAppointment["specialization"]) ?></p>
                <p class="mt-3 text-sm text-slate-700">
                    <?= date("d M Y", strtotime($nextAppointment["appointment_date"])) ?>
                </p>
                <p class="text-sm text-slate-500">
                    <?= date("h:i A", strtotime($nextAppointment["appointment_time"])) ?>
                </p>
                <span class="mt-3 inline-block px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                    <?= htmlspecialchars($nextAppointment["status"]) ?>
                </span>
            <?php else: ?>
                <p class="text-sm text-slate-400 mt-2">No upcoming appointment.</p>
            <?php endif; ?>
        </div>

        <!-- My Queue Token -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="ticket" class="w-5 h-5 text-amber-600"></i>
                <p class="text-sm font-semibold text-slate-700">My Queue Token</p>
            </div>
            <?php if ($myQueue): ?>
                <p class="text-4xl font-bold text-amber-600"><?= htmlspecialchars($myQueue["queue_number"]) ?></p>
                <p class="mt-2 text-sm text-slate-500">
                    Dr. <?= htmlspecialchars($myQueue["doctor_name"]) ?>
                </p>
                <?php
                $qClass = match ($myQueue["status"]) {
                    "Waiting"   => "bg-amber-50 text-amber-700",
                    "Serving"   => "bg-blue-50 text-blue-700",
                    "Skipped"   => "bg-red-50 text-red-700",
                    "Completed" => "bg-emerald-50 text-emerald-700",
                    default      => "bg-slate-50 text-slate-700"
                };
                ?>
                <span class="mt-3 inline-block px-2.5 py-1 rounded-lg <?= $qClass ?> text-xs font-semibold">
                    <?= htmlspecialchars($myQueue["status"]) ?>
                </span>
            <?php else: ?>
                <p class="text-sm text-slate-400 mt-2">You are not in today's queue.</p>
            <?php endif; ?>
        </div>

        <!-- Current Token -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="megaphone" class="w-5 h-5 text-indigo-600"></i>
                <p class="text-sm font-semibold text-slate-700">Current Token</p>
            </div>
            <?php if ($currentToken): ?>
                <p class="text-4xl font-bold text-indigo-600"><?= htmlspecialchars($currentToken["queue_number"]) ?></p>
                <p class="mt-2 text-sm text-slate-500">Now serving</p>
            <?php else: ?>
                <p class="text-sm text-slate-400 mt-2">No patient currently being served.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Last Visit -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <div class="flex items-center gap-2 mb-3">
            <i data-lucide="history" class="w-5 h-5 text-emerald-600"></i>
            <p class="text-sm font-semibold text-slate-700">Last Medical Visit</p>
        </div>
        <?php if ($lastVisit): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-slate-400 uppercase">Date</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">
                        <?= date("d M Y", strtotime($lastVisit["visit_date"])) ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase">Doctor</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">
                        Dr. <?= htmlspecialchars($lastVisit["doctor_name"]) ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase">Diagnosis</p>
                    <p class="mt-1 text-sm text-slate-700">
                        <?= htmlspecialchars($lastVisit["diagnosis"]) ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-400 mt-2">No medical visits recorded yet.</p>
        <?php endif; ?>
    </div>

</div>

<script>
    lucide.createIcons();
</script>