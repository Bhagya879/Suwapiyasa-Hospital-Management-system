<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";


$message = "";
$messageType = "";

$userId = $_SESSION['user_id'] ?? 0;

function generatePatientNumber($conn)
{
    $result = $conn->query("SELECT patient_number FROM patients ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $number = (int) preg_replace('/[^0-9]/', '', $row["patient_number"]);
        return "PAT" . str_pad($number + 1, 6, "0", STR_PAD_LEFT);
    }
    return "PAT000001";
}

// Check if this user already has a patient record
$existingStmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
$existingStmt->bind_param("i", $userId);
$existingStmt->execute();
$existingPatient = $existingStmt->get_result()->fetch_assoc();

if ($existingPatient) {
    $message = "You already have a patient record.";
    $messageType = "info";
}

/* =================== REGISTER =================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "register" && !$existingPatient) {
    $full_name         = trim($_POST["full_name"] ?? "");
    $nic               = trim($_POST["nic"] ?? "");
    $date_of_birth     = $_POST["date_of_birth"] ?? "";
    $gender            = $_POST["gender"] ?? "";
    $blood_group       = $_POST["blood_group"] ?? "";
    $phone             = trim($_POST["phone"] ?? "");
    $email             = trim($_POST["email"] ?? "");
    $address           = trim($_POST["address"] ?? "");
    $city              = trim($_POST["city"] ?? "");
    $emergency_name    = trim($_POST["emergency_contact_name"] ?? "");
    $emergency_phone   = trim($_POST["emergency_contact_phone"] ?? "");
    $emergency_rel     = trim($_POST["emergency_contact_relationship"] ?? "");
    $allergies         = trim($_POST["allergies"] ?? "");
    $chronic           = trim($_POST["chronic_conditions"] ?? "");
    $medications       = trim($_POST["current_medications"] ?? "");

    // Validation
    if ($full_name === "" || $date_of_birth === "" || $gender === "" || $phone === "" || $address === "") {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif ($nic !== "") {
        $nicCheck = $conn->prepare("SELECT id FROM patients WHERE nic = ? LIMIT 1");
        $nicCheck->bind_param("s", $nic);
        $nicCheck->execute();
        if ($nicCheck->get_result()->num_rows > 0) {
            $message = "NIC already exists.";
            $messageType = "error";
        }
    }

    if ($message === "") {
        $conn->begin_transaction();
        try {
            $patientNumber = generatePatientNumber($conn);
            $patientStmt = $conn->prepare(
                "INSERT INTO patients (
                    user_id, patient_number, full_name, nic, date_of_birth, gender,
                    blood_group, phone, email, address, city,
                    emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                    allergies, chronic_conditions, current_medications,
                    registration_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Active')"
            );
            $patientStmt->bind_param(
                "issssssssssssssss",
                $userId,
                $patientNumber,
                $full_name,
                $nic,
                $date_of_birth,
                $gender,
                $blood_group,
                $phone,
                $email,
                $address,
                $city,
                $emergency_name,
                $emergency_phone,
                $emergency_rel,
                $allergies,
                $chronic,
                $medications
            );
            if (!$patientStmt->execute()) throw new Exception("Failed to create patient record.");
            $conn->commit();
            $message = "Registration successful. Your Patient Number: " . $patientNumber;
            $messageType = "success";
            $existingPatient = true; // prevent re-submission
        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-slate-50 min-h-screen">

    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Patient Registration</h2>
                <p class="text-sm text-slate-500 mt-1">Complete your patient profile to get started.</p>
            </div>
            <a href="login.php"
                class="inline-flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-3 rounded-xl font-semibold transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                Back to Login
            </a>
        </div>

        <?php if ($existingPatient && $messageType !== "success"): ?>
            <!-- Already registered -->
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 text-center">
                <i data-lucide="info" class="w-12 h-12 text-blue-500 mx-auto mb-3"></i>
                <h3 class="text-lg font-bold text-blue-800">You already have a patient record</h3>
                <p class="text-blue-600 mt-1">No further action is needed.</p>
                <a href="login.php"
                    class="inline-flex items-center gap-2 mt-4 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    Go to Login
                </a>
            </div>
        <?php elseif ($messageType === "success"): ?>
            <!-- Success -->
            <div class="bg-green-50 border border-green-200 rounded-2xl p-6 text-center">
                <i data-lucide="check-circle" class="w-12 h-12 text-green-500 mx-auto mb-3"></i>
                <h3 class="text-lg font-bold text-green-800">Registration Successful</h3>
                <p class="text-green-700 mt-2"><?php echo htmlspecialchars($message); ?></p>
                <a href="index.php?page=dashboard"
                    class="inline-flex items-center gap-2 mt-4 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    Go to Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Registration Form -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="register">

                    <div class="space-y-7">
                        <!-- Patient information -->
                        <div>
                            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                                <i data-lucide="user-round" class="w-5 h-5 text-blue-600"></i>
                                Personal Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <div class="lg:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="full_name" required
                                        placeholder="Enter full name"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">NIC</label>
                                    <input type="text" name="nic"
                                        placeholder="NIC number"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Date of Birth <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="date_of_birth" required
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Gender <span class="text-red-500">*</span>
                                    </label>
                                    <select name="gender" required
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Blood Group</label>
                                    <select name="blood_group"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                        <option value="">Select Blood Group</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Contact information -->
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
                                    <input type="text" name="phone" required
                                        placeholder="07XXXXXXXX"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                                    <input type="email" name="email"
                                        placeholder="patient@example.com"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">City</label>
                                    <input type="text" name="city"
                                        placeholder="City"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">
                                        Address <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="address" required rows="3"
                                        placeholder="Residential address"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency contact -->
                        <div class="border-t border-slate-200 pt-6">
                            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                                <i data-lucide="phone-call" class="w-5 h-5 text-blue-600"></i>
                                Emergency Contact
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Contact Name</label>
                                    <input type="text" name="emergency_contact_name"
                                        placeholder="Emergency contact name"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Contact Phone</label>
                                    <input type="text" name="emergency_contact_phone"
                                        placeholder="Emergency contact phone"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Relationship</label>
                                    <input type="text" name="emergency_contact_relationship"
                                        placeholder="e.g. Mother, Father"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Medical information -->
                        <div class="border-t border-slate-200 pt-6">
                            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                                <i data-lucide="clipboard-medical" class="w-5 h-5 text-blue-600"></i>
                                Medical Information
                            </h4>
                            <div class="grid grid-cols-1 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Allergies</label>
                                    <textarea name="allergies" rows="2"
                                        placeholder="Known drug, food or other allergies"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Chronic Conditions</label>
                                    <textarea name="chronic_conditions" rows="2"
                                        placeholder="e.g. Diabetes, Hypertension"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Current Medications</label>
                                    <textarea name="current_medications" rows="2"
                                        placeholder="Current medicines if applicable"
                                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
                        <a href="index.php?page=dashboard"
                            class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold">
                            <i data-lucide="user-plus" class="w-5 h-5"></i>
                            Register
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
        lucide.createIcons();

        <?php if ($message !== "" && $messageType !== "success"): ?>
            document.addEventListener("DOMContentLoaded", function() {
                <?php if ($messageType === "error"): ?>
                    toastr.error(<?php echo json_encode($message); ?>, "Error");
                <?php elseif ($messageType === "info"): ?>
                    toastr.info(<?php echo json_encode($message); ?>, "Info");
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>

</body>

</html>