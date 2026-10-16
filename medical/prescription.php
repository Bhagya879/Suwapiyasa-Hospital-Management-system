<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireMedicalRole();

if (($_SESSION["role"] ?? "") !== "doctor") {
    header("Location: index.php?page=dashboard");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$messageType = "error";
$today = date("Y-m-d");

// Verify the logged-in doctor's database record
$stmt = $conn->prepare("SELECT id, full_name FROM doctors WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    exit("Doctor profile not found. Please contact the administrator.");
}

$doctorId = (int) $doctor["id"];

// Create a prescription and its medicine items
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save") {
    $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
    $instructions = trim($_POST["instructions"] ?? "");
    $followUpDate = trim($_POST["follow_up_date"] ?? "");
    $medicines = $_POST["medicine_name"] ?? [];
    $dosages = $_POST["dosage"] ?? [];
    $frequencies = $_POST["frequency"] ?? [];
    $durations = $_POST["duration"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $itemInstructions = $_POST["item_instructions"] ?? [];

    $appointmentStmt = $conn->prepare("
        SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date,
               a.appointment_number,
               p.patient_number,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               d.full_name AS doctor_name
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        INNER JOIN doctors d ON d.id = a.doctor_id
        WHERE a.id = ? AND a.doctor_id = ?
          AND a.status <> 'Cancelled'
        LIMIT 1
    ");
    $appointmentStmt->bind_param("ii", $appointmentId, $doctorId);
    $appointmentStmt->execute();
    $appointment = $appointmentStmt->get_result()->fetch_assoc();

    $validItems = [];
    if (is_array($medicines) && is_array($dosages) && is_array($frequencies)) {
        foreach ($medicines as $i => $medicine) {
            $medicine = trim((string) $medicine);
            $dosage = trim((string) ($dosages[$i] ?? ""));
            $frequency = trim((string) ($frequencies[$i] ?? ""));
            $duration = trim((string) ($durations[$i] ?? ""));
            $quantity = trim((string) ($quantities[$i] ?? ""));
            $itemNote = trim((string) ($itemInstructions[$i] ?? ""));

            if ($medicine === "" && $dosage === "" && $frequency === "") {
                continue;
            }

            if ($medicine === "" || $dosage === "" || $frequency === "") {
                $message = "Complete medicine name, dosage and frequency for every medicine.";
                break;
            }

            $validItems[] = [
                "medicine" => $medicine,
                "dosage" => $dosage,
                "frequency" => $frequency,
                "duration" => $duration,
                "quantity" => $quantity,
                "notes" => $itemNote
            ];
        }
    }

    if (!$appointment) {
        $message = "Invalid appointment or you can only prescribe for your own patients.";
    } elseif (!$validItems) {
        $message = $message ?: "Add at least one complete medicine.";
    } elseif ($followUpDate !== "" && $followUpDate < $today) {
        $message = "Follow-up date cannot be in the past.";
    } else {
        $conn->begin_transaction();

        try {
            // Prevent duplicate prescriptions for the same appointment
            $existsStmt = $conn->prepare("
                SELECT id FROM prescriptions WHERE appointment_id = ? LIMIT 1
            ");
            $existsStmt->bind_param("i", $appointmentId);
            $existsStmt->execute();

            if ($existsStmt->get_result()->fetch_assoc()) {
                throw new Exception("A prescription already exists for this appointment.");
            }

            $prescriptionNumber = "RX-" . date("YmdHis") . "-" . random_int(100, 999);
            $prescriptionDate = $today;
            $followUpValue = $followUpDate !== "" ? $followUpDate : null;

            $insertStmt = $conn->prepare("
                INSERT INTO prescriptions (
                    prescription_number, patient_id, doctor_id, appointment_id,
                    prescription_date, instructions, follow_up_date, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->bind_param(
                "siiisssi",
                $prescriptionNumber,
                $appointment["patient_id"],
                $doctorId,
                $appointmentId,
                $prescriptionDate,
                $instructions,
                $followUpValue,
                $userId
            );
            $insertStmt->execute();
            $prescriptionId = (int) $conn->insert_id;

            $itemStmt = $conn->prepare("
                INSERT INTO prescription_items (
                    prescription_id, medicine_name, dosage, frequency,
                    duration, quantity, item_instructions
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($validItems as $item) {
                $itemStmt->bind_param(
                    "issssss",
                    $prescriptionId,
                    $item["medicine"],
                    $item["dosage"],
                    $item["frequency"],
                    $item["duration"],
                    $item["quantity"],
                    $item["notes"]
                );
                $itemStmt->execute();
            }

            $conn->commit();
            header("Location: prescription_print.php?id=" . $prescriptionId . "&created=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage() ?: "Unable to save the prescription.";
        }
    }
}

// Appointments belonging to this doctor without a prescription
$appointmentsStmt = $conn->prepare("
    SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
           p.patient_number,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name
    FROM appointments a
    INNER JOIN patients p ON p.id = a.patient_id
    LEFT JOIN prescriptions rx ON rx.appointment_id = a.id
    WHERE a.doctor_id = ?
      AND a.status <> 'Cancelled'
      AND rx.id IS NULL
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointmentsStmt->bind_param("i", $doctorId);
$appointmentsStmt->execute();
$appointments = $appointmentsStmt->get_result();

// Recent prescriptions created by this doctor
$listStmt = $conn->prepare("
    SELECT rx.id, rx.prescription_number, rx.prescription_date,
           rx.follow_up_date, a.appointment_number,
           p.patient_number,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name
    FROM prescriptions rx
    INNER JOIN appointments a ON a.id = rx.appointment_id
    INNER JOIN patients p ON p.id = rx.patient_id
    WHERE rx.doctor_id = ?
    ORDER BY rx.id DESC
    LIMIT 50
");
$listStmt->bind_param("i", $doctorId);
$listStmt->execute();
$prescriptions = $listStmt->get_result();
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Digital Prescription</h1>
            <p class="mt-1 text-sm text-slate-500">Create prescriptions and print patient medication instructions.</p>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
            <span class="text-sm font-medium text-slate-600"><?= date("d M Y") ?></span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
                <i data-lucide="file-plus-2" class="h-5 w-5 text-blue-600"></i>
            </div>
            <div>
                <h2 class="font-semibold text-slate-800">Create Prescription</h2>
                <p class="text-xs text-slate-500">Select an appointment and add one or more medicines.</p>
            </div>
        </div>

        <form method="POST" class="space-y-6 p-6" id="prescriptionForm">
            <input type="hidden" name="action" value="save">

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Patient Appointment *</label>
                    <select name="appointment_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">Select appointment / patient</option>
                        <?php while ($appointment = $appointments->fetch_assoc()): ?>
                            <option value="<?= (int) $appointment["id"] ?>">
                                <?= htmlspecialchars($appointment["appointment_number"]) ?> -
                                <?= htmlspecialchars($appointment["patient_name"]) ?>
                                (<?= htmlspecialchars($appointment["patient_number"]) ?>) -
                                <?= date("d M Y", strtotime($appointment["appointment_date"])) ?>
                                <?= date("h:i A", strtotime($appointment["appointment_time"])) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Doctor</label>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <?= htmlspecialchars($doctor["full_name"]) ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-slate-800">Prescription Medicines</h3>
                    <button type="button" id="addMedicine" class="inline-flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                        <i data-lucide="plus" class="h-4 w-4"></i> Add Medicine
                    </button>
                </div>

                <div id="medicineList" class="space-y-4">
                    <div class="medicine-row rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-700">Medicine 1</span>
                            <button type="button" class="remove-medicine hidden rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Remove medicine">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-600">Medicine Name *</label>
                                <input name="medicine_name[]" required maxlength="150" placeholder="e.g. Paracetamol" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-600">Dosage *</label>
                                <input name="dosage[]" required maxlength="100" placeholder="e.g. 500 mg" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-600">Frequency *</label>
                                <input name="frequency[]" required maxlength="100" placeholder="e.g. 3 times/day" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-600">Duration</label>
                                <input name="duration[]" maxlength="100" placeholder="e.g. 5 days" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-600">Quantity</label>
                                <input name="quantity[]" maxlength="50" placeholder="e.g. 15 tablets" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-600">Medicine Instructions</label>
                                <input name="item_instructions[]" maxlength="255" placeholder="e.g. After meals" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Prescription Instructions</label>
                    <textarea name="instructions" rows="4" placeholder="General instructions for the patient..." class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Follow-up Date</label>
                    <input type="date" name="follow_up_date" min="<?= $today ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <p class="mt-2 text-xs text-slate-400">Leave empty if no follow-up is required.</p>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-100 pt-5">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                    <i data-lucide="save" class="h-4 w-4"></i> Save & Print Prescription
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="font-semibold text-slate-800">Recent Prescriptions</h2>
            <p class="mt-1 text-xs text-slate-500">Your latest 50 prescriptions.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[750px] text-left">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold uppercase text-slate-500">Prescription</th>
                        <th class="px-6 py-4 text-xs font-semibold uppercase text-slate-500">Patient</th>
                        <th class="px-6 py-4 text-xs font-semibold uppercase text-slate-500">Date</th>
                        <th class="px-6 py-4 text-xs font-semibold uppercase text-slate-500">Follow-up</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($prescriptions->num_rows): ?>
                        <?php while ($rx = $prescriptions->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-semibold text-slate-800"><?= htmlspecialchars($rx["prescription_number"]) ?></td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($rx["patient_name"]) ?></p>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($rx["patient_number"]) ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600"><?= date("d M Y", strtotime($rx["prescription_date"])) ?></td>
                                <td class="px-6 py-4 text-sm text-slate-600"><?= $rx["follow_up_date"] ? date("d M Y", strtotime($rx["follow_up_date"])) : "—" ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="prescription_print.php?id=<?= (int) $rx["id"] ?>" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                        <i data-lucide="printer" class="h-4 w-4"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">No prescriptions created yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function() {
        const list = document.getElementById("medicineList");
        const addButton = document.getElementById("addMedicine");

        function refreshRows() {
            const rows = list.querySelectorAll(".medicine-row");
            rows.forEach((row, index) => {
                row.querySelector("span").textContent = "Medicine " + (index + 1);
                row.querySelector(".remove-medicine").classList.toggle("hidden", rows.length === 1);
            });
            if (typeof lucide !== "undefined") lucide.createIcons();
        }

        addButton.addEventListener("click", function() {
            const row = list.querySelector(".medicine-row").cloneNode(true);
            row.querySelectorAll("input").forEach(input => input.value = "");
            row.querySelector(".remove-medicine").classList.remove("hidden");
            list.appendChild(row);
            refreshRows();
        });

        list.addEventListener("click", function(event) {
            const button = event.target.closest(".remove-medicine");
            if (button && list.querySelectorAll(".medicine-row").length > 1) {
                button.closest(".medicine-row").remove();
                refreshRows();
            }
        });

        refreshRows();
    })();
</script>