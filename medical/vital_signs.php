<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireMedicalRole();

if ($_SESSION["role"] !== "nurse") {
    header("Location: index.php?page=dashboard");
    exit;
}

$today = date("Y-m-d");
$message = "";
$messageType = "success";
$editVital = null;

// Save vital signs
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_vitals"])) {
    $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
    $bloodPressure = trim($_POST["blood_pressure"] ?? "");
    $temperature = (float) ($_POST["temperature"] ?? 0);
    $pulseRate = (int) ($_POST["pulse_rate"] ?? 0);
    $weight = (float) ($_POST["weight"] ?? 0);
    $height = (float) ($_POST["height"] ?? 0);
    $oxygenLevel = (float) ($_POST["oxygen_level"] ?? 0);

    if (
        $appointmentId <= 0 ||
        $bloodPressure === "" ||
        $temperature <= 0 ||
        $pulseRate <= 0 ||
        $weight <= 0 ||
        $height <= 0 ||
        $oxygenLevel <= 0
    ) {
        $message = "Please enter all vital sign details.";
        $messageType = "error";
    } elseif (!preg_match("/^\d{2,3}\/\d{2,3}$/", $bloodPressure)) {
        $message = "Enter blood pressure in format such as 120/80.";
        $messageType = "error";
    } elseif ($oxygenLevel > 100) {
        $message = "Oxygen level cannot be greater than 100%.";
        $messageType = "error";
    } else {
        $appointmentStmt = $conn->prepare("
            SELECT
                a.id,
                a.patient_id,
                a.doctor_id,
                a.appointment_date,
                a.status,
                p.patient_number,
                p.full_name AS patient_name,
                d.doctor_number,
                d.full_name AS doctor_name
            FROM appointments a
            INNER JOIN patients p ON a.patient_id = p.id
            INNER JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?
            AND a.appointment_date = ?
            AND a.status IN ('Pending', 'Confirmed')
            LIMIT 1
        ");

        $appointmentStmt->bind_param("is", $appointmentId, $today);
        $appointmentStmt->execute();
        $appointment = $appointmentStmt->get_result()->fetch_assoc();

        if (!$appointment) {
            $message = "Invalid or unavailable appointment.";
            $messageType = "error";
        } else {
            $checkStmt = $conn->prepare("
                SELECT id
                FROM vital_signs
                WHERE appointment_id = ?
                LIMIT 1
            ");
            $checkStmt->bind_param("i", $appointmentId);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();

            if ($existing) {
                $updateStmt = $conn->prepare("
                    UPDATE vital_signs
                    SET
                        blood_pressure = ?,
                        temperature = ?,
                        pulse_rate = ?,
                        weight = ?,
                        height = ?,
                        oxygen_level = ?,
                        recorded_by = ?,
                        updated_at = NOW()
                    WHERE appointment_id = ?
                ");

                $updateStmt->bind_param(
                    "sdidddii",
                    $bloodPressure,
                    $temperature,
                    $pulseRate,
                    $weight,
                    $height,
                    $oxygenLevel,
                    $_SESSION["user_id"],
                    $appointmentId
                );

                if ($updateStmt->execute()) {
                    $message = "Vital signs updated successfully.";
                    $messageType = "success";
                } else {
                    $message = "Unable to update vital signs.";
                    $messageType = "error";
                }
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO vital_signs
                    (
                        appointment_id,
                        patient_id,
                        doctor_id,
                        blood_pressure,
                        temperature,
                        pulse_rate,
                        weight,
                        height,
                        oxygen_level,
                        recorded_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $insertStmt->bind_param(
                    "iiisdidddi",
                    $appointment["id"],
                    $appointment["patient_id"],
                    $appointment["doctor_id"],
                    $bloodPressure,
                    $temperature,
                    $pulseRate,
                    $weight,
                    $height,
                    $oxygenLevel,
                    $_SESSION["user_id"]
                );

                if ($insertStmt->execute()) {
                    $message = "Vital signs recorded successfully.";
                    $messageType = "success";
                } else {
                    $message = "Unable to save vital signs.";
                    $messageType = "error";
                }
            }
        }
    }
}

// Load edit record
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    $editStmt = $conn->prepare("
        SELECT
            v.*,
            p.patient_number,
            p.full_name AS patient_name,
            d.doctor_number,
            d.full_name AS doctor_name,
            a.appointment_number,
            a.appointment_time
        FROM vital_signs v
        INNER JOIN patients p ON v.patient_id = p.id
        INNER JOIN doctors d ON v.doctor_id = d.id
        INNER JOIN appointments a ON v.appointment_id = a.id
        WHERE v.id = ?
        LIMIT 1
    ");

    $editStmt->bind_param("i", $editId);
    $editStmt->execute();
    $editVital = $editStmt->get_result()->fetch_assoc();
}

// Today's appointments
$appointmentStmt = $conn->prepare("
    SELECT
        a.id,
        a.appointment_number,
        a.appointment_time,
        p.patient_number,
        p.full_name AS patient_name,
        d.doctor_number,
        d.full_name AS doctor_name,
        v.id AS vital_id
    FROM appointments a
    INNER JOIN patients p ON a.patient_id = p.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN vital_signs v ON a.id = v.appointment_id
    WHERE a.appointment_date = ?
    AND a.status IN ('Pending', 'Confirmed')
    ORDER BY a.appointment_time ASC
");

$appointmentStmt->bind_param("s", $today);
$appointmentStmt->execute();
$appointmentResult = $appointmentStmt->get_result();

// Search
$search = trim($_GET["search"] ?? "");
$searchLike = "%" . $search . "%";

$listStmt = $conn->prepare("
    SELECT
        v.id,
        v.blood_pressure,
        v.temperature,
        v.pulse_rate,
        v.weight,
        v.height,
        v.oxygen_level,
        v.recorded_at,
        p.patient_number,
        p.full_name AS patient_name,
        d.doctor_number,
        d.full_name AS doctor_name,
        a.appointment_number,
        a.appointment_time
    FROM vital_signs v
    INNER JOIN patients p ON v.patient_id = p.id
    INNER JOIN doctors d ON v.doctor_id = d.id
    INNER JOIN appointments a ON v.appointment_id = a.id
    WHERE a.appointment_date = ?
    AND (
        p.patient_number LIKE ?
        OR p.full_name LIKE ?
        OR d.full_name LIKE ?
        OR a.appointment_number LIKE ?
    )
    ORDER BY v.recorded_at DESC
");

$listStmt->bind_param(
    "sssss",
    $today,
    $searchLike,
    $searchLike,
    $searchLike,
    $searchLike
);

$listStmt->execute();
$vitalResult = $listStmt->get_result();

// Today's count
$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM vital_signs v
    INNER JOIN appointments a ON v.appointment_id = a.id
    WHERE a.appointment_date = ?
");

$countStmt->bind_param("s", $today);
$countStmt->execute();
$totalVitals = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="activity" class="w-7 h-7 text-blue-600"></i>
                Vital Signs
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Record and manage today's patient vital signs.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="bg-blue-50 text-blue-700 px-4 py-2 rounded-xl font-medium">
                <?= date("d M Y") ?>
            </div>
            <div class="bg-emerald-50 text-emerald-700 px-4 py-2 rounded-xl font-medium">
                <?= $totalVitals ?> Recorded
            </div>
        </div>
    </div>

    <!-- Record Vital Signs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <div class="flex items-center gap-2 mb-5">
            <i data-lucide="heart-pulse" class="w-5 h-5 text-blue-600"></i>
            <h2 class="text-lg font-bold text-slate-800">
                <?= $editVital ? "Update Vital Signs" : "Record Vital Signs" ?>
            </h2>
        </div>

        <?php if ($editVital): ?>
            <div class="mb-5 bg-blue-50 border border-blue-100 rounded-xl p-4">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <div>
                        <span class="text-slate-500">Patient:</span>
                        <span class="font-semibold text-slate-800">
                            <?= htmlspecialchars($editVital["patient_name"]) ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-500">Patient No:</span>
                        <span class="font-semibold text-slate-800">
                            <?= htmlspecialchars($editVital["patient_number"]) ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-500">Doctor:</span>
                        <span class="font-semibold text-slate-800">
                            <?= htmlspecialchars($editVital["doctor_name"]) ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">

            <?php if (!$editVital): ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Today's Appointment
                    </label>

                    <select
                        name="appointment_id"
                        required
                        class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">

                        <option value="">
                            Select Patient / Appointment
                        </option>

                        <?php while ($appointment = $appointmentResult->fetch_assoc()): ?>
                            <option
                                value="<?= $appointment["id"] ?>"
                                <?= $appointment["vital_id"] ? "disabled" : "" ?>>

                                <?= htmlspecialchars($appointment["appointment_number"]) ?>
                                -
                                <?= htmlspecialchars($appointment["patient_name"]) ?>
                                -
                                <?= htmlspecialchars($appointment["patient_number"]) ?>
                                -
                                <?= htmlspecialchars($appointment["doctor_name"]) ?>
                                (<?= date("h:i A", strtotime($appointment["appointment_time"])) ?>)
                                <?= $appointment["vital_id"] ? " - Recorded" : "" ?>

                            </option>
                        <?php endwhile; ?>

                    </select>

                    <p class="text-xs text-slate-400 mt-2">
                        Select today's appointment to record the patient's vital signs.
                    </p>
                </div>
            <?php else: ?>
                <input
                    type="hidden"
                    name="appointment_id"
                    value="<?= $editVital["appointment_id"] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                <!-- Blood Pressure -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Blood Pressure
                    </label>

                    <div class="relative">
                        <i
                            data-lucide="heart-pulse"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400">
                        </i>

                        <input
                            type="text"
                            name="blood_pressure"
                            required
                            placeholder="120/80"
                            value="<?= htmlspecialchars($editVital["blood_pressure"] ?? "") ?>"
                            class="w-full pl-10 rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <p class="text-xs text-slate-400 mt-1">mmHg</p>
                </div>

                <!-- Temperature -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Temperature
                    </label>

                    <input
                        type="number"
                        name="temperature"
                        step="0.1"
                        min="25"
                        max="45"
                        required
                        placeholder="36.8"
                        value="<?= htmlspecialchars($editVital["temperature"] ?? "") ?>"
                        class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">

                    <p class="text-xs text-slate-400 mt-1">°C</p>
                </div>

                <!-- Pulse -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Pulse Rate
                    </label>

                    <input
                        type="number"
                        name="pulse_rate"
                        min="1"
                        max="250"
                        required
                        placeholder="72"
                        value="<?= htmlspecialchars($editVital["pulse_rate"] ?? "") ?>"
                        class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">

                    <p class="text-xs text-slate-400 mt-1">BPM</p>
                </div>

                <!-- Weight -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Weight
                    </label>

                    <input
                        type="number"
                        name="weight"
                        step="0.01"
                        min="0.1"
                        max="500"
                        required
                        placeholder="65.50"
                        value="<?= htmlspecialchars($editVital["weight"] ?? "") ?>"
                        class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">

                    <p class="text-xs text-slate-400 mt-1">kg</p>
                </div>

                <!-- Height -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Height
                    </label>

                    <input
                        type="number"
                        name="height"
                        step="0.01"
                        min="20"
                        max="250"
                        required
                        placeholder="170"
                        value="<?= htmlspecialchars($editVital["height"] ?? "") ?>"
                        class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">

                    <p class="text-xs text-slate-400 mt-1">cm</p>
                </div>

                <!-- Oxygen -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Oxygen Level
                    </label>

                    <input
                        type="number"
                        name="oxygen_level"
                        step="0.1"
                        min="1"
                        max="100"
                        required
                        placeholder="98"
                        value="<?= htmlspecialchars($editVital["oxygen_level"] ?? "") ?>"
                        class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">

                    <p class="text-xs text-slate-400 mt-1">SpO₂ %</p>
                </div>

            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">

                <?php if ($editVital): ?>
                    <a
                        href="index.php?page=vital_signs"
                        class="px-5 py-3 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium text-center">
                        Cancel
                    </a>
                <?php endif; ?>

                <button
                    type="submit"
                    name="save_vitals"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium flex items-center justify-center gap-2">

                    <i
                        data-lucide="<?= $editVital ? "save" : "plus" ?>"
                        class="w-5 h-5">
                    </i>

                    <?= $editVital ? "Update Vital Signs" : "Save Vital Signs" ?>

                </button>

            </div>

        </form>
    </div>

    <!-- Today's Records -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <div class="p-6 border-b border-slate-100">

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Today's Vital Signs
                    </h2>

                    <p class="text-sm text-slate-500">
                        Patient vital sign records
                    </p>
                </div>

                <form method="GET" class="flex gap-2">

                    <input
                        type="hidden"
                        name="page"
                        value="vital_signs">

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search patient, doctor..."
                        class="rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">

                    <button
                        type="submit"
                        class="bg-slate-800 hover:bg-slate-900 text-white px-4 rounded-xl">

                        <i data-lucide="search" class="w-4 h-4"></i>

                    </button>

                </form>

            </div>

        </div>

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            Patient
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            Doctor
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            BP
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            Temp
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            Pulse
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            Weight
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            Height
                        </th>

                        <th class="text-left px-6 py-4 font-semibold text-slate-600">
                            SpO₂
                        </th>

                        <th class="text-right px-6 py-4 font-semibold text-slate-600">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    <?php if ($vitalResult->num_rows > 0): ?>

                        <?php while ($row = $vitalResult->fetch_assoc()): ?>

                            <tr class="hover:bg-slate-50">

                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-800">
                                        <?= htmlspecialchars($row["patient_name"]) ?>
                                    </div>

                                    <div class="text-xs text-slate-500">
                                        <?= htmlspecialchars($row["patient_number"]) ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-700">
                                        <?= htmlspecialchars($row["doctor_name"]) ?>
                                    </div>

                                    <div class="text-xs text-slate-500">
                                        <?= htmlspecialchars($row["doctor_number"]) ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="font-semibold text-red-600">
                                        <?= htmlspecialchars($row["blood_pressure"]) ?>
                                    </span>
                                    <span class="text-xs text-slate-400">mmHg</span>
                                </td>

                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($row["temperature"]) ?> °C
                                </td>

                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($row["pulse_rate"]) ?> BPM
                                </td>

                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($row["weight"]) ?> kg
                                </td>

                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($row["height"]) ?> cm
                                </td>

                                <td class="px-6 py-4">
                                    <span class="font-semibold text-blue-600">
                                        <?= htmlspecialchars($row["oxygen_level"]) ?>%
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex justify-end">

                                        <a
                                            href="index.php?page=vital_signs&edit=<?= $row["id"] ?>"
                                            title="Edit"
                                            class="p-2 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200">

                                            <i
                                                data-lucide="pencil"
                                                class="w-4 h-4">
                                            </i>

                                        </a>

                                    </div>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">

                                <i
                                    data-lucide="activity"
                                    class="w-10 h-10 mx-auto text-slate-300">
                                </i>

                                <p class="mt-3 text-slate-500">
                                    No vital signs recorded today.
                                </p>

                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
    lucide.createIcons();

    <?php if ($message): ?>
        toastr.<?= $messageType ?>(
            <?= json_encode($message) ?>
        );
    <?php endif; ?>
</script>