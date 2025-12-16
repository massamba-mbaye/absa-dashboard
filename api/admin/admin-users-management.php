<?php
/**
 * API GESTION DES UTILISATEURS ADMINS
 * Permet de gérer les comptes d'accès au dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

checkAdminAuth(false);

function jsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function jsonSuccess($data, $message = null) {
    $response = ['success' => true, 'data' => $data];
    if ($message) $response['message'] = $message;
    echo json_encode($response);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    $db = getDB();
    $currentAdminId = $_SESSION['admin_id'] ?? null;
    
    switch ($action) {
        
        // ====================================
        // LISTE DES ADMINS
        // ====================================
        case 'list':
            $stmt = $db->query("
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.role,
                    u.is_active,
                    u.created_at,
                    u.last_login,
                    creator.email as created_by_email,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                FROM public.users u
                LEFT JOIN public.users creator ON u.created_by = creator.id
                ORDER BY u.created_at DESC
            ");
            
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = [
                    'id' => (int)$row['id'],
                    'email' => $row['email'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'full_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'role' => $row['role'],
                    'role_label' => $row['role'] === 'admin' ? 'Administrateur' : 'Lecture seule',
                    'is_active' => (bool)$row['is_active'],
                    'created_at' => $row['created_at'],
                    'last_login' => $row['last_login'],
                    'created_by' => [
                        'email' => $row['created_by_email'],
                        'name' => $row['created_by_name']
                    ]
                ];
            }
            
            jsonSuccess(['users' => $users, 'total' => count($users)]);
            break;
        
        // ====================================
        // DÉTAILS D'UN ADMIN
        // ====================================
        case 'details':
            $userId = (int)($_GET['id'] ?? 0);
            if (!$userId) jsonError('ID utilisateur manquant');
            
            $stmt = $db->prepare("
                SELECT 
                    u.*,
                    creator.email as created_by_email,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                FROM public.users u
                LEFT JOIN public.users creator ON u.created_by = creator.id
                WHERE u.id = :id
            ");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) jsonError('Utilisateur introuvable', 404);
            
            jsonSuccess([
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'is_active' => (bool)$user['is_active'],
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login'],
                'created_by' => [
                    'email' => $user['created_by_email'],
                    'name' => $user['created_by_name']
                ]
            ]);
            break;
        
        // ====================================
        // CRÉER UN ADMIN
        // ====================================
        case 'create':
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $role = $_POST['role'] ?? 'viewer';
            
            // Validation
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonError('Email invalide');
            }
            if (strlen($password) < 8) {
                jsonError('Le mot de passe doit contenir au moins 8 caractères');
            }
            if (!in_array($role, ['admin', 'viewer'])) {
                jsonError('Rôle invalide');
            }
            
            // Vérifier si l'email existe déjà
            $stmtCheck = $db->prepare("SELECT id FROM public.users WHERE email = :email");
            $stmtCheck->execute(['email' => $email]);
            if ($stmtCheck->fetch()) {
                jsonError('Cet email est déjà utilisé');
            }
            
            // Créer l'utilisateur
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("
                INSERT INTO public.users (email, password_hash, first_name, last_name, role, is_active, created_by)
                VALUES (:email, :password_hash, :first_name, :last_name, :role, true, :created_by)
                RETURNING id
            ");
            
            $stmt->execute([
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $role,
                'created_by' => $currentAdminId
            ]);
            
            $newId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
            
            logAdminActivity('create_admin_user', [
                'new_user_id' => $newId,
                'email' => $email,
                'role' => $role
            ]);
            
            jsonSuccess(['id' => $newId], 'Utilisateur créé avec succès');
            break;
        
        // ====================================
        // MODIFIER UN ADMIN
        // ====================================
        case 'update':
            $userId = (int)($_POST['id'] ?? 0);
            if (!$userId) jsonError('ID utilisateur manquant');
            
            // Empêcher la modification de son propre compte via cette API
            if ($userId === $currentAdminId) {
                jsonError('Utilisez la page "Mon Profil" pour modifier votre propre compte');
            }
            
            $updates = [];
            $params = ['id' => $userId];
            
            if (isset($_POST['first_name'])) {
                $updates[] = "first_name = :first_name";
                $params['first_name'] = trim($_POST['first_name']);
            }
            
            if (isset($_POST['last_name'])) {
                $updates[] = "last_name = :last_name";
                $params['last_name'] = trim($_POST['last_name']);
            }
            
            if (isset($_POST['role']) && in_array($_POST['role'], ['admin', 'viewer'])) {
                $updates[] = "role = :role";
                $params['role'] = $_POST['role'];
            }
            
            if (isset($_POST['is_active'])) {
                $updates[] = "is_active = :is_active";
                $params['is_active'] = (bool)$_POST['is_active'];
            }
            
            if (empty($updates)) jsonError('Aucune donnée à mettre à jour');
            
            $updates[] = "updated_at = NOW()";
            $sql = "UPDATE public.users SET " . implode(', ', $updates) . " WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            logAdminActivity('update_admin_user', [
                'user_id' => $userId,
                'updated_fields' => array_keys($params)
            ]);
            
            jsonSuccess(['id' => $userId], 'Utilisateur modifié avec succès');
            break;
        
        // ====================================
        // RÉINITIALISER MOT DE PASSE
        // ====================================
        case 'reset_password':
            $userId = (int)($_POST['id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            
            if (!$userId) jsonError('ID utilisateur manquant');
            if (strlen($newPassword) < 8) {
                jsonError('Le mot de passe doit contenir au moins 8 caractères');
            }
            
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("
                UPDATE public.users 
                SET password_hash = :password_hash, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'password_hash' => $passwordHash,
                'id' => $userId
            ]);
            
            logAdminActivity('reset_admin_password', ['user_id' => $userId]);
            
            jsonSuccess(['id' => $userId], 'Mot de passe réinitialisé avec succès');
            break;
        
        // ====================================
        // ACTIVER/DÉSACTIVER UN ADMIN
        // ====================================
        case 'toggle_status':
            $userId = (int)($_POST['id'] ?? 0);
            if (!$userId) jsonError('ID utilisateur manquant');
            
            if ($userId === $currentAdminId) {
                jsonError('Vous ne pouvez pas désactiver votre propre compte');
            }
            
            $stmt = $db->prepare("
                UPDATE public.users 
                SET is_active = NOT is_active, updated_at = NOW()
                WHERE id = :id
                RETURNING is_active
            ");
            $stmt->execute(['id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            logAdminActivity('toggle_admin_status', [
                'user_id' => $userId,
                'new_status' => $result['is_active']
            ]);
            
            $message = $result['is_active'] ? 'Utilisateur activé' : 'Utilisateur désactivé';
            jsonSuccess(['id' => $userId, 'is_active' => (bool)$result['is_active']], $message);
            break;
        
        // ====================================
        // SUPPRIMER UN ADMIN
        // ====================================
        case 'delete':
            $userId = (int)($_POST['id'] ?? 0);
            if (!$userId) jsonError('ID utilisateur manquant');
            
            if ($userId === $currentAdminId) {
                jsonError('Vous ne pouvez pas supprimer votre propre compte');
            }
            
            // Vérifier qu'il reste au moins un admin actif
            $stmtCount = $db->query("
                SELECT COUNT(*) as count 
                FROM public.users 
                WHERE role = 'admin' AND is_active = true AND id != $userId
            ");
            $adminCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($adminCount == 0) {
                jsonError('Impossible de supprimer le dernier administrateur actif');
            }
            
            $stmt = $db->prepare("DELETE FROM public.users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            
            logAdminActivity('delete_admin_user', ['user_id' => $userId]);
            
            jsonSuccess(['id' => $userId], 'Utilisateur supprimé avec succès');
            break;
        
        // ====================================
        // STATISTIQUES
        // ====================================
        case 'stats':
            $stats = [
                'total' => 0,
                'admins' => 0,
                'viewers' => 0,
                'active' => 0,
                'inactive' => 0
            ];
            
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN role = 'viewer' THEN 1 ELSE 0 END) as viewers,
                    SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = false THEN 1 ELSE 0 END) as inactive
                FROM public.users
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats = [
                    'total' => (int)$result['total'],
                    'admins' => (int)$result['admins'],
                    'viewers' => (int)$result['viewers'],
                    'active' => (int)$result['active'],
                    'inactive' => (int)$result['inactive']
                ];
            }
            
            jsonSuccess($stats);
            break;
        
        default:
            jsonError('Action inconnue: ' . $action);
    }
    
} catch (PDOException $e) {
    error_log('Erreur BDD admin_users: ' . $e->getMessage());
    jsonError('Erreur base de données: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Erreur admin_users: ' . $e->getMessage());
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}