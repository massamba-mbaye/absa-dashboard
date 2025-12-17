<?php
/**
 * API ANALYSE ÉMOTIONS - VERSION 3 FINALE (SANS ERREUR)
 * Compatible avec le VRAI schéma Supabase
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

// Mapping des émotions avec emojis
function getEmotionEmoji($emotion) {
    $emojis = [
        'joie' => '😊', 'tristesse' => '😢', 'colère' => '😠',
        'peur' => '😰', 'surprise' => '😲', 'anxiété' => '😰',
        'confusion' => '😕', 'espoir' => '🙏', 'désespoir' => '😞',
        'honte' => '😳', 'soulagement' => '😌', 'frustration' => '😤',
        'inquiétude' => '😟', 'détresse' => '😖', 'nervosité' => '😬',
        'stress' => '😓', 'dégoût' => '🤢', 'culpabilité' => '😔',
        'fierté' => '😌', 'gratitude' => '🙏', 'amour' => '❤️',
        'jalousie' => '😒', 'solitude' => '😔', 'panique' => '😱',
        'terreur' => '😱', 'empathie' => '🤝', 'soutien' => '🤗',
        'urgence' => '⚡', 'préoccupation' => '😟', 'patience' => '😌',
        'professionnalisme' => '💼', 'curiosité' => '🤔', 'information' => 'ℹ️',
        'admiration' => '🌟', 'reconnaissance' => '🙏', 'bienveillance' => '💚',
        'satisfaction' => '😊'
    ];
    
    $emotionLower = strtolower(trim($emotion));
    return $emojis[$emotionLower] ?? '💭';
}

// Mapping des couleurs pour niveaux d'urgence
function getUrgencyColor($level) {
    if ($level >= 5) return '#ef4444'; // red-500
    if ($level >= 4) return '#f97316'; // orange-500
    if ($level >= 3) return '#f59e0b'; // amber-500
    if ($level >= 2) return '#eab308'; // yellow-500
    return '#22c55e'; // green-500
}

$action = $_GET['action'] ?? 'overview';

try {
    $db = getDB();
    
    // Période par défaut : 30 jours
    $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
    
    if ($period < 0) $period = 0;
    if ($period > 90) $period = 90;
    
    $dateLimit = $period > 0 
        ? date('Y-m-d H:i:s', strtotime("-{$period} days"))
        : '1970-01-01 00:00:00';
    
    switch ($action) {
        
        case 'overview':
            // 1. Statistiques globales (TOUTES, pas seulement la période)
            $stmtTotalAnalyses = $db->query("
                SELECT COUNT(*) as total
                FROM public.messages
                WHERE urgency_analysis IS NOT NULL
            ");
            $totalAnalyses = (int)$stmtTotalAnalyses->fetch(PDO::FETCH_ASSOC)['total'];

            $stmtAvgUrgency = $db->query("
                SELECT AVG(current_urgency_level) as avg_urgency
                FROM public.conversations
                WHERE current_urgency_level > 0
            ");
            $avgUrgency = $stmtAvgUrgency->fetch(PDO::FETCH_ASSOC)['avg_urgency'];
            $avgUrgency = $avgUrgency ? round((float)$avgUrgency, 2) : 0;
            
            $stmtUrgentCases = $db->query("
                SELECT COUNT(*) as total
                FROM public.conversations
                WHERE current_urgency_level >= 4
            ");
            $urgentCases = (int)$stmtUrgentCases->fetch(PDO::FETCH_ASSOC)['total'];

            $stmtViolence = $db->query("
                SELECT COUNT(*) as total
                FROM public.messages
                WHERE urgency_analysis->>'violence_type' IS NOT NULL
                AND urgency_analysis->>'violence_type' != ''
            ");
            $violenceReported = (int)$stmtViolence->fetch(PDO::FETCH_ASSOC)['total'];

            // 2. Distribution des sentiments (TOUS)
            $stmtSentiments = $db->query("
                SELECT
                    urgency_analysis->>'sentiment' as sentiment,
                    COUNT(*) as count
                FROM public.messages
                WHERE urgency_analysis IS NOT NULL
                AND urgency_analysis->>'sentiment' IS NOT NULL
                GROUP BY urgency_analysis->>'sentiment'
            ");
            
            $sentiments = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
            
            while ($row = $stmtSentiments->fetch(PDO::FETCH_ASSOC)) {
                $sent = strtolower(trim($row['sentiment']));
                if (isset($sentiments[$sent])) {
                    $sentiments[$sent] = (int)$row['count'];
                }
            }
            
            $totalSentiments = array_sum($sentiments);
            $sentimentsWithPercentage = [];
            foreach ($sentiments as $sentiment => $count) {
                $percentage = $totalSentiments > 0 
                    ? round(($count / $totalSentiments) * 100, 1)
                    : 0;
                
                $sentimentsWithPercentage[] = [
                    'sentiment' => ucfirst($sentiment),
                    'count' => $count,
                    'percentage' => $percentage
                ];
            }
            
            // Distribution pour graphiques (format objet)
            $sentimentDistribution = [
                'positive' => $sentiments['positive'],
                'neutral' => $sentiments['neutral'],
                'negative' => $sentiments['negative']
            ];
            
            // 3. Top 10 émotions (TOUTES)
            $stmtEmotions = $db->query("
                SELECT
                    urgency_analysis->>'emotion' as emotion,
                    COUNT(*) as count
                FROM public.messages
                WHERE urgency_analysis IS NOT NULL
                AND urgency_analysis->>'emotion' IS NOT NULL
                AND urgency_analysis->>'emotion' != ''
                GROUP BY urgency_analysis->>'emotion'
                ORDER BY count DESC
                LIMIT 10
            ");

            $topEmotions = [];
            while ($row = $stmtEmotions->fetch(PDO::FETCH_ASSOC)) {
                if ($row['emotion']) {
                    $emotion = trim($row['emotion']);
                    $topEmotions[] = [
                        'emotion' => ucfirst($emotion),
                        'emoji' => getEmotionEmoji($emotion),
                        'count' => (int)$row['count']
                    ];
                }
            }

            // 4. Distribution des niveaux d'urgence (TOUTES)
            $stmtUrgencyDist = $db->query("
                SELECT
                    current_urgency_level as level,
                    COUNT(*) as count
                FROM public.conversations
                WHERE current_urgency_level > 0
                GROUP BY current_urgency_level
                ORDER BY current_urgency_level ASC
            ");
            
            $urgencyDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $urgencyDistribution[] = [
                    'level' => $i,
                    'count' => 0,
                    'label' => "Niveau $i",
                    'color' => getUrgencyColor($i)
                ];
            }
            
            while ($row = $stmtUrgencyDist->fetch(PDO::FETCH_ASSOC)) {
                $level = (int)$row['level'];
                if ($level >= 1 && $level <= 5) {
                    $urgencyDistribution[$level - 1]['count'] = (int)$row['count'];
                }
            }
            
            // 5. Évolution des sentiments (30 derniers jours max)
            $evolutionDays = min($period > 0 ? $period : 30, 30);
            $sentimentEvolution = [];
            
            for ($i = $evolutionDays - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                
                $stmtEvol = $db->prepare("
                    SELECT 
                        urgency_analysis->>'sentiment' as sentiment,
                        COUNT(*) as count
                    FROM public.messages
                    WHERE DATE(sent_at) = :date
                    AND urgency_analysis IS NOT NULL
                    AND urgency_analysis->>'sentiment' IS NOT NULL
                    GROUP BY urgency_analysis->>'sentiment'
                ");
                $stmtEvol->execute(['date' => $date]);
                
                $daySentiments = [
                    'date' => $date,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0
                ];
                
                while ($row = $stmtEvol->fetch(PDO::FETCH_ASSOC)) {
                    $sent = strtolower(trim($row['sentiment']));
                    if (isset($daySentiments[$sent])) {
                        $daySentiments[$sent] = (int)$row['count'];
                    }
                }
                
                $sentimentEvolution[] = $daySentiments;
            }
            
            // 6. Types de violences
            $stmtViolenceTypes = $db->query("
                SELECT
                    urgency_analysis->>'violence_type' as violence_type,
                    COUNT(*) as count
                FROM public.messages
                WHERE urgency_analysis IS NOT NULL
                AND urgency_analysis->>'violence_type' IS NOT NULL
                AND urgency_analysis->>'violence_type' != ''
                GROUP BY urgency_analysis->>'violence_type'
                ORDER BY count DESC
            ");

            $violenceTypes = [];
            while ($row = $stmtViolenceTypes->fetch(PDO::FETCH_ASSOC)) {
                if ($row['violence_type']) {
                    $violenceTypes[] = [
                        'type' => ucfirst($row['violence_type']),
                        'count' => (int)$row['count']
                    ];
                }
            }

            // 7. Émotions par sentiment (TOUTES)
            $stmtEmotionsBySentiment = $db->query("
                SELECT
                    urgency_analysis->>'sentiment' as sentiment,
                    urgency_analysis->>'emotion' as emotion,
                    COUNT(*) as count
                FROM public.messages
                WHERE urgency_analysis IS NOT NULL
                AND urgency_analysis->>'sentiment' IS NOT NULL
                AND urgency_analysis->>'emotion' IS NOT NULL
                AND urgency_analysis->>'emotion' != ''
                GROUP BY
                    urgency_analysis->>'sentiment',
                    urgency_analysis->>'emotion'
                ORDER BY count DESC
            ");
            
            $emotionsBySentiment = [
                'positive' => [],
                'neutral' => [],
                'negative' => []
            ];
            
            while ($row = $stmtEmotionsBySentiment->fetch(PDO::FETCH_ASSOC)) {
                $sentiment = strtolower(trim($row['sentiment']));
                $emotion = trim($row['emotion']);
                
                if (isset($emotionsBySentiment[$sentiment]) && $emotion) {
                    if (count($emotionsBySentiment[$sentiment]) < 5) {
                        $emotionsBySentiment[$sentiment][] = [
                            'emotion' => ucfirst($emotion),
                            'emoji' => getEmotionEmoji($emotion),
                            'count' => (int)$row['count']
                        ];
                    }
                }
            }
            
            logAdminActivity('view_emotions_overview', ['period' => $period]);
            
            jsonSuccess([
                'period' => [
                    'days' => $period,
                    'from' => $dateLimit,
                    'to' => date('Y-m-d H:i:s')
                ],
                'global_stats' => [
                    'total_analyses' => $totalAnalyses,
                    'avg_urgency' => $avgUrgency,
                    'urgent_cases' => $urgentCases,
                    'violence_reported' => $violenceReported
                ],
                'sentiments' => $sentimentsWithPercentage,
                'sentiment_distribution' => $sentimentDistribution,
                'top_emotions' => $topEmotions,
                'urgency_distribution' => $urgencyDistribution,
                'sentiment_evolution' => $sentimentEvolution,
                'violence_types' => $violenceTypes,
                'emotions_by_sentiment' => $emotionsBySentiment
            ]);
            break;
        
        default:
            jsonError('Action inconnue: ' . $action, 400);
    }
    
} catch (PDOException $e) {
    error_log('Erreur BDD emotions: ' . $e->getMessage());
    jsonError('Erreur base de données: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Erreur emotions: ' . $e->getMessage());
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}