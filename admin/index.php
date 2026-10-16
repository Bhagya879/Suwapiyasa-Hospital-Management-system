<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth/checkauth.php";
requireAdminRole();

$username = $_SESSION["username"];
$role     = $_SESSION["role"] ?? "User";

$page = $_GET["page"] ?? "dashboard";

$allowedPages = [
    "dashboard",
    "nurse",
    "patients",
    "doctors",
    "schedules",
    "appointments",
    "queue",
    "reports"
];

if (!in_array($page, $allowedPages, true)) {
    $page = "dashboard";
}

$pageTitles = [
    "nurse" => "Nurse",
    "dashboard" => "Dashboard",
    "patients" => "Patients",
    "doctors" => "Doctors",
    "schedules" => "Schedules",
    "appointments" => "Appointments",
    "queue" => "Queue",
    "reports" => "Reports"
];

$currentTitle = $pageTitles[$page];
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
    <!-- jQuery - Required by Toastr -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Toastr CSS -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        /* Toastr customization */
        #toast-container>.toast {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
    </style>
    <style>
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

            <div class="h-20 flex-shrink-0 flex items-center px-6 border-b border-slate-800">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center mr-3">
                    <i data-lucide="heart-pulse" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg">Suwapiyasa</h1>
                    <p class="text-xs text-slate-400">Medical Center</p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto p-4 space-y-2 scrollbar-thin-transparent">

                <a href="index.php?page=dashboard"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'dashboard'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>Dashboard</span>
                </a>

                <a href="index.php?page=patients"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'patients'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="user-round" class="w-5 h-5"></i>
                    <span>Patients Management</span>
                </a>

                <a href="index.php?page=doctors"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'doctors'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="stethoscope" class="w-5 h-5"></i>
                    <span>Doctors Management</span>
                </a>

                <a href="index.php?page=nurse"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'nurse'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="user-nurse" class="w-5 h-5"></i>
                    <span>Nurse Management</span>
                </a>

                <a href="index.php?page=schedules"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'schedules'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                    <span>Schedules Management</span>
                </a>

                <a href="index.php?page=appointments"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'appointments'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                    <span>Appointments Management</span>
                </a>

                <a href="index.php?page=queue"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'queue'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="list-ordered" class="w-5 h-5"></i>
                    <span>Queue Management</span>
                </a>

                <a href="index.php?page=reports"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition
               <?php echo $page === 'reports'
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800'; ?>">
                    <i data-lucide="file-bar-chart" class="w-5 h-5"></i>
                    <span>Reports Management</span>
                </a>

            </nav>

            <div class="flex-shrink-0 p-4 border-t border-slate-800 bg-slate-900">

                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <i data-lucide="shield-user" class="w-5 h-5"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate">
                            <?php echo htmlspecialchars($username); ?>
                        </p>
                        <p class="text-xs text-slate-400 truncate">
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
                        <p class="text-xs text-slate-400">
                            <?php echo htmlspecialchars($role); ?>
                        </p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                </div>

            </header>

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