<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

$today = date("Y-m-d");

// Total patients
$totalPatients = (int) $conn->query("SELECT COUNT(*) AS c FROM patients")->fetch_assoc()["c"];

// Total appointments
$totalAppointments = (int) $conn->query("SELECT COUNT(*) AS c FROM appointments")->fetch_assoc()["c"];

// Today's queue
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM queue WHERE queue_date = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todayQueue = (int) $stmt->get_result()->fetch_assoc()["c"];

// Total doctors
$totalDoctors = (int) $conn->query("SELECT COUNT(*) AS c FROM doctors WHERE status = 'Active'")->fetch_assoc()["c"];

// Today's visits (appointments today)
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todayVisits = (int) $stmt->get_result()->fetch_assoc()["c"];

// Total nurses
$totalNurses = (int) $conn->query("SELECT COUNT(*) AS c FROM nurses WHERE status = 'Active'")->fetch_assoc()["c"];

// Total prescriptions
$totalPrescriptions = (int) $conn->query("SELECT COUNT(*) AS c FROM prescriptions")->fetch_assoc()["c"];
?>
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Admin Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Overview of hospital operations.</p>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
            <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
            <span class="text-sm font-medium text-slate-600"><?= date("d M Y") ?></span>
        </div>
    </div>

    <!-- Main Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Total Patients</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1"><?= $totalPatients ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-xl">
                    <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Appointments</p>
                    <p class="text-3xl font-bold text-emerald-600 mt-1"><?= $totalAppointments ?></p>
                </div>
                <div class="bg-emerald-100 p-3 rounded-xl">
                    <i data-lucide="calendar-check" class="w-6 h-6 text-emerald-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Today's Queue</p>
                    <p class="text-3xl font-bold text-amber-600 mt-1"><?= $todayQueue ?></p>
                </div>
                <div class="bg-amber-100 p-3 rounded-xl">
                    <i data-lucide="list-ordered" class="w-6 h-6 text-amber-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Doctors</p>
                    <p class="text-3xl font-bold text-indigo-600 mt-1"><?= $totalDoctors ?></p>
                </div>
                <div class="bg-indigo-100 p-3 rounded-xl">
                    <i data-lucide="stethoscope" class="w-6 h-6 text-indigo-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Today's Visits</p>
                    <p class="text-3xl font-bold text-rose-600 mt-1"><?= $todayVisits ?></p>
                </div>
                <div class="bg-rose-100 p-3 rounded-xl">
                    <i data-lucide="activity" class="w-6 h-6 text-rose-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Extra -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Nurses</p>
                    <p class="text-3xl font-bold text-teal-600 mt-1"><?= $totalNurses ?></p>
                </div>
                <div class="bg-teal-100 p-3 rounded-xl">
                    <i data-lucide="heart-pulse" class="w-6 h-6 text-teal-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Prescriptions</p>
                    <p class="text-3xl font-bold text-purple-600 mt-1"><?= $totalPrescriptions ?></p>
                </div>
                <div class="bg-purple-100 p-3 rounded-xl">
                    <i data-lucide="pill" class="w-6 h-6 text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    lucide.createIcons();
</script>