<?php
/**
 * SCRIPT DE DIAGNOSTIC DE CONNEXION DATABASE
 * À placer à la racine du projet et accéder via le navigateur
 * Exemple: https://absa.polaris-asso.org/test-db-connection.php
 */

// Affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostic de connexion à la base de données</h1>";
echo "<pre>";

// ============================================
// 1. VÉRIFIER L'EXISTENCE DU FICHIER .ENV
// ============================================
echo "=== 1. VÉRIFICATION FICHIER .ENV ===\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "✅ Fichier .env trouvé: $envPath\n";
    echo "   Permissions: " . substr(sprintf('%o', fileperms($envPath)), -4) . "\n";
    echo "   Lisible: " . (is_readable($envPath) ? 'OUI' : 'NON') . "\n";
} else {
    echo "❌ Fichier .env INTROUVABLE à: $envPath\n";
    echo "   SOLUTION: Créez le fichier .env à la racine du projet\n";
    exit;
}

echo "\n";

// ============================================
// 2. CHARGER LES VARIABLES D'ENVIRONNEMENT
// ============================================
echo "=== 2. CHARGEMENT DES VARIABLES D'ENVIRONNEMENT ===\n";

require_once __DIR__ . '/config/database.php';

echo "Variables chargées:\n";
echo "   DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NON DÉFINI') . "\n";
echo "   DB_PORT: " . (defined('DB_PORT') ? DB_PORT : 'NON DÉFINI') . "\n";
echo "   DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NON DÉFINI') . "\n";
echo "   DB_USER: " . (defined('DB_USER') ? DB_USER : 'NON DÉFINI') . "\n";
echo "   DB_PASSWORD: " . (defined('DB_PASSWORD') ? (DB_PASSWORD ? '****** (défini)' : 'VIDE') : 'NON DÉFINI') . "\n";

echo "\n";

// ============================================
// 3. VÉRIFIER L'EXTENSION PDO POSTGRESQL
// ============================================
echo "=== 3. VÉRIFICATION EXTENSION PHP ===\n";

if (extension_loaded('pdo')) {
    echo "✅ Extension PDO chargée\n";
} else {
    echo "❌ Extension PDO NON chargée\n";
}

if (extension_loaded('pdo_pgsql')) {
    echo "✅ Extension PDO PostgreSQL chargée\n";
} else {
    echo "❌ Extension PDO PostgreSQL NON chargée\n";
    echo "   SOLUTION: Activez l'extension pdo_pgsql dans php.ini\n";
    echo "   Pour OVH: Contactez le support ou vérifiez les modules PHP disponibles\n";
}

$availableDrivers = PDO::getAvailableDrivers();
echo "   Drivers PDO disponibles: " . implode(', ', $availableDrivers) . "\n";

if (!in_array('pgsql', $availableDrivers)) {
    echo "   ⚠️ ATTENTION: Le driver 'pgsql' n'est pas disponible!\n";
}

echo "\n";

// ============================================
// 4. TESTER LA CONNEXION À LA BASE DE DONNÉES
// ============================================
echo "=== 4. TEST DE CONNEXION À LA BASE DE DONNÉES ===\n";

try {
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;sslmode=require",
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    echo "DSN: $dsn\n";
    echo "Tentative de connexion...\n";

    $startTime = microtime(true);

    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);

    echo "✅ CONNEXION RÉUSSIE en {$duration}ms\n";

    // Test d'une requête simple
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "   Version PostgreSQL: $version\n";

    // Test de la table conversations
    $stmt = $pdo->query("SELECT COUNT(*) FROM public.conversations");
    $count = $stmt->fetchColumn();
    echo "   Nombre de conversations: $count\n";

    echo "\n✅ TOUT FONCTIONNE CORRECTEMENT!\n";
    echo "   Vous pouvez supprimer ce fichier de diagnostic.\n";

} catch (PDOException $e) {
    echo "❌ ERREUR DE CONNEXION\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   Code: " . $e->getCode() . "\n";

    echo "\n📋 SOLUTIONS POSSIBLES:\n";
    echo "   1. Vérifiez les credentials dans le fichier .env\n";
    echo "   2. Vérifiez que votre serveur OVH peut accéder à Supabase\n";
    echo "   3. Vérifiez que l'extension pdo_pgsql est activée\n";
    echo "   4. Vérifiez les règles de pare-feu (port 6543 ouvert)\n";
    echo "   5. Testez la connexion depuis un autre outil (psql, pgAdmin)\n";

} catch (Exception $e) {
    echo "❌ ERREUR GÉNÉRALE\n";
    echo "   Message: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// 5. INFORMATIONS SYSTÈME
// ============================================
echo "=== 5. INFORMATIONS SYSTÈME ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Système d'exploitation: " . php_uname() . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . __DIR__ . "\n";

echo "</pre>";

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT:</strong> Supprimez ce fichier après le diagnostic pour des raisons de sécurité!</p>";
?>
