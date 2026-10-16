<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();
$message = "";
$messageType = "";
$editNurse = null;
$viewNurse = null;
/* Generate Nurse Number */
function generateNurseNumber($conn)
{
    $result = $conn->query("SELECT nurse_number FROM nurses ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $number = (int) preg_replace('/[^0-9]/', '', $row["nurse_number"]);
        return "NUR" . str_pad($number + 1, 6, "0", STR_PAD_LEFT);
    }
    return "NUR000001";
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    /* Add Nurse */
    if ($action === "add") {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $registrationNumber = trim($_POST["registration_number"] ?? "");
        $qualification = trim($_POST["qualification"] ?? "");
        $specialization = trim($_POST["specialization"] ?? "");
        $gender = $_POST["gender"] ?? "";
        $dateOfBirth = $_POST["date_of_birth"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $yearsOfExperience = (int) ($_POST["years_of_experience"] ?? 0);
        $joiningDate = $_POST["joining_date"] ?? date("Y-m-d");
        $status = $_POST["status"] ?? "Active";
        if ($username === "" || $password === "" || $fullName === "" || $registrationNumber === "" || $qualification === "" || $gender === "" || $phone === "" || $joiningDate === "") {
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
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Username already exists.";
                $messageType = "error";
            } else {
                $checkReg = $conn->prepare("SELECT id FROM nurses WHERE registration_number = ? LIMIT 1");
                $checkReg->bind_param("s", $registrationNumber);
                $checkReg->execute();
                if ($checkReg->get_result()->num_rows > 0) {
                    $message = "Nurse registration number already exists.";
                    $messageType = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $userStmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'nurse')");
                        $userStmt->bind_param("ss", $username, $hashedPassword);
                        if (!$userStmt->execute()) {
                            throw new Exception("Failed to create nurse account.");
                        }
                        $userId = $conn->insert_id;
                        $nurseNumber = generateNurseNumber($conn);
                        $nurseStmt = $conn->prepare("INSERT INTO nurses (user_id, nurse_number, registration_number, full_name, qualification, specialization, gender, date_of_birth, phone, email, address, city, department, years_of_experience, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $nurseStmt->bind_param("issssssssssssiss", $userId, $nurseNumber, $registrationNumber, $fullName, $qualification, $specialization, $gender, $dateOfBirth, $phone, $email, $address, $city, $department, $yearsOfExperience, $joiningDate, $status);
                        if (!$nurseStmt->execute()) {
                            throw new Exception("Failed to create nurse record.");
                        }
                        $conn->commit();
                        $message = "Nurse registered successfully. Nurse Number: " . $nurseNumber;
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
    /* Update Nurse */
    if ($action === "update") {
        $nurseId = (int) ($_POST["nurse_id"] ?? 0);
        $userId = (int) ($_POST["user_id"] ?? 0);
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $registrationNumber = trim($_POST["registration_number"] ?? "");
        $qualification = trim($_POST["qualification"] ?? "");
        $specialization = trim($_POST["specialization"] ?? "");
        $gender = $_POST["gender"] ?? "";
        $dateOfBirth = $_POST["date_of_birth"] ?? "";
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $yearsOfExperience = (int) ($_POST["years_of_experience"] ?? 0);
        $joiningDate = $_POST["joining_date"] ?? "";
        $status = $_POST["status"] ?? "Active";
        if ($nurseId <= 0 || $userId <= 0 || $username === "" || $fullName === "" || $registrationNumber === "" || $qualification === "" || $gender === "" || $phone === "" || $joiningDate === "") {
            $message = "Please fill in all required fields.";
            $messageType = "error";
        } elseif ($yearsOfExperience < 0) {
            $message = "Years of experience cannot be negative.";
            $messageType = "error";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
            $check->bind_param("si", $username, $userId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Username already exists.";
                $messageType = "error";
            } else {
                $checkReg = $conn->prepare("SELECT id FROM nurses WHERE registration_number = ? AND id != ? LIMIT 1");
                $checkReg->bind_param("si", $registrationNumber, $nurseId);
                $checkReg->execute();
                if ($checkReg->get_result()->num_rows > 0) {
                    $message = "Nurse registration number already exists.";
                    $messageType = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        if ($password !== "") {
                            if (strlen($password) < 6) {
                                throw new Exception("Password must contain at least 6 characters.");
                            }
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $userStmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = 'nurse' WHERE id = ?");
                            $userStmt->bind_param("ssi", $username, $hashedPassword, $userId);
                        } else {
                            $userStmt = $conn->prepare("UPDATE users SET username = ?, role = 'nurse' WHERE id = ?");
                            $userStmt->bind_param("si", $username, $userId);
                        }
                        if (!$userStmt->execute()) {
                            throw new Exception("Failed to update nurse account.");
                        }
                        $nurseStmt = $conn->prepare("UPDATE nurses SET full_name = ?, registration_number = ?, qualification = ?, specialization = ?, gender = ?, date_of_birth = ?, phone = ?, email = ?, address = ?, city = ?, department = ?, years_of_experience = ?, joining_date = ?, status = ? WHERE id = ? AND user_id = ?");
                        $nurseStmt->bind_param("sssssssssssissii", $fullName, $registrationNumber, $qualification, $specialization, $gender, $dateOfBirth, $phone, $email, $address, $city, $department, $yearsOfExperience, $joiningDate, $status, $nurseId, $userId);
                        if (!$nurseStmt->execute()) {
                            throw new Exception("Failed to update nurse information.");
                        }
                        $conn->commit();
                        $message = "Nurse updated successfully.";
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
    /* Delete Nurse */
    if ($action === "delete") {
        $nurseId = (int) ($_POST["nurse_id"] ?? 0);
        $userId = (int) ($_POST["user_id"] ?? 0);
        if ($nurseId > 0 && $userId > 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'nurse'");
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to delete nurse.");
                }
                $conn->commit();
                $message = "Nurse deleted successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Invalid nurse.";
            $messageType = "error";
        }
    }
}
/* View Nurse */
if (isset($_GET["view"])) {
    $nurseId = (int) $_GET["view"];
    $stmt = $conn->prepare("SELECT n.*, u.username FROM nurses n INNER JOIN users u ON n.user_id = u.id WHERE n.id = ? LIMIT 1");
    $stmt->bind_param("i", $nurseId);
    $stmt->execute();
    $viewNurse = $stmt->get_result()->fetch_assoc();
}
/* Edit Nurse */
if (isset($_GET["edit"])) {
    $nurseId = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT n.*, u.username FROM nurses n INNER JOIN users u ON n.user_id = u.id WHERE n.id = ? LIMIT 1");
    $stmt->bind_param("i", $nurseId);
    $stmt->execute();
    $editNurse = $stmt->get_result()->fetch_assoc();
}
/* Search Nurses */
$search = trim($_GET["search"] ?? "");
if ($search !== "") {
    $stmt = $conn->prepare("SELECT n.*, u.username FROM nurses n INNER JOIN users u ON n.user_id = u.id WHERE n.nurse_number LIKE ? OR n.full_name LIKE ? OR n.registration_number LIKE ? OR n.specialization LIKE ? OR n.phone LIKE ? ORDER BY n.id DESC");
    $searchValue = "%" . $search . "%";
    $stmt->bind_param("sssss", $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
    $stmt->execute();
    $nurses = $stmt->get_result();
} else {
    $nurses = $conn->query("SELECT n.*, u.username FROM nurses n INNER JOIN users u ON n.user_id = u.id ORDER BY n.id DESC");
}
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Nurse Management</h2>
            <p class="text-sm text-slate-500 mt-1">Register and manage nurse information and accounts.</p>
        </div>
        <button onclick="openNurseModal()" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold transition">
            <i data-lucide="user-plus" class="w-5 h-5"></i>
            Add Nurse
        </button>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-3">
            <input type="hidden" name="page" value="nurses">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by nurse number, name, registration, specialization or phone..." class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <button type="submit" class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-medium transition">Search</button>
            <?php if ($search !== ""): ?>
                <a href="index.php?page=nurses" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition text-center">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200">
            <h3 class="font-bold text-lg text-slate-900">Registered Nurses</h3>
            <p class="text-sm text-slate-500">Total records: <?php echo $nurses ? $nurses->num_rows : 0; ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Nurse</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Specialization</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Contact</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Department</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($nurses && $nurses->num_rows > 0): ?>
                        <?php while ($nurse = $nurses->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-pink-100 text-pink-600 flex items-center justify-center flex-shrink-0">
                                            <i data-lucide="heart-pulse" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($nurse["full_name"]); ?></p>
                                            <p class="text-xs text-blue-600 font-medium"><?php echo htmlspecialchars($nurse["nurse_number"]); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($nurse["specialization"] ?: "-"); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($nurse["registration_number"]); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-slate-700"><?php echo htmlspecialchars($nurse["phone"]); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($nurse["email"] ?: "-"); ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($nurse["department"] ?: "-"); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($nurse["status"] === "Active"): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-green-50 text-green-700 text-xs font-semibold">Active</span>
                                    <?php elseif ($nurse["status"] === "On Leave"): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold">On Leave</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-semibold">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="index.php?page=nurses&view=<?php echo $nurse["id"]; ?>" class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="View">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <a href="index.php?page=nurses&edit=<?php echo $nurse["id"]; ?>" class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center" title="Edit">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this nurse?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="nurse_id" value="<?php echo $nurse["id"]; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $nurse["user_id"]; ?>">
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
                                        <i data-lucide="heart-pulse" class="w-7 h-7 text-slate-400"></i>
                                    </div>
                                    <h3 class="font-semibold text-slate-700">No nurses found</h3>
                                    <p class="text-sm text-slate-400 mt-1">Add a nurse to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($viewNurse): ?>
    <div id="viewModal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Nurse Details</h3>
                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($viewNurse["nurse_number"]); ?></p>
                </div>
                <a href="index.php?page=nurses" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Full Name</p>
                        <p class="mt-1 font-medium text-slate-800"><?php echo htmlspecialchars($viewNurse["full_name"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Username</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["username"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Nurse Number</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["nurse_number"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Registration Number</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["registration_number"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Qualification</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["qualification"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Specialization</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["specialization"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Gender</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["gender"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Date of Birth</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["date_of_birth"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Phone</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["phone"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Email</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["email"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Department</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["department"] ?: "-"); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Experience</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["years_of_experience"]); ?> years</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Joining Date</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["joining_date"]); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase">Status</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["status"]); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-400 uppercase">Address</p>
                        <p class="mt-1 text-slate-700"><?php echo htmlspecialchars($viewNurse["address"] ?: "-"); ?></p>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-5 mt-6 flex justify-end">
                    <a href="index.php?page=nurses&edit=<?php echo $viewNurse["id"]; ?>" class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        Edit Nurse
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<div id="nurseModal" class="<?php echo $editNurse ? 'flex' : 'hidden'; ?> fixed inset-0 bg-slate-900/60 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div>
                <h3 class="text-xl font-bold text-slate-900"><?php echo $editNurse ? "Edit Nurse" : "Add Nurse"; ?></h3>
                <p class="text-sm text-slate-500"><?php echo $editNurse ? "Update nurse information" : "Register a new nurse"; ?></p>
            </div>
            <button type="button" onclick="closeNurseModal()" class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="<?php echo $editNurse ? 'update' : 'add'; ?>">
            <?php if ($editNurse): ?>
                <input type="hidden" name="nurse_id" value="<?php echo $editNurse["id"]; ?>">
                <input type="hidden" name="user_id" value="<?php echo $editNurse["user_id"]; ?>">
            <?php endif; ?>
            <div class="space-y-7">
                <div>
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="shield-user" class="w-5 h-5 text-blue-600"></i>Login Account</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" required value="<?php echo htmlspecialchars($editNurse["username"] ?? ""); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2"><?php echo $editNurse ? "New Password" : "Password"; ?><?php if (!$editNurse): ?><span class="text-red-500">*</span><?php endif; ?></label>
                            <input type="password" name="password" <?php echo !$editNurse ? "required" : ""; ?> placeholder="<?php echo $editNurse ? "Leave blank to keep current password" : "Minimum 6 characters"; ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <?php if (!$editNurse): ?>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="heart-pulse" class="w-5 h-5 text-blue-600"></i>Professional Information</h4>
                    <?php if ($editNurse): ?>
                        <div class="mb-5 p-4 bg-blue-50 border border-blue-100 rounded-xl">
                            <p class="text-xs text-blue-500 font-semibold uppercase">Nurse Number</p>
                            <p class="text-lg font-bold text-blue-700 mt-1"><?php echo htmlspecialchars($editNurse["nurse_number"]); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($editNurse["full_name"] ?? ""); ?>" placeholder="Full Name" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Registration Number <span class="text-red-500">*</span></label>
                            <input type="text" name="registration_number" required value="<?php echo htmlspecialchars($editNurse["registration_number"] ?? ""); ?>" placeholder="Nursing registration number" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Qualification <span class="text-red-500">*</span></label>
                            <input type="text" name="qualification" required value="<?php echo htmlspecialchars($editNurse["qualification"] ?? ""); ?>" placeholder="e.g. BSc Nursing" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Specialization</label>
                            <input type="text" name="specialization" value="<?php echo htmlspecialchars($editNurse["specialization"] ?? ""); ?>" placeholder="e.g. General Nursing" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (($editNurse["gender"] ?? "") === "Male") ? "selected" : ""; ?>>Male</option>
                                <option value="Female" <?php echo (($editNurse["gender"] ?? "") === "Female") ? "selected" : ""; ?>>Female</option>
                                <option value="Other" <?php echo (($editNurse["gender"] ?? "") === "Other") ? "selected" : ""; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($editNurse["date_of_birth"] ?? ""); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="phone" class="w-5 h-5 text-blue-600"></i>Contact Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" required value="<?php echo htmlspecialchars($editNurse["phone"] ?? ""); ?>" placeholder="07XXXXXXXX" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($editNurse["email"] ?? ""); ?>" placeholder="nurse@example.com" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">City</label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($editNurse["city"] ?? ""); ?>" placeholder="City" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($editNurse["address"] ?? ""); ?>" placeholder="Residential address" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Department</label>
                            <input type="text" name="department" value="<?php echo htmlspecialchars($editNurse["department"] ?? ""); ?>" placeholder="e.g. OPD" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="briefcase-medical" class="w-5 h-5 text-blue-600"></i>Professional Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Years of Experience</label>
                            <input type="number" name="years_of_experience" min="0" max="60" value="<?php echo htmlspecialchars($editNurse["years_of_experience"] ?? "0"); ?>" placeholder="0" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Joining Date <span class="text-red-500">*</span></label>
                            <input type="date" name="joining_date" required value="<?php echo htmlspecialchars($editNurse["joining_date"] ?? date("Y-m-d")); ?>" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 pt-6">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i data-lucide="settings-2" class="w-5 h-5 text-blue-600"></i>Account Status</h4>
                    <div class="max-w-sm">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <option value="Active" <?php echo (($editNurse["status"] ?? "Active") === "Active") ? "selected" : ""; ?>>Active</option>
                            <option value="Inactive" <?php echo (($editNurse["status"] ?? "") === "Inactive") ? "selected" : ""; ?>>Inactive</option>
                            <option value="On Leave" <?php echo (($editNurse["status"] ?? "") === "On Leave") ? "selected" : ""; ?>>On Leave</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                <button type="button" onclick="closeNurseModal()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="<?php echo $editNurse ? 'save' : 'user-plus'; ?>" class="w-5 h-5"></i>
                    <?php echo $editNurse ? "Update Nurse" : "Register Nurse"; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    function openNurseModal() {
        document.getElementById("nurseModal").classList.remove("hidden");
        document.getElementById("nurseModal").classList.add("flex");
    }

    function closeNurseModal() {
        window.location.href = "index.php?page=nurses";
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