<?php
/**
 * API AUTHENTIFICATION ADMIN
 * Endpoints: login, logout, check-session
 */

// Démarrer la session
session_start();

// Charger les dépendances
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ============================================
// GESTION DES ERREURS
// ============================================

/**
 * Retourne une réponse JSON avec erreur
 */
function jsonError($message, $code = 400, $details = []) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Retourne une réponse JSON avec succès
 */
function jsonSuccess($data = [], $message = null) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ============================================
// ROUTER - GESTION DES ACTIONS
// ============================================

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    jsonError('Action non spécifiée', 400);
}

// Router vers la bonne fonction
switch ($action) {
    case 'login':
        handleLogin();
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'check-session':
        handleCheckSession();
        break;
        
    case 'refresh-session':
        handleRefreshSession();
        break;
        
    default:
        jsonError('Action invalide: ' . $action, 400);
}

// ============================================
// HANDLER: LOGIN
// ============================================

function handleLogin() {
    // Récupérer les données POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        jsonError('Données JSON invalides', 400);
    }
    
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    // Validation des champs
    if (empty($username) || empty($password)) {
        jsonError('Nom d\'utilisateur et mot de passe requis', 400);
    }
    
    // Vérifier si l'utilisateur est bloqué
    if (isLoginBlocked($username)) {
        $timeRemaining = getBlockTimeRemaining($username);
        $minutes = ceil($timeRemaining / 60);
        
        jsonError(
            "Trop de tentatives échouées. Réessayez dans $minutes minute(s).",
            429,
            ['blocked_until' => time() + $timeRemaining]
        );
    }
    
    // Vérifier les credentials
    $admin = verifyCredentials($username, $password);
    
    if (!$admin) {
        // Enregistrer la tentative échouée
        recordFailedLogin($username);
        
        // Log de sécurité
        error_log("⚠️ Tentative de connexion échouée pour: $username depuis " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        jsonError('Nom d\'utilisateur ou mot de passe incorrect', 401);
    }
    
    // Connexion réussie
    loginAdmin($admin);
    
    // Log de succès
    logAdminActivity('login', [
        'username' => $username,
        'success' => true
    ]);
    
    error_log("✅ Connexion réussie pour: $username");
    
    jsonSuccess([
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'name' => $admin['name'],
            'email' => $admin['email'] ?? null
        ],
        'session' => [
            'expires_in' => SESSION_LIFETIME,
            'expires_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
        ]
    ], 'Connexion réussie');
}

// ============================================
// HANDLER: LOGOUT
// ============================================

function handleLogout() {
    if (isAdminLoggedIn()) {
        $admin = getCurrentAdmin();
        
        logAdminActivity('logout', [
            'username' => $admin['username']
        ]);
        
        error_log("🚪 Déconnexion de: " . $admin['username']);
    }
    
    logoutAdmin();
    
    jsonSuccess([], 'Déconnexion réussie');
}

// ============================================
// HANDLER: CHECK SESSION
// ============================================

function handleCheckSession() {
    if (!isAdminLoggedIn()) {
        jsonError('Session expirée ou invalide', 401);
    }
    
    $admin = getCurrentAdmin();
    $timeRemaining = getSessionTimeRemaining();
    $expiringSoon = isSessionExpiringSoon();
    
    jsonSuccess([
        'logged_in' => true,
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'name' => $admin['name']
        ],
        'session' => [
            'time_remaining' => $timeRemaining,
            'expires_at' => date('Y-m-d H:i:s', time() + $timeRemaining),
            'expiring_soon' => $expiringSoon,
            'warning_threshold' => SESSION_WARNING_TIME
        ]
    ]);
}

// ============================================
// HANDLER: REFRESH SESSION
// ============================================

function handleRefreshSession() {
    if (!isAdminLoggedIn()) {
        jsonError('Session expirée', 401);
    }
    
    // Prolonger la session
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    logAdminActivity('session_refresh');
    
    jsonSuccess([
        'new_expires_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME),
        'time_remaining' => SESSION_LIFETIME
    ], 'Session prolongée');
}

// ============================================
// FONCTION: VÉRIFICATION DES CREDENTIALS
// ============================================

/**
 * Vérifie les identifiants de connexion
 * 
 * @param string $username Nom d'utilisateur
 * @param string $password Mot de passe
 * @return array|false Données admin ou false
 */
function verifyCredentials($username, $password) {
    try {
        $db = getDB();

        // D'abord, chercher dans la table public.users (système principal)
        $stmt = $db->prepare("
            SELECT id, email, password_hash, first_name, last_name, role, is_active
            FROM public.users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute(['email' => $username]);
        $user = $stmt->fetch();

        // Si l'utilisateur existe dans public.users
        if ($user) {
            // Vérifier le mot de passe hashé
            if (password_verify($password, $user['password_hash'])) {
                // Vérifier si le compte est actif
                if (!$user['is_active']) {
                    error_log("⚠️ Tentative de connexion sur compte désactivé: $username");
                    return false;
                }

                // Construire le nom complet
                $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
                if (empty($fullName)) {
                    $fullName = $user['email'];
                }

                return [
                    'id' => $user['id'],
                    'username' => $user['email'],
                    'name' => $fullName,
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
            }
        }

        // Fallback : chercher dans l'ancienne table admins (rétrocompatibilité)
        $stmt = $db->prepare("
            SELECT id, username, password_hash, name, email, active
            FROM admins
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        // Si l'admin existe en DB
        if ($admin) {
            // Vérifier le mot de passe hashé
            if (password_verify($password, $admin['password_hash'])) {
                // Vérifier si le compte est actif
                if (!$admin['active']) {
                    error_log("⚠️ Tentative de connexion sur compte désactivé: $username");
                    return false;
                }

                return [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'name' => $admin['name'],
                    'email' => $admin['email']
                ];
            }
        }

    } catch (Exception $e) {
        error_log("❌ Erreur lors de la vérification des credentials: " . $e->getMessage());
    }

    // Fallback : vérifier avec les credentials du .env (compte par défaut)
    return verifyDefaultCredentials($username, $password);
}

/**
 * Vérifie les credentials par défaut depuis .env
 * Utilisé si pas de table admins ou comme fallback
 * 
 * @param string $username Nom d'utilisateur
 * @param string $password Mot de passe
 * @return array|false Données admin ou false
 */
function verifyDefaultCredentials($username, $password) {
    $defaultUsername = getenv('ADMIN_USERNAME') ?: 'admin';
    $defaultPassword = getenv('ADMIN_PASSWORD') ?: 'admin';
    
    if ($username === $defaultUsername && $password === $defaultPassword) {
        error_log("✅ Connexion via credentials par défaut (.env)");
        
        return [
            'id' => 1,
            'username' => $defaultUsername,
            'name' => 'Administrateur',
            'email' => null
        ];
    }
    
    return false;
}

// ============================================
// CRÉATION TABLE ADMINS (SI N'EXISTE PAS)
// ============================================

/**
 * Crée la table admins si elle n'existe pas
 * Appelée automatiquement au premier accès
 */
function ensureAdminsTableExists() {
    try {
        $db = getDB();
        
        // Vérifier si la table existe
        $stmt = $db->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'admins'
            )
        ");
        
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // Créer la table admins
            $db->exec("
                CREATE TABLE admins (
                    id SERIAL PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100),
                    active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Créer un admin par défaut avec credentials du .env
            $defaultUsername = getenv('ADMIN_USERNAME') ?: 'admin';
            $defaultPassword = getenv('ADMIN_PASSWORD') ?: 'admin';
            $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("
                INSERT INTO admins (username, password_hash, name, email)
                VALUES (:username, :password_hash, :name, :email)
            ");
            
            $stmt->execute([
                'username' => $defaultUsername,
                'password_hash' => $passwordHash,
                'name' => 'Administrateur',
                'email' => null
            ]);
            
            error_log("✅ Table 'admins' créée avec compte par défaut");
        }
        
    } catch (Exception $e) {
        error_log("⚠️ Impossible de créer la table admins: " . $e->getMessage());
        // Ne pas bloquer si erreur (fallback sur .env)
    }
}

// Initialiser la table au chargement
ensureAdminsTableExists();