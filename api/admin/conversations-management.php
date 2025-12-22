<?php
/**
 * API GESTION CONVERSATIONS - VERSION 3 FINALE
 * Compatible avec le VRAI schéma Supabase (sans colonne topic)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
            $filterSentiment = isset($_GET['sentiment']) ? strtolower(trim($_GET['sentiment'])) : '';
            $filterUrgency = isset($_GET['urgency']) ? (int)$_GET['urgency'] : 0;
            
            $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'last_activity';
            $sortOrder = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
            
            $allowedSort = [
                'last_activity' => 'c.last_message_at',
                'created' => 'c.started_at',
                'messages' => 'c.message_count'
            ];
            
            if (!isset($allowedSort[$sortBy])) {
                $sortBy = 'last_activity';
            }
            
            $sortColumn = $allowedSort[$sortBy];
            
            // Construction WHERE
            $whereConditions = [];
            $params = [];
            
            if ($search !== '') {
                $whereConditions[] = "c.wa_id LIKE :search";
                $params['search'] = "%{$search}%";
            }
            
            if ($filterUrgency > 0) {
                $whereConditions[] = "c.current_urgency_level >= :urgency";
                $params['urgency'] = $filterUrgency;
            }
            
            $whereClause = count($whereConditions) > 0 
                ? 'WHERE ' . implode(' AND ', $whereConditions)
                : '';
            
            // Compter le total
            $countQuery = "
                SELECT COUNT(*) as total
                FROM public.conversations c
                {$whereClause}
            ";
            
            $stmtCount = $db->prepare($countQuery);
            $stmtCount->execute($params);
            $totalConversations = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Récupérer les conversations
            $query = "
                SELECT 
                    c.id,
                    c.wa_id,
                    c.started_at,
                    c.last_message_at,
                    c.message_count,
                    c.current_urgency_level
                FROM public.conversations c
                {$whereClause}
                ORDER BY {$sortColumn} {$sortOrder}
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $conversations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $convId = $row['id'];
                
                // Récupérer le dernier message user
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
                $lastEmotion = null;
                $violenceType = null;
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
                            $lastEmotion = isset($urgencyData['emotion']) ? ucfirst($urgencyData['emotion']) : null;
                            $violenceType = isset($urgencyData['violence_type']) ? ucfirst($urgencyData['violence_type']) : null;
                        }
                    }
                }
                
                // Filtrer par sentiment si nécessaire
                if ($filterSentiment !== '' && $lastSentiment !== $filterSentiment) {
                    continue;
                }
                
                $conversations[] = [
                    'id' => (int)$convId,
                    'title' => $title,
                    'user_id' => $row['wa_id'],
                    'user_id_short' => substr($row['wa_id'], 0, 8),
                    'message_count' => (int)$row['message_count'],
                    'sentiment' => $lastSentiment,
                    'emotion' => $lastEmotion,
                    'urgency' => $row['current_urgency_level'] ? (int)$row['current_urgency_level'] : 0,
                    'violence_type' => $violenceType,
                    'created_at' => $row['started_at'],
                    'updated_at' => $row['last_message_at']
                ];
            }
            
            $totalPages = ceil($totalConversations / $perPage);
            
            logAdminActivity('view_conversations_list', ['page' => $page]);
            
            jsonSuccess([
                'conversations' => $conversations,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_conversations' => $totalConversations,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'filters' => [
                    'search' => $search,
                    'sentiment' => $filterSentiment,
                    'urgency' => $filterUrgency,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder
                ]
            ]);
            break;
        
        case 'details':
            $conversationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($conversationId <= 0) {
                jsonError('Paramètre id requis', 400);
            }
            
            // Infos conversation
            $stmtConv = $db->prepare("
                SELECT 
                    c.id,
                    c.wa_id,
                    c.started_at,
                    c.last_message_at,
                    c.message_count
                FROM public.conversations c
                WHERE c.id = :id
            ");
            $stmtConv->execute(['id' => $conversationId]);
            
            $conversation = $stmtConv->fetch(PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                jsonError('Conversation introuvable', 404);
            }
            
            // Générer un titre depuis le premier message user
            $stmtFirstMsg = $db->prepare("
                SELECT content
                FROM public.messages
                WHERE conversation_id = :conv_id
                AND sender = 'user'
                ORDER BY sent_at ASC
                LIMIT 1
            ");
            $stmtFirstMsg->execute(['conv_id' => $conversationId]);
            $firstMsg = $stmtFirstMsg->fetch(PDO::FETCH_ASSOC);
            
            $title = "Conversation #" . $conversationId;
            if ($firstMsg && !empty($firstMsg['content'])) {
                $preview = substr($firstMsg['content'], 0, 50);
                if (strlen($firstMsg['content']) > 50) {
                    $preview .= '...';
                }
                $title = $preview;
            }
            
            // Messages (timeline)
            $stmtMessages = $db->prepare("
                SELECT 
                    id,
                    sender,
                    content,
                    urgency_analysis,
                    sent_at
                FROM public.messages
                WHERE conversation_id = :conversation_id
                ORDER BY sent_at ASC
            ");
            $stmtMessages->execute(['conversation_id' => $conversationId]);
            
            $messages = [];
            while ($row = $stmtMessages->fetch(PDO::FETCH_ASSOC)) {
                $urgencyAnalysis = null;
                if ($row['urgency_analysis']) {
                    $decoded = json_decode($row['urgency_analysis'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $urgencyAnalysis = $decoded;
                    }
                }
                
                $sentiment = $urgencyAnalysis['sentiment'] ?? null;
                $emotion = $urgencyAnalysis['emotion'] ?? null;
                $urgencyLevel = $urgencyAnalysis['urgency_level'] ?? 0;
                $violenceType = $urgencyAnalysis['violence_type'] ?? null;
                
                $messages[] = [
                    'id' => $row['id'],
                    'role' => $row['sender'] === 'user' ? 'user' : 'assistant',
                    'content' => $row['content'],
                    'sentiment' => $sentiment,
                    'emotion' => $emotion ? ucfirst($emotion) : null,
                    'urgency_level' => (int)$urgencyLevel,
                    'urgency_details' => $urgencyAnalysis,
                    'violence_type' => $violenceType ? ucfirst($violenceType) : null,
                    'created_at' => $row['sent_at']
                ];
            }
            
            // Stats de la conversation
            $stmtStats = $db->prepare("
                SELECT 
                    COUNT(CASE WHEN urgency_analysis->>'sentiment' = 'positive' THEN 1 END) as positive_count,
                    COUNT(CASE WHEN urgency_analysis->>'sentiment' = 'neutral' THEN 1 END) as neutral_count,
                    COUNT(CASE WHEN urgency_analysis->>'sentiment' = 'negative' THEN 1 END) as negative_count,
                    MAX(CAST(urgency_analysis->>'urgency_level' AS INTEGER)) as max_urgency,
                    AVG(CAST(urgency_analysis->>'urgency_level' AS DECIMAL)) as avg_urgency
                FROM public.messages
                WHERE conversation_id = :conversation_id
                AND urgency_analysis IS NOT NULL
            ");
            $stmtStats->execute(['conversation_id' => $conversationId]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
            
            logAdminActivity('view_conversation_details', ['conversation_id' => $conversationId]);
            
            jsonSuccess([
                'conversation' => [
                    'id' => (int)$conversation['id'],
                    'title' => $title,
                    'user_id' => $conversation['wa_id'],
                    'user_id_short' => substr($conversation['wa_id'], 0, 8),
                    'message_count' => (int)$conversation['message_count'],
                    'created_at' => $conversation['started_at'],
                    'updated_at' => $conversation['last_message_at']
                ],
                'messages' => $messages,
                'stats' => [
                    'sentiments' => [
                        'positive' => (int)($stats['positive_count'] ?? 0),
                        'neutral' => (int)($stats['neutral_count'] ?? 0),
                        'negative' => (int)($stats['negative_count'] ?? 0)
                    ],
                    'max_urgency' => $stats['max_urgency'] ? (int)$stats['max_urgency'] : 0,
                    'avg_urgency' => $stats['avg_urgency'] ? round((float)$stats['avg_urgency'], 2) : 0
                ]
            ]);
            break;
        
        case 'delete':
            // Seuls les admins peuvent supprimer des conversations
            requireRole('admin');

            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonError('Méthode non autorisée', 405);
            }

            $conversationId = 0;
            
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                $input = json_decode(file_get_contents('php://input'), true);
                $conversationId = isset($input['id']) ? (int)$input['id'] : 0;
            } else {
                $conversationId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
            }
            
            if ($conversationId <= 0) {
                jsonError('Paramètre id requis', 400);
            }
            
            // Vérifier existence
            $stmtCheck = $db->prepare("SELECT id FROM public.conversations WHERE id = :id");
            $stmtCheck->execute(['id' => $conversationId]);
            
            if (!$stmtCheck->fetch()) {
                jsonError('Conversation introuvable', 404);
            }
            
            // Supprimer les messages
            $stmtDeleteMsg = $db->prepare("DELETE FROM public.messages WHERE conversation_id = :id");
            $stmtDeleteMsg->execute(['id' => $conversationId]);
            $deletedMessages = $stmtDeleteMsg->rowCount();
            
            // Supprimer les alertes
            try {
                $stmtDeleteAlert = $db->prepare("DELETE FROM public.alerts WHERE conversation_id = :id");
                $stmtDeleteAlert->execute(['id' => $conversationId]);
            } catch (Exception $e) {
                // Ignorer si la table n'existe pas
            }
            
            // Supprimer la conversation
            $stmtDeleteConv = $db->prepare("DELETE FROM public.conversations WHERE id = :id");
            $stmtDeleteConv->execute(['id' => $conversationId]);
            
            logAdminActivity('delete_conversation', [
                'conversation_id' => $conversationId,
                'deleted_messages' => $deletedMessages
            ]);
            
            jsonSuccess([
                'message' => 'Conversation supprimée avec succès',
                'deleted_conversation_id' => $conversationId,
                'deleted_messages_count' => $deletedMessages
            ]);
            break;
        
        case 'stats':
            $stmtTotal = $db->query("SELECT COUNT(*) as total FROM public.conversations");
            $totalConversations = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmtMsg = $db->query("SELECT COUNT(*) as total FROM public.messages");
            $totalMessages = (int)$stmtMsg->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Taux positifs
            $stmtSent = $db->query("
                SELECT 
                    urgency_analysis->>'sentiment' as sentiment,
                    COUNT(*) as count
                FROM public.messages
                WHERE urgency_analysis IS NOT NULL
                AND urgency_analysis->>'sentiment' IS NOT NULL
                GROUP BY urgency_analysis->>'sentiment'
            ");
            
            $sentimentCounts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
            
            while ($row = $stmtSent->fetch(PDO::FETCH_ASSOC)) {
                $sent = strtolower(trim($row['sentiment']));
                if (isset($sentimentCounts[$sent])) {
                    $sentimentCounts[$sent] = (int)$row['count'];
                }
            }
            
            $totalSent = array_sum($sentimentCounts);
            $positiveRate = $totalSent > 0 
                ? round(($sentimentCounts['positive'] / $totalSent) * 100, 1)
                : 0;
            
            // Conversations urgentes
            $stmtUrg = $db->query("
                SELECT COUNT(*) as total
                FROM public.conversations
                WHERE current_urgency_level >= 4
            ");
            $urgentConversations = (int)$stmtUrg->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Actives 24h
            $stmtActive = $db->query("
                SELECT COUNT(*) as total
                FROM public.conversations
                WHERE last_message_at >= NOW() - INTERVAL '24 hours'
            ");
            $activeConversations24h = (int)$stmtActive->fetch(PDO::FETCH_ASSOC)['total'];
            
            jsonSuccess([
                'total_conversations' => $totalConversations,
                'total_messages' => $totalMessages,
                'positive_rate' => $positiveRate,
                'urgent_conversations' => $urgentConversations,
                'active_conversations_24h' => $activeConversations24h,
                'sentiment_distribution' => $sentimentCounts
            ]);
            break;
        
        case 'export':
            // Seuls les admins peuvent exporter les données
            requireRole('admin');

            $query = "
                SELECT 
                    c.id,
                    c.wa_id,
                    c.started_at,
                    c.last_message_at,
                    c.message_count,
                    c.current_urgency_level
                FROM public.conversations c
                ORDER BY c.last_message_at DESC
            ";
            
            $stmt = $db->query($query);
            
            $conversations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $conversations[] = [
                    'ID' => $row['id'],
                    'Titre' => 'Conversation #' . $row['id'],
                    'UUID_Utilisateur' => $row['wa_id'],
                    'UUID_Court' => substr($row['wa_id'], 0, 8),
                    'Nombre_Messages' => $row['message_count'],
                    'Urgence_Max' => $row['current_urgency_level'] ?: 0,
                    'Date_Creation' => $row['started_at'],
                    'Derniere_Activite' => $row['last_message_at']
                ];
            }
            
            logAdminActivity('export_conversations', ['count' => count($conversations)]);
            
            jsonSuccess([
                'conversations' => $conversations,
                'count' => count($conversations),
                'exported_at' => date('Y-m-d H:i:s')
            ]);
            break;
        
        default:
            jsonError('Action inconnue: ' . $action, 400);
    }
    
} catch (PDOException $e) {
    error_log('Erreur BDD conversations: ' . $e->getMessage());
    jsonError('Erreur base de données: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Erreur conversations: ' . $e->getMessage());
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}