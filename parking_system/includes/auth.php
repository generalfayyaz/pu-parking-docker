<?php
// ============================================================
//  Auth Helper
//  File: includes/auth.php
//  Include at top of every protected page
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Redirect to login if not logged in.
 * Usage: require_auth();
 */
function require_auth(): void {
    if (empty($_SESSION['operator_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Redirect to login if not admin.
 */
function require_admin(): void {
    require_auth();
    if ($_SESSION['operator_role'] !== 'admin') {
        header('Location: ' . BASE_URL . 'index.php?error=forbidden');
        exit;
    }
}

/**
 * Return current logged-in operator info from session.
 */
function current_user(): array {
    return [
        'id'       => $_SESSION['operator_id']   ?? null,
        'username' => $_SESSION['operator_username'] ?? '',
        'name'     => $_SESSION['operator_name']  ?? '',
        'role'     => $_SESSION['operator_role']  ?? '',
    ];
}

/**
 * True if logged in.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['operator_id']);
}
