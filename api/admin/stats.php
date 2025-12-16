<?php
/**
 * API STATISTIQUES DASHBOARD - VERSION 3 FINALE
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
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

function jsonSuccess($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit;
}

try {
    $db = getDB();
    
    $period = isset($_GET['period']) ? (int)$_GET['period'] : 7;
    $period = max(1, min(365, $period));
    
    $dateLimit = date('Y-m-d H:i:s', strtotime("-{$period} days"));
    
    // ============================================
    // 1. STATISTIQUES GLOBALES
    // ============================================
    
    // Total utilisateurs uniques
    $stmtUsers = $db->query("
        SELECT COUNT(DISTINCT wa_id) as total
        FROM public.conversations
        WHERE started_at >= '{$dateLimit}'
    ");
    $totalUsers = (int)$stmtUsers->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total conversations
    $stmtConv = $db->query("
        SELECT COUNT(*) as total
        FROM public.conversations
        WHERE started_at >= '{$dateLimit}'
    ");
    $totalConversations = (int)$stmtConv->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total messages
    $stmtMsg = $db->query("
        SELECT COUNT(*) as total
        FROM public.messages
        WHERE sent_at >= '{$dateLimit}'
    ");
    $totalMessages = (int)$stmtMsg->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Urgences critiques
    $stmtUrg = $db->query("
        SELECT COUNT(*) as total
        FROM public.conversations
        WHERE current_urgency_level >= 4
        AND started_at >= '{$dateLimit}'
    ");
    $totalUrgencies = (int)$stmtUrg->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ============================================
    // 2. SENTIMENTS (7 derniers jours)
    // ============================================
    
    $sentiments = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        $stmtSent = $db->prepare("
            SELECT 
                urgency_analysis->>'sentiment' as sentiment,
                COUNT(*) as count
            FROM public.messages
            WHERE DATE(sent_at) = :date
            AND urgency_analysis IS NOT NULL
            AND urgency_analysis->>'sentiment' IS NOT NULL
            GROUP BY urgency_analysis->>'sentiment'
        ");
        $stmtSent->execute(['date' => $date]);
        
        $daySentiments = [
            'date' => $date,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0
        ];
        
        while ($row = $stmtSent->fetch(PDO::FETCH_ASSOC)) {
            $sent = strtolower(trim($row['sentiment']));
            if (in_array($sent, ['positive', 'neutral', 'negative'])) {
                $daySentiments[$sent] = (int)$row['count'];
            }
        }
        
        $sentiments[] = $daySentiments;
    }
    
    // ============================================
    // 3. TOP 5 ÉMOTIONS
    // ============================================
    
    $stmtEmotions = $db->prepare("
        SELECT 
            urgency_analysis->>'emotion' as emotion,
            COUNT(*) as count
        FROM public.messages
        WHERE urgency_analysis IS NOT NULL
        AND urgency_analysis->>'emotion' IS NOT NULL
        AND urgency_analysis->>'emotion' != ''
        AND sent_at >= :date_limit
        GROUP BY urgency_analysis->>'emotion'
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmtEmotions->execute(['date_limit' => $dateLimit]);
    
    $topEmotions = [];
    $emojis = [
        'joie' => '😊', 'tristesse' => '😢', 'colère' => '😠',
        'peur' => '😰', 'surprise' => '😲', 'anxiété' => '😰',
        'confusion' => '😕', 'espoir' => '🙏', 'désespoir' => '😞',
        'honte' => '😳', 'soulagement' => '😌', 'frustration' => '😤',
        'inquiétude' => '😟', 'détresse' => '😖'
    ];
    
    while ($row = $stmtEmotions->fetch(PDO::FETCH_ASSOC)) {
        if ($row['emotion']) {
            $emotion = strtolower(trim($row['emotion']));
            $emoji = $emojis[$emotion] ?? '💭';
            
            $topEmotions[] = [
                'emotion' => ucfirst($emotion),
                'emoji' => $emoji,
                'count' => (int)$row['count']
            ];
        }
    }
    
    // ============================================
    // 4. TYPES DE VIOLENCES
    // ============================================
    
    $stmtViolence = $db->prepare("
        SELECT 
            urgency_analysis->>'violence_type' as violence_type,
            COUNT(*) as count
        FROM public.messages
        WHERE urgency_analysis IS NOT NULL
        AND urgency_analysis->>'violence_type' IS NOT NULL
        AND urgency_analysis->>'violence_type' != ''
        AND sent_at >= :date_limit
        GROUP BY urgency_analysis->>'violence_type'
        ORDER BY count DESC
    ");
    $stmtViolence->execute(['date_limit' => $dateLimit]);
    
    $violenceTypes = [];
    while ($row = $stmtViolence->fetch(PDO::FETCH_ASSOC)) {
        if ($row['violence_type']) {
            $violenceTypes[] = [
                'type' => ucfirst($row['violence_type']),
                'count' => (int)$row['count']
            ];
        }
    }
    
    // ============================================
    // 5. CONVERSATIONS RÉCENTES (SANS TOPIC)
    // ============================================
    
    // Récupérer les 10 dernières conversations
    $stmtRecentConv = $db->query("
        SELECT 
            id,
            wa_id,
            started_at,
            last_message_at,
            message_count,
            current_urgency_level
        FROM public.conversations
        ORDER BY last_message_at DESC
        LIMIT 10
    ");
    
    $recentConversations = [];
    
    while ($conv = $stmtRecentConv->fetch(PDO::FETCH_ASSOC)) {
        $convId = $conv['id'];
        
        // Récupérer le dernier message user pour analyse
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
        
        $urgencyData = null;
        if ($lastMsg && $lastMsg['urgency_analysis']) {
            $urgencyData = json_decode($lastMsg['urgency_analysis'], true);
        }
        
        // Générer un titre automatique
        $title = "Conversation #" . $convId;
        
        // Optionnel : utiliser un aperçu du contenu comme titre
        if ($lastMsg && !empty($lastMsg['content'])) {
            $preview = substr($lastMsg['content'], 0, 40);
            if (strlen($lastMsg['content']) > 40) {
                $preview .= '...';
            }
            $title = $preview;
        }
        
        $shortWaId = substr($conv['wa_id'], 0, 8);
        
        $recentConversations[] = [
            'id' => (int)$convId,
            'title' => $title,
            'user_id' => $conv['wa_id'],
            'user_id_short' => $shortWaId,
            'message_count' => (int)$conv['message_count'],
            'sentiment' => $urgencyData['sentiment'] ?? 'neutral',
            'emotion' => isset($urgencyData['emotion']) ? ucfirst($urgencyData['emotion']) : '-',
            'urgency' => $conv['current_urgency_level'] ? (int)$conv['current_urgency_level'] : 0,
            'violence_type' => isset($urgencyData['violence_type']) ? ucfirst($urgencyData['violence_type']) : null,
            'created_at' => $conv['started_at'],
            'updated_at' => $conv['last_message_at']
        ];
    }
    
    // ============================================
    // 6. STATS SUPPLÉMENTAIRES
    // ============================================
    
    $stmtSentRate = $db->prepare("
        SELECT 
            urgency_analysis->>'sentiment' as sentiment,
            COUNT(*) as count
        FROM public.messages
        WHERE urgency_analysis IS NOT NULL
        AND urgency_analysis->>'sentiment' IS NOT NULL
        AND sent_at >= :date_limit
        GROUP BY urgency_analysis->>'sentiment'
    ");
    $stmtSentRate->execute(['date_limit' => $dateLimit]);
    
    $sentimentCounts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
    
    while ($row = $stmtSentRate->fetch(PDO::FETCH_ASSOC)) {
        $sent = strtolower(trim($row['sentiment']));
        if (isset($sentimentCounts[$sent])) {
            $sentimentCounts[$sent] = (int)$row['count'];
        }
    }
    
    $totalSent = array_sum($sentimentCounts);
    $positiveRate = $totalSent > 0 
        ? round(($sentimentCounts['positive'] / $totalSent) * 100, 1)
        : 0;
    
    // Moyenne urgence
    $stmtAvgUrg = $db->prepare("
        SELECT AVG(current_urgency_level) as avg_urgency
        FROM public.conversations
        WHERE current_urgency_level IS NOT NULL
        AND current_urgency_level > 0
        AND started_at >= :date_limit
    ");
    $stmtAvgUrg->execute(['date_limit' => $dateLimit]);
    $avgUrgency = $stmtAvgUrg->fetch(PDO::FETCH_ASSOC)['avg_urgency'];
    $avgUrgency = $avgUrgency ? round((float)$avgUrgency, 2) : 0;
    
    $avgMessagesPerConv = $totalConversations > 0 
        ? round($totalMessages / $totalConversations, 1)
        : 0;
    
    // ============================================
    // RÉPONSE
    // ============================================
    
    $response = [
        'period' => [
            'days' => $period,
            'from' => $dateLimit,
            'to' => date('Y-m-d H:i:s')
        ],
        'global_stats' => [
            'users' => [
                'total' => $totalUsers,
                'label' => 'Utilisateurs',
                'icon' => 'users',
                'color' => 'blue',
                'change' => null
            ],
            'conversations' => [
                'total' => $totalConversations,
                'label' => 'Conversations',
                'icon' => 'comments',
                'color' => 'green',
                'badge' => $avgMessagesPerConv . ' msgs/conv'
            ],
            'messages' => [
                'total' => $totalMessages,
                'label' => 'Messages',
                'icon' => 'envelope',
                'color' => 'purple',
                'badge' => null
            ],
            'urgencies' => [
                'total' => $totalUrgencies,
                'label' => 'Urgences',
                'icon' => 'exclamation-triangle',
                'color' => 'orange',
                'badge' => 'Niveau ≥ 4',
                'is_danger' => $totalUrgencies > 0
            ]
        ],
        'sentiments' => [
            'timeline' => $sentiments,
            'distribution' => $sentimentCounts,
            'positive_rate' => $positiveRate
        ],
        'top_emotions' => $topEmotions,
        'violence_types' => $violenceTypes,
        'recent_conversations' => $recentConversations,
        'extra_stats' => [
            'avg_urgency' => $avgUrgency,
            'avg_messages_per_conv' => $avgMessagesPerConv,
            'total_sentiments' => $totalSent
        ]
    ];
    
    logAdminActivity('view_dashboard_stats', [
        'period' => $period,
        'total_users' => $totalUsers
    ]);
    
    jsonSuccess($response);
    
} catch (PDOException $e) {
    error_log('Erreur BDD stats: ' . $e->getMessage());
    jsonError('Erreur base de données: ' . $e->getMessage(), 500);
    
} catch (Exception $e) {
    error_log('Erreur stats: ' . $e->getMessage());
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}