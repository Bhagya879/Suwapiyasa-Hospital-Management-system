<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function dashboardUrlFor(string $role): ?string
{
    $paths = [
        'admin'   => '../admin/index.php?page=dashboard',
        'doctor'  => '../medical/index.php?page=dashboard',
        'nurse'   => '../medical/index.php?page=dashboard',
        'patient' => '../patient/index.php?page=dashboard'
    ];

    return $paths[$role] ?? null;
}

function redirectToDashboard(string $role): void
{
    $target = dashboardUrlFor($role);

    if ($target === null) {
        session_unset();
        session_destroy();
        header('Location: ../login.php');
        exit;
    }

    header('Location: ' . $target);
    exit;
}

function requireRole(string $requiredRole): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: ../index.php');
        exit;
    }

    if ($_SESSION['role'] !== $requiredRole) {
        redirectToDashboard($_SESSION['role']);
        exit;
    }
}

function requireAdminRole(): void
{
    requireRole('admin');
}

function requireMedicalRole(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: ../index.php');
        exit;
    }

    if (!in_array($_SESSION['role'], ['doctor', 'nurse'], true)) {
        redirectToDashboard($_SESSION['role']);
        exit;
    }
}

function requirePatientRole(): void
{
    requireRole('patient');
}
