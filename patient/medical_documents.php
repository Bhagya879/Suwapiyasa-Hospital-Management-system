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

/* =================== UPLOAD =================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "upload") {
    $documentName = trim($_POST["document_name"] ?? "");
    $docType      = trim($_POST["document_type"] ?? "");
    $appointmentId = (int) ($_POST["appointment_id"] ?? 0);

    $allowedTypes = [
        "Blood Test Report",
        "X-Ray",
        "Scan",
        "Lab Report",
        "Prescription",
        "Discharge Summary",
        "Other"
    ];

    if ($documentName === "" || $docType === "") {
        $message = "Please provide a document name and document type.";
        $messageType = "error";
    } elseif (!in_array($docType, $allowedTypes, true)) {
        $message = "Invalid document type.";
        $messageType = "error";
    } elseif ($appointmentId <= 0) {
        $message = "Please select the related appointment.";
        $messageType = "error";
    } elseif (!isset($_FILES["document"]) || $_FILES["document"]["error"] !== UPLOAD_ERR_OK) {
        $message = "Please select a file to upload.";
        $messageType = "error";
    } else {
        // Verify appointment belongs to this patient
        $apptCheck = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND patient_id = ? LIMIT 1");
        $apptCheck->bind_param("ii", $appointmentId, $patientId);
        $apptCheck->execute();
        if ($apptCheck->get_result()->num_rows === 0) {
            $message = "Selected appointment is not valid for your account.";
            $messageType = "error";
        } else {
            $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $fileName = $_FILES["document"]["name"];
            $fileTmp  = $_FILES["document"]["tmp_name"];
            $fileSize = (int) $_FILES["document"]["size"];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExtensions, true)) {
                $message = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.";
                $messageType = "error";
            } elseif ($fileSize > $maxSize) {
                $message = "File is too large. Maximum size is 5MB.";
                $messageType = "error";
            } else {
                $uploadDir = __DIR__ . "/../uploads/documents/";
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                $safeName = "patient_{$patientId}_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $destination = $uploadDir . $safeName;

                if (move_uploaded_file($fileTmp, $destination)) {
                    $relativePath = "uploads/documents/" . $safeName;

                    $stmt = $conn->prepare("
                        INSERT INTO medical_documents
                        (patient_id, appointment_id, uploaded_by, document_name, file_path, document_type, uploaded_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param(
                        "iiisss",
                        $patientId,
                        $appointmentId,
                        $userId,
                        $documentName,
                        $relativePath,
                        $docType
                    );

                    if ($stmt->execute()) {
                        $message = "Document uploaded successfully.";
                        $messageType = "success";
                    } else {
                        @unlink($destination);
                        $message = "Failed to save document record.";
                        $messageType = "error";
                    }
                } else {
                    $message = "Failed to upload file. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* =================== VIEW =================== */
$viewDocument = null;
if (isset($_GET["view"])) {
    $docId = (int) $_GET["view"];
    $stmt = $conn->prepare("
        SELECT md.id, md.document_name, md.document_type, md.file_path, md.uploaded_at,
               a.appointment_number, a.appointment_date,
               d.full_name AS doctor_name
        FROM medical_documents md
        INNER JOIN appointments a ON a.id = md.appointment_id
        INNER JOIN doctors d ON d.id = a.doctor_id
        WHERE md.id = ? AND md.patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $docId, $patientId);
    $stmt->execute();
    $viewDocument = $stmt->get_result()->fetch_assoc();
}

/* =================== LIST =================== */
$stmt = $conn->prepare("
    SELECT md.id, md.document_name, md.document_type, md.file_path, md.uploaded_at,
           a.appointment_number, a.appointment_date,
           d.full_name AS doctor_name
    FROM medical_documents md
    INNER JOIN appointments a ON a.id = md.appointment_id
    INNER JOIN doctors d ON d.id = a.doctor_id
    WHERE md.patient_id = ?
    ORDER BY md.uploaded_at DESC, md.id DESC
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$documents = $stmt->get_result();

/* =================== APPOINTMENTS FOR UPLOAD =================== */
$apptStmt = $conn->prepare("
    SELECT a.id, a.appointment_number, a.appointment_date,
           d.full_name AS doctor_name
    FROM appointments a
    INNER JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.id DESC
");
$apptStmt->bind_param("i", $patientId);
$apptStmt->execute();
$appointments = $apptStmt->get_result();

function getDocTypeClass($type)
{
    switch ($type) {
        case "Blood Test Report":
        case "Lab Report":
            return "bg-blue-50 text-blue-700";
        case "Prescription":
            return "bg-emerald-50 text-emerald-700";
        case "Scan":
        case "X-Ray":
            return "bg-purple-50 text-purple-700";
        case "Discharge Summary":
            return "bg-amber-50 text-amber-700";
        default:
            return "bg-slate-100 text-slate-700";
    }
}
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="folder-heart" class="w-7 h-7 text-blue-600"></i>
                Medical Documents
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Upload and view your medical documents securely.
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Upload Form -->
        <div class="xl:col-span-1">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
                            <i data-lucide="upload-cloud" class="h-5 w-5 text-blue-600"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-slate-800">Upload Document</h2>
                            <p class="text-xs text-slate-500">Add a new medical document.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5 p-6">
                    <input type="hidden" name="action" value="upload">

                    <!-- Document Name -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Document Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="document_name" required
                            placeholder="e.g. Blood Test Report - Jan 2026"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    </div>

                    <!-- Document Type -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Document Type <span class="text-red-500">*</span>
                        </label>
                        <select name="document_type" required
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Select type</option>
                            <?php foreach (["Blood Test Report", "X-Ray", "Scan", "Lab Report", "Prescription", "Discharge Summary", "Other"] as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Related Appointment -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Related Appointment <span class="text-red-500">*</span>
                        </label>
                        <select name="appointment_id" required
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Select appointment</option>
                            <?php while ($appt = $appointments->fetch_assoc()): ?>
                                <option value="<?= (int) $appt["id"] ?>">
                                    <?= htmlspecialchars($appt["appointment_number"]) ?>
                                    · <?= date("d M Y", strtotime($appt["appointment_date"])) ?>
                                    · Dr. <?= htmlspecialchars($appt["doctor_name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($appointments->num_rows === 0): ?>
                            <p class="mt-2 text-xs text-amber-600">
                                You have no appointments yet. Book an appointment first.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- File -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            File <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="document" required
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-blue-700 file:font-medium">
                        <p class="mt-2 text-xs text-slate-400">
                            Allowed: PDF, JPG, PNG, DOC, DOCX. Max 5MB.
                        </p>
                    </div>

                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                        <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                        Upload Document
                    </button>
                </form>
            </div>
        </div>

        <!-- Documents List -->
        <div class="xl:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

                <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-800">My Documents</h2>
                        <p class="text-xs text-slate-500">All your uploaded medical documents.</p>
                    </div>
                    <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
                        <i data-lucide="folder-open" class="h-3.5 w-3.5"></i>
                        Total: <?= $documents ? $documents->num_rows : 0 ?>
                    </span>
                </div>

                <?php if ($documents && $documents->num_rows > 0): ?>
                    <div class="divide-y divide-slate-100">
                        <?php while ($doc = $documents->fetch_assoc()): ?>
                            <div class="p-6 transition hover:bg-slate-50">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                                    <div class="flex items-start gap-4">
                                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                                            <i data-lucide="file-text" class="h-5 w-5 text-emerald-600"></i>
                                        </div>

                                        <div>
                                            <p class="font-semibold text-slate-800 truncate max-w-[150px]"
                                                title="<?= htmlspecialchars((string)($doc['document_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string)($doc['document_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-semibold <?= getDocTypeClass($doc["document_type"]) ?>">
                                                    <?= htmlspecialchars($doc["document_type"]) ?>
                                                </span>

                                                <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                                    <i data-lucide="link" class="h-3.5 w-3.5"></i>
                                                    <?= htmlspecialchars($doc["appointment_number"]) ?>
                                                </span>

                                                <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                                    <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                                                    <?= date("d M Y", strtotime($doc["appointment_date"])) ?>
                                                </span>

                                                <span class="text-xs text-slate-400">
                                                    Dr. <?= htmlspecialchars($doc["doctor_name"]) ?>
                                                </span>
                                            </div>

                                            <p class="mt-1 text-xs text-slate-400">
                                                Uploaded <?= date("d M Y, h:i A", strtotime($doc["uploaded_at"])) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 sm:flex-shrink-0">
                                        <a href="index.php?page=medical_documents&view=<?= (int) $doc["id"] ?>"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-700">
                                            <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                            View
                                        </a>

                                        <a href="../<?= htmlspecialchars($doc["file_path"]) ?>" download
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                            <i data-lucide="download" class="h-3.5 w-3.5"></i>
                                            Download
                                        </a>
                                    </div>

                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                            <i data-lucide="folder-x" class="h-7 w-7 text-slate-400"></i>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-700">No Documents</h3>
                        <p class="mt-1 text-sm text-slate-400">
                            You haven't uploaded any medical documents yet.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<?php if ($viewDocument): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">

            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                        <i data-lucide="file-text" class="h-5 w-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Document Details</h3>
                        <p class="text-sm text-blue-600"><?= htmlspecialchars($viewDocument["document_name"]) ?></p>
                    </div>
                </div>
                <a href="index.php?page=medical_documents"
                    class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>

            <div class="p-6 space-y-5">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Type</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= htmlspecialchars($viewDocument["document_type"]) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Appointment</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= htmlspecialchars($viewDocument["appointment_number"]) ?>
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Uploaded</p>
                        <p class="mt-1 font-semibold text-slate-800">
                            <?= date("d M Y, h:i A", strtotime($viewDocument["uploaded_at"])) ?>
                        </p>
                    </div>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Doctor / Visit Date</p>
                    <p class="mt-1 font-semibold text-slate-800">
                        Dr. <?= htmlspecialchars($viewDocument["doctor_name"]) ?>
                        · <?= date("d M Y", strtotime($viewDocument["appointment_date"])) ?>
                    </p>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">File Preview</p>

                    <?php
                    $ext = strtolower(pathinfo($viewDocument["file_path"], PATHINFO_EXTENSION));
                    $fileUrl = "../" . htmlspecialchars($viewDocument["file_path"]);
                    ?>

                    <?php if (in_array($ext, ["jpg", "jpeg", "png"], true)): ?>
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <img src="<?= $fileUrl ?>" alt="<?= htmlspecialchars($viewDocument["document_name"]) ?>" class="w-full max-h-[500px] object-contain bg-slate-50">
                        </div>
                    <?php elseif ($ext === "pdf"): ?>
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <iframe src="<?= $fileUrl ?>" class="w-full h-[500px]" title="PDF Preview"></iframe>
                        </div>
                    <?php else: ?>
                        <div class="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                <i data-lucide="file" class="h-6 w-6 text-slate-400"></i>
                            </div>
                            <p class="mt-3 text-sm font-medium text-slate-700">
                                Preview not available for this file type.
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <a href="<?= $fileUrl ?>" target="_blank"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                            <i data-lucide="external-link" class="h-4 w-4"></i>
                            Open in New Tab
                        </a>
                        <a href="<?= $fileUrl ?>" download
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Download
                        </a>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=medical_documents"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Documents
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