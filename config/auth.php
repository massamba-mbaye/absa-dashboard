<?php
/**
 * GESTION AUTHENTIFICATION ADMIN
 * Vérification des sessions et protection des pages
 */

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CONSTANTES DE SÉCURITÉ
// ============================================

// Durée de vie de la session (2 heures)
define('SESSION_LIFETIME', 7200);

// Durée avant warning d'expiration (15 minutes)
define('SESSION_WARNING_TIME', 900);

// Nombre max de tentatives de connexion
define('MAX_LOGIN_ATTEMPTS', 5);

// Durée de blocage après tentatives échouées (15 minutes)
define('LOGIN_BLOCK_DURATION', 900);

// ============================================
// FONCTIONS D'AUTHENTIFICATION
// ============================================

/**
 * Vérifie si l'admin est connecté
 * Redirige vers la page de login si non connecté
 *
 * @param bool $redirect Si true, redirige automatiquement
 * @return bool True si connecté, False sinon
 */
function checkAdminAuth($redirect = true) {
    // Vérifier si la session existe
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if ($redirect) {
            // Charger config-path.php si pas déjà chargé
            if (!defined('ADMIN_URL')) {
                require_once __DIR__ . '/../admin/config-path.php';
            }
            header('Location: ' . ADMIN_URL . '/index.php?error=not_logged_in');
            exit;
        }
        return false;
    }

    // Vérifier l'expiration de la session (2 heures)
    if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
        logoutAdmin();
        if ($redirect) {
            // Charger config-path.php si pas déjà chargé
            if (!defined('ADMIN_URL')) {
                require_once __DIR__ . '/../admin/config-path.php';
            }
            header('Location: ' . ADMIN_URL . '/index.php?error=session_expired');
            exit;
        }
        return false;
    }

    // Mettre à jour l'activité
    $_SESSION['last_activity'] = time();

    return true;
}

/**
 * Vérifie si l'admin est connecté (version sans redirection)
 * 
 * @return bool True si connecté
 */
function isAdminLoggedIn() {
    return checkAdminAuth(false);
}

/**
 * Récupère les informations de l'admin connecté
 * 
 * @return array|null Informations admin ou null
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'admin',
        'name' => $_SESSION['admin_name'] ?? 'Administrateur',
        'email' => $_SESSION['admin_email'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? time(),
        'last_activity' => $_SESSION['last_activity'] ?? time()
    ];
}

/**
 * Récupère le nom de l'admin connecté
 * 
 * @return string Nom de l'admin
 */
function getAdminName() {
    return $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
}

/**
 * Récupère l'ID de l'admin connecté
 * 
 * @return int|null ID admin ou null
 */
function getAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Vérifie le temps restant avant expiration
 * 
 * @return int Secondes restantes
 */
function getSessionTimeRemaining() {
    if (!isset($_SESSION['login_time'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['login_time'];
    $remaining = SESSION_LIFETIME - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Vérifie si la session arrive bientôt à expiration
 * 
 * @return bool True si < 15 minutes restantes
 */
function isSessionExpiringSoon() {
    return getSessionTimeRemaining() < SESSION_WARNING_TIME;
}

// ============================================
// GESTION DES TENTATIVES DE CONNEXION
// ============================================

/**
 * Enregistre une tentative de connexion échouée
 * 
 * @param string $username Nom d'utilisateur
 */
function recordFailedLogin($username) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = [
            'count' => 0,
            'first_attempt' => time(),
            'last_attempt' => time()
        ];
    }
    
    $_SESSION['login_attempts'][$username]['count']++;
    $_SESSION['login_attempts'][$username]['last_attempt'] = time();
}

/**
 * Vérifie si un utilisateur est bloqué (trop de tentatives)
 * 
 * @param string $username Nom d'utilisateur
 * @return bool True si bloqué
 */
function isLoginBlocked($username) {
    if (!isset($_SESSION['login_attempts'][$username])) {
        return false;
    }
    
    $attempts = $_SESSION['login_attempts'][$username];
    
    // Si moins de MAX_LOGIN_ATTEMPTS, pas bloqué
    if ($attempts['count'] < MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    // Vérifier si la durée de blocage est écoulée
    $timeSinceLastAttempt = time() - $attempts['last_attempt'];
    
    if ($timeSinceLastAttempt > LOGIN_BLOCK_DURATION) {
        // Débloquer
        unset($_SESSION['login_attempts'][$username]);
        return false;
    }
    
    return true;
}

/**
 * Récupère le temps restant de blocage
 * 
 * @param string $username Nom d'utilisateur
 * @return int Secondes restantes
 */
function getBlockTimeRemaining($username) {
    if (!isset($_SESSION['login_attempts'][$username])) {
        return 0;
    }
    
    $attempts = $_SESSION['login_attempts'][$username];
    $timeSinceLastAttempt = time() - $attempts['last_attempt'];
    $remaining = LOGIN_BLOCK_DURATION - $timeSinceLastAttempt;
    
    return max(0, $remaining);
}

/**
 * Réinitialise les tentatives de connexion après succès
 * 
 * @param string $username Nom d'utilisateur
 */
function resetLoginAttempts($username) {
    if (isset($_SESSION['login_attempts'][$username])) {
        unset($_SESSION['login_attempts'][$username]);
    }
}

// ============================================
// CONNEXION / DÉCONNEXION
// ============================================

/**
 * Connecte un administrateur
 * 
 * @param array $adminData Données de l'admin
 * @return bool True si succès
 */
function loginAdmin($adminData) {
    // Régénérer l'ID de session pour sécurité
    session_regenerate_id(true);
    
    // Définir les variables de session
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $adminData['id'] ?? null;
    $_SESSION['admin_username'] = $adminData['username'] ?? 'admin';
    $_SESSION['admin_name'] = $adminData['name'] ?? $adminData['username'] ?? 'Admin';
    $_SESSION['admin_email'] = $adminData['email'] ?? null;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Réinitialiser les tentatives de connexion
    if (isset($adminData['username'])) {
        resetLoginAttempts($adminData['username']);
    }
    
    return true;
}

/**
 * Déconnecte l'administrateur
 */
function logoutAdmin() {
    // Détruire toutes les variables de session
    $_SESSION = [];
    
    // Détruire le cookie de session
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Détruire la session
    session_destroy();
    
    // Démarrer une nouvelle session propre
    session_start();
}

// ============================================
// PROTECTION CSRF
// ============================================

/**
 * Génère un token CSRF
 * 
 * @return string Token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 * 
 * @param string $token Token à vérifier
 * @return bool True si valide
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Retourne le champ input HTML pour le token CSRF
 * 
 * @return string HTML input
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// ============================================
// LOGS D'ACTIVITÉ
// ============================================

/**
 * Enregistre une action admin dans les logs
 * 
 * @param string $action Action effectuée
 * @param array $details Détails supplémentaires
 */
function logAdminActivity($action, $details = []) {
    $admin = getCurrentAdmin();
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'admin_id' => $admin['id'] ?? null,
        'admin_username' => $admin['username'] ?? 'unknown',
        'action' => $action,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    // Log dans le fichier système
    error_log("ADMIN_ACTIVITY: " . json_encode($logEntry));
    
    // TODO: Optionnel - Enregistrer dans une table logs en DB
}

// ============================================
// UTILITAIRES
// ============================================

/**
 * Formate le temps restant en format lisible
 * 
 * @param int $seconds Secondes
 * @return string Format "Xh Ym"
 */
function formatTimeRemaining($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return sprintf("%dh %02dm", $hours, $minutes);
    }
    
    return sprintf("%d minutes", $minutes);
}

/**
 * Redirige vers une page avec message
 * 
 * @param string $page Page de destination
 * @param string $message Message à afficher
 * @param string $type Type (success, error, warning)
 */
function redirectWithMessage($page, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $page");
    exit;
}

/**
 * Récupère et supprime le message flash
 * 
 * @return array|null ['message' => '...', 'type' => '...']
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $message;
    }
    
    return null;
}