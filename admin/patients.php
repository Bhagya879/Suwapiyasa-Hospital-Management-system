<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();

$message = "";
$messageType = "";
$editPatient = null;
$viewPatient = null;
function generatePatientNumber($conn)
{
    $result = $conn->query("SELECT patient_number FROM patients ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $number = (int) preg_replace('/[^0-9]/', '', $row["patient_number"]);
        return "PAT" . str_pad($number + 1, 6, "0", STR_PAD_LEFT);
    }
    return "PAT000001";
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "add") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $nic = trim($_POST["nic"] ?? "");
        $dateOfBirth = $_POST["date_of_birth"] ?? "";
        $gender = $_POST["gender"] ?? "";
        $bloodGroup = $_POST["blood_group"] ?? "";
        $maritalStatus = $_POST["marital_status"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $emergencyName = trim($_POST["emergency_contact_name"] ?? "");
        $emergencyPhone = trim($_POST["emergency_contact_phone"] ?? "");
        $emergencyRelationship = trim($_POST["emergency_contact_relationship"] ?? "");
        $allergies = trim($_POST["allergies"] ?? "");
        $chronicConditions = trim($_POST["chronic_conditions"] ?? "");
        $currentMedications = trim($_POST["current_medications"] ?? "");
        $registrationDate = $_POST["registration_date"] ?? date("Y-m-d");
        $status = $_POST["status"] ?? "Active";
        if ($username === "" || $password === "" || $fullName === "" || $dateOfBirth === "" || $gender === "" || $phone === "" || $address === "") {
            $message = "Please fill in all required fields.";
            $messageType = "error";
        } elseif ($password !== $confirmPassword) {
            $message = "Passwords do not match.";
            $messageType = "error";
        } elseif (strlen($password) < 6) {
            $message = "Password must contain at least 6 characters.";
            $messageType = "error";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $check->bind_param("s", $username);
            $check->execute();
            $checkResult = $check->get_result();
            if ($checkResult->num_rows > 0) {
                $message = "Username already exists.";
                $messageType = "error";
            } else {
                $checkNic = null;
                if ($nic !== "") {
                    $checkNic = $conn->prepare("SELECT id FROM patients WHERE nic = ? LIMIT 1");
                    $checkNic->bind_param("s", $nic);
                    $checkNic->execute();
                    $nicResult = $checkNic->get_result();
                    if ($nicResult->num_rows > 0) {
                        $message = "NIC already exists.";
                        $messageType = "error";
                    }
                }
                if ($message === "") {
                    $conn->begin_transaction();
                    try {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $userStmt = $conn->prepare(
                            "INSERT INTO users (username, password, role)
                             VALUES (?, ?, 'patient')"
                        );
                        $userStmt->bind_param(
                            "ss",
                            $username,
                            $hashedPassword
                        );
                        if (!$userStmt->execute()) {
                            throw new Exception("Failed to create patient account.");
                        }
                        $userId = $conn->insert_id;
                        $patientNumber = generatePatientNumber($conn);
                        $patientStmt = $conn->prepare(
                            "INSERT INTO patients (
                                user_id,
                                patient_number,
                                full_name,
                                nic,
                                date_of_birth,
                                gender,
                                blood_group,
                                marital_status,
                                phone,
                                email,
                                address,
                                city,
                                emergency_contact_name,
                                emergency_contact_phone,
                                emergency_contact_relationship,
                                allergies,
                                chronic_conditions,
                                current_medications,
                                registration_date,
                                status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $patientStmt->bind_param(
                            "isssssssssssssssssss",
                            $userId,
                            $patientNumber,
                            $fullName,
                            $nic,
                            $dateOfBirth,
                            $gender,
                            $bloodGroup,
                            $maritalStatus,
                            $phone,
                            $email,
                            $address,
                            $city,
                            $emergencyName,
                            $emergencyPhone,
                            $emergencyRelationship,
                            $allergies,
                            $chronicConditions,
                            $currentMedications,
                            $registrationDate,
                            $status
                        );
                        if (!$patientStmt->execute()) {
                            throw new Exception("Failed to create patient record.");
                        }
                        $conn->commit();
                        $message = "Patient registered successfully. Patient Number: " . $patientNumber;
                        $messageType = "success";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = $e->getMessage();
                        $messageType = "error";
                    }
                }
            }
        }
    }
    if ($action === "update") {
        $patientId = (int) ($_POST["patient_id"] ?? 0);
        $userId = (int) ($_POST["user_id"] ?? 0);
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $nic = trim($_POST["nic"] ?? "");
        $dateOfBirth = $_POST["date_of_birth"] ?? "";
        $gender = $_POST["gender"] ?? "";
        $bloodGroup = $_POST["blood_group"] ?? "";
        $maritalStatus = $_POST["marital_status"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $emergencyName = trim($_POST["emergency_contact_name"] ?? "");
        $emergencyPhone = trim($_POST["emergency_contact_phone"] ?? "");
        $emergencyRelationship = trim($_POST["emergency_contact_relationship"] ?? "");
        $allergies = trim($_POST["allergies"] ?? "");
        $chronicConditions = trim($_POST["chronic_conditions"] ?? "");
        $currentMedications = trim($_POST["current_medications"] ?? "");
        $registrationDate = $_POST["registration_date"] ?? "";
        $status = $_POST["status"] ?? "Active";
        if ($patientId <= 0 || $userId <= 0 || $username === "" || $fullName === "" || $dateOfBirth === "" || $gender === "" || $phone === "" || $address === "") {
            $message = "Please fill in all required fields.";
            $messageType = "error";
        } else {
            $check = $conn->prepare(
                "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1"
            );
            $check->bind_param("si", $username, $userId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Username already exists.";
                $messageType = "error";
            } else {
                $nicExists = false;
                if ($nic !== "") {
                    $checkNic = $conn->prepare(
                        "SELECT id FROM patients WHERE nic = ? AND id != ? LIMIT 1"
                    );
                    $checkNic->bind_param("si", $nic, $patientId);
                    $checkNic->execute();
                    if ($checkNic->get_result()->num_rows > 0) {
                        $nicExists = true;
                    }
                }
                if ($nicExists) {
                    $message = "NIC already exists.";
                    $messageType = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        if ($password !== "") {
                            if (strlen($password) < 6) {
                                throw new Exception("Password must contain at least 6 characters.");
                            }
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $userStmt = $conn->prepare(
                                "UPDATE users
                                 SET username = ?, password = ?, role = 'patient'
                                 WHERE id = ?"
                            );
                            $userStmt->bind_param(
                                "ssi",
                                $username,
                                $hashedPassword,
                                $userId
                            );
                        } else {
                            $userStmt = $conn->prepare(
                                "UPDATE users
                                 SET username = ?, role = 'patient'
                                 WHERE id = ?"
                            );
                            $userStmt->bind_param(
                                "si",
                                $username,
                                $userId
                            );
                        }
                        if (!$userStmt->execute()) {
                            throw new Exception("Failed to update patient account.");
                        }
                        $patientStmt = $conn->prepare(
                            "UPDATE patients SET
                                full_name = ?,
                                nic = ?,
                                date_of_birth = ?,
                                gender = ?,
                                blood_group = ?,
                                marital_status = ?,
                                phone = ?,
                                email = ?,
                                address = ?,
                                city = ?,
                                emergency_contact_name = ?,
                                emergency_contact_phone = ?,
                                emergency_contact_relationship = ?,
                                allergies = ?,
                                chronic_conditions = ?,
                                current_medications = ?,
                                registration_date = ?,
                                status = ?
                             WHERE id = ? AND user_id = ?"
                        );
                        $patientStmt->bind_param(
                            "ssssssssssssssssssii",
                            $fullName,
                            $nic,
                            $dateOfBirth,
                            $gender,
                            $bloodGroup,
                            $maritalStatus,
                            $phone,
                            $email,
                            $address,
                            $city,
                            $emergencyName,
                            $emergencyPhone,
                            $emergencyRelationship,
                            $allergies,
                            $chronicConditions,
                            $currentMedications,
                            $registrationDate,
                            $status,
                            $patientId,
                            $userId
                        );
                        if (!$patientStmt->execute()) {
                            throw new Exception("Failed to update patient information.");
                        }
                        $conn->commit();
                        $message = "Patient updated successfully.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = $e->getMessage();
                        $messageType = "error";
                    }
                }
            }
        }
    }
    if ($action === "delete") {
        $patientId = (int) ($_POST["patient_id"] ?? 0);
        $userId = (int) ($_POST["user_id"] ?? 0);
        if ($patientId > 0 && $userId > 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    "DELETE FROM users WHERE id = ? AND role = 'patient'"
                );
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to delete patient.");
                }
                $conn->commit();
                $message = "Patient deleted successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Invalid patient.";
            $messageType = "error";
        }
    }
}
if (isset($_GET["view"])) {
    $patientId = (int) $_GET["view"];
    $stmt = $conn->prepare(
        "SELECT p.*, u.username
         FROM patients p
         INNER JOIN users u ON p.user_id = u.id
         WHERE p.id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewPatient = $result->fetch_assoc();
}
if (isset($_GET["edit"])) {
    $patientId = (int) $_GET["edit"];
    $stmt = $conn->prepare(
        "SELECT p.*, u.username
         FROM patients p
         INNER JOIN users u ON p.user_id = u.id
         WHERE p.id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editPatient = $result->fetch_assoc();
}
$search = trim($_GET["search"] ?? "");
if ($search !== "") {
    $stmt = $conn->prepare(
        "SELECT p.*, u.username
         FROM patients p
         INNER JOIN users u ON p.user_id = u.id
         WHERE p.patient_number LIKE ?
            OR p.full_name LIKE ?
            OR p.nic LIKE ?
            OR p.phone LIKE ?
         ORDER BY p.id DESC"
    );
    $searchValue = "%" . $search . "%";
    $stmt->bind_param(
        "ssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );
    $stmt->execute();
    $patients = $stmt->get_result();
} else {
    $patients = $conn->query(
        "SELECT p.*, u.username
         FROM patients p
         INNER JOIN users u ON p.user_id = u.id
         ORDER BY p.id DESC"
    );
}
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Patient Management</h2>
            <p class="text-sm text-slate-500 mt-1">
                Register and manage patient information and accounts.
            </p>
        </div>
        <button onclick="openAddModal()"
            class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="user-plus" class="w-5 h-5"></i>
            Add Patient
        </button>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="page" value="patients">
            <div class="relative flex-1">
                <i data-lucide="search"
                    class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                <input type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by patient number, name, NIC or phone..."
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit"
                class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-medium transition">
                Search
            </button>
            <?php if ($search !== ""): ?>
                <a href="index.php?page=patients"
                    class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition text-center">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200">
            <h3 class="font-bold text-lg text-slate-900">Registered Patients</h3>
            <p class="text-sm text-slate-500">
                Total records:
                <?php echo $patients ? $patients->num_rows : 0; ?>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Patient</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Contact</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Gender</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Blood</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($patients && $patients->num_rows > 0): ?>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                            <i data-lucide="user-round" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800">
                                                <?php echo htmlspecialchars($patient["full_name"]); ?>
                                            </p>
                                            <p class="text-xs text-blue-600 font-medium">
                                                <?php echo htmlspecialchars($patient["patient_number"]); ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-slate-700">
                                        <?php echo htmlspecialchars($patient["phone"]); ?>
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        <?php echo htmlspecialchars($patient["username"]); ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <?php echo htmlspecialchars($patient["gender"]); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($patient["blood_group"])): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-red-50 text-red-600 text-xs font-semibold">
                                            <?php echo htmlspecialchars($patient["blood_group"]); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($patient["status"] === "Active"): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-green-50 text-green-700 text-xs font-semibold">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-semibold">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="index.php?page=patients&view=<?php echo $patient["id"]; ?>"
                                            class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center"
                                            title="View">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <a href="index.php?page=patients&edit=<?php echo $patient["id"]; ?>"
                                            class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center"
                                            title="Edit">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </a>
                                        <form method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this patient?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="patient_id" value="<?php echo $patient["id"]; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $patient["user_id"]; ?>">
                                            <button type="submit"
                                                class="w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center"
                                                title="Delete">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                                        <i data-lucide="users-round" class="w-7 h-7 text-slate-400"></i>
                                    </div>
                                    <h3 class="font-semibold text-slate-700">No patients found</h3>
                                    <p class="text-sm text-slate-400 mt-1">
                                        Add a patient to get started.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($viewPatient): ?>
    <div id="viewModal"
        class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Patient Details</h3>
                    <p class="text-sm text-slate-500">
                        <?php echo htmlspecialchars($viewPatient["patient_number"]); ?>
                    </p>
                </div>
                <a href="index.php?page=patients"
                    class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Full Name</p>
                        <p class="mt-1 font-medium text-slate-800">
                            <?php echo htmlspecialchars($viewPatient["full_name"]); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Username</p>
                        <p class="mt-1 font-medium text-slate-800">
                            <?php echo htmlspecialchars($viewPatient["username"]); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">NIC</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["nic"] ?: "-"); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Date of Birth</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["date_of_birth"]); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Gender</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["gender"]); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Blood Group</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["blood_group"] ?: "-"); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Marital Status</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["marital_status"] ?: "-"); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Phone</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["phone"]); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Email</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["email"] ?: "-"); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">City</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["city"] ?: "-"); ?>
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Address</p>
                        <p class="mt-1 text-slate-700">
                            <?php echo htmlspecialchars($viewPatient["address"]); ?>
                        </p>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-5">
                    <h4 class="font-bold text-slate-800 mb-4">Emergency Contact</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Name</p>
                            <p class="mt-1 text-slate-700">
                                <?php echo htmlspecialchars($viewPatient["emergency_contact_name"] ?: "-"); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Phone</p>
                            <p class="mt-1 text-slate-700">
                                <?php echo htmlspecialchars($viewPatient["emergency_contact_phone"] ?: "-"); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Relationship</p>
                            <p class="mt-1 text-slate-700">
                                <?php echo htmlspecialchars($viewPatient["emergency_contact_relationship"] ?: "-"); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-5">
                    <h4 class="font-bold text-slate-800 mb-4">Medical Information</h4>
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Allergies</p>
                            <p class="mt-1 text-slate-700">
                                <?php echo nl2br(htmlspecialchars($viewPatient["allergies"] ?: "None recorded")); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Chronic Conditions</p>
                            <p class="mt-1 text-slate-700">
                                <?php echo nl2br(htmlspecialchars($viewPatient["chronic_conditions"] ?: "None recorded")); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">Current Medications</p>
                            <p class="mt-1 text-slate-700">
                                <?php echo nl2br(htmlspecialchars($viewPatient["current_medications"] ?: "None recorded")); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=patients&edit=<?php echo $viewPatient["id"]; ?>"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        Edit Patient
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<div id="patientModal"
    class="<?php echo $editPatient ? 'flex' : 'hidden'; ?> fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div>
                <h3 id="modalTitle" class="text-xl font-bold text-slate-900">
                    <?php echo $editPatient ? "Edit Patient" : "Add Patient"; ?>
                </h3>
                <p class="text-sm text-slate-500">
                    <?php echo $editPatient ? "Update patient information" : "Register a new patient"; ?>
                </p>
            </div>
            <button type="button"
                onclick="closePatientModal()"
                class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden"
                name="action"
                value="<?php echo $editPatient ? 'update' : 'add'; ?>">
            <?php if ($editPatient): ?>
                <input type="hidden"
                    name="patient_id"
                    value="<?php echo $editPatient["id"]; ?>">
                <input type="hidden"
                    name="user_id"
                    value="<?php echo $editPatient["user_id"]; ?>">
            <?php endif; ?>
            <div class="space-y-7">
                <div>
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="shield-user" class="w-5 h-5 text-blue-600"></i>
                        Login Account
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                name="username"
                                required
                                value="<?php echo htmlspecialchars($editPatient["username"] ?? ""); ?>"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                <?php echo $editPatient ? "New Password" : "Password"; ?>
                                <?php if (!$editPatient): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <input type="password"
                                name="password"
                                <?php echo !$editPatient ? "required" : ""; ?>
                                placeholder="<?php echo $editPatient ? "Leave blank to keep current password" : "Minimum 6 characters"; ?>"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <?php if (!$editPatient): ?>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password"
                                    name="confirm_password"
                                    required
                                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="user-round" class="w-5 h-5 text-blue-600"></i>
                        Patient Information
                    </h4>
                    <?php if ($editPatient): ?>
                        <div class="mb-5 p-4 bg-blue-50 border border-blue-100 rounded-xl">
                            <p class="text-xs text-blue-500 font-semibold uppercase">
                                Patient Number
                            </p>
                            <p class="text-lg font-bold text-blue-700 mt-1">
                                <?php echo htmlspecialchars($editPatient["patient_number"]); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                name="full_name"
                                required
                                value="<?php echo htmlspecialchars($editPatient["full_name"] ?? ""); ?>"
                                placeholder="Enter full name"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                NIC
                            </label>
                            <input type="text"
                                name="nic"
                                value="<?php echo htmlspecialchars($editPatient["nic"] ?? ""); ?>"
                                placeholder="NIC number"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                name="date_of_birth"
                                required
                                value="<?php echo htmlspecialchars($editPatient["date_of_birth"] ?? ""); ?>"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <select name="gender"
                                required
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (($editPatient["gender"] ?? "") === "Male") ? "selected" : ""; ?>>Male</option>
                                <option value="Female" <?php echo (($editPatient["gender"] ?? "") === "Female") ? "selected" : ""; ?>>Female</option>
                                <option value="Other" <?php echo (($editPatient["gender"] ?? "") === "Other") ? "selected" : ""; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Blood Group
                            </label>
                            <select name="blood_group"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Blood Group</option>
                                <?php
                                $bloodGroups = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];
                                foreach ($bloodGroups as $blood) {
                                    $selected = (($editPatient["blood_group"] ?? "") === $blood) ? "selected" : "";
                                    echo "<option value=\"$blood\" $selected>$blood</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Marital Status
                            </label>
                            <select name="marital_status"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Status</option>
                                <option value="Single" <?php echo (($editPatient["marital_status"] ?? "") === "Single") ? "selected" : ""; ?>>Single</option>
                                <option value="Married" <?php echo (($editPatient["marital_status"] ?? "") === "Married") ? "selected" : ""; ?>>Married</option>
                                <option value="Divorced" <?php echo (($editPatient["marital_status"] ?? "") === "Divorced") ? "selected" : ""; ?>>Divorced</option>
                                <option value="Widowed" <?php echo (($editPatient["marital_status"] ?? "") === "Widowed") ? "selected" : ""; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="phone" class="w-5 h-5 text-blue-600"></i>
                        Contact Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                name="phone"
                                required
                                value="<?php echo htmlspecialchars($editPatient["phone"] ?? ""); ?>"
                                placeholder="07XXXXXXXX"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Email
                            </label>
                            <input type="email"
                                name="email"
                                value="<?php echo htmlspecialchars($editPatient["email"] ?? ""); ?>"
                                placeholder="patient@example.com"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                City
                            </label>
                            <input type="text"
                                name="city"
                                value="<?php echo htmlspecialchars($editPatient["city"] ?? ""); ?>"
                                placeholder="City"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Registration Date
                            </label>
                            <input type="date"
                                name="registration_date"
                                value="<?php echo htmlspecialchars($editPatient["registration_date"] ?? date("Y-m-d")); ?>"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Address <span class="text-red-500">*</span>
                            </label>
                            <textarea name="address"
                                required
                                rows="3"
                                placeholder="Residential address"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"><?php echo htmlspecialchars($editPatient["address"] ?? ""); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="phone-call" class="w-5 h-5 text-blue-600"></i>
                        Emergency Contact
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Contact Name
                            </label>
                            <input type="text"
                                name="emergency_contact_name"
                                value="<?php echo htmlspecialchars($editPatient["emergency_contact_name"] ?? ""); ?>"
                                placeholder="Emergency contact name"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Contact Phone
                            </label>
                            <input type="text"
                                name="emergency_contact_phone"
                                value="<?php echo htmlspecialchars($editPatient["emergency_contact_phone"] ?? ""); ?>"
                                placeholder="Emergency contact phone"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Relationship
                            </label>
                            <input type="text"
                                name="emergency_contact_relationship"
                                value="<?php echo htmlspecialchars($editPatient["emergency_contact_relationship"] ?? ""); ?>"
                                placeholder="e.g. Mother, Father"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="clipboard-medical" class="w-5 h-5 text-blue-600"></i>
                        Medical Information
                    </h4>
                    <div class="grid grid-cols-1 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Allergies
                            </label>
                            <textarea name="allergies"
                                rows="2"
                                placeholder="Known drug, food or other allergies"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"><?php echo htmlspecialchars($editPatient["allergies"] ?? ""); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Chronic Conditions
                            </label>
                            <textarea name="chronic_conditions"
                                rows="2"
                                placeholder="e.g. Diabetes, Hypertension"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"><?php echo htmlspecialchars($editPatient["chronic_conditions"] ?? ""); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Current Medications
                            </label>
                            <textarea name="current_medications"
                                rows="2"
                                placeholder="Current medicines if applicable"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"><?php echo htmlspecialchars($editPatient["current_medications"] ?? ""); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <i data-lucide="settings-2" class="w-5 h-5 text-blue-600"></i>
                        Account Status
                    </h4>
                    <div class="max-w-sm">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Status
                        </label>
                        <select name="status"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <option value="Active" <?php echo (($editPatient["status"] ?? "Active") === "Active") ? "selected" : ""; ?>>
                                Active
                            </option>
                            <option value="Inactive" <?php echo (($editPatient["status"] ?? "") === "Inactive") ? "selected" : ""; ?>>
                                Inactive
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <button type="button"
                    onclick="closePatientModal()"
                    class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="<?php echo $editPatient ? 'save' : 'user-plus'; ?>" class="w-5 h-5"></i>
                    <?php echo $editPatient ? "Update Patient" : "Register Patient"; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    function openAddModal() {
        document.getElementById("patientModal").classList.remove("hidden");
        document.getElementById("patientModal").classList.add("flex");
    }

    function closePatientModal() {
        window.location.href = "index.php?page=patients";
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
<script>
    lucide.createIcons();
</script>