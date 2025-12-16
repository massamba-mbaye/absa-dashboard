<?php
/**
 * PAGE DASHBOARD PRINCIPAL
 * Vue d'ensemble des statistiques ABSA
 */

session_start();

// Charger les configurations
require_once __DIR__ . '/config-path.php';
require_once __DIR__ . '/../config/auth.php';

// Vérifier l'authentification
checkAdminAuth(true);

// Récupérer les infos admin
$adminName = getAdminName();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Dashboard - ABSA Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS Admin -->
    <link rel="stylesheet" href="style-admin.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- ========================================
         MAIN CONTENT
         ======================================== -->
    <main class="main-content">
        
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p class="subtitle">Vue d'ensemble de l'activité ABSA</p>
            </div>
            <button class="btn-refresh" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i>
                <span>Actualiser</span>
            </button>
        </div>
        
        <!-- Loading -->
        <div id="loading" class="loading-container" style="display: none;">
            <div class="spinner"></div>
            <p>Chargement des données...</p>
        </div>
        
        <!-- Dashboard Content -->
        <div id="dashboard-content" class="dashboard-content" style="display: none;">
            
            <!-- ========================================
                 STATISTIQUES GLOBALES (4 cartes)
                 ======================================== -->
            <div class="stats-grid">
                <!-- Utilisateurs -->
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-users">0</h3>
                        <p>Utilisateurs</p>
                        <span class="stat-badge" id="stat-users-badge"></span>
                    </div>
                </div>
                
                <!-- Conversations -->
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-conversations">0</h3>
                        <p>Conversations</p>
                        <span class="stat-badge" id="stat-conversations-badge"></span>
                    </div>
                </div>
                
                <!-- Messages -->
                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-messages">0</h3>
                        <p>Messages</p>
                        <span class="stat-badge" id="stat-messages-badge"></span>
                    </div>
                </div>
                
                <!-- Urgences -->
                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="stat-urgencies">0</h3>
                        <p>Urgences</p>
                        <span class="stat-badge danger" id="stat-urgencies-badge">Niveau ≥ 4</span>
                    </div>
                </div>
            </div>
            
            <!-- ========================================
                 GRAPHIQUES
                 ======================================== -->
            <div class="charts-grid">
                
                <!-- Sentiments (7 derniers jours) -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-chart-line"></i>
                            Sentiments (7 derniers jours)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="sentiment-bars" id="sentiment-bars">
                            <!-- Généré dynamiquement -->
                        </div>
                    </div>
                </div>
                
                <!-- Top 5 Émotions -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-heart"></i>
                            Top 5 Émotions
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="emotions-list">
                            <!-- Généré dynamiquement -->
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- ========================================
                 TYPES DE VIOLENCE
                 ======================================== -->
            <div class="chart-card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-exclamation-circle"></i>
                        Types de Violences Signalées
                    </h3>
                </div>
                <div class="card-body">
                    <div id="violence-types">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>
            </div>
            
            <!-- ========================================
                 CONVERSATIONS RÉCENTES
                 ======================================== -->
            <div class="chart-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-clock"></i>
                        Conversations Récentes
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Utilisateur</th>
                                    <th>Messages</th>
                                    <th>Sentiment</th>
                                    <th>Émotion</th>
                                    <th>Urgence</th>
                                    <th>Violence</th>
                                    <th>Dernière activité</th>
                                </tr>
                            </thead>
                            <tbody id="recent-conversations">
                                <!-- Généré dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
        
    </main>
    
    <!-- ========================================
         SCRIPTS
         ======================================== -->
    <script src="script-admin.js"></script>
    <script>
        // ============================================
        // VARIABLES GLOBALES
        // ============================================
        
        let statsData = null;
        
        // ============================================
        // CHARGEMENT DES DONNÉES
        // ============================================
        
        async function loadDashboardData() {
            const loading = document.getElementById('loading');
            const content = document.getElementById('dashboard-content');
            
            loading.style.display = 'flex';
            content.style.display = 'none';
            
            try {
                const response = await fetch('<?= apiUrl('stats', ['period' => 7]) ?>');
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }
                
                statsData = result.data;
                
                // Afficher les données
                displayGlobalStats(statsData.global_stats);
                displaySentiments(statsData.sentiments);
                displayTopEmotions(statsData.top_emotions);
                displayViolenceTypes(statsData.violence_types);
                displayRecentConversations(statsData.recent_conversations);
                
                loading.style.display = 'none';
                content.style.display = 'block';
                
            } catch (error) {
                console.error('Erreur:', error);
                loading.style.display = 'none';
                adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
            }
        }
        
        // ============================================
        // AFFICHAGE STATISTIQUES GLOBALES
        // ============================================
        
        function displayGlobalStats(stats) {
            // Utilisateurs
            document.getElementById('stat-users').textContent = adminUtils.formatNumber(stats.users.total);
            
            // Conversations
            document.getElementById('stat-conversations').textContent = adminUtils.formatNumber(stats.conversations.total);
            const convBadge = document.getElementById('stat-conversations-badge');
            if (stats.conversations.badge) {
                convBadge.textContent = stats.conversations.badge;
                convBadge.style.display = 'inline-block';
            }
            
            // Messages
            document.getElementById('stat-messages').textContent = adminUtils.formatNumber(stats.messages.total);
            
            // Urgences
            const urgenciesEl = document.getElementById('stat-urgencies');
            urgenciesEl.textContent = adminUtils.formatNumber(stats.urgencies.total);
            
            // Ajouter classe danger si urgences
            if (stats.urgencies.is_danger && stats.urgencies.total > 0) {
                urgenciesEl.style.color = '#ff6b6b';
            }
        }
        
        // ============================================
        // AFFICHAGE SENTIMENTS (7 jours)
        // ============================================
        
        function displaySentiments(sentiments) {
            const container = document.getElementById('sentiment-bars');
            const distribution = sentiments.distribution;
            const total = distribution.positive + distribution.neutral + distribution.negative;
            
            if (total === 0) {
                container.innerHTML = '<p class="no-data">Aucune donnée disponible</p>';
                return;
            }
            
            // Calculer les pourcentages
            const positivePercent = Math.round((distribution.positive / total) * 100);
            const neutralPercent = Math.round((distribution.neutral / total) * 100);
            const negativePercent = Math.round((distribution.negative / total) * 100);
            
            // Configuration sentiments
            const sentimentConfig = [
                {
                    label: 'Positif',
                    icon: 'smile',
                    count: distribution.positive,
                    percent: positivePercent,
                    color: '#51cf66'
                },
                {
                    label: 'Neutre',
                    icon: 'meh',
                    count: distribution.neutral,
                    percent: neutralPercent,
                    color: '#ffd43b'
                },
                {
                    label: 'Négatif',
                    icon: 'frown',
                    count: distribution.negative,
                    percent: negativePercent,
                    color: '#ff6b6b'
                }
            ];
            
            // Générer le HTML
            let html = '';
            sentimentConfig.forEach(sentiment => {
                html += `
                    <div class="sentiment-item">
                        <div class="sentiment-label">
                            <i class="fas fa-${sentiment.icon}" style="color: ${sentiment.color};"></i>
                            <span>${sentiment.label}</span>
                            <strong>${adminUtils.formatNumber(sentiment.count)}</strong>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${sentiment.percent}%; background: ${sentiment.color};"></div>
                        </div>
                        <div class="sentiment-count">${sentiment.percent}%</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // ============================================
        // AFFICHAGE TOP ÉMOTIONS
        // ============================================
        
        function displayTopEmotions(emotions) {
            const container = document.getElementById('emotions-list');
            
            if (emotions.length === 0) {
                container.innerHTML = '<p class="no-data">Aucune émotion détectée</p>';
                return;
            }
            
            let html = '';
            emotions.forEach(emotion => {
                html += `
                    <div class="emotion-item">
                        <div class="emotion-icon">${emotion.emoji}</div>
                        <div class="emotion-name">${emotion.emotion}</div>
                        <div class="emotion-count">${adminUtils.formatNumber(emotion.count)}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // ============================================
        // AFFICHAGE TYPES DE VIOLENCE
        // ============================================
        
        function displayViolenceTypes(violenceTypes) {
            const container = document.getElementById('violence-types');
            
            if (violenceTypes.length === 0) {
                container.innerHTML = '<p class="no-data">Aucune violence signalée</p>';
                return;
            }
            
            let html = '';
            violenceTypes.forEach(violence => {
                html += `
                    <div class="violence-item">
                        <div class="violence-label">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>${violence.type}</span>
                        </div>
                        <div class="violence-count">${adminUtils.formatNumber(violence.count)}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // ============================================
        // AFFICHAGE CONVERSATIONS RÉCENTES
        // ============================================
        
        function displayRecentConversations(conversations) {
            const tbody = document.getElementById('recent-conversations');
            
            if (conversations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Aucune conversation récente</td></tr>';
                return;
            }
            
            let html = '';
            conversations.forEach(conv => {
                // Badge sentiment
                let sentimentBadge = '';
                if (conv.sentiment === 'positive') {
                    sentimentBadge = '<span class="badge success">Positif</span>';
                } else if (conv.sentiment === 'negative') {
                    sentimentBadge = '<span class="badge danger">Négatif</span>';
                } else {
                    sentimentBadge = '<span class="badge">Neutre</span>';
                }
                
                // Badge urgence
                let urgencyBadge = '';
                if (conv.urgency >= 4) {
                    urgencyBadge = `<span class="urgency-badge level-${conv.urgency}">${conv.urgency}</span>`;
                } else if (conv.urgency > 0) {
                    urgencyBadge = `<span class="urgency-badge level-${conv.urgency}">${conv.urgency}</span>`;
                } else {
                    urgencyBadge = '<span style="color: #6b7280;">-</span>';
                }
                
                // Violence
                const violence = conv.violence_type 
                    ? `<span class="badge danger">${conv.violence_type}</span>`
                    : '<span style="color: #6b7280;">-</span>';
                
                html += `
                    <tr>
                        <td><strong>#${conv.id}</strong></td>
                        <td class="text-truncate">${conv.title}</td>
                        <td>
                            <span class="uuid-short" title="${conv.user_id}">${conv.user_id_short}</span>
                        </td>
                        <td>${conv.message_count}</td>
                        <td>${sentimentBadge}</td>
                        <td>${conv.emotion || '<span style="color: #6b7280;">-</span>'}</td>
                        <td>${urgencyBadge}</td>
                        <td>${violence}</td>
                        <td>${adminUtils.formatRelativeDate(conv.updated_at)}</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // ============================================
        // REFRESH DONNÉES
        // ============================================
        
        async function refreshData() {
            adminUtils.showNotification('🔄 Actualisation...', 'info', 1500);
            await loadDashboardData();
            adminUtils.showNotification('✅ Données actualisées', 'success');
        }
        
        // ============================================
        // DÉCONNEXION
        // ============================================
        
        async function logout() {
            try {
                const response = await fetch('<?= apiUrl('auth', ['action' => 'logout']) ?>', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Erreur déconnexion:', error);
                window.location.href = 'logout.php';
            }
        }
        
        // ============================================
        // INITIALISATION
        // ============================================

        window.addEventListener('DOMContentLoaded', () => {
            loadDashboardData();
        });
    </script>
</body>
</html>