<?php
/**
 * SCRIPT DE DIAGNOSTIC - Structure de la base de données
 * À placer dans : /absa-dashboard/admin/diagnostic.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "<h1>Structure de la Base de Données ABSA</h1>";
    echo "<style>
        body { font-family: monospace; background: #1a1625; color: #e5e7eb; padding: 20px; }
        h1, h2 { color: #51c6e1; }
        table { border-collapse: collapse; margin: 20px 0; width: 100%; }
        th, td { padding: 10px; text-align: left; border: 1px solid #3a3a4a; }
        th { background: #4b3795; color: white; }
        tr:nth-child(even) { background: #252533; }
        .success { color: #51cf66; }
        .error { color: #ff6b6b; }
    </style>";
    
    // Liste des tables
    $tables = ['conversations', 'messages', 'alerts'];
    
    foreach ($tables as $table) {
        echo "<h2>📊 Table : {$table}</h2>";
        
        try {
            // Récupérer les colonnes de la table
            $stmt = $db->query("
                SELECT 
                    column_name, 
                    data_type, 
                    is_nullable,
                    column_default
                FROM information_schema.columns
                WHERE table_name = '{$table}'
                ORDER BY ordinal_position
            ");
            
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($columns) > 0) {
                echo "<table>";
                echo "<tr><th>Nom Colonne</th><th>Type</th><th>Nullable</th><th>Défaut</th></tr>";
                
                foreach ($columns as $col) {
                    echo "<tr>";
                    echo "<td><strong>{$col['column_name']}</strong></td>";
                    echo "<td>{$col['data_type']}</td>";
                    echo "<td>{$col['is_nullable']}</td>";
                    echo "<td>{$col['column_default']}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                
                // Compter les enregistrements
                $countStmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<p class='success'>✅ {$count} enregistrements dans cette table</p>";
                
            } else {
                echo "<p class='error'>❌ Table introuvable ou vide</p>";
            }
            
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Erreur : " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<hr><h2>🔍 Exemple de données (5 premières lignes)</h2>";
    
    // Afficher quelques exemples de conversations
    try {
        echo "<h3>Table conversations:</h3>";
        $stmt = $db->query("SELECT * FROM conversations LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "<table>";
            echo "<tr>";
            foreach (array_keys($rows[0]) as $header) {
                echo "<th>{$header}</th>";
            }
            echo "</tr>";
            
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    $displayValue = is_string($value) ? htmlspecialchars(substr($value, 0, 50)) : $value;
                    echo "<td>{$displayValue}</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Aucune donnée</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Erreur : " . $e->getMessage() . "</p>";
    }
    
    // Messages
    try {
        echo "<h3>Table messages:</h3>";
        $stmt = $db->query("SELECT * FROM messages LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "<table>";
            echo "<tr>";
            foreach (array_keys($rows[0]) as $header) {
                echo "<th>{$header}</th>";
            }
            echo "</tr>";
            
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    $displayValue = is_string($value) ? htmlspecialchars(substr($value, 0, 50)) : $value;
                    echo "<td>{$displayValue}</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Aucune donnée</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Erreur : " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>ERREUR CONNEXION : " . $e->getMessage() . "</p>";
}
?>