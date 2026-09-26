<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Suwapiyasa Medical Center | Hospital Management</title>
    <!-- Tailwind via CDN (default classes only) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-slate-50 text-slate-800">

    <!-- =========================
       NAVBAR
  ========================== -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="h-20 flex items-center justify-between">

                <!-- Logo -->
                <a href="#" class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-teal-600 flex items-center justify-center shadow-lg shadow-teal-600/20">
                        <i data-lucide="heart-pulse" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-slate-900">Suwapiyasa</h1>
                        <p class="text-xs text-slate-500">Medical Center</p>
                    </div>
                </a>

                <!-- Navigation -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="#home" class="text-sm font-medium text-slate-600 hover:text-teal-600 transition">Home</a>
                    <a href="#panels" class="text-sm font-medium text-slate-600 hover:text-teal-600 transition">System Panels</a>
                    <a href="#features" class="text-sm font-medium text-slate-600 hover:text-teal-600 transition">Features</a>
                </nav>



            </div>
        </div>
    </header>

    <!-- =========================
       HERO
  ========================== -->
    <main id="home">
        <section class="relative overflow-hidden">
            <!-- decorative blobs -->
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-teal-100 rounded-full blur-3xl opacity-70"></div>
            <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-blue-100 rounded-full blur-3xl opacity-70"></div>

            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center py-20 lg:py-28">

                    <!-- text -->
                    <div>
                        <div class="inline-flex items-center gap-2 bg-teal-50 border border-teal-100 text-teal-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
                            <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                            Digital Healthcare Management
                        </div>

                        <h2 class="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 leading-tight">
                            Smarter Healthcare.
                            <span class="text-teal-600">Better Care.</span>
                        </h2>

                        <p class="mt-6 text-lg text-slate-600 leading-8 max-w-xl">
                            A modern web-based hospital management system designed
                            to manage patients, appointments, medical records,
                            prescriptions and smart patient queues efficiently.
                        </p>

                        <div class="flex flex-col sm:flex-row gap-4 mt-8">
                            <a href="#panels" class="inline-flex items-center justify-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-6 py-3.5 rounded-xl font-semibold transition shadow-lg shadow-teal-600/20">
                                Access System <i data-lucide="arrow-right" class="w-5 h-5"></i>
                            </a>
                            <a href="#features" class="inline-flex items-center justify-center gap-2 bg-white border border-slate-200 hover:border-teal-300 hover:bg-teal-50 text-slate-700 px-6 py-3.5 rounded-xl font-semibold transition">
                                Explore Features <i data-lucide="layout-grid" class="w-5 h-5"></i>
                            </a>
                        </div>

                        <!-- trust -->
                        <div class="flex flex-wrap gap-6 mt-10 text-sm text-slate-500">
                            <div class="flex items-center gap-2"><i data-lucide="shield-check" class="w-5 h-5 text-teal-600"></i> Secure Access</div>
                            <div class="flex items-center gap-2"><i data-lucide="database" class="w-5 h-5 text-teal-600"></i> Digital Records</div>
                            <div class="flex items-center gap-2"><i data-lucide="clock-3" class="w-5 h-5 text-teal-600"></i> Smart Queue</div>
                        </div>
                    </div>

                    <!-- hero image (placeholder) -->
                    <div class="hidden lg:flex justify-center">
                        <div class="w-full max-w-md bg-teal-50 rounded-3xl flex items-center justify-center border border-teal-100 shadow-xl">
                            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRYmUW4BernFOHqGkW-YdpgJkj-HwpjAeshFG0oNnZmIw&s=10" alt="">
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- =========================
         PANELS
    ========================== -->
        <section id="panels" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    <span class="text-teal-600 font-semibold text-sm uppercase tracking-wider">System Access</span>
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mt-3">Choose Your Panel</h2>
                    <p class="text-slate-600 mt-4">Access the healthcare management system according to your role.</p>
                </div>

                <div class="grid md:grid-cols-3 gap-6 mt-12">

                    <!-- Admin -->
                    <div class="group bg-white border border-slate-200 rounded-3xl p-7 hover:border-blue-300 hover:shadow-xl hover:shadow-blue-100/50 transition duration-300">
                        <div class="flex items-center justify-between">
                            <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center group-hover:bg-blue-600 transition">
                                <i data-lucide="layout-dashboard" class="w-7 h-7 text-blue-600 group-hover:text-white transition"></i>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-full">PANEL 01</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mt-6">Admin Panel</h3>
                        <p class="text-slate-500 mt-3 leading-6">Manage hospital operations, users, doctors, patients, appointments, queues and reports.</p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-600">
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-blue-600"></i> User Management</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-blue-600"></i> Doctor & Patient Management</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-blue-600"></i> Appointments & Queue</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-blue-600"></i> Reports & Analytics</li>
                        </ul>
                        <a href="./admin/login.php" class="mt-7 w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold transition">
                            Admin Login <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                    <!-- Medical Staff -->
                    <div class="group bg-white border border-slate-200 rounded-3xl p-7 hover:border-teal-300 hover:shadow-xl hover:shadow-teal-100/50 transition duration-300">
                        <div class="flex items-center justify-between">
                            <div class="w-14 h-14 bg-teal-50 rounded-2xl flex items-center justify-center group-hover:bg-teal-600 transition">
                                <i data-lucide="stethoscope" class="w-7 h-7 text-teal-600 group-hover:text-white transition"></i>
                            </div>
                            <span class="text-xs font-semibold text-teal-600 bg-teal-50 px-3 py-1.5 rounded-full">PANEL 02</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mt-6">Medical Staff Panel</h3>
                        <p class="text-slate-500 mt-3 leading-6">Doctors and nurses manage consultations, queue patients, records and prescriptions.</p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-600">
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-teal-600"></i> Doctor Appointments</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-teal-600"></i> Patient Queue</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-teal-600"></i> Electronic Medical Records</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-teal-600"></i> Prescriptions & Vital Signs</li>
                        </ul>
                        <a href="./medical/login.php" class="mt-7 w-full inline-flex items-center justify-center gap-2 bg-teal-600 hover:bg-teal-700 text-white py-3 rounded-xl font-semibold transition">
                            Staff Login <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                    <!-- Patient -->
                    <div class="group bg-white border border-slate-200 rounded-3xl p-7 hover:border-purple-300 hover:shadow-xl hover:shadow-purple-100/50 transition duration-300">
                        <div class="flex items-center justify-between">
                            <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center group-hover:bg-purple-600 transition">
                                <i data-lucide="user-round" class="w-7 h-7 text-purple-600 group-hover:text-white transition"></i>
                            </div>
                            <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-3 py-1.5 rounded-full">PANEL 03</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mt-6">Patient Panel</h3>
                        <p class="text-slate-500 mt-3 leading-6">Patients can book appointments, monitor their queue, and access medical info.</p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-600">
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-purple-600"></i> Book Appointments</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-purple-600"></i> View Appointment History</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-purple-600"></i> Live Queue Status</li>
                            <li class="flex items-center gap-3"><i data-lucide="check-circle" class="w-4 h-4 text-purple-600"></i> Medical Records & Prescriptions</li>
                        </ul>
                        <a href="./patient/login.php" class="mt-7 w-full inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-semibold transition">
                            Patient Login <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                </div>
            </div>
        </section>

        <!-- =========================
         FEATURES
    ========================== -->
        <section id="features" class="py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto">
                    <span class="text-teal-600 font-semibold text-sm uppercase tracking-wider">Core Features</span>
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mt-3">Everything in One System</h2>
                    <p class="text-slate-600 mt-4">A centralized platform for efficient hospital administration and patient care.</p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-12">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6">
                        <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center"><i data-lucide="calendar-check" class="w-5 h-5 text-blue-600"></i></div>
                        <h3 class="font-bold text-slate-900 mt-5">Appointments</h3>
                        <p class="text-sm text-slate-500 mt-2 leading-6">Easy appointment booking and scheduling.</p>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6">
                        <div class="w-11 h-11 bg-teal-50 rounded-xl flex items-center justify-center"><i data-lucide="file-heart" class="w-5 h-5 text-teal-600"></i></div>
                        <h3 class="font-bold text-slate-900 mt-5">EMR</h3>
                        <p class="text-sm text-slate-500 mt-2 leading-6">Secure electronic medical records.</p>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6">
                        <div class="w-11 h-11 bg-amber-50 rounded-xl flex items-center justify-center"><i data-lucide="ticket" class="w-5 h-5 text-amber-600"></i></div>
                        <h3 class="font-bold text-slate-900 mt-5">Smart Queue</h3>
                        <p class="text-sm text-slate-500 mt-2 leading-6">Token-based patient queue management.</p>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6">
                        <div class="w-11 h-11 bg-purple-50 rounded-xl flex items-center justify-center"><i data-lucide="shield-check" class="w-5 h-5 text-purple-600"></i></div>
                        <h3 class="font-bold text-slate-900 mt-5">Secure Access</h3>
                        <p class="text-sm text-slate-500 mt-2 leading-6">Role-based access and protected data.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================
         CTA
    ========================== -->
        <section class="py-20 bg-teal-900">
            <div class="max-w-4xl mx-auto px-6 text-center">
                <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center mx-auto">
                    <i data-lucide="heart-pulse" class="w-8 h-8 text-white"></i>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold text-white mt-6">Better Management. Better Patient Care.</h2>
                <p class="text-teal-100 mt-4 max-w-2xl mx-auto leading-7">
                    Suwapiyasa Medical Center uses a centralized digital platform to improve healthcare service coordination and patient experience.
                </p>
                <a href="#panels" class="inline-flex items-center gap-2 mt-8 bg-white text-teal-800 hover:bg-teal-50 px-6 py-3.5 rounded-xl font-semibold transition">
                    Access System <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </a>
            </div>
        </section>
    </main>

    <!-- =========================
       FOOTER
  ========================== -->
    <footer class="bg-slate-950 text-slate-400">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-10">
            <div class="flex flex-col md:flex-row justify-between gap-8">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-teal-600 rounded-xl flex items-center justify-center">
                            <i data-lucide="heart-pulse" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <p class="text-white font-bold">Suwapiyasa Medical Center</p>
                            <p class="text-xs text-slate-500">Hospital Management System</p>
                        </div>
                    </div>
                    <p class="text-sm mt-4 max-w-md leading-6">A web-based healthcare management solution for efficient hospital operations and improved patient care.</p>
                </div>
                <div class="text-sm">
                    <p class="text-white font-semibold mb-3">Quick Access</p>
                    <div class="space-y-2">
                        <a href="#" class="block hover:text-white transition">Admin Panel</a>
                        <a href="#" class="block hover:text-white transition">Medical Staff</a>
                        <a href="#" class="block hover:text-white transition">Patient Panel</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-800 mt-8 pt-6 text-xs text-slate-500 flex flex-col sm:flex-row justify-between gap-3">
                <p>© 2026 Suwapiyasa Medical Center. All rights reserved.</p>
                <p>Web-Based Hospital Management System</p>
            </div>
        </div>
    </footer>

    <!-- Lucide -->
    <script>
        lucide.createIcons();
    </script>
</body>

</html>