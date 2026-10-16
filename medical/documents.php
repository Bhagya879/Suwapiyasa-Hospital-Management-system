<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireMedicalRole(); // allows doctor + nurse

$userId   = (int) $_SESSION["user_id"];
$userRole = $_SESSION["role"] ?? "";
$isDoctor = ($userRole === "doctor");
$isNurse  = ($userRole === "nurse");

$message = "";
$messageType = "error";
$uploadDir = dirname(__DIR__) . "/uploads/medical/";
$relativeDir = "uploads/medical/";

$doctorId = null;

if ($isDoctor) {
    $stmt = $conn->prepare("SELECT id, full_name FROM doctors WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        exit("Doctor profile not found.");
    }
    $doctorId = (int) $doctor["id"];
}

/* ============ UPLOAD ============ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "upload") {
    $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
    $documentType = trim($_POST["document_type"] ?? "");
    $file = $_FILES["medical_file"] ?? null;
    $allowedTypes = [
        "Blood Test Report",
        "X-Ray",
        "Scan",
        "Lab Report",
        "Other Medical Document"
    ];

    if ($isDoctor) {
        $appointmentStmt = $conn->prepare("
            SELECT id, patient_id
            FROM appointments
            WHERE id = ? AND doctor_id = ? AND status <> 'Cancelled'
            LIMIT 1
        ");
        $appointmentStmt->bind_param("ii", $appointmentId, $doctorId);
    } else {
        $appointmentStmt = $conn->prepare("
            SELECT id, patient_id
            FROM appointments
            WHERE id = ? AND status <> 'Cancelled'
            LIMIT 1
        ");
        $appointmentStmt->bind_param("i", $appointmentId);
    }
    $appointmentStmt->execute();
    $appointment = $appointmentStmt->get_result()->fetch_assoc();

    if (!$appointment) {
        $message = $isDoctor
            ? "Select a valid appointment belonging to you."
            : "Select a valid appointment.";
    } elseif (!in_array($documentType, $allowedTypes, true)) {
        $message = "Select a valid document type.";
    } elseif (!$file || $file["error"] !== UPLOAD_ERR_OK) {
        $message = "Choose a file to upload.";
    } elseif ($file["size"] <= 0 || $file["size"] > 10 * 1024 * 1024) {
        $message = "The file must be smaller than 10 MB.";
    } else {
        $originalName = basename(str_replace("\\", "/", $file["name"]));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ["pdf", "jpg", "jpeg", "png"];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file["tmp_name"]);

        $allowedMimeTypes = [
            "pdf"  => ["application/pdf"],
            "jpg"  => ["image/jpeg"],
            "jpeg" => ["image/jpeg"],
            "png"  => ["image/png"]
        ];

        if (
            !in_array($extension, $allowedExtensions, true) ||
            !in_array($mimeType, $allowedMimeTypes[$extension] ?? [], true)
        ) {
            $message = "Only valid PDF, JPG, JPEG, and PNG files are allowed.";
        } else {
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) {
                $message = "Could not create the secure upload directory.";
            } elseif (!is_writable($uploadDir)) {
                $message = "The upload directory is not writable by PHP.";
            } else {
                $storedName = bin2hex(random_bytes(24)) . "." . $extension;
                $absolutePath = $uploadDir . $storedName;
                $relativePath = $relativeDir . $storedName;

                if (!move_uploaded_file($file["tmp_name"], $absolutePath)) {
                    $message = "Failed to store the uploaded file.";
                } else {
                    $insertStmt = $conn->prepare("
                        INSERT INTO medical_documents (
                            patient_id, appointment_id, uploaded_by,
                            document_name, file_path, document_type
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->bind_param(
                        "iiisss",
                        $appointment["patient_id"],
                        $appointmentId,
                        $userId,
                        $originalName,
                        $relativePath,
                        $documentType
                    );

                    if ($insertStmt->execute()) {
                        echo '<script>window.location.href = "index.php?page=documents&uploaded=1";</script>';
                        exit;
                    }
                    if (is_file($absolutePath)) {
                        unlink($absolutePath);
                    }

                    $message = "Could not save document details to the database.";
                }
            }
        }
    }
}

if (isset($_GET["uploaded"])) {
    $message = "Medical document uploaded successfully.";
    $messageType = "success";
}

/* ============ APPOINTMENT LIST ============ */
if ($isDoctor) {
    $appointmentStmt = $conn->prepare("
        SELECT a.id, a.appointment_number, a.appointment_date,
               a.appointment_time, p.patient_number,
               CONCAT(p.full_name) AS patient_name
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        WHERE a.doctor_id = ? AND a.status <> 'Cancelled'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 300
    ");
    $appointmentStmt->bind_param("i", $doctorId);
} else {
    $appointmentStmt = $conn->prepare("
        SELECT a.id, a.appointment_number, a.appointment_date,
               a.appointment_time, p.patient_number,
               CONCAT(p.full_name) AS patient_name,
               d.full_name AS doctor_name
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        INNER JOIN doctors d ON d.id = a.doctor_id
        WHERE a.status <> 'Cancelled'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 300
    ");
}
$appointmentStmt->execute();
$appointments = $appointmentStmt->get_result();

/* ============ DOCUMENT LIST ============ */
$search = trim($_GET["search"] ?? "");
$typeFilter = trim($_GET["type"] ?? "");

if ($isDoctor) {
    $sql = "
        SELECT md.id, md.document_name, md.document_type, md.uploaded_at,
               md.appointment_id, md.file_path, a.appointment_number,
               p.patient_number,
               CONCAT(p.full_name) AS patient_name,
               u.username AS uploader
        FROM medical_documents md
        INNER JOIN appointments a ON a.id = md.appointment_id
        INNER JOIN patients p ON p.id = md.patient_id
        INNER JOIN users u ON u.id = md.uploaded_by
        WHERE a.doctor_id = ?
    ";
    $types = "i";
    $params = [$doctorId];
} else {
    $sql = "
        SELECT md.id, md.document_name, md.document_type, md.uploaded_at,
               md.appointment_id, md.file_path, a.appointment_number,
               p.patient_number,
               CONCAT(p.full_name) AS patient_name,
               u.username AS uploader
        FROM medical_documents md
        INNER JOIN appointments a ON a.id = md.appointment_id
        INNER JOIN patients p ON p.id = md.patient_id
        INNER JOIN users u ON u.id = md.uploaded_by
        WHERE 1=1
    ";
    $types = "";
    $params = [];
}

if ($search !== "") {
    $sql .= " AND (md.document_name LIKE ? OR p.patient_number LIKE ?
               OR p.full_name LIKE ? OR a.appointment_number LIKE ?)";
    $term = "%" . $search . "%";
    $types .= "ssss";
    array_push($params, $term, $term, $term, $term);
}

if ($typeFilter !== "") {
    $sql .= " AND md.document_type = ?";
    $types .= "s";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY md.uploaded_at DESC LIMIT 200";

$listStmt = $conn->prepare($sql);
if ($types !== "") {
    $listStmt->bind_param($types, ...$params);
}
$listStmt->execute();
$documents = $listStmt->get_result();

$documentTypes = [
    "Blood Test Report",
    "X-Ray",
    "Scan",
    "Lab Report",
    "Other Medical Document"
];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Medical Documents</h1>
            <p class="mt-1 text-sm text-slate-500">
                <?= $isDoctor
                    ? "Securely upload and manage reports for your patients."
                    : "Securely upload and manage patient medical reports." ?>
            </p>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <i data-lucide="shield-check" class="h-4 w-4 text-blue-600"></i>
            <span class="text-sm font-medium text-slate-600">
                <?= $isDoctor ? "Doctor Access" : "Nurse Access" ?>
            </span>
        </div>
    </div>

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
        <div class="xl:col-span-1">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-5">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
                        <i data-lucide="file-up" class="h-5 w-5 text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-800">Upload Document</h2>
                        <p class="text-xs text-slate-500">PDF, JPG or PNG · Maximum 10 MB</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5 p-5">
                    <input type="hidden" name="action" value="upload">

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Patient Appointment *</label>
                        <select name="appointment_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Select appointment / patient</option>
                            <?php while ($appointment = $appointments->fetch_assoc()): ?>
                                <option value="<?= (int) $appointment["id"] ?>">
                                    <?= htmlspecialchars($appointment["appointment_number"]) ?> -
                                    <?= htmlspecialchars($appointment["patient_name"]) ?>
                                    (<?= htmlspecialchars($appointment["patient_number"]) ?>)
                                    <?php if ($isNurse && !empty($appointment["doctor_name"])): ?>
                                        · Dr. <?= htmlspecialchars($appointment["doctor_name"]) ?>
                                    <?php endif; ?>
                                    · <?= date("d M Y", strtotime($appointment["appointment_date"])) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Document Type *</label>
                        <select name="document_type" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Select document type</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Choose File *</label>
                        <input type="file" name="medical_file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                        <p class="mt-2 text-xs text-slate-400">Use PDF or image files from a trusted source.</p>
                    </div>

                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-xs leading-5 text-amber-800">
                        Medical documents contain private patient information. Upload only documents associated with the selected appointment.
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        Upload Medical Document
                    </button>
                </form>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-5">
                    <h2 class="font-semibold text-slate-800">Patient Documents</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        <?= $isDoctor
                            ? "Documents associated with your appointments."
                            : "All uploaded medical documents across the clinic." ?>
                    </p>

                    <form method="GET" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <input type="hidden" name="page" value="documents">
                        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient or document..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500">

                        <select name="type" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                            <option value="">All document types</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $typeFilter === $type ? "selected" : "" ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-700">
                            Search Documents
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-slate-500">Document / Patient</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-slate-500">Type</th>
                                <th class="px-5 py-4 text-xs font-semibold uppercase text-slate-500">Uploaded</th>
                                <th class="px-5 py-4 text-right text-xs font-semibold uppercase text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($documents->num_rows > 0): ?>
                                <?php while ($doc = $documents->fetch_assoc()): ?>
                                    <?php
                                    $ext = strtolower(pathinfo($doc["document_name"], PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ["jpg", "jpeg", "png"], true);
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-4">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50">
                                                    <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="break-all text-sm font-semibold text-slate-800"><?= htmlspecialchars($doc["document_name"]) ?></p>
                                                    <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($doc["patient_name"]) ?> · <?= htmlspecialchars($doc["patient_number"]) ?></p>
                                                    <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($doc["appointment_number"]) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"><?= htmlspecialchars($doc["document_type"]) ?></span>
                                        </td>
                                        <td class="px-5 py-4 text-xs text-slate-500"><?= date("d M Y, h:i A", strtotime($doc["uploaded_at"])) ?></td>
                                        <td class="px-5 py-4 text-right">
                                            <button type="button"
                                                onclick='openDocumentModal(
            <?= json_encode($doc["file_path"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            <?= json_encode($doc["document_name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            <?= json_encode($doc["patient_name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            <?= json_encode($doc["document_type"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            "<?= $isImage ? "image" : "pdf" ?>"
        )'
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                                <i data-lucide="eye" class="h-4 w-4"></i> Open
                                            </button>
                                        </td>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-5 py-12 text-center">
                                            <i data-lucide="folder-search" class="mx-auto h-10 w-10 text-slate-300"></i>
                                            <p class="mt-3 text-sm font-medium text-slate-700">No medical documents found</p>
                                            <p class="mt-1 text-xs text-slate-400">Upload a report or change your search filters.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= DOCUMENT VIEWER MODAL ================= -->
<div id="docViewerModal"
    class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/70 p-4">
    <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50">
                    <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                </div>
                <div class="min-w-0">
                    <h3 id="docModalTitle" class="truncate text-base font-bold text-slate-800">Document</h3>
                    <p id="docModalSubtitle" class="truncate text-xs text-slate-500"></p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a id="docModalDownload" href="#" download
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    <i data-lucide="download" class="h-4 w-4"></i> Download
                </a>
                <button type="button" onclick="closeDocumentModal()"
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-auto bg-slate-50 p-4">
            <div id="docModalSpinner" class="flex h-64 items-center justify-center">
                <div class="flex flex-col items-center gap-3 text-slate-400">
                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin"></i>
                    <p class="text-sm">Loading document…</p>
                </div>
            </div>

            <iframe id="docModalFrame"
                class="hidden h-[75vh] w-full rounded-xl border border-slate-200 bg-white"
                src="about:blank"
                title="Document preview"></iframe>

            <div id="docModalImageWrap" class="hidden items-center justify-center">
                <img id="docModalImage" src="" alt="Document preview"
                    class="max-h-[75vh] max-w-full rounded-xl border border-slate-200 bg-white object-contain shadow-sm">
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== "undefined") lucide.createIcons();

    function openDocumentModal(filePath, name, patientName, docType, kind) {
        const modal = document.getElementById("docViewerModal");
        const title = document.getElementById("docModalTitle");
        const subtitle = document.getElementById("docModalSubtitle");
        const frame = document.getElementById("docModalFrame");
        const imageWrap = document.getElementById("docModalImageWrap");
        const image = document.getElementById("docModalImage");
        const spinner = document.getElementById("docModalSpinner");
        const downloadBtn = document.getElementById("docModalDownload");

        // filePath from DB looks like: "uploads/medical/abc123.jpg"
        // We're inside medical/, so go up one level: "../uploads/medical/abc123.jpg"
        const url = "../" + filePath.replace(/^\/+/, "");

        // Set text + download link
        title.textContent = name || "Document";
        subtitle.textContent = (patientName ? patientName + " · " : "") + (docType || "");
        downloadBtn.href = url;
        downloadBtn.setAttribute("download", name || "document");

        // Reset views + show spinner
        frame.classList.add("hidden");
        frame.src = "about:blank";
        imageWrap.classList.add("hidden");
        imageWrap.classList.remove("flex");
        image.src = "";
        spinner.classList.remove("hidden");

        // Show modal
        modal.classList.remove("hidden");
        modal.classList.add("flex");

        if (kind === "image") {
            image.onload = function() {
                spinner.classList.add("hidden");
                imageWrap.classList.remove("hidden");
                imageWrap.classList.add("flex");
            };
            image.onerror = function() {
                spinner.classList.add("hidden");
                imageWrap.classList.remove("hidden");
                imageWrap.classList.add("flex");
                imageWrap.innerHTML = `
                <div class="flex flex-col items-center gap-3 text-slate-500">
                    <i data-lucide="image-off" class="h-10 w-10 text-slate-300"></i>
                    <p class="text-sm font-medium">Preview unavailable</p>
                    <p class="text-xs text-slate-400">Use the Download button to open this file.</p>
                </div>`;
                if (typeof lucide !== "undefined") lucide.createIcons();
            };
            image.src = url;
        } else {
            frame.onload = function() {
                spinner.classList.add("hidden");
                frame.classList.remove("hidden");
            };
            frame.src = url;
        }

        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function closeDocumentModal() {
        const modal = document.getElementById("docViewerModal");
        const frame = document.getElementById("docModalFrame");
        const image = document.getElementById("docModalImage");
        const imageWrap = document.getElementById("docModalImageWrap");
        const spinner = document.getElementById("docModalSpinner");

        // Stop any ongoing loads
        frame.src = "about:blank";
        image.src = "";
        frame.classList.add("hidden");
        imageWrap.classList.add("hidden");
        imageWrap.classList.remove("flex");
        spinner.classList.remove("hidden");

        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }

    // Close on ESC or backdrop click
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            const modal = document.getElementById("docViewerModal");
            if (modal && !modal.classList.contains("hidden")) {
                closeDocumentModal();
            }
        }
    });

    document.addEventListener("click", function(e) {
        const modal = document.getElementById("docViewerModal");
        if (modal && e.target === modal) {
            closeDocumentModal();
        }
    });
</script>