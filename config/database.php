<?php
/**
 * CONFIGURATION BASE DE DONNÉES
 * Connexion PostgreSQL Supabase via PDO
 */

// ============================================
// CHARGEMENT DES VARIABLES D'ENVIRONNEMENT
// ============================================

/**
 * Charge les variables depuis le fichier .env
 */
function loadEnv() {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        error_log("⚠️ Fichier .env introuvable. Utilisez .env.example comme template.");
        return;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parser KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Supprimer les guillemets si présents
            $value = trim($value, '"\'');
            
            // Définir la variable d'environnement
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Charger l'environnement
loadEnv();

// ============================================
// CONSTANTES DE CONNEXION
// ============================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// ============================================
// FONCTION DE CONNEXION PDO
// ============================================

/**
 * Retourne une instance PDO connectée à Supabase
 * Utilise le pattern Singleton pour réutiliser la connexion
 * 
 * @return PDO Instance PDO connectée
 * @throws Exception Si la connexion échoue
 */
function getDB() {
    static $pdo = null;
    
    // Si déjà connecté, retourner l'instance existante
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Construction du DSN (Data Source Name)
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require",
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
        
        // Options PDO
        $options = [
            // Mode d'erreur : exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Fetch mode par défaut : tableau associatif
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Désactiver l'émulation des requêtes préparées (meilleure sécurité)
            PDO::ATTR_EMULATE_PREPARES => false,
            
            // Timeout de connexion (10 secondes)
            PDO::ATTR_TIMEOUT => 10,
            
            // Connexion persistante pour performance
            PDO::ATTR_PERSISTENT => false
        ];
        
        // Création de la connexion
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        
        // Log succès
        error_log("✅ Connexion Supabase réussie");
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log détaillé de l'erreur (sans exposer le mot de passe)
        error_log("❌ Erreur connexion Supabase: " . $e->getMessage());
        error_log("Host: " . DB_HOST);
        error_log("Port: " . DB_PORT);
        error_log("Database: " . DB_NAME);
        error_log("User: " . DB_USER);
        
        // Message générique pour l'utilisateur
        throw new Exception("Impossible de se connecter à la base de données. Vérifiez vos credentials dans le fichier .env");
    }
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Vérifie si la connexion à la base de données fonctionne
 * 
 * @return bool True si connecté, False sinon
 */
function testConnection() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Exécute une requête SQL simple et retourne les résultats
 * 
 * @param string $sql Requête SQL
 * @param array $params Paramètres de la requête préparée
 * @return array|false Résultats ou false en cas d'erreur
 */
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur SQL: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère une seule ligne
 * 
 * @param string $sql Requête SQL
 * @param array $params Paramètres
 * @return array|false Ligne ou false
 */
function fetchOne($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur SQL: " . $e->getMessage());
        return false;
    }
}

/**
 * Compte le nombre de lignes
 * 
 * @param string $table Nom de la table
 * @param string $where Clause WHERE (optionnelle)
 * @param array $params Paramètres
 * @return int Nombre de lignes
 */
function countRows($table, $where = '', $params = []) {
    try {
        $sql = "SELECT COUNT(*) as count FROM $table";
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        $result = fetchOne($sql, $params);
        return $result ? (int)$result['count'] : 0;
    } catch (Exception $e) {
        error_log("Erreur count: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// INITIALISATION AU CHARGEMENT
// ============================================

// Tester la connexion au chargement (optionnel, pour debug)
if (getenv('DB_TEST_ON_LOAD') === 'true') {
    if (testConnection()) {
        error_log("✅ Test de connexion DB : OK");
    } else {
        error_log("❌ Test de connexion DB : ÉCHEC");
    }
}