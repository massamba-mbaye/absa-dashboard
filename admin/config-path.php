<?php
/**
 * CONFIGURATION DES CHEMINS
 * Détection automatique du chemin de base et configuration des URLs
 */

// ============================================
// DÉTECTION AUTOMATIQUE DU CHEMIN DE BASE
// ============================================

// Récupérer le chemin du script actuel
$scriptPath = $_SERVER['SCRIPT_NAME'];

// Séparer en parties
$pathParts = explode('/', $scriptPath);

// Retirer le dernier élément (nom du fichier)
array_pop($pathParts);

// Si on est dans le dossier /admin, remonter d'un niveau
if (end($pathParts) === 'admin') {
    array_pop($pathParts);
}

// Reconstruire le chemin de base
$basePath = implode('/', $pathParts);

// Si vide, on est à la racine
if (empty($basePath)) {
    $basePath = '';
}

// ============================================
// CONSTANTES DE CHEMINS
// ============================================

// Chemin de base de l'application
define('BASE_URL', $basePath);

// Chemin vers les APIs admin
define('API_URL', BASE_URL . '/api/admin');

// Chemin vers les APIs publiques (si besoin futur)
define('API_FRONTEND_URL', BASE_URL . '/api');

// Chemin vers le dossier admin
define('ADMIN_URL', BASE_URL . '/admin');

// Chemin vers les assets
define('ASSETS_URL', BASE_URL . '/assets');

// ============================================
// CHEMINS ABSOLUS SYSTÈME DE FICHIERS
// ============================================

// Racine du projet
define('ROOT_PATH', dirname(__DIR__));

// Dossier config
define('CONFIG_PATH', ROOT_PATH . '/config');

// Dossier admin
define('ADMIN_PATH', ROOT_PATH . '/admin');

// Dossier API
define('API_PATH', ROOT_PATH . '/api');

// Dossier uploads (si besoin futur)
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Dossier logs (si besoin futur)
define('LOGS_PATH', ROOT_PATH . '/logs');

// ============================================
// FONCTIONS UTILITAIRES DE CHEMIN
// ============================================

/**
 * Génère une URL complète vers une ressource
 * 
 * @param string $path Chemin relatif
 * @return string URL complète
 */
function url($path = '') {
    $path = ltrim($path, '/');
    
    if (empty($path)) {
        return BASE_URL ?: '/';
    }
    
    return BASE_URL . '/' . $path;
}

/**
 * Génère une URL vers une page admin
 * 
 * @param string $page Nom de la page (sans .php)
 * @return string URL complète
 */
function adminUrl($page = 'dashboard') {
    return ADMIN_URL . '/' . $page . '.php';
}

/**
 * Génère une URL vers une API admin
 * 
 * @param string $endpoint Nom du endpoint (sans .php)
 * @param array $params Paramètres GET optionnels
 * @return string URL complète
 */
function apiUrl($endpoint, $params = []) {
    $url = API_URL . '/' . $endpoint . '.php';
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

/**
 * Génère un chemin absolu vers un fichier
 * 
 * @param string $relativePath Chemin relatif depuis la racine
 * @return string Chemin absolu
 */
function absolutePath($relativePath) {
    return ROOT_PATH . '/' . ltrim($relativePath, '/');
}

/**
 * Vérifie si un fichier existe dans le projet
 * 
 * @param string $relativePath Chemin relatif
 * @return bool True si existe
 */
function fileExists($relativePath) {
    return file_exists(absolutePath($relativePath));
}

/**
 * Redirige vers une page admin
 * 
 * @param string $page Nom de la page
 * @param array $params Paramètres GET optionnels
 */
function redirectToAdmin($page = 'dashboard', $params = []) {
    $url = adminUrl($page);
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Redirige vers la page de login
 * 
 * @param string $message Message d'erreur optionnel
 */
function redirectToLogin($message = null) {
    $params = [];
    
    if ($message) {
        $params['error'] = $message;
    }
    
    redirectToAdmin('index', $params);
}

/**
 * Obtient l'URL complète actuelle
 * 
 * @return string URL complète
 */
function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return $protocol . '://' . $host . $uri;
}

/**
 * Vérifie si on est sur une page spécifique
 * 
 * @param string $page Nom de la page (sans .php)
 * @return bool True si on est sur cette page
 */
function isCurrentPage($page) {
    $currentScript = basename($_SERVER['SCRIPT_NAME'], '.php');
    return $currentScript === $page;
}

/**
 * Génère le chemin vers un asset (CSS, JS, image)
 * 
 * @param string $asset Chemin relatif de l'asset
 * @return string URL complète
 */
function asset($asset) {
    return ASSETS_URL . '/' . ltrim($asset, '/');
}

// ============================================
// DÉTECTION ENVIRONNEMENT
// ============================================

/**
 * Vérifie si on est en environnement local
 * 
 * @return bool True si local
 */
function isLocalEnvironment() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    return in_array($host, [
        'localhost',
        '127.0.0.1',
        '::1'
    ]) || strpos($host, 'localhost:') === 0;
}

/**
 * Vérifie si on est en production
 * 
 * @return bool True si production
 */
function isProduction() {
    return !isLocalEnvironment();
}

/**
 * Récupère le nom de l'environnement
 * 
 * @return string 'local' ou 'production'
 */
function getEnvironment() {
    return isLocalEnvironment() ? 'local' : 'production';
}

// ============================================
// DEBUG & LOGS
// ============================================

/**
 * Log de debug (uniquement en environnement local)
 * 
 * @param mixed $data Données à logger
 * @param string $label Label optionnel
 */
function debugLog($data, $label = 'DEBUG') {
    if (isLocalEnvironment()) {
        $output = is_array($data) || is_object($data) 
            ? json_encode($data, JSON_PRETTY_PRINT) 
            : $data;
        
        error_log("[$label] " . $output);
    }
}

/**
 * Affiche les informations de debug (uniquement en local)
 */
function showDebugInfo() {
    if (!isLocalEnvironment()) {
        return;
    }
    
    echo "<!-- DEBUG INFO -->\n";
    echo "<!-- Environment: " . getEnvironment() . " -->\n";
    echo "<!-- Base URL: " . BASE_URL . " -->\n";
    echo "<!-- API URL: " . API_URL . " -->\n";
    echo "<!-- Root Path: " . ROOT_PATH . " -->\n";
    echo "<!-- Current URL: " . currentUrl() . " -->\n";
}

// ============================================
// VALIDATION DES CHEMINS
// ============================================

/**
 * Vérifie que tous les dossiers critiques existent
 * 
 * @return array Liste des erreurs (vide si tout OK)
 */
function validatePaths() {
    $errors = [];
    
    $requiredDirs = [
        'config' => CONFIG_PATH,
        'admin' => ADMIN_PATH,
        'api' => API_PATH
    ];
    
    foreach ($requiredDirs as $name => $path) {
        if (!is_dir($path)) {
            $errors[] = "Dossier '$name' introuvable: $path";
        }
    }
    
    return $errors;
}

/**
 * Vérifie les permissions d'écriture sur les dossiers
 * 
 * @return array Liste des dossiers sans permission
 */
function checkWritableDirectories() {
    $dirsToCheck = [];
    
    // Vérifier uniquement les dossiers qui existent
    if (defined('UPLOADS_PATH') && is_dir(UPLOADS_PATH)) {
        $dirsToCheck['uploads'] = UPLOADS_PATH;
    }
    
    if (defined('LOGS_PATH') && is_dir(LOGS_PATH)) {
        $dirsToCheck['logs'] = LOGS_PATH;
    }
    
    $notWritable = [];
    
    foreach ($dirsToCheck as $name => $path) {
        if (!is_writable($path)) {
            $notWritable[] = $name;
        }
    }
    
    return $notWritable;
}

// ============================================
// AUTO-CONFIGURATION
// ============================================

// En environnement local, afficher les infos de debug
if (isLocalEnvironment() && isset($_GET['debug'])) {
    showDebugInfo();
}

// Logger les erreurs de configuration si présentes
$pathErrors = validatePaths();
if (!empty($pathErrors)) {
    foreach ($pathErrors as $error) {
        error_log("⚠️ CONFIGURATION: $error");
    }
}