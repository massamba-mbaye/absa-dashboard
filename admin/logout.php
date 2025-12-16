<?php
/**
 * PAGE DE DÉCONNEXION
 * Détruit la session admin et redirige vers le login
 */

session_start();

// Charger les configurations
require_once __DIR__ . '/../config/auth.php';

// Log de la déconnexion (avant de détruire la session)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    logAdminActivity('logout', [
        'admin_id' => $_SESSION['admin_id'] ?? null,
        'admin_name' => $_SESSION['admin_name'] ?? 'Unknown',
        'logout_time' => date('Y-m-d H:i:s')
    ]);
}

// Déconnexion (détruit la session)
logoutAdmin();

// Redirection vers la page de login
header('Location: index.php?message=logged_out');
exit;