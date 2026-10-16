<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireMedicalRole();

$message = "";
$messageType = "success";
$role = $_SESSION["role"] ?? "";
$userId = (int) ($_SESSION["user_id"] ?? 0);
$editRecord = null;
$selectedPatientId = (int) ($_GET["patient_id"] ?? 0);
$today = date("Y-m-d");

// Get doctor's own doctor ID
$doctorId = 0;

if ($role === "doctor") {
    $doctorStmt = $conn->prepare("
        SELECT id
        FROM doctors
        WHERE user_id = ?
        LIMIT 1
    ");
    $doctorStmt->bind_param("i", $userId);
    $doctorStmt->execute();
    $doctorData = $doctorStmt->get_result()->fetch_assoc();
    $doctorId = (int) ($doctorData["id"] ?? 0);
}

// Save medical record
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
        $symptoms = trim($_POST["symptoms"] ?? "");
        $diagnosis = trim($_POST["diagnosis"] ?? "");
        $treatment = trim($_POST["treatment"] ?? "");
        $notes = trim($_POST["notes"] ?? "");
        $followUpDate = trim($_POST["follow_up_date"] ?? "");

        if ($appointmentId <= 0 || $symptoms === "" || $diagnosis === "" || $treatment === "") {
            $message = "Please complete all required consultation fields.";
            $messageType = "error";
        } else {
            // Get appointment and verify medical staff access
            if ($role === "doctor") {
                $appointmentStmt = $conn->prepare("
                    SELECT
                        a.id,
                        a.appointment_number,
                        a.patient_id,
                        a.doctor_id,
                        a.appointment_date,
                        a.appointment_time,
                        p.patient_number,
                        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                        d.full_name AS doctor_name
                    FROM appointments a
                    INNER JOIN patients p ON p.id = a.patient_id
                    INNER JOIN doctors d ON d.id = a.doctor_id
                    WHERE a.id = ?
                    AND a.doctor_id = ?
                    LIMIT 1
                ");
                $appointmentStmt->bind_param("ii", $appointmentId, $doctorId);
            } else {
                $appointmentStmt = $conn->prepare("
                    SELECT
                        a.id,
                        a.appointment_number,
                        a.patient_id,
                        a.doctor_id,
                        a.appointment_date,
                        a.appointment_time,
                        p.patient_number,
                         CONCAT(p.full_name, ' ', p.full_name) AS patient_name,
                        d.full_name AS doctor_name
                    FROM appointments a
                    INNER JOIN patients p ON p.id = a.patient_id
                    INNER JOIN doctors d ON d.id = a.doctor_id
                    WHERE a.id = ?
                    LIMIT 1
                ");
                $appointmentStmt->bind_param("i", $appointmentId);
            }

            $appointmentStmt->execute();
            $appointment = $appointmentStmt->get_result()->fetch_assoc();

            if (!$appointment) {
                $message = "Invalid appointment or you do not have access to it.";
                $messageType = "error";
            } else {
                // Check whether a record already exists
                $recordStmt = $conn->prepare("
                    SELECT id
                    FROM medical_records
                    WHERE appointment_id = ?
                    LIMIT 1
                ");
                $recordStmt->bind_param("i", $appointmentId);
                $recordStmt->execute();
                $existingRecord = $recordStmt->get_result()->fetch_assoc();

                if ($existingRecord) {
                    $recordId = (int) $existingRecord["id"];

                    $updateStmt = $conn->prepare("
                        UPDATE medical_records
                        SET
                            symptoms = ?,
                            diagnosis = ?,
                            treatment = ?,
                            notes = ?,
                            follow_up_date = ?
                        WHERE id = ?
                    ");

                    $updateStmt->bind_param(
                        "sssssi",
                        $symptoms,
                        $diagnosis,
                        $treatment,
                        $notes,
                        $followUpDate,
                        $recordId
                    );

                    if ($updateStmt->execute()) {
                        $message = "Medical record updated successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Failed to update medical record.";
                        $messageType = "error";
                    }
                } else {
                    $insertStmt = $conn->prepare("
                        INSERT INTO medical_records (
                            patient_id,
                            doctor_id,
                            appointment_id,
                            visit_date,
                            symptoms,
                            diagnosis,
                            treatment,
                            notes,
                            follow_up_date,
                            created_by
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)
                    ");

                    $visitDate = $appointment["appointment_date"];

                    $insertStmt->bind_param(
                        "iiissssssi",
                        $appointment["patient_id"],
                        $appointment["doctor_id"],
                        $appointment["id"],
                        $visitDate,
                        $symptoms,
                        $diagnosis,
                        $treatment,
                        $notes,
                        $followUpDate,
                        $userId
                    );

                    if ($insertStmt->execute()) {
                        $message = "Medical record saved successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Failed to save medical record.";
                        $messageType = "error";
                    }
                }
            }
        }
    }
}

// Edit record
if (isset($_GET["edit"])) {
    $recordId = (int) $_GET["edit"];

    if ($role === "doctor") {
        $editStmt = $conn->prepare("
            SELECT
                mr.*,
                a.appointment_number,
                a.appointment_date,
                a.appointment_time,
                p.patient_number,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                d.full_name AS doctor_name
            FROM medical_records mr
            INNER JOIN appointments a ON a.id = mr.appointment_id
            INNER JOIN patients p ON p.id = mr.patient_id
            INNER JOIN doctors d ON d.id = mr.doctor_id
            WHERE mr.id = ?
            AND mr.doctor_id = ?
            LIMIT 1
        ");
        $editStmt->bind_param("ii", $recordId, $doctorId);
    } else {
        $editStmt = $conn->prepare("
            SELECT
                mr.*,
                a.appointment_number,
                a.appointment_date,
                a.appointment_time,
                p.patient_number,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                d.full_name AS doctor_name
            FROM medical_records mr
            INNER JOIN appointments a ON a.id = mr.appointment_id
            INNER JOIN patients p ON p.id = mr.patient_id
            INNER JOIN doctors d ON d.id = mr.doctor_id
            WHERE mr.id = ?
            LIMIT 1
        ");
        $editStmt->bind_param("i", $recordId);
    }

    $editStmt->execute();
    $editRecord = $editStmt->get_result()->fetch_assoc();
}

// Today's appointments available for consultation
if ($role === "doctor") {
    $appointmentStmt = $conn->prepare("
        SELECT
            a.id,
            a.appointment_number,
            a.appointment_date,
            a.appointment_time,
            a.patient_id,
            CONCAT(p.full_name, ' ', p.full_name) AS patient_name,
            p.patient_number,
            d.full_name AS doctor_name,
            mr.id AS record_id
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        INNER JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN medical_records mr ON mr.appointment_id = a.id
        WHERE a.doctor_id = ?
        AND a.appointment_date = ?
        AND a.status IN ('Pending', 'Confirmed')
        ORDER BY a.appointment_time ASC
    ");
    $appointmentStmt->bind_param("is", $doctorId, $today);
} else {
    $appointmentStmt = $conn->prepare("
        SELECT
            a.id,
            a.appointment_number,
            a.appointment_date,
            a.appointment_time,
            a.patient_id,
             CONCAT(p.full_name, ' ', p.full_name) AS patient_name,
            p.patient_number,
            d.full_name AS doctor_name,
            mr.id AS record_id
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        INNER JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN medical_records mr ON mr.appointment_id = a.id
        WHERE a.appointment_date = ?
        AND a.status IN ('Pending', 'Confirmed')
        ORDER BY a.appointment_time ASC
    ");
    $appointmentStmt->bind_param("s", $today);
}

$appointmentStmt->execute();
$appointmentResult = $appointmentStmt->get_result();

// Patient history
$historyResult = null;

if ($selectedPatientId > 0) {
    if ($role === "doctor") {
        $historyStmt = $conn->prepare("
            SELECT
                mr.*,
                a.appointment_number,
                a.appointment_date,
                a.appointment_time,
                d.full_name AS doctor_name
            FROM medical_records mr
            INNER JOIN appointments a ON a.id = mr.appointment_id
            INNER JOIN doctors d ON d.id = mr.doctor_id
            WHERE mr.patient_id = ?
            AND mr.doctor_id = ?
            ORDER BY mr.visit_date DESC, mr.id DESC
        ");
        $historyStmt->bind_param("ii", $selectedPatientId, $doctorId);
    } else {
        $historyStmt = $conn->prepare("
            SELECT
                mr.*,
                a.appointment_number,
                a.appointment_date,
                a.appointment_time,
                d.full_name AS doctor_name
            FROM medical_records mr
            INNER JOIN appointments a ON a.id = mr.appointment_id
            INNER JOIN doctors d ON d.id = mr.doctor_id
            WHERE mr.patient_id = ?
            ORDER BY mr.visit_date DESC, mr.id DESC
        ");
        $historyStmt->bind_param("i", $selectedPatientId);
    }

    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
}

// All patients with medical history
if ($role === "doctor") {
    $patientStmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.patient_number,
            C CONCAT(p.full_name, ' ', p.full_name) AS patient_name
        FROM patients p
        INNER JOIN medical_records mr ON mr.patient_id = p.id
        WHERE mr.doctor_id = ?
        ORDER BY patient_name ASC
    ");
    $patientStmt->bind_param("i", $doctorId);
} else {
    $patientStmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.patient_number,
             CONCAT(p.full_name, ' ', p.full_name) AS patient_name
        FROM patients p
        INNER JOIN medical_records mr ON mr.patient_id = p.id
        ORDER BY patient_name ASC
    ");
    $patientStmt->execute();
}

if ($role === "doctor") {
    $patientStmt->execute();
}

$patientResult = $patientStmt->get_result();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Medical Records</h1>
            <p class="mt-1 text-sm text-slate-500">
                Manage patient consultations and medical history.
            </p>
        </div>

        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
            <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
            <span class="text-sm font-medium text-slate-600">
                <?= date("d M Y") ?>
            </span>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                toastr.<?= $messageType === "success" ? "success" : "error" ?>(
                    <?= json_encode($message) ?>
                );
            });
        </script>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Consultation Form -->
        <div class="xl:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
                            <i data-lucide="stethoscope" class="h-5 w-5 text-blue-600"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-slate-800">
                                <?= $editRecord ? "Edit Medical Record" : "Current Consultation" ?>
                            </h2>
                            <p class="text-xs text-slate-500">
                                Select an appointment to load patient details.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="space-y-5 p-6">
                    <input type="hidden" name="action" value="save">

                    <!-- Appointment -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Patient Appointment <span class="text-red-500">*</span>
                        </label>

                        <?php if ($editRecord): ?>

                            <input
                                type="hidden"
                                name="appointment_id"
                                value="<?= (int) $editRecord["appointment_id"] ?>">

                            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="user-round" class="h-5 w-5 text-blue-600"></i>
                                    <div>
                                        <p class="font-semibold text-slate-800">
                                            <?= htmlspecialchars($editRecord["patient_name"]) ?>
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            <?= htmlspecialchars($editRecord["patient_number"]) ?>
                                            ·
                                            <?= htmlspecialchars($editRecord["appointment_number"]) ?>
                                            ·
                                            <?= htmlspecialchars($editRecord["doctor_name"]) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>

                            <select
                                name="appointment_id"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                <option value="">Select patient appointment</option>

                                <?php while ($appointment = $appointmentResult->fetch_assoc()): ?>
                                    <option
                                        value="<?= (int) $appointment["id"] ?>"
                                        <?= $appointment["record_id"] ? "disabled" : "" ?>>
                                        <?= htmlspecialchars($appointment["appointment_number"]) ?>
                                        -
                                        <?= htmlspecialchars($appointment["patient_name"]) ?>
                                        (<?= htmlspecialchars($appointment["patient_number"]) ?>)
                                        ·
                                        <?= date("h:i A", strtotime($appointment["appointment_time"])) ?>
                                        ·
                                        Dr. <?= htmlspecialchars($appointment["doctor_name"]) ?>
                                        <?= $appointment["record_id"] ? " - Already Recorded" : "" ?>
                                    </option>
                                <?php endwhile; ?>

                            </select>

                        <?php endif; ?>
                    </div>

                    <?php if ($editRecord): ?>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs text-slate-500">Patient</p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    <?= htmlspecialchars($editRecord["patient_name"]) ?>
                                </p>
                            </div>

                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs text-slate-500">Visit Date</p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    <?= date("d M Y", strtotime($editRecord["visit_date"])) ?>
                                </p>
                            </div>

                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs text-slate-500">Doctor</p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    <?= htmlspecialchars($editRecord["doctor_name"]) ?>
                                </p>
                            </div>

                        </div>
                    <?php endif; ?>

                    <!-- Symptoms -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Symptoms <span class="text-red-500">*</span>
                        </label>

                        <textarea
                            name="symptoms"
                            rows="3"
                            required
                            placeholder="Enter patient's symptoms..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><?= htmlspecialchars($editRecord["symptoms"] ?? "") ?></textarea>
                    </div>

                    <!-- Diagnosis -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Diagnosis <span class="text-red-500">*</span>
                        </label>

                        <textarea
                            name="diagnosis"
                            rows="3"
                            required
                            placeholder="Enter diagnosis..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><?= htmlspecialchars($editRecord["diagnosis"] ?? "") ?></textarea>
                    </div>

                    <!-- Treatment -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Treatment <span class="text-red-500">*</span>
                        </label>

                        <textarea
                            name="treatment"
                            rows="3"
                            required
                            placeholder="Enter treatment or treatment plan..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><?= htmlspecialchars($editRecord["treatment"] ?? "") ?></textarea>
                    </div>

                    <!-- Notes + Follow-up -->
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Clinical Notes
                            </label>

                            <textarea
                                name="notes"
                                rows="4"
                                placeholder="Additional clinical notes..."
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><?= htmlspecialchars($editRecord["notes"] ?? "") ?></textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Follow-up Date
                            </label>

                            <input
                                type="date"
                                name="follow_up_date"
                                value="<?= htmlspecialchars($editRecord["follow_up_date"] ?? "") ?>"
                                min="<?= $today ?>"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">

                            <p class="mt-2 text-xs text-slate-400">
                                Leave empty if no follow-up is required.
                            </p>
                        </div>

                    </div>

                    <!-- Buttons -->
                    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">

                        <?php if ($editRecord): ?>
                            <a
                                href="index.php?page=medical_records"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                <i data-lucide="x" class="h-4 w-4"></i>
                                Cancel
                            </a>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                            <i data-lucide="<?= $editRecord ? "save" : "file-plus-2" ?>" class="h-4 w-4"></i>
                            <?= $editRecord ? "Update Record" : "Save Medical Record" ?>
                        </button>

                    </div>
                </form>
            </div>
        </div>

        <!-- Patient History -->
        <div class="xl:col-span-1">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-5 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                            <i data-lucide="history" class="h-5 w-5 text-emerald-600"></i>
                        </div>

                        <div>
                            <h2 class="font-semibold text-slate-800">
                                Previous Medical Records
                            </h2>
                            <p class="text-xs text-slate-500">
                                Patient medical history
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5">

                    <!-- Patient selector -->
                    <form method="GET" class="mb-5">
                        <input type="hidden" name="page" value="medical_records">

                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Select Patient
                        </label>

                        <select
                            name="patient_id"
                            onchange="this.form.submit()"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Select patient history</option>

                            <?php while ($patient = $patientResult->fetch_assoc()): ?>
                                <option
                                    value="<?= (int) $patient["id"] ?>"
                                    <?= $selectedPatientId === (int) $patient["id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($patient["patient_number"]) ?>
                                    -
                                    <?= htmlspecialchars($patient["patient_name"]) ?>
                                </option>
                            <?php endwhile; ?>

                        </select>
                    </form>

                    <?php if ($selectedPatientId > 0 && $historyResult): ?>

                        <?php if ($historyResult->num_rows > 0): ?>

                            <div class="max-h-[650px] space-y-4 overflow-y-auto pr-1 scrollbar-thin-transparent">

                                <?php while ($history = $historyResult->fetch_assoc()): ?>

                                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">

                                        <div class="mb-3 flex items-start justify-between gap-3">

                                            <div>
                                                <p class="font-semibold text-slate-800">
                                                    <?= date("d M Y", strtotime($history["visit_date"])) ?>
                                                </p>

                                                <p class="mt-1 text-xs text-slate-500">
                                                    <?= htmlspecialchars($history["appointment_number"]) ?>
                                                    ·
                                                    Dr. <?= htmlspecialchars($history["doctor_name"]) ?>
                                                </p>
                                            </div>

                                            <a
                                                href="index.php?page=medical_records&edit=<?= (int) $history["id"] ?>"
                                                class="rounded-lg p-2 text-slate-400 transition hover:bg-white hover:text-blue-600"
                                                title="Edit record">
                                                <i data-lucide="pencil" class="h-4 w-4"></i>
                                            </a>

                                        </div>

                                        <div class="space-y-3 text-sm">

                                            <div>
                                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                    Symptoms
                                                </p>
                                                <p class="text-slate-700">
                                                    <?= nl2br(htmlspecialchars($history["symptoms"])) ?>
                                                </p>
                                            </div>

                                            <div>
                                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                    Diagnosis
                                                </p>
                                                <p class="text-slate-700">
                                                    <?= nl2br(htmlspecialchars($history["diagnosis"])) ?>
                                                </p>
                                            </div>

                                            <div>
                                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                    Treatment
                                                </p>
                                                <p class="text-slate-700">
                                                    <?= nl2br(htmlspecialchars($history["treatment"])) ?>
                                                </p>
                                            </div>

                                            <?php if (!empty($history["notes"])): ?>
                                                <div>
                                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                        Clinical Notes
                                                    </p>
                                                    <p class="text-slate-700">
                                                        <?= nl2br(htmlspecialchars($history["notes"])) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($history["follow_up_date"])): ?>
                                                <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                                    <i data-lucide="calendar-clock" class="h-4 w-4"></i>
                                                    Follow-up:
                                                    <?= date("d M Y", strtotime($history["follow_up_date"])) ?>
                                                </div>
                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endwhile; ?>

                            </div>

                        <?php else: ?>

                            <div class="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center">

                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                    <i data-lucide="file-clock" class="h-6 w-6 text-slate-400"></i>
                                </div>

                                <p class="mt-3 text-sm font-medium text-slate-700">
                                    No Previous Records
                                </p>

                                <p class="mt-1 text-xs text-slate-400">
                                    No medical records found for this patient.
                                </p>

                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <div class="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center">

                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50">
                                <i data-lucide="user-search" class="h-6 w-6 text-blue-500"></i>
                            </div>

                            <p class="mt-3 text-sm font-medium text-slate-700">
                                Select a Patient
                            </p>

                            <p class="mt-1 text-xs text-slate-400">
                                Select a patient to view previous medical records.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

    <!-- Today's Records -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h2 class="font-semibold text-slate-800">
                    Today's Consultation Appointments
                </h2>
                <p class="text-xs text-slate-500">
                    Appointments available for medical records.
                </p>
            </div>

            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
                <i data-lucide="calendar-check" class="h-3.5 w-3.5"></i>
                <?= date("d M Y") ?>
            </span>

        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left">

                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Appointment
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Patient
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Doctor
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Time
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    <?php
                    $appointmentStmt->execute();
                    $tableAppointments = $appointmentStmt->get_result();
                    ?>

                    <?php if ($tableAppointments->num_rows > 0): ?>

                        <?php while ($appointment = $tableAppointments->fetch_assoc()): ?>

                            <tr class="transition hover:bg-slate-50">

                                <td class="px-6 py-4">
                                    <span class="font-medium text-slate-800">
                                        <?= htmlspecialchars($appointment["appointment_number"]) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-slate-800">
                                            <?= htmlspecialchars($appointment["patient_name"]) ?>
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            <?= htmlspecialchars($appointment["patient_number"]) ?>
                                        </p>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-600">
                                    Dr. <?= htmlspecialchars($appointment["doctor_name"]) ?>
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <?= date("h:i A", strtotime($appointment["appointment_time"])) ?>
                                </td>

                                <td class="px-6 py-4 text-right">

                                    <?php if ($appointment["record_id"]): ?>

                                        <a
                                            href="index.php?page=medical_records&edit=<?= (int) $appointment["record_id"] ?>"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                            Edit Record
                                        </a>

                                    <?php else: ?>

                                        <a
                                            href="index.php?page=medical_records"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700">
                                            <i data-lucide="file-plus-2" class="h-3.5 w-3.5"></i>
                                            Add Record
                                        </a>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">

                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                    <i data-lucide="calendar-x" class="h-6 w-6 text-slate-400"></i>
                                </div>

                                <p class="mt-3 text-sm font-medium text-slate-700">
                                    No appointments available
                                </p>

                                <p class="mt-1 text-xs text-slate-400">
                                    There are no pending or confirmed appointments for today.
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
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
</script>