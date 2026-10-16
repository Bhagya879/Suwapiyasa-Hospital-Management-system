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

/* =================== VIEW SINGLE =================== */
$viewVital = null;
if (isset($_GET["view"])) {
    $vitalId = (int) $_GET["view"];

    $stmt = $conn->prepare("
        SELECT
            v.id,
            v.blood_pressure,
            v.temperature,
            v.pulse_rate,
            v.weight,
            v.height,
            v.oxygen_level,
            v.recorded_at,
            v.updated_at,
            p.patient_number,
            p.full_name AS patient_name,
            d.doctor_number,
            d.full_name AS doctor_name,
            d.specialization,
            a.appointment_number,
            a.appointment_date,
            a.appointment_time
        FROM vital_signs v
        INNER JOIN patients p ON v.patient_id = p.id
        INNER JOIN doctors d ON v.doctor_id = d.id
        INNER JOIN appointments a ON v.appointment_id = a.id
        WHERE v.id = ? AND v.patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $vitalId, $patientId);
    $stmt->execute();
    $viewVital = $stmt->get_result()->fetch_assoc();
}

/* =================== LIST ALL =================== */
$stmt = $conn->prepare("
    SELECT
        v.id,
        v.blood_pressure,
        v.temperature,
        v.pulse_rate,
        v.weight,
        v.height,
        v.oxygen_level,
        v.recorded_at,
        d.doctor_number,
        d.full_name AS doctor_name,
        d.specialization,
        a.appointment_number,
        a.appointment_date,
        a.appointment_time
    FROM vital_signs v
    INNER JOIN doctors d ON v.doctor_id = d.id
    INNER JOIN appointments a ON v.appointment_id = a.id
    WHERE v.patient_id = ?
    ORDER BY v.recorded_at DESC, v.id DESC
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$vitals = $stmt->get_result();

/* ---------- Summary stats ---------- */
$summaryStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_records,
        MAX(recorded_at) AS last_recorded,
        AVG(temperature) AS avg_temp,
        AVG(oxygen_level) AS avg_oxygen
    FROM vital_signs
    WHERE patient_id = ?
");
$summaryStmt->bind_param("i", $patientId);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$totalRecords = (int) ($summary["total_records"] ?? 0);
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="activity" class="w-7 h-7 text-blue-600"></i>
                My Vital Signs
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                View your recorded vital signs and health measurements.
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

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Total Records</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1"><?= $totalRecords ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-xl">
                    <i data-lucide="clipboard-list" class="w-6 h-6 text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Last Recorded</p>
                    <p class="text-sm font-bold text-slate-800 mt-1">
                        <?= !empty($summary["last_recorded"]) ? date("d M Y", strtotime($summary["last_recorded"])) : "—" ?>
                    </p>
                </div>
                <div class="bg-emerald-100 p-3 rounded-xl">
                    <i data-lucide="calendar-check" class="w-6 h-6 text-emerald-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Avg Temperature</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">
                        <?= !empty($summary["avg_temp"]) ? number_format((float) $summary["avg_temp"], 1) . "°C" : "—" ?>
                    </p>
                </div>
                <div class="bg-amber-100 p-3 rounded-xl">
                    <i data-lucide="thermometer" class="w-6 h-6 text-amber-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Avg SpO₂</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1">
                        <?= !empty($summary["avg_oxygen"]) ? number_format((float) $summary["avg_oxygen"], 1) . "%" : "—" ?>
                    </p>
                </div>
                <div class="bg-purple-100 p-3 rounded-xl">
                    <i data-lucide="droplets" class="w-6 h-6 text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Vital Signs List -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-800">Vital Signs History</h2>
                <p class="text-xs text-slate-500">All your recorded vital sign measurements.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
                <i data-lucide="activity" class="h-3.5 w-3.5"></i>
                Total: <?= $vitals ? $vitals->num_rows : 0 ?>
            </span>
        </div>

        <?php if ($vitals && $vitals->num_rows > 0): ?>

            <!-- Desktop table -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Recorded</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Doctor</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">BP</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Temp</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Pulse</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Weight</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">Height</th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-600">SpO₂</th>
                            <th class="text-right px-6 py-4 font-semibold text-slate-600">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        // Need to re-run query since we already iterated. Use a fresh query for the table.
                        $tableStmt = $conn->prepare("
                            SELECT
                                v.id, v.blood_pressure, v.temperature, v.pulse_rate,
                                v.weight, v.height, v.oxygen_level, v.recorded_at,
                                d.full_name AS doctor_name
                            FROM vital_signs v
                            INNER JOIN doctors d ON v.doctor_id = d.id
                            WHERE v.patient_id = ?
                            ORDER BY v.recorded_at DESC, v.id DESC
                        ");
                        $tableStmt->bind_param("i", $patientId);
                        $tableStmt->execute();
                        $tableRows = $tableStmt->get_result();

                        while ($row = $tableRows->fetch_assoc()):
                        ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-800">
                                        <?= date("d M Y", strtotime($row["recorded_at"])) ?>
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        <?= date("h:i A", strtotime($row["recorded_at"])) ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    Dr. <?= htmlspecialchars($row["doctor_name"]) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-red-600">
                                        <?= htmlspecialchars($row["blood_pressure"]) ?>
                                    </span>
                                    <span class="text-xs text-slate-400">mmHg</span>
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    <?= htmlspecialchars($row["temperature"]) ?> °C
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    <?= htmlspecialchars($row["pulse_rate"]) ?> BPM
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    <?= htmlspecialchars($row["weight"]) ?> kg
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    <?= htmlspecialchars($row["height"]) ?> cm
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-blue-600">
                                        <?= htmlspecialchars($row["oxygen_level"]) ?>%
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="index.php?page=vital_signs&view=<?= (int) $row["id"] ?>"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="lg:hidden divide-y divide-slate-100">
                <?php
                $mobileStmt = $conn->prepare("
                    SELECT
                        v.id, v.blood_pressure, v.temperature, v.pulse_rate,
                        v.weight, v.height, v.oxygen_level, v.recorded_at,
                        d.full_name AS doctor_name
                    FROM vital_signs v
                    INNER JOIN doctors d ON v.doctor_id = d.id
                    WHERE v.patient_id = ?
                    ORDER BY v.recorded_at DESC, v.id DESC
                ");
                $mobileStmt->bind_param("i", $patientId);
                $mobileStmt->execute();
                $mobileRows = $mobileStmt->get_result();

                while ($row = $mobileRows->fetch_assoc()):
                ?>
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <p class="font-semibold text-slate-800">
                                    <?= date("d M Y", strtotime($row["recorded_at"])) ?>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <?= date("h:i A", strtotime($row["recorded_at"])) ?>
                                    · Dr. <?= htmlspecialchars($row["doctor_name"]) ?>
                                </p>
                            </div>
                            <a href="index.php?page=vital_signs&view=<?= (int) $row["id"] ?>"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                View
                            </a>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <span class="text-slate-500">BP:</span>
                                <span class="font-semibold text-red-600"><?= htmlspecialchars($row["blood_pressure"]) ?></span>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <span class="text-slate-500">Temp:</span>
                                <span class="font-semibold text-slate-700"><?= htmlspecialchars($row["temperature"]) ?>°C</span>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <span class="text-slate-500">Pulse:</span>
                                <span class="font-semibold text-slate-700"><?= htmlspecialchars($row["pulse_rate"]) ?> BPM</span>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <span class="text-slate-500">SpO₂:</span>
                                <span class="font-semibold text-blue-600"><?= htmlspecialchars($row["oxygen_level"]) ?>%</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                    <i data-lucide="activity" class="h-7 w-7 text-slate-400"></i>
                </div>
                <h3 class="mt-4 font-semibold text-slate-700">No Vital Signs</h3>
                <p class="mt-1 text-sm text-slate-400">
                    You don't have any vital signs recorded yet.
                </p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- View Modal -->
<?php if ($viewVital): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">

            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                        <i data-lucide="activity" class="h-5 w-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Vital Signs Details</h3>
                        <p class="text-sm text-blue-600"><?= htmlspecialchars($viewVital["appointment_number"]) ?></p>
                    </div>
                </div>
                <a href="index.php?page=vital_signs"
                    class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="p-6 space-y-6">

                <!-- Summary -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Recorded At</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= date("d M Y, h:i A", strtotime($viewVital["recorded_at"])) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Doctor</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            Dr. <?= htmlspecialchars($viewVital["doctor_name"]) ?>
                        </p>
                        <?php if (!empty($viewVital["specialization"])): ?>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($viewVital["specialization"]) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Appointment</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= date("d M Y", strtotime($viewVital["appointment_date"])) ?>
                        </p>
                        <p class="text-xs text-slate-400">
                            <?= date("h:i A", strtotime($viewVital["appointment_time"])) ?>
                        </p>
                    </div>
                </div>

                <!-- Measurements -->
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Measurements
                    </p>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-red-100 bg-red-50 p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i data-lucide="heart-pulse" class="h-4 w-4 text-red-600"></i>
                                <p class="text-xs text-red-600 font-medium">Blood Pressure</p>
                            </div>
                            <p class="text-xl font-bold text-red-700">
                                <?= htmlspecialchars($viewVital["blood_pressure"]) ?>
                            </p>
                            <p class="text-xs text-red-500">mmHg</p>
                        </div>

                        <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i data-lucide="thermometer" class="h-4 w-4 text-amber-600"></i>
                                <p class="text-xs text-amber-600 font-medium">Temperature</p>
                            </div>
                            <p class="text-xl font-bold text-amber-700">
                                <?= htmlspecialchars($viewVital["temperature"]) ?>
                            </p>
                            <p class="text-xs text-amber-500">°C</p>
                        </div>

                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i data-lucide="droplets" class="h-4 w-4 text-blue-600"></i>
                                <p class="text-xs text-blue-600 font-medium">Oxygen Level</p>
                            </div>
                            <p class="text-xl font-bold text-blue-700">
                                <?= htmlspecialchars($viewVital["oxygen_level"]) ?>
                            </p>
                            <p class="text-xs text-blue-500">SpO₂ %</p>
                        </div>

                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i data-lucide="activity" class="h-4 w-4 text-emerald-600"></i>
                                <p class="text-xs text-emerald-600 font-medium">Pulse Rate</p>
                            </div>
                            <p class="text-xl font-bold text-emerald-700">
                                <?= htmlspecialchars($viewVital["pulse_rate"]) ?>
                            </p>
                            <p class="text-xs text-emerald-500">BPM</p>
                        </div>

                        <div class="rounded-xl border border-purple-100 bg-purple-50 p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i data-lucide="scale" class="h-4 w-4 text-purple-600"></i>
                                <p class="text-xs text-purple-600 font-medium">Weight</p>
                            </div>
                            <p class="text-xl font-bold text-purple-700">
                                <?= htmlspecialchars($viewVital["weight"]) ?>
                            </p>
                            <p class="text-xs text-purple-500">kg</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center gap-2 mb-1">
                                <i data-lucide="ruler" class="h-4 w-4 text-slate-600"></i>
                                <p class="text-xs text-slate-600 font-medium">Height</p>
                            </div>
                            <p class="text-xl font-bold text-slate-700">
                                <?= htmlspecialchars($viewVital["height"]) ?>
                            </p>
                            <p class="text-xs text-slate-500">cm</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=vital_signs"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Vital Signs
                    </a>
                </div>

            </div>
        </div>
    </div>
<?php endif; ?>

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