<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();
$today = date("Y-m-d");
$dateFrom = $_GET["date_from"] ?? date("Y-m-01");
$dateTo = $_GET["date_to"] ?? $today;
$reportGenerated = isset($_GET["generate_report"]);
if (!$reportGenerated) {
    $dateFrom = date("Y-m-01");
    $dateTo = $today;
}
$message = "";
$messageType = "success";
if ($dateFrom > $dateTo) {
    $message = "The From Date cannot be later than the To Date.";
    $messageType = "error";
    $dateFrom = $dateTo;
}
$reportTitle = "Medical Center Report";
$appointmentTotal = 0;
$pendingCount = 0;
$confirmedCount = 0;
$completedCount = 0;
$cancelledCount = 0;
$queueTotal = 0;
$waitingCount = 0;
$servingCount = 0;
$skippedCount = 0;
$queueCompletedCount = 0;
$patientCount = 0;
$doctorCount = 0;
$appointmentRows = [];
$doctorRows = [];
/* Appointment summary */
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending_count,
        SUM(status = 'Confirmed') AS confirmed_count,
        SUM(status = 'Completed') AS completed_count,
        SUM(status = 'Cancelled') AS cancelled_count
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$appointmentTotal = (int)($summary["total"] ?? 0);
$pendingCount = (int)($summary["pending_count"] ?? 0);
$confirmedCount = (int)($summary["confirmed_count"] ?? 0);
$completedCount = (int)($summary["completed_count"] ?? 0);
$cancelledCount = (int)($summary["cancelled_count"] ?? 0);
/* Queue summary */
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Waiting') AS waiting_count,
        SUM(status = 'Serving') AS serving_count,
        SUM(status = 'Skipped') AS skipped_count,
        SUM(status = 'Completed') AS completed_count
    FROM queue
    WHERE queue_date BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$queueSummary = $stmt->get_result()->fetch_assoc();
$queueTotal = (int)($queueSummary["total"] ?? 0);
$waitingCount = (int)($queueSummary["waiting_count"] ?? 0);
$servingCount = (int)($queueSummary["serving_count"] ?? 0);
$skippedCount = (int)($queueSummary["skipped_count"] ?? 0);
$queueCompletedCount = (int)($queueSummary["completed_count"] ?? 0);
/* Patient count */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM patients
    WHERE registration_date BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$patientCount = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
/* Doctor count */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM doctors
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$doctorCount = (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
/* Appointment details */
$stmt = $conn->prepare("
    SELECT
        a.appointment_number,
        a.appointment_date,
        a.appointment_time,
        a.reason,
        a.status,
        p.patient_number,
        p.full_name AS patient_name,
        d.doctor_number,
        d.full_name AS doctor_name,
        d.specialization
    FROM appointments a
    INNER JOIN patients p ON a.patient_id = p.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    WHERE a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointmentRows[] = $row;
}
/* Doctor-wise report */
$stmt = $conn->prepare("
    SELECT
        d.doctor_number,
        d.full_name AS doctor_name,
        d.specialization,
        COUNT(a.id) AS total_appointments,
        SUM(a.status = 'Pending') AS pending_appointments,
        SUM(a.status = 'Confirmed') AS confirmed_appointments,
        SUM(a.status = 'Completed') AS completed_appointments,
        SUM(a.status = 'Cancelled') AS cancelled_appointments
    FROM doctors d
    LEFT JOIN appointments a
        ON d.id = a.doctor_id
        AND a.appointment_date BETWEEN ? AND ?
    GROUP BY d.id, d.doctor_number, d.full_name, d.specialization
    ORDER BY total_appointments DESC, d.full_name ASC
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $doctorRows[] = $row;
}
/* Patient report */
$patientRows = [];
$stmt = $conn->prepare("
    SELECT
        patient_number,
        full_name,
        nic,
        date_of_birth,
        gender,
        blood_group,
        marital_status,
        phone,
        email,
        city,
        registration_date,
        status
    FROM patients
    WHERE registration_date BETWEEN ? AND ?
    ORDER BY registration_date DESC
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $patientRows[] = $row;
}
/* Queue report */
$queueRows = [];
$stmt = $conn->prepare("
    SELECT
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
        a.appointment_time
    FROM queue q
    INNER JOIN patients p ON q.patient_id = p.id
    INNER JOIN doctors d ON q.doctor_id = d.id
    INNER JOIN appointments a ON q.appointment_id = a.id
    WHERE q.queue_date BETWEEN ? AND ?
    ORDER BY q.queue_date DESC, q.id ASC
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $queueRows[] = $row;
}
/* Doctor report */
$doctorReportRows = [];
$stmt = $conn->prepare("
    SELECT
        d.doctor_number,
        d.full_name,
        d.registration_number,
        d.specialization,
        d.qualification,
        d.gender,
        d.phone,
        d.email,
        d.department,
        d.years_of_experience,
        d.consultation_fee,
        d.joining_date,
        d.status
    FROM doctors d
    WHERE DATE(d.created_at) BETWEEN ? AND ?
    ORDER BY d.full_name ASC
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $doctorReportRows[] = $row;
}
function reportStatusClass($status)
{
    switch ($status) {
        case "Pending":
            return "bg-amber-100 text-amber-700";
        case "Confirmed":
            return "bg-blue-100 text-blue-700";
        case "Completed":
            return "bg-emerald-100 text-emerald-700";
        case "Cancelled":
            return "bg-red-100 text-red-700";
        case "Waiting":
            return "bg-amber-100 text-amber-700";
        case "Serving":
            return "bg-blue-100 text-blue-700";
        case "Skipped":
            return "bg-red-100 text-red-700";
        default:
            return "bg-slate-100 text-slate-700";
    }
}
?>
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="bar-chart-3" class="w-7 h-7 text-blue-600"></i>
                Reports
            </h1>
            <p class="text-sm text-slate-500 mt-1">Generate medical center reports by selecting a date range.</p>
        </div>
        <button type="button" onclick="downloadExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
            <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
            Download Excel
        </button>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <input type="hidden" name="page" value="reports">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" required class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" required class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" name="generate_report" value="1" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-medium flex items-center justify-center gap-2">
                <i data-lucide="filter" class="w-5 h-5"></i>
                Generate Report
            </button>
        </form>
    </div>
    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex items-center gap-3">
        <i data-lucide="calendar-range" class="w-5 h-5 text-blue-600"></i>
        <div>
            <p class="text-sm text-blue-700 font-medium">Report Period</p>
            <p class="text-sm text-blue-600"><?= date("d M Y", strtotime($dateFrom)) ?> - <?= date("d M Y", strtotime($dateTo)) ?></p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Appointments</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1"><?= $appointmentTotal ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-xl">
                    <i data-lucide="calendar-check" class="w-6 h-6 text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">New Patients</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1"><?= $patientCount ?></p>
                </div>
                <div class="bg-emerald-100 p-3 rounded-xl">
                    <i data-lucide="users" class="w-6 h-6 text-emerald-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Queue Records</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1"><?= $queueTotal ?></p>
                </div>
                <div class="bg-indigo-100 p-3 rounded-xl">
                    <i data-lucide="list-ordered" class="w-6 h-6 text-indigo-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Doctors</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1"><?= $doctorCount ?></p>
                </div>
                <div class="bg-purple-100 p-3 rounded-xl">
                    <i data-lucide="stethoscope" class="w-6 h-6 text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center gap-2 mb-5">
                <i data-lucide="calendar-days" class="w-5 h-5 text-blue-600"></i>
                <h2 class="text-lg font-bold text-slate-800">Appointment Summary</h2>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-amber-50 rounded-xl p-4">
                    <p class="text-sm text-amber-700">Pending</p>
                    <p class="text-2xl font-bold text-amber-700 mt-1"><?= $pendingCount ?></p>
                </div>
                <div class="bg-blue-50 rounded-xl p-4">
                    <p class="text-sm text-blue-700">Confirmed</p>
                    <p class="text-2xl font-bold text-blue-700 mt-1"><?= $confirmedCount ?></p>
                </div>
                <div class="bg-emerald-50 rounded-xl p-4">
                    <p class="text-sm text-emerald-700">Completed</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1"><?= $completedCount ?></p>
                </div>
                <div class="bg-red-50 rounded-xl p-4">
                    <p class="text-sm text-red-700">Cancelled</p>
                    <p class="text-2xl font-bold text-red-700 mt-1"><?= $cancelledCount ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center gap-2 mb-5">
                <i data-lucide="list-checks" class="w-5 h-5 text-indigo-600"></i>
                <h2 class="text-lg font-bold text-slate-800">Queue Summary</h2>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-amber-50 rounded-xl p-4">
                    <p class="text-sm text-amber-700">Waiting</p>
                    <p class="text-2xl font-bold text-amber-700 mt-1"><?= $waitingCount ?></p>
                </div>
                <div class="bg-blue-50 rounded-xl p-4">
                    <p class="text-sm text-blue-700">Serving</p>
                    <p class="text-2xl font-bold text-blue-700 mt-1"><?= $servingCount ?></p>
                </div>
                <div class="bg-red-50 rounded-xl p-4">
                    <p class="text-sm text-red-700">Skipped</p>
                    <p class="text-2xl font-bold text-red-700 mt-1"><?= $skippedCount ?></p>
                </div>
                <div class="bg-emerald-50 rounded-xl p-4">
                    <p class="text-sm text-emerald-700">Completed</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1"><?= $queueCompletedCount ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i data-lucide="stethoscope" class="w-5 h-5 text-purple-600"></i>
                <h2 class="text-lg font-bold text-slate-800">Doctor-wise Appointment Report</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Doctor</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Specialization</th>
                        <th class="text-center px-6 py-4 font-semibold text-slate-600">Total</th>
                        <th class="text-center px-6 py-4 font-semibold text-slate-600">Pending</th>
                        <th class="text-center px-6 py-4 font-semibold text-slate-600">Confirmed</th>
                        <th class="text-center px-6 py-4 font-semibold text-slate-600">Completed</th>
                        <th class="text-center px-6 py-4 font-semibold text-slate-600">Cancelled</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($doctorRows) > 0): ?>
                        <?php foreach ($doctorRows as $row): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($row["doctor_name"]) ?></div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($row["doctor_number"]) ?></div>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($row["specialization"]) ?></td>
                                <td class="px-6 py-4 text-center font-semibold"><?= (int)$row["total_appointments"] ?></td>
                                <td class="px-6 py-4 text-center text-amber-600"><?= (int)$row["pending_appointments"] ?></td>
                                <td class="px-6 py-4 text-center text-blue-600"><?= (int)$row["confirmed_appointments"] ?></td>
                                <td class="px-6 py-4 text-center text-emerald-600"><?= (int)$row["completed_appointments"] ?></td>
                                <td class="px-6 py-4 text-center text-red-600"><?= (int)$row["cancelled_appointments"] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-slate-500">No doctor data found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="reportTableSection" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>
                        <h2 class="text-lg font-bold text-slate-800">Appointment Report</h2>
                    </div>
                    <p class="text-sm text-slate-500 mt-1"><?= count($appointmentRows) ?> appointment record(s) found.</p>
                </div>
                <button type="button" onclick="downloadExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Excel
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table id="appointmentReportTable" class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Appointment No</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Date</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Time</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Patient</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Doctor</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Specialization</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Reason</th>
                        <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($appointmentRows) > 0): ?>
                        <?php foreach ($appointmentRows as $row): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-medium text-blue-600"><?= htmlspecialchars($row["appointment_number"]) ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= date("d M Y", strtotime($row["appointment_date"])) ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= date("h:i A", strtotime($row["appointment_time"])) ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($row["patient_name"]) ?></div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($row["patient_number"]) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($row["doctor_name"]) ?></div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($row["doctor_number"]) ?></div>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($row["specialization"]) ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($row["reason"]) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= reportStatusClass($row["status"]) ?>">
                                        <?= htmlspecialchars($row["status"]) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <i data-lucide="file-x" class="w-10 h-10 mx-auto text-slate-300"></i>
                                <p class="mt-3 text-slate-500">No appointment records found for this date range.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
    lucide.createIcons();

    function downloadExcel() {
        const workbook = XLSX.utils.book_new();
        const summaryData = [
            ["SUWAPIYASA MEDICAL CENTER - REPORT"],
            ["Report Period", "<?= date("d M Y", strtotime($dateFrom)) ?> - <?= date("d M Y", strtotime($dateTo)) ?>"],
            [],
            ["APPOINTMENT SUMMARY"],
            ["Total Appointments", <?= $appointmentTotal ?>],
            ["Pending", <?= $pendingCount ?>],
            ["Confirmed", <?= $confirmedCount ?>],
            ["Completed", <?= $completedCount ?>],
            ["Cancelled", <?= $cancelledCount ?>],
            [],
            ["QUEUE SUMMARY"],
            ["Total Queue Records", <?= $queueTotal ?>],
            ["Waiting", <?= $waitingCount ?>],
            ["Serving", <?= $servingCount ?>],
            ["Skipped", <?= $skippedCount ?>],
            ["Completed", <?= $queueCompletedCount ?>],
            [],
            ["OTHER SUMMARY"],
            ["New Patients", <?= $patientCount ?>],
            ["Doctors", <?= $doctorCount ?>]
        ];
        const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
        XLSX.utils.book_append_sheet(workbook, summarySheet, "Summary");
        const appointmentData = [
            ["Appointment No", "Date", "Time", "Patient No", "Patient", "Doctor No", "Doctor", "Specialization", "Reason", "Status"]
            <?php foreach ($appointmentRows as $row): ?>,
                [
                    "<?= htmlspecialchars($row["appointment_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["appointment_date"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["appointment_time"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["patient_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["patient_name"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["doctor_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["doctor_name"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["specialization"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["reason"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["status"], ENT_QUOTES) ?>"
                ]
            <?php endforeach; ?>
        ];
        const appointmentSheet = XLSX.utils.aoa_to_sheet(appointmentData);
        XLSX.utils.book_append_sheet(workbook, appointmentSheet, "Appointments");
        const patientData = [
            ["Patient No", "Full Name", "NIC", "Date of Birth", "Gender", "Blood Group", "Marital Status", "Phone", "Email", "City", "Registration Date", "Status"]
            <?php foreach ($patientRows as $row): ?>,
                [
                    "<?= htmlspecialchars($row["patient_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["full_name"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["nic"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["date_of_birth"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["gender"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["blood_group"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["marital_status"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["phone"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["email"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["city"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["registration_date"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["status"], ENT_QUOTES) ?>"
                ]
            <?php endforeach; ?>
        ];
        const patientSheet = XLSX.utils.aoa_to_sheet(patientData);
        XLSX.utils.book_append_sheet(workbook, patientSheet, "Patients");
        const queueData = [
            ["Token", "Queue Date", "Appointment No", "Appointment Time", "Patient No", "Patient", "Doctor No", "Doctor", "Check-in Time", "Called Time", "Completed Time", "Status"]
            <?php foreach ($queueRows as $row): ?>,
                [
                    "<?= htmlspecialchars($row["queue_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["queue_date"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["appointment_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["appointment_time"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["patient_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["patient_name"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["doctor_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["doctor_name"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["check_in_time"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["called_time"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["completed_time"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["status"], ENT_QUOTES) ?>"
                ]
            <?php endforeach; ?>
        ];
        const queueSheet = XLSX.utils.aoa_to_sheet(queueData);
        XLSX.utils.book_append_sheet(workbook, queueSheet, "Queue");
        const doctorData = [
            ["Doctor No", "Full Name", "Registration No", "Specialization", "Qualification", "Gender", "Phone", "Email", "Department", "Experience Years", "Consultation Fee", "Joining Date", "Status"]
            <?php foreach ($doctorReportRows as $row): ?>,
                [
                    "<?= htmlspecialchars($row["doctor_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["full_name"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["registration_number"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["specialization"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["qualification"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["gender"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["phone"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["email"] ?? "", ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["department"] ?? "", ENT_QUOTES) ?>",
                    <?= (int)$row["years_of_experience"] ?>,
                    <?= (float)$row["consultation_fee"] ?>,
                    "<?= htmlspecialchars($row["joining_date"], ENT_QUOTES) ?>",
                    "<?= htmlspecialchars($row["status"], ENT_QUOTES) ?>"
                ]
            <?php endforeach; ?>
        ];
        const doctorSheet = XLSX.utils.aoa_to_sheet(doctorData);
        XLSX.utils.book_append_sheet(workbook, doctorSheet, "Doctors");
        const fileName = "Suwapiyasa_Report_<?= date("Y-m-d", strtotime($dateFrom)) ?>_to_<?= date("Y-m-d", strtotime($dateTo)) ?>.xlsx";
        XLSX.writeFile(workbook, fileName);
        toastr.success("Complete Excel report downloaded successfully.");
    }
    <?php if ($message): ?>
        toastr.<?= $messageType ?>("<?= htmlspecialchars($message, ENT_QUOTES) ?>");
    <?php endif; ?>
</script>