<?php
/**
 * API GESTION UTILISATEURS - VERSION 3 FINALE
 * Compatible avec le VRAI schéma Supabase (sans colonne topic)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

function jsonSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data, 'timestamp' => date('c')]);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    
    switch ($action) {
        
        case 'list':
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 20;
            $offset = ($page - 1) * $perPage;
            
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'last_activity';
            $sortOrder = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
            
            $whereClause = '';
            $params = [];
            
            if ($search !== '') {
                $whereClause = "WHERE c.wa_id LIKE :search";
                $params['search'] = "%{$search}%";
            }
            
            // Compter le total
            $countQuery = "
                SELECT COUNT(DISTINCT c.wa_id) as total
                FROM public.conversations c
                {$whereClause}
            ";
            
            $stmtCount = $db->prepare($countQuery);
            $stmtCount->execute($params);
            $totalUsers = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Récupérer les utilisateurs avec stats de base
            $query = "
                SELECT 
                    c.wa_id,
                    COUNT(DISTINCT c.id) as conversation_count,
                    SUM(c.message_count) as message_count,
                    MIN(c.started_at) as first_seen,
                    MAX(c.last_message_at) as last_activity,
                    MAX(c.current_urgency_level) as max_urgency
                FROM public.conversations c
                {$whereClause}
                GROUP BY c.wa_id
                ORDER BY MAX(c.last_message_at) {$sortOrder}
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $wa_id = $row['wa_id'];
                $shortWaId = substr($wa_id, 0, 8);
                
                // Récupérer le dernier message de cet utilisateur
                $stmtLastMsg = $db->prepare("
                    SELECT 
                        m.sent_at,
                        m.content,
                        m.urgency_analysis
                    FROM public.messages m
                    JOIN public.conversations c ON m.conversation_id = c.id
                    WHERE c.wa_id = :wa_id
                    AND m.sender = 'user'
                    ORDER BY m.sent_at DESC
                    LIMIT 1
                ");
                $stmtLastMsg->execute(['wa_id' => $wa_id]);
                $lastMsg = $stmtLastMsg->fetch(PDO::FETCH_ASSOC);
                
                $lastSentiment = 'neutral';
                $lastEmotion = null;
                $lastConversation = null;
                
                if ($lastMsg) {
                    // Utiliser un aperçu du contenu comme "dernière conversation"
                    if (!empty($lastMsg['content'])) {
                        $lastConversation = substr($lastMsg['content'], 0, 50);
                        if (strlen($lastMsg['content']) > 50) {
                            $lastConversation .= '...';
                        }
                    }
                    
                    // Extraire sentiment et émotion
                    if ($lastMsg['urgency_analysis']) {
                        $urgencyData = json_decode($lastMsg['urgency_analysis'], true);
                        if ($urgencyData) {
                            $lastSentiment = $urgencyData['sentiment'] ?? 'neutral';
                            $lastEmotion = isset($urgencyData['emotion']) ? ucfirst($urgencyData['emotion']) : null;
                        }
                    }
                }
                
                $users[] = [
                    'user_id' => $wa_id,
                    'user_id_short' => $shortWaId,
                    'conversation_count' => (int)$row['conversation_count'],
                    'message_count' => (int)($row['message_count'] ?? 0),
                    'first_seen' => $row['first_seen'],
                    'last_activity' => $row['last_activity'],
                    'last_conversation' => $lastConversation,
                    'last_conversation_date' => $lastMsg ? $lastMsg['sent_at'] : null,
                    'last_sentiment' => $lastSentiment,
                    'max_urgency' => $row['max_urgency'] ? (int)$row['max_urgency'] : 0,
                    'last_emotion' => $lastEmotion
                ];
            }
            
            $totalPages = ceil($totalUsers / $perPage);
            
            logAdminActivity('view_users_list', ['page' => $page, 'search' => $search]);
            
            jsonSuccess([
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_users' => $totalUsers,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'filters' => [
                    'search' => $search,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder
                ]
            ]);
            break;
        
        case 'details':
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) {
                jsonError('Paramètre user_id requis', 400);
            }
            
            // Vérifier si l'utilisateur existe
            $stmtExists = $db->prepare("
                SELECT COUNT(*) as count
                FROM public.conversations
                WHERE wa_id = :wa_id
            ");
            $stmtExists->execute(['wa_id' => $userId]);
            
            if ((int)$stmtExists->fetch(PDO::FETCH_ASSOC)['count'] === 0) {
                jsonError('Utilisateur introuvable', 404);
            }
            
            // Stats globales
            $stmtStats = $db->prepare("
                SELECT 
                    COUNT(DISTINCT c.id) as conversation_count,
                    SUM(c.message_count) as message_count,
                    MIN(c.started_at) as first_seen,
                    MAX(c.last_message_at) as last_activity,
                    AVG(c.current_urgency_level) as avg_urgency
                FROM public.conversations c
                WHERE c.wa_id = :wa_id
            ");
            $stmtStats->execute(['wa_id' => $userId]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
            
            // Sentiments
            $stmtSent = $db->prepare("
                SELECT 
                    m.urgency_analysis->>'sentiment' as sentiment,
                    COUNT(*) as count
                FROM public.messages m
                JOIN public.conversations c ON m.conversation_id = c.id
                WHERE c.wa_id = :wa_id
                AND m.urgency_analysis IS NOT NULL
                AND m.urgency_analysis->>'sentiment' IS NOT NULL
                GROUP BY m.urgency_analysis->>'sentiment'
            ");
            $stmtSent->execute(['wa_id' => $userId]);
            
            $sentiments = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
            
            while ($row = $stmtSent->fetch(PDO::FETCH_ASSOC)) {
                $sent = strtolower(trim($row['sentiment']));
                if (isset($sentiments[$sent])) {
                    $sentiments[$sent] = (int)$row['count'];
                }
            }
            
            // Émotions principales
            $stmtEmot = $db->prepare("
                SELECT 
                    m.urgency_analysis->>'emotion' as emotion,
                    COUNT(*) as count
                FROM public.messages m
                JOIN public.conversations c ON m.conversation_id = c.id
                WHERE c.wa_id = :wa_id
                AND m.urgency_analysis IS NOT NULL
                AND m.urgency_analysis->>'emotion' IS NOT NULL
                AND m.urgency_analysis->>'emotion' != ''
                GROUP BY m.urgency_analysis->>'emotion'
                ORDER BY count DESC
                LIMIT 5
            ");
            $stmtEmot->execute(['wa_id' => $userId]);
            
            $emotions = [];
            while ($row = $stmtEmot->fetch(PDO::FETCH_ASSOC)) {
                if ($row['emotion']) {
                    $emotions[] = [
                        'emotion' => ucfirst($row['emotion']),
                        'count' => (int)$row['count']
                    ];
                }
            }
            
            // Types de violence
            $stmtViolence = $db->prepare("
                SELECT 
                    m.urgency_analysis->>'violence_type' as violence_type,
                    COUNT(*) as count
                FROM public.messages m
                JOIN public.conversations c ON m.conversation_id = c.id
                WHERE c.wa_id = :wa_id
                AND m.urgency_analysis IS NOT NULL
                AND m.urgency_analysis->>'violence_type' IS NOT NULL
                AND m.urgency_analysis->>'violence_type' != ''
                GROUP BY m.urgency_analysis->>'violence_type'
                ORDER BY count DESC
            ");
            $stmtViolence->execute(['wa_id' => $userId]);
            
            $violenceTypes = [];
            while ($row = $stmtViolence->fetch(PDO::FETCH_ASSOC)) {
                if ($row['violence_type']) {
                    $violenceTypes[] = [
                        'type' => ucfirst($row['violence_type']),
                        'count' => (int)$row['count']
                    ];
                }
            }
            
            // Conversations
            $stmtConv = $db->prepare("
                SELECT 
                    c.id,
                    c.started_at,
                    c.last_message_at,
                    c.message_count,
                    c.current_urgency_level
                FROM public.conversations c
                WHERE c.wa_id = :wa_id
                ORDER BY c.last_message_at DESC
            ");
            $stmtConv->execute(['wa_id' => $userId]);
            
            $conversations = [];
            while ($row = $stmtConv->fetch(PDO::FETCH_ASSOC)) {
                $convId = $row['id'];
                
                // Récupérer le dernier message de la conversation
                $stmtLastMsg = $db->prepare("
                    SELECT 
                        content,
                        urgency_analysis
                    FROM public.messages
                    WHERE conversation_id = :conv_id
                    AND sender = 'user'
                    ORDER BY sent_at DESC
                    LIMIT 1
                ");
                $stmtLastMsg->execute(['conv_id' => $convId]);
                $lastMsg = $stmtLastMsg->fetch(PDO::FETCH_ASSOC);
                
                $lastSentiment = 'neutral';
                $title = "Conversation #" . $convId;
                
                if ($lastMsg) {
                    // Utiliser aperçu du contenu comme titre
                    if (!empty($lastMsg['content'])) {
                        $preview = substr($lastMsg['content'], 0, 40);
                        if (strlen($lastMsg['content']) > 40) {
                            $preview .= '...';
                        }
                        $title = $preview;
                    }
                    
                    if ($lastMsg['urgency_analysis']) {
                        $urgencyData = json_decode($lastMsg['urgency_analysis'], true);
                        if ($urgencyData) {
                            $lastSentiment = $urgencyData['sentiment'] ?? 'neutral';
                        }
                    }
                }
                
                $conversations[] = [
                    'id' => (int)$convId,
                    'title' => $title,
                    'message_count' => (int)$row['message_count'],
                    'max_urgency' => $row['current_urgency_level'] ? (int)$row['current_urgency_level'] : 0,
                    'last_sentiment' => $lastSentiment,
                    'created_at' => $row['started_at'],
                    'updated_at' => $row['last_message_at']
                ];
            }
            
            logAdminActivity('view_user_details', ['user_id' => $userId]);
            
            jsonSuccess([
                'user_id' => $userId,
                'user_id_short' => substr($userId, 0, 8),
                'stats' => [
                    'conversation_count' => (int)$stats['conversation_count'],
                    'message_count' => (int)($stats['message_count'] ?? 0),
                    'first_seen' => $stats['first_seen'],
                    'last_activity' => $stats['last_activity'],
                    'avg_urgency' => $stats['avg_urgency'] ? round((float)$stats['avg_urgency'], 2) : 0
                ],
                'sentiments' => $sentiments,
                'emotions' => $emotions,
                'violence_types' => $violenceTypes,
                'conversations' => $conversations
            ]);
            break;
        
        case 'stats':
            $stmtTotal = $db->query("SELECT COUNT(DISTINCT wa_id) as total FROM public.conversations");
            $totalUsers = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmtConv = $db->query("SELECT COUNT(*) as total FROM public.conversations");
            $totalConversations = (int)$stmtConv->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmtMsg = $db->query("SELECT COUNT(*) as total FROM public.messages");
            $totalMessages = (int)$stmtMsg->fetch(PDO::FETCH_ASSOC)['total'];
            
            $avgMessagesPerUser = $totalUsers > 0 ? round($totalMessages / $totalUsers, 1) : 0;
            
            $stmtActive = $db->query("
                SELECT COUNT(DISTINCT wa_id) as total
                FROM public.conversations
                WHERE last_message_at >= NOW() - INTERVAL '24 hours'
            ");
            $activeUsers24h = (int)$stmtActive->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmtNew = $db->query("
                SELECT COUNT(DISTINCT wa_id) as total
                FROM public.conversations
                WHERE started_at >= NOW() - INTERVAL '7 days'
            ");
            $newUsers7d = (int)$stmtNew->fetch(PDO::FETCH_ASSOC)['total'];
            
            jsonSuccess([
                'total_users' => $totalUsers,
                'total_conversations' => $totalConversations,
                'total_messages' => $totalMessages,
                'avg_messages_per_user' => $avgMessagesPerUser,
                'active_users_24h' => $activeUsers24h,
                'new_users_7d' => $newUsers7d
            ]);
            break;
        
        case 'export':
            $query = "
                SELECT 
                    c.wa_id,
                    COUNT(DISTINCT c.id) as conversation_count,
                    SUM(c.message_count) as message_count,
                    MIN(c.started_at) as first_seen,
                    MAX(c.last_message_at) as last_activity,
                    MAX(c.current_urgency_level) as max_urgency
                FROM public.conversations c
                GROUP BY c.wa_id
                ORDER BY MAX(c.last_message_at) DESC
            ";
            
            $stmt = $db->query($query);
            
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = [
                    'UUID' => $row['wa_id'],
                    'UUID_Court' => substr($row['wa_id'], 0, 8),
                    'Conversations' => $row['conversation_count'],
                    'Messages' => $row['message_count'] ?? 0,
                    'Premiere_Visite' => $row['first_seen'],
                    'Derniere_Activite' => $row['last_activity'],
                    'Urgence_Max' => $row['max_urgency'] ?: 0
                ];
            }
            
            logAdminActivity('export_users', ['count' => count($users)]);
            
            jsonSuccess([
                'users' => $users,
                'count' => count($users),
                'exported_at' => date('Y-m-d H:i:s')
            ]);
            break;
        
        default:
            jsonError('Action inconnue: ' . $action, 400);
    }
    
} catch (PDOException $e) {
    error_log('Erreur BDD users: ' . $e->getMessage());
    jsonError('Erreur base de données: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Erreur users: ' . $e->getMessage());
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}