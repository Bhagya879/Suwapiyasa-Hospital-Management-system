<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requirePatientRole();

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "User";

$page = $_GET["page"] ?? "dashboard";

$allowedPages = [
    "dashboard",
    "book_appointment",
    "my_appointments",
    "my_medical_records",
    "my_queue",
    "medical_documents",
    "prescriptions",
    "vital_signs"
];

if (!in_array($page, $allowedPages, true)) {
    $page = "dashboard";
}

$pageTitles = [
    "dashboard" => "Dashboard",
    "book_appointment" => "Book Appointment",
    "my_appointments" => "My Appointments",
    "my_medical_records" => "My Medical Records",
    "my_queue" => "My Queue",
    "medical_documents" => "Medical Documents",
    "prescriptions" => "Prescriptions",
    "vital_signs" => "Vital Signs"
];

$currentTitle = $pageTitles[$page] ?? "Dashboard";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentTitle); ?> | Suwapiyasa Medical Center</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Toastr CSS -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <style>
        #toast-container>.toast {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .scrollbar-thin-transparent {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
        }

        .scrollbar-thin-transparent::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .scrollbar-thin-transparent::-webkit-scrollbar-track {
            background: transparent;
        }

        .scrollbar-thin-transparent::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.3);
            border-radius: 9999px;
        }

        .scrollbar-thin-transparent::-webkit-scrollbar-thumb:hover {
            background-color: rgba(148, 163, 184, 0.6);
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800">

    <div class="min-h-screen flex">

        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 text-white fixed inset-y-0 left-0 z-40 flex flex-col">

            <!-- Logo -->
            <div class="h-20 flex-shrink-0 flex items-center px-6 border-b border-slate-800">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center mr-3">
                    <i data-lucide="heart-pulse" class="w-6 h-6"></i>
                </div>

                <div>
                    <h1 class="font-bold text-lg">Suwapiyasa</h1>
                    <p class="text-xs text-slate-400">Medical Center</p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto p-4 space-y-2 scrollbar-thin-transparent">

                <!-- Dashboard -->
                <a href="index.php?page=dashboard"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'dashboard'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Book Appointment -->
                <a href="index.php?page=book_appointment"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'book_appointment'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                    <span>Book Appointment</span>
                </a>

                <!-- My Appointments -->
                <a href="index.php?page=my_appointments"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'my_appointments'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                    <span>My Appointments</span>
                </a>

                <!-- My Medical Records -->
                <a href="index.php?page=my_medical_records"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'my_medical_records'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="file-heart" class="w-5 h-5"></i>
                    <span>My Medical Records</span>
                </a>

                <!-- My Queue -->
                <a href="index.php?page=my_queue"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'my_queue'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="list-ordered" class="w-5 h-5"></i>
                    <span>My Queue</span>
                </a>

                <!-- Medical Documents -->
                <a href="index.php?page=medical_documents"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'medical_documents'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                    <span>Medical Documents</span>
                </a>

                <!-- Prescriptions -->
                <a href="index.php?page=prescriptions"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'prescriptions'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="pill" class="w-5 h-5"></i>
                    <span>Prescriptions</span>
                </a>

                <!-- Vital Signs -->
                <a href="index.php?page=vital_signs"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
                    <?php echo $page === 'vital_signs'
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="heart-pulse" class="w-5 h-5"></i>
                    <span>Vital Signs</span>
                </a>

            </nav>

            <!-- User -->
            <div class="flex-shrink-0 p-4 border-t border-slate-800 bg-slate-900">

                <div class="flex items-center gap-3 mb-3">

                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <i data-lucide="user-round" class="w-5 h-5"></i>
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate">
                            <?php echo htmlspecialchars($username); ?>
                        </p>

                        <p class="text-xs text-slate-400 truncate capitalize">
                            <?php echo htmlspecialchars($role); ?>
                        </p>
                    </div>

                </div>

                <a href="./logout.php"
                    class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    <span>Logout</span>
                </a>

            </div>

        </aside>

        <!-- Main -->
        <div class="ml-64 flex-1 min-h-screen">

            <!-- Header -->
            <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-8">

                <div>
                    <h2 class="text-2xl font-bold text-slate-900">
                        <?php echo htmlspecialchars($currentTitle); ?>
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Suwapiyasa Medical Center
                    </p>
                </div>

                <div class="flex items-center gap-3">

                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-700">
                            <?php echo htmlspecialchars($username); ?>
                        </p>

                        <p class="text-xs text-slate-400 capitalize">
                            <?php echo htmlspecialchars($role); ?>
                        </p>
                    </div>

                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                        <i data-lucide="user-round" class="w-5 h-5"></i>
                    </div>

                </div>

            </header>

            <!-- Content -->
            <main class="p-8">

                <?php

                $file = __DIR__ . "/" . $page . ".php";

                if (file_exists($file)) {
                    include $file;
                } else {
                    echo '
                    <div class="bg-white rounded-2xl p-8 text-center">
                        <h2 class="text-xl font-bold text-slate-800">Page Not Found</h2>
                    </div>';
                }

                ?>

            </main>

        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>

</body>

</html>