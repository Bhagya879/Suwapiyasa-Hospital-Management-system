<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();
$message = "";
$messageType = "";
$editDoctor = null;
$viewDoctor = null;
function generateDoctorNumber($conn)
{
    $result = $conn->query("SELECT doctor_number FROM doctors ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $number = (int) preg_replace('/[^0-9]/', '', $row["doctor_number"]);
        return "DOC" . str_pad($number + 1, 6, "0", STR_PAD_LEFT);
    }
    return "DOC000001";
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "add") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $registrationNumber = trim($_POST["registration_number"] ?? "");
        $specialization = trim($_POST["specialization"] ?? "");
        $qualification = trim($_POST["qualification"] ?? "");
        $gender = $_POST["gender"] ?? "";
        $dateOfBirth = $_POST["date_of_birth"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $yearsOfExperience = (int) ($_POST["years_of_experience"] ?? 0);
        $consultationFee = (float) ($_POST["consultation_fee"] ?? 0);
        $joiningDate = $_POST["joining_date"] ?? date("Y-m-d");
        $status = $_POST["status"] ?? "Active";
        if ($username === "" || $password === "" || $fullName === "" || $registrationNumber === "" || $specialization === "" || $qualification === "" || $gender === "" || $phone === "" || $joiningDate === "") {
            $message = "Please fill in all required fields.";
            $messageType = "error";
        } elseif ($password !== $confirmPassword) {
            $message = "Passwords do not match.";
            $messageType = "error";
        } elseif (strlen($password) < 6) {
            $message = "Password must contain at least 6 characters.";
            $messageType = "error";
        } elseif ($yearsOfExperience < 0) {
            $message = "Years of experience cannot be negative.";
            $messageType = "error";
        } elseif ($consultationFee < 0) {
            $message = "Consultation fee cannot be negative.";
            $messageType = "error";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Username already exists.";
                $messageType = "error";
            } else {
                $checkReg = $conn->prepare("SELECT id FROM doctors WHERE registration_number = ? LIMIT 1");
                $checkReg->bind_param("s", $registrationNumber);
                $checkReg->execute();
                if ($checkReg->get_result()->num_rows > 0) {
                    $message = "Doctor registration number already exists.";
                    $messageType = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $userStmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'doctor')");
                        $userStmt->bind_param("ss", $username, $hashedPassword);
                        if (!$userStmt->execute()) {
                            throw new Exception("Failed to create doctor account.");
                        }
                        $userId = $conn->insert_id;
                        $doctorNumber = generateDoctorNumber($conn);
                        $doctorStmt = $conn->prepare("INSERT INTO doctors (user_id, doctor_number, full_name, registration_number, specialization, qualification, gender, date_of_birth, phone, email, address, city, department, years_of_experience, consultation_fee, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $doctorStmt->bind_param("issssssssssssidss", $userId, $doctorNumber, $fullName, $registrationNumber, $specialization, $qualification, $gender, $dateOfBirth, $phone, $email, $address, $city, $department, $yearsOfExperience, $consultationFee, $joiningDate, $status);
                        if (!$doctorStmt->execute()) {
                            throw new Exception("Failed to create doctor record.");
                        }
                        $conn->commit();
                        $message = "Doctor registered successfully. Doctor Number: " . $doctorNumber;
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
        $doctorId = (int) ($_POST["doctor_id"] ?? 0);
        $userId = (int) ($_POST["user_id"] ?? 0);
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $registrationNumber = trim($_POST["registration_number"] ?? "");
        $specialization = trim($_POST["specialization"] ?? "");
        $qualification = trim($_POST["qualification"] ?? "");
        $gender = $_POST["gender"] ?? "";
        $dateOfBirth = $_POST["date_of_birth"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $yearsOfExperience = (int) ($_POST["years_of_experience"] ?? 0);
        $consultationFee = (float) ($_POST["consultation_fee"] ?? 0);
        $joiningDate = $_POST["joining_date"] ?? "";
        $status = $_POST["status"] ?? "Active";
        if ($doctorId <= 0 || $userId <= 0 || $username === "" || $fullName === "" || $registrationNumber === "" || $specialization === "" || $qualification === "" || $gender === "" || $phone === "" || $joiningDate === "") {
            $message = "Please fill in all required fields.";
            $messageType = "error";
        } elseif ($yearsOfExperience < 0 || $consultationFee < 0) {
            $message = "Invalid experience or consultation fee.";
            $messageType = "error";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
            $check->bind_param("si", $username, $userId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Username already exists.";
                $messageType = "error";
            } else {
                $checkReg = $conn->prepare("SELECT id FROM doctors WHERE registration_number = ? AND id != ? LIMIT 1");
                $checkReg->bind_param("si", $registrationNumber, $doctorId);
                $checkReg->execute();
                if ($checkReg->get_result()->num_rows > 0) {
                    $message = "Doctor registration number already exists.";
                    $messageType = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        if ($password !== "") {
                            if (strlen($password) < 6) {
                                throw new Exception("Password must contain at least 6 characters.");
                            }
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $userStmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = 'doctor' WHERE id = ?");
                            $userStmt->bind_param("ssi", $username, $hashedPassword, $userId);
                        } else {
                            $userStmt = $conn->prepare("UPDATE users SET username = ?, role = 'doctor' WHERE id = ?");
                            $userStmt->bind_param("si", $username, $userId);
                        }
                        if (!$userStmt->execute()) {
                            throw new Exception("Failed to update doctor account.");
                        }
                        $doctorStmt = $conn->prepare("UPDATE doctors SET full_name = ?, registration_number = ?, specialization = ?, qualification = ?, gender = ?, date_of_birth = ?, phone = ?, email = ?, address = ?, city = ?, department = ?, years_of_experience = ?, consultation_fee = ?, joining_date = ?, status = ? WHERE id = ? AND user_id = ?");
                        $doctorStmt->bind_param("sssssssssssisdssii", $fullName, $registrationNumber, $specialization, $qualification, $gender, $dateOfBirth, $phone, $email, $address, $city, $department, $yearsOfExperience, $consultationFee, $joiningDate, $status, $doctorId, $userId);
                        if (!$doctorStmt->execute()) {
                            throw new Exception("Failed to update doctor information.");
                        }
                        $conn->commit();
                        $message = "Doctor updated successfully.";
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
        $doctorId = (int) ($_POST["doctor_id"] ?? 0);
        $userId = (int) ($_POST["user_id"] ?? 0);
        if ($doctorId > 0 && $userId > 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to delete doctor.");
                }
                $conn->commit();
                $message = "Doctor deleted successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Invalid doctor.";
            $messageType = "error";
        }
    }
}
if (isset($_GET["view"])) {
    $doctorId = (int) $_GET["view"];
    $stmt = $conn->prepare("SELECT d.*, u.username FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.id = ? LIMIT 1");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $viewDoctor = $stmt->get_result()->fetch_assoc();
}
if (isset($_GET["edit"])) {
    $doctorId = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT d.*, u.username FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.id = ? LIMIT 1");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $editDoctor = $stmt->get_result()->fetch_assoc();
}
$search = trim($_GET["search"] ?? "");
if ($search !== "") {
    $stmt = $conn->prepare("SELECT d.*, u.username FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.doctor_number LIKE ? OR d.full_name LIKE ? OR d.registration_number LIKE ? OR d.specialization LIKE ? OR d.phone LIKE ? ORDER BY d.id DESC");
    $searchValue = "%" . $search . "%";
    $stmt->bind_param("sssss", $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
    $stmt->execute();
    $doctors = $stmt->get_result();
} else {
    $doctors = $conn->query("SELECT d.*, u.username FROM doctors d INNER JOIN users u ON d.user_id = u.id ORDER BY d.id DESC");
}
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Doctor Management</h2>
            <p class="text-sm text-slate-500 mt-1">Register and manage doctor information and accounts.</p>
        </div>
        <button onclick="openDoctorModal()" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="user-plus" class="w-5 h-5"></i>
            Add Doctor
        </button>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="page" value="doctors">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by doctor number, name, registration, specialization or phone..." class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit" class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-medium transition">Search</button>
            <?php if ($search !== ""): ?>
                <a href="index.php?page=doctors" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition text-center">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200">
            <h3 class="font-bold text-lg text-slate-900">Registered Doctors</h3>
            <p class="text-sm text-slate-500">Total records: <?php echo $doctors ? $doctors->num_rows : 0; ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Doctor</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Specialization</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Contact</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Department</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($doctors && $doctors->num_rows > 0): ?>
                        <?php while ($doctor = $doctors->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                            <i data-lucide="stethoscope" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($doctor["full_name"]); ?></p>
                                            <p class="text-xs text-blue-600 font-medium"><?php echo htmlspecialchars($doctor["doctor_number"]); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($doctor["specialization"]); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($doctor["registration_number"]); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-slate-700"><?php echo htmlspecialchars($doctor["phone"]); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($doctor["email"] ?: "-"); ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($doctor["department"] ?: "-"); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($doctor["status"] === "Active"): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-green-50 text-green-700 text-xs font-semibold">Active</span>
                                    <?php elseif ($doctor["status"] === "On Leave"): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold">On Leave</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-semibold">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="index.php?page=doctors&view=<?php echo $doctor["id"]; ?>" class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="View">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <a href="index.php?page=doctors&edit=<?php echo $doctor["id"]; ?>" class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center" title="Edit">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this doctor?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor["id"]; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $doctor["user_id"]; ?>">
                                            <button type="submit" class="w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center" title="Delete">
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
                                        <i data-lucide="stethoscope" class="w-7 h-7 text-slate-400"></i>
                                    </div>
                                    <h3 class="font-semibold text-slate-700">No doctors found</h3>
                                    <p class="text-sm text-slate-400 mt-1">Add a doctor to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($viewDoctor): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Doctor Details</h3>
                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($viewDoctor["doctor_number"]); ?></p>
                </div>
                <a href="index.php?page=doctors" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Full Name</p>
                        <p class="mt-1 font-medium text-slate-800"><?php echo htmlspecialchars($viewDoctor["full_name"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Username</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["username"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Registration Number</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["registration_number"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Specialization</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["specialization"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Qualification</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["qualification"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Gender</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["gender"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Date of Birth</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["date_of_birth"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Phone</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["phone"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Email</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["email"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Department</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["department"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Experience</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["years_of_experience"]); ?> years</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Consultation Fee</p>
                        <p class="mt-1 text-slate-700">LKR <?php echo number_format((float) $viewDoctor["consultation_fee"], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Joining Date</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["joining_date"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Status</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["status"]); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Address</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewDoctor["address"] ?: "-"); ?></p>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-5 flex justify-end">
                    <a href="index.php?page=doctors&edit=<?php echo $viewDoctor["id"]; ?>" class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        Edit Doctor
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<div id="doctorModal" class="<?php echo $editDoctor ? 'flex' : 'hidden'; ?> fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div>
                <h3 class="text-xl font-bold text-slate-900"><?php echo $editDoctor ? "Edit Doctor" : "Add Doctor"; ?></h3>
                <p class="text-sm text-slate-500"><?php echo $editDoctor ? "Update doctor information" : "Register a new doctor"; ?></p>
            </div>
            <button type="button" onclick="closeDoctorModal()" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="<?php echo $editDoctor ? 'update' : 'add'; ?>">
            <?php if ($editDoctor): ?>
                <input type="hidden" name="doctor_id" value="<?php echo $editDoctor["id"]; ?>">
                <input type="hidden" name="user_id" value="<?php echo $editDoctor["user_id"]; ?>">
            <?php endif; ?>
            <div class="space-y-7">
                <div>
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="shield-user" class="w-5 h-5 text-blue-600"></i>Login Account</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" required value="<?php echo htmlspecialchars($editDoctor["username"] ?? ""); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2"><?php echo $editDoctor ? "New Password" : "Password"; ?><?php if (!$editDoctor): ?><span class="text-red-500">*</span><?php endif; ?></label>
                            <input type="password" name="password" <?php echo !$editDoctor ? "required" : ""; ?> placeholder="<?php echo $editDoctor ? "Leave blank to keep current password" : "Minimum 6 characters"; ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <?php if (!$editDoctor): ?>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="stethoscope" class="w-5 h-5 text-blue-600"></i>Professional Information</h4>
                    <?php if ($editDoctor): ?>
                        <div class="mb-5 p-4 bg-blue-50 border border-blue-100 rounded-xl">
                            <p class="text-xs text-blue-500 font-semibold uppercase">Doctor Number</p>
                            <p class="text-lg font-bold text-blue-700 mt-1"><?php echo htmlspecialchars($editDoctor["doctor_number"]); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($editDoctor["full_name"] ?? ""); ?>" placeholder="Dr. Full Name" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Registration Number <span class="text-red-500">*</span></label>
                            <input type="text" name="registration_number" required value="<?php echo htmlspecialchars($editDoctor["registration_number"] ?? ""); ?>" placeholder="Medical registration number" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Specialization <span class="text-red-500">*</span></label>
                            <input type="text" name="specialization" required value="<?php echo htmlspecialchars($editDoctor["specialization"] ?? ""); ?>" placeholder="e.g. Cardiology" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Qualification <span class="text-red-500">*</span></label>
                            <input type="text" name="qualification" required value="<?php echo htmlspecialchars($editDoctor["qualification"] ?? ""); ?>" placeholder="e.g. MBBS, MD" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (($editDoctor["gender"] ?? "") === "Male") ? "selected" : ""; ?>>Male</option>
                                <option value="Female" <?php echo (($editDoctor["gender"] ?? "") === "Female") ? "selected" : ""; ?>>Female</option>
                                <option value="Other" <?php echo (($editDoctor["gender"] ?? "") === "Other") ? "selected" : ""; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($editDoctor["date_of_birth"] ?? ""); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="phone" class="w-5 h-5 text-blue-600"></i>Contact Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" required value="<?php echo htmlspecialchars($editDoctor["phone"] ?? ""); ?>" placeholder="07XXXXXXXX" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($editDoctor["email"] ?? ""); ?>" placeholder="doctor@example.com" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">City</label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($editDoctor["city"] ?? ""); ?>" placeholder="City" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($editDoctor["address"] ?? ""); ?>" placeholder="Residential address" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Department</label>
                            <input type="text" name="department" value="<?php echo htmlspecialchars($editDoctor["department"] ?? ""); ?>" placeholder="e.g. OPD" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="briefcase-medical" class="w-5 h-5 text-blue-600"></i>Professional Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Years of Experience</label>
                            <input type="number" name="years_of_experience" min="0" max="60" value="<?php echo htmlspecialchars($editDoctor["years_of_experience"] ?? "0"); ?>" placeholder="0" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Consultation Fee (LKR)</label>
                            <input type="number" name="consultation_fee" min="0" step="0.01" value="<?php echo htmlspecialchars($editDoctor["consultation_fee"] ?? "0.00"); ?>" placeholder="0.00" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Joining Date <span class="text-red-500">*</span></label>
                            <input type="date" name="joining_date" required value="<?php echo htmlspecialchars($editDoctor["joining_date"] ?? date("Y-m-d")); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="settings-2" class="w-5 h-5 text-blue-600"></i>Account Status</h4>
                    <div class="max-w-sm">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <option value="Active" <?php echo (($editDoctor["status"] ?? "Active") === "Active") ? "selected" : ""; ?>>Active</option>
                            <option value="Inactive" <?php echo (($editDoctor["status"] ?? "") === "Inactive") ? "selected" : ""; ?>>Inactive</option>
                            <option value="On Leave" <?php echo (($editDoctor["status"] ?? "") === "On Leave") ? "selected" : ""; ?>>On Leave</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <button type="button" onclick="closeDoctorModal()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="<?php echo $editDoctor ? 'save' : 'user-plus'; ?>" class="w-5 h-5"></i>
                    <?php echo $editDoctor ? "Update Doctor" : "Register Doctor"; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    function openDoctorModal() {
        document.getElementById("doctorModal").classList.remove("hidden");
        document.getElementById("doctorModal").classList.add("flex");
    }

    function closeDoctorModal() {
        window.location.href = "index.php?page=doctors";
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