<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();

$message = "";
$messageType = "success";

$userId = $_SESSION["user_id"] ?? 0;
$today = date("Y-m-d");

// Get the patient record for the logged-in user
$patientStmt = $conn->prepare("SELECT id, patient_number, full_name FROM patients WHERE user_id = ? AND status = 'Active' LIMIT 1");
$patientStmt->bind_param("i", $userId);
$patientStmt->execute();
$patient = $patientStmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient record not found. Please complete your registration first.");
}

$patientId = (int) $patient["id"];

/* ---------- View single record ---------- */
$viewRecord = null;
if (isset($_GET["view"])) {
    $recordId = (int) $_GET["view"];
    $stmt = $conn->prepare("
        SELECT
            mr.*,
            a.appointment_number,
            a.appointment_date,
            a.appointment_time,
            d.full_name AS doctor_name,
            d.specialization
        FROM medical_records mr
        INNER JOIN appointments a ON a.id = mr.appointment_id
        INNER JOIN doctors d ON d.id = mr.doctor_id
        WHERE mr.id = ? AND mr.patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $recordId, $patientId);
    $stmt->execute();
    $viewRecord = $stmt->get_result()->fetch_assoc();
}

/* ---------- List all records ---------- */
$stmt = $conn->prepare("
    SELECT
        mr.*,
        a.appointment_number,
        a.appointment_date,
        a.appointment_time,
        d.full_name AS doctor_name,
        d.specialization
    FROM medical_records mr
    INNER JOIN appointments a ON a.id = mr.appointment_id
    INNER JOIN doctors d ON d.id = mr.doctor_id
    WHERE mr.patient_id = ?
    ORDER BY mr.visit_date DESC, mr.id DESC
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$records = $stmt->get_result();
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">My Medical Records</h1>
            <p class="mt-1 text-sm text-slate-500">
                View your consultation history and medical information.
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

    <!-- Records list -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-800">Medical History</h2>
                <p class="text-xs text-slate-500">All your recorded consultations.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
                <i data-lucide="file-heart" class="h-3.5 w-3.5"></i>
                Total: <?php echo $records ? $records->num_rows : 0; ?>
            </span>
        </div>

        <?php if ($records && $records->num_rows > 0): ?>

            <div class="divide-y divide-slate-100">
                <?php while ($record = $records->fetch_assoc()): ?>
                    <div class="p-6 transition hover:bg-slate-50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                            <div class="flex items-start gap-4">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                                    <i data-lucide="file-heart" class="h-5 w-5 text-emerald-600"></i>
                                </div>

                                <div>
                                    <p class="font-semibold text-slate-800">
                                        <?= date("d M Y", strtotime($record["visit_date"])) ?>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        <?= htmlspecialchars($record["appointment_number"]) ?>
                                        · Dr. <?= htmlspecialchars($record["doctor_name"]) ?>
                                        <?php if (!empty($record["specialization"])): ?>
                                            · <?= htmlspecialchars($record["specialization"]) ?>
                                        <?php endif; ?>
                                    </p>

                                    <div class="mt-3 space-y-2 text-sm">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Diagnosis</p>
                                            <p class="text-slate-700 line-clamp-2">
                                                <?= nl2br(htmlspecialchars($record["diagnosis"])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 sm:flex-shrink-0">
                                <?php if (!empty($record["follow_up_date"])): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700">
                                        <i data-lucide="calendar-clock" class="h-3.5 w-3.5"></i>
                                        Follow-up: <?= date("d M Y", strtotime($record["follow_up_date"])) ?>
                                    </span>
                                <?php endif; ?>

                                <a href="index.php?page=my_medical_records&view=<?= (int) $record["id"] ?>"
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
                    <i data-lucide="file-x" class="h-7 w-7 text-slate-400"></i>
                </div>
                <h3 class="mt-4 font-semibold text-slate-700">No Medical Records</h3>
                <p class="mt-1 text-sm text-slate-400">
                    You don't have any medical records yet.
                </p>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- View Modal -->
<?php if ($viewRecord): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">

            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                        <i data-lucide="file-heart" class="h-5 w-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Medical Record</h3>
                        <p class="text-sm text-blue-600"><?= htmlspecialchars($viewRecord["appointment_number"]) ?></p>
                    </div>
                </div>
                <a href="index.php?page=my_medical_records"
                    class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="p-6 space-y-6">

                <!-- Summary -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Visit Date</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= date("d M Y", strtotime($viewRecord["visit_date"])) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Doctor</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            Dr. <?= htmlspecialchars($viewRecord["doctor_name"]) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Specialization</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= htmlspecialchars($viewRecord["specialization"] ?: "-") ?>
                        </p>
                    </div>
                </div>

                <!-- Symptoms -->
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Symptoms</p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <?= nl2br(htmlspecialchars($viewRecord["symptoms"])) ?>
                    </div>
                </div>

                <!-- Diagnosis -->
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Diagnosis</p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <?= nl2br(htmlspecialchars($viewRecord["diagnosis"])) ?>
                    </div>
                </div>

                <!-- Treatment -->
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Treatment</p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <?= nl2br(htmlspecialchars($viewRecord["treatment"])) ?>
                    </div>
                </div>

                <!-- Notes -->
                <?php if (!empty($viewRecord["notes"])): ?>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Clinical Notes</p>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <?= nl2br(htmlspecialchars($viewRecord["notes"])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Follow-up -->
                <?php if (!empty($viewRecord["follow_up_date"])): ?>
                    <div class="flex items-center gap-2 rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-700">
                        <i data-lucide="calendar-clock" class="h-5 w-5"></i>
                        <span>
                            <span class="font-semibold">Follow-up:</span>
                            <?= date("d M Y", strtotime($viewRecord["follow_up_date"])) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=my_medical_records"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Records
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