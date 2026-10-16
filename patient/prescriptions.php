<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();

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

/* =================== VIEW SINGLE PRESCRIPTION =================== */
$viewPrescription = null;
$viewItems = null;

if (isset($_GET["view"])) {
    $prescriptionId = (int) $_GET["view"];

    $stmt = $conn->prepare("
        SELECT rx.id, rx.prescription_number, rx.prescription_date,
               rx.instructions, rx.follow_up_date, rx.created_at,
               a.appointment_number, a.appointment_date, a.appointment_time,
               d.full_name AS doctor_name, d.specialization
        FROM prescriptions rx
        INNER JOIN appointments a ON a.id = rx.appointment_id
        INNER JOIN doctors d ON d.id = rx.doctor_id
        WHERE rx.id = ? AND rx.patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $prescriptionId, $patientId);
    $stmt->execute();
    $viewPrescription = $stmt->get_result()->fetch_assoc();

    if ($viewPrescription) {
        $itemStmt = $conn->prepare("
            SELECT medicine_name, dosage, frequency, duration, quantity, item_instructions
            FROM prescription_items
            WHERE prescription_id = ?
            ORDER BY id ASC
        ");
        $itemStmt->bind_param("i", $prescriptionId);
        $itemStmt->execute();
        $viewItems = $itemStmt->get_result();
    }
}

/* =================== LIST ALL PRESCRIPTIONS =================== */
$stmt = $conn->prepare("
    SELECT rx.id, rx.prescription_number, rx.prescription_date,
           rx.follow_up_date,
           a.appointment_number, a.appointment_date,
           d.full_name AS doctor_name, d.specialization,
           (SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id = rx.id) AS item_count
    FROM prescriptions rx
    INNER JOIN appointments a ON a.id = rx.appointment_id
    INNER JOIN doctors d ON d.id = rx.doctor_id
    WHERE rx.patient_id = ?
    ORDER BY rx.id DESC
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$prescriptions = $stmt->get_result();
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="pill" class="w-7 h-7 text-blue-600"></i>
                My Prescriptions
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                View your prescribed medicines and doctor instructions.
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

    <!-- Prescriptions List -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-800">Prescription History</h2>
                <p class="text-xs text-slate-500">All your prescriptions from doctors.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
                <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                Total: <?= $prescriptions ? $prescriptions->num_rows : 0 ?>
            </span>
        </div>

        <?php if ($prescriptions && $prescriptions->num_rows > 0): ?>
            <div class="divide-y divide-slate-100">
                <?php while ($rx = $prescriptions->fetch_assoc()): ?>
                    <div class="p-6 transition hover:bg-slate-50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                            <div class="flex items-start gap-4">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                                    <i data-lucide="pill" class="h-5 w-5 text-emerald-600"></i>
                                </div>

                                <div>
                                    <p class="font-semibold text-slate-800">
                                        <?= htmlspecialchars($rx["prescription_number"]) ?>
                                    </p>

                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                                            <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                                            <?= date("d M Y", strtotime($rx["prescription_date"])) ?>
                                        </span>

                                        <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                                            <i data-lucide="link" class="h-3.5 w-3.5"></i>
                                            <?= htmlspecialchars($rx["appointment_number"]) ?>
                                        </span>

                                        <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                            <i data-lucide="pill" class="h-3 w-3"></i>
                                            <?= (int) $rx["item_count"] ?> medicine(s)
                                        </span>

                                        <?php if (!empty($rx["follow_up_date"])): ?>
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                                <i data-lucide="calendar-clock" class="h-3 w-3"></i>
                                                Follow-up: <?= date("d M Y", strtotime($rx["follow_up_date"])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <p class="mt-2 text-sm text-slate-600">
                                        Dr. <?= htmlspecialchars($rx["doctor_name"]) ?>
                                        <?php if (!empty($rx["specialization"])): ?>
                                            · <?= htmlspecialchars($rx["specialization"]) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 sm:flex-shrink-0">
                                <a href="index.php?page=prescriptions&view=<?= (int) $rx["id"] ?>"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700">
                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                    View
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                    <i data-lucide="pill-bottle" class="h-7 w-7 text-slate-400"></i>
                </div>
                <h3 class="mt-4 font-semibold text-slate-700">No Prescriptions</h3>
                <p class="mt-1 text-sm text-slate-400">
                    You don't have any prescriptions yet.
                </p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- View Modal -->
<?php if ($viewPrescription): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">

            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                        <i data-lucide="pill" class="h-5 w-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Prescription Details</h3>
                        <p class="text-sm text-blue-600"><?= htmlspecialchars($viewPrescription["prescription_number"]) ?></p>
                    </div>
                </div>
                <a href="index.php?page=prescriptions"
                    class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="p-6 space-y-6">

                <!-- Summary -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Prescription Date</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= date("d M Y", strtotime($viewPrescription["prescription_date"])) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Doctor</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            Dr. <?= htmlspecialchars($viewPrescription["doctor_name"]) ?>
                        </p>
                        <?php if (!empty($viewPrescription["specialization"])): ?>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($viewPrescription["specialization"]) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Appointment</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= htmlspecialchars($viewPrescription["appointment_number"]) ?>
                        </p>
                        <p class="text-xs text-slate-400">
                            <?= date("d M Y", strtotime($viewPrescription["appointment_date"])) ?>
                            · <?= date("h:i A", strtotime($viewPrescription["appointment_time"])) ?>
                        </p>
                    </div>
                </div>

                <!-- Medicines -->
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Prescribed Medicines
                    </p>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[600px] text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr class="border-b border-slate-200">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Medicine</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Dosage</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Frequency</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Duration</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if ($viewItems && $viewItems->num_rows > 0): ?>
                                    <?php while ($item = $viewItems->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-slate-800"><?= htmlspecialchars($item["medicine_name"]) ?></p>
                                                <?php if (!empty($item["item_instructions"])): ?>
                                                    <p class="mt-0.5 text-xs text-slate-400"><?= htmlspecialchars($item["item_instructions"]) ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($item["dosage"] ?: "—") ?></td>
                                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($item["frequency"] ?: "—") ?></td>
                                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($item["duration"] ?: "—") ?></td>
                                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($item["quantity"] ?: "—") ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">
                                            No medicine items found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Instructions -->
                <?php if (!empty($viewPrescription["instructions"])): ?>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Prescription Instructions
                        </p>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <?= nl2br(htmlspecialchars($viewPrescription["instructions"])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Follow-up -->
                <?php if (!empty($viewPrescription["follow_up_date"])): ?>
                    <div class="flex items-center gap-2 rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-700">
                        <i data-lucide="calendar-clock" class="h-5 w-5"></i>
                        <span>
                            <span class="font-semibold">Follow-up:</span>
                            <?= date("d M Y", strtotime($viewPrescription["follow_up_date"])) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=prescriptions"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Prescriptions
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