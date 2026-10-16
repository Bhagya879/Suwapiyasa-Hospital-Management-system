<?php
session_start();
require_once __DIR__ . "/../config.php";
$error = "";
$loginSuccess = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {
        $sql = "SELECT id, username, password, role
                FROM users
                WHERE username = ? AND role = 'doctor' OR role = 'nurse'
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($user = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $user["password"])) {
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["role"] = $user["role"];
                    $loginSuccess = true;
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Staff Login | Suwapiyasa Medical Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>

<body class="bg-slate-50 text-slate-800 min-h-screen">
    <main class="min-h-screen flex items-center justify-center px-6 py-12 relative overflow-hidden">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-100 rounded-full blur-3xl opacity-70"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-teal-100 rounded-full blur-3xl opacity-70"></div>
        <div class="relative w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-600 shadow-lg shadow-blue-600/20">
                    <i data-lucide="stethoscope" class="w-8 h-8 text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-5">Suwapiyasa</h1>
                <p class="text-sm text-slate-500 mt-1">Medical Center</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-xl shadow-slate-200/50">
                <div class="text-center mb-8">
                    <div class="flex items-center justify-center gap-2">
                        <i data-lucide="user-round-check" class="w-5 h-5 text-blue-600"></i>
                        <h2 class="text-2xl font-bold text-slate-900">Medical Staff Login</h2>
                    </div>
                    <p class="text-sm text-slate-500 mt-2">Sign in to access your medical staff dashboard.</p>
                </div>
                <?php if ($error !== ""): ?>
                    <div class="mb-6 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                        <i data-lucide="circle-alert" class="w-5 h-5 flex-shrink-0"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                <form method="POST" class="space-y-5">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                        <div class="relative">
                            <i data-lucide="user" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                            <input type="text" id="username" name="username" placeholder="Enter your username" autocomplete="username" required value="<?php echo htmlspecialchars($_POST["username"] ?? ""); ?>" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 outline-none focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition">
                        </div>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                        <div class="relative">
                            <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required class="w-full pl-12 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 outline-none focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition">
                            <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-600">
                                <i data-lucide="eye" id="passwordIcon" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl font-semibold shadow-lg shadow-blue-600/20 transition">
                        <i data-lucide="log-in" class="w-5 h-5"></i>
                        Sign In
                    </button>
                </form>
            </div>
            <div class="flex items-center justify-center gap-2 mt-6 text-xs text-slate-500">
                <i data-lucide="shield-check" class="w-4 h-4 text-teal-600"></i>
                Secure medical staff access
            </div>
            <p class="text-center text-xs text-slate-400 mt-4">© 2026 Suwapiyasa Medical Center</p>
        </div>
    </main>
    <script>
        lucide.createIcons();

        function togglePassword() {
            const password = document.getElementById("password");
            const icon = document.getElementById("passwordIcon");
            if (password.type === "password") {
                password.type = "text";
                icon.setAttribute("data-lucide", "eye-off");
            } else {
                password.type = "password";
                icon.setAttribute("data-lucide", "eye");
            }
            lucide.createIcons();
        }
        <?php if ($loginSuccess): ?>
            $(document).ready(function() {
                toastr.success("Login successful! Redirecting to dashboard...", "Welcome");
                setTimeout(function() {
                    window.location.href = "index.php?page=dashboard";
                }, 2000);
            });
        <?php endif; ?>
    </script>
</body>

</html>