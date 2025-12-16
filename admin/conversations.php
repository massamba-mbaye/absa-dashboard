<?php
/**
 * PAGE GESTION DES CONVERSATIONS
 * Liste, filtres, détails et suppression des conversations
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
    <title>Conversations - ABSA Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS Admin -->
    <link rel="stylesheet" href="style-admin.css">
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
                <h1>Gestion des Conversations</h1>
                <p class="subtitle">Liste et détails des conversations ABSA</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button class="btn-secondary" onclick="exportConversations()">
                    <i class="fas fa-file-csv"></i>
                    <span>Export CSV</span>
                </button>
                <button class="btn-refresh" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i>
                    <span>Actualiser</span>
                </button>
            </div>
        </div>
        
        <!-- ========================================
             STATISTIQUES
             ======================================== -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-info">
                    <h3 id="stat-total-conversations">0</h3>
                    <p>Total Conversations</p>
                </div>
            </div>
            
            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-info">
                    <h3 id="stat-total-messages">0</h3>
                    <p>Total Messages</p>
                </div>
            </div>
            
            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-smile"></i>
                </div>
                <div class="stat-info">
                    <h3 id="stat-positive-rate">0%</h3>
                    <p>Taux Positifs</p>
                </div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3 id="stat-urgent-conversations">0</h3>
                    <p>Conversations Urgentes</p>
                    <span class="stat-badge danger">Niveau ≥ 4</span>
                </div>
            </div>
        </div>
        
        <!-- ========================================
             FILTRES & RECHERCHE
             ======================================== -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    id="search-input" 
                    placeholder="Rechercher par titre ou UUID utilisateur..."
                    autocomplete="off"
                >
            </div>
            
            <div class="filter-group">
                <label for="filter-sentiment">Sentiment :</label>
                <select id="filter-sentiment">
                    <option value="">Tous</option>
                    <option value="positive">Positif</option>
                    <option value="neutral">Neutre</option>
                    <option value="negative">Négatif</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-urgency">Urgence ≥ :</label>
                <select id="filter-urgency">
                    <option value="0">Toutes</option>
                    <option value="4">4 (Critique)</option>
                    <option value="5">5 (Extrême)</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="sort-by">Trier par :</label>
                <select id="sort-by">
                    <option value="last_activity">Dernière activité</option>
                    <option value="created">Date création</option>
                    <option value="messages">Nombre messages</option>
                </select>
            </div>
        </div>
        
        <!-- ========================================
             TABLEAU CONVERSATIONS
             ======================================== -->
        <div class="chart-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Liste des Conversations
                </h3>
            </div>
            <div class="card-body">
                
                <!-- Loading -->
                <div id="loading" class="loading-container" style="display: none;">
                    <div class="spinner"></div>
                    <p>Chargement des conversations...</p>
                </div>
                
                <!-- Tableau -->
                <div id="table-container" style="display: none;">
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="conversations-table">
                                <!-- Généré dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="pagination">
                        <button id="btn-prev" onclick="previousPage()">
                            <i class="fas fa-chevron-left"></i>
                            Précédent
                        </button>
                        <span id="pagination-info">Page 1 sur 1</span>
                        <button id="btn-next" onclick="nextPage()">
                            Suivant
                            <i class="fas fa-chevron-right"></i>
                        </button>
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
        
        let currentPage = 1;
        let totalPages = 1;
        let currentSearch = '';
        let currentSentiment = '';
        let currentUrgency = 0;
        let currentSortBy = 'last_activity';
        
        // ============================================
        // CHARGEMENT DES STATISTIQUES
        // ============================================
        
        async function loadStats() {
            try {
                const response = await fetch('<?= apiUrl('conversations-management', ['action' => 'stats']) ?>');
                const result = await response.json();
                
                if (result.success) {
                    const stats = result.data;
                    document.getElementById('stat-total-conversations').textContent = adminUtils.formatNumber(stats.total_conversations);
                    document.getElementById('stat-total-messages').textContent = adminUtils.formatNumber(stats.total_messages);
                    document.getElementById('stat-positive-rate').textContent = stats.positive_rate + '%';
                    document.getElementById('stat-urgent-conversations').textContent = adminUtils.formatNumber(stats.urgent_conversations);
                }
            } catch (error) {
                console.error('Erreur stats:', error);
            }
        }
        
        // ============================================
        // CHARGEMENT DES CONVERSATIONS
        // ============================================
        
        async function loadConversations(page = 1) {
            const loading = document.getElementById('loading');
            const tableContainer = document.getElementById('table-container');
            
            loading.style.display = 'flex';
            tableContainer.style.display = 'none';
            
            try {
                const params = new URLSearchParams({
                    action: 'list',
                    page: page,
                    per_page: 20,
                    search: currentSearch,
                    sentiment: currentSentiment,
                    urgency: currentUrgency,
                    sort_by: currentSortBy,
                    sort_order: 'DESC'
                });
                
                const response = await fetch('<?= apiUrl('conversations-management') ?>?' + params);
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }
                
                const data = result.data;
                
                // Mettre à jour les variables
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                
                // Afficher les conversations
                displayConversations(data.conversations);
                
                // Mettre à jour la pagination
                updatePagination(data.pagination);
                
                loading.style.display = 'none';
                tableContainer.style.display = 'block';
                
            } catch (error) {
                console.error('Erreur:', error);
                loading.style.display = 'none';
                adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
            }
        }
        
        // ============================================
        // AFFICHAGE DES CONVERSATIONS
        // ============================================
        
        function displayConversations(conversations) {
            const tbody = document.getElementById('conversations-table');
            
            if (conversations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="no-data">Aucune conversation trouvée</td></tr>';
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
                
                // Émotion
                const emotion = conv.emotion || '<span style="color: #6b7280;">-</span>';
                
                html += `
                    <tr>
                        <td><strong>#${conv.id}</strong></td>
                        <td class="text-truncate" title="${conv.title}">${adminUtils.truncateText(conv.title, 40)}</td>
                        <td>
                            <span class="uuid-short" title="${conv.user_id}">${conv.user_id_short}</span>
                        </td>
                        <td>${conv.message_count}</td>
                        <td>${sentimentBadge}</td>
                        <td>${emotion}</td>
                        <td>${urgencyBadge}</td>
                        <td>${violence}</td>
                        <td>${adminUtils.formatRelativeDate(conv.updated_at)}</td>
                        <td>
                            <button class="btn-action" onclick="viewConversationDetails(${conv.id})" title="Voir la timeline">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon" style="color: #ff6b6b;" onclick="deleteConversation(${conv.id}, '${conv.title.replace(/'/g, "\\'")}')" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // ============================================
        // MISE À JOUR PAGINATION
        // ============================================
        
        function updatePagination(pagination) {
            document.getElementById('pagination-info').textContent = 
                `Page ${pagination.current_page} sur ${pagination.total_pages} (${adminUtils.formatNumber(pagination.total_conversations)} conversations)`;
            
            document.getElementById('btn-prev').disabled = !pagination.has_prev;
            document.getElementById('btn-next').disabled = !pagination.has_next;
        }
        
        // ============================================
        // NAVIGATION PAGINATION
        // ============================================
        
        function nextPage() {
            if (currentPage < totalPages) {
                loadConversations(currentPage + 1);
            }
        }
        
        function previousPage() {
            if (currentPage > 1) {
                loadConversations(currentPage - 1);
            }
        }
        
        // ============================================
        // DÉTAILS CONVERSATION (MODAL TIMELINE)
        // ============================================
        
        async function viewConversationDetails(conversationId) {
            adminUtils.showLoader('Chargement de la timeline...');
            
            try {
                const response = await fetch('<?= apiUrl('conversations-management') ?>?action=details&id=' + conversationId);
                const result = await response.json();
                
                adminUtils.hideLoader();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }
                
                const data = result.data;
                
                // Créer la timeline
                let timelineHTML = '';
                
                data.messages.forEach((msg, index) => {
                    const isUser = msg.role === 'user';
                    const bgColor = isUser ? '#2a2a3a' : 'rgba(75, 55, 149, 0.1)';
                    const borderColor = isUser ? '#3a3a4a' : 'rgba(75, 55, 149, 0.3)';
                    const icon = isUser ? 'user' : 'robot';
                    const iconColor = isUser ? '#51c6e1' : '#4b3795';
                    
                    let metaHTML = '';
                    if (isUser && (msg.sentiment || msg.emotion || msg.urgency_level > 0)) {
                        metaHTML = '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #3a3a4a; display: flex; gap: 15px; flex-wrap: wrap;">';
                        
                        if (msg.sentiment) {
                            const sentColor = msg.sentiment === 'positive' ? '#51cf66' : (msg.sentiment === 'negative' ? '#ff6b6b' : '#ffd43b');
                            metaHTML += `<span style="font-size: 12px; color: ${sentColor};"><i class="fas fa-heart"></i> ${msg.sentiment}</span>`;
                        }
                        
                        if (msg.emotion) {
                            metaHTML += `<span style="font-size: 12px; color: #9ca3af;"><i class="fas fa-smile"></i> ${msg.emotion}</span>`;
                        }
                        
                        if (msg.urgency_level > 0) {
                            const urgColor = msg.urgency_level >= 4 ? '#ff6b6b' : (msg.urgency_level >= 3 ? '#ffd43b' : '#51cf66');
                            metaHTML += `<span style="font-size: 12px; color: ${urgColor};"><i class="fas fa-exclamation-triangle"></i> Urgence: ${msg.urgency_level}</span>`;
                        }
                        
                        if (msg.violence_type) {
                            metaHTML += `<span style="font-size: 12px; color: #ff6b6b;"><i class="fas fa-exclamation-circle"></i> ${msg.violence_type}</span>`;
                        }
                        
                        metaHTML += '</div>';
                    }
                    
                    timelineHTML += `
                        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                            <div style="flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%; background: ${iconColor}; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-${icon}"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="background: ${bgColor}; padding: 15px; border-radius: 10px; border: 1px solid ${borderColor};">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <strong style="color: ${iconColor}; text-transform: capitalize;">${msg.role}</strong>
                                        <span style="color: #6b7280; font-size: 12px;">${adminUtils.formatRelativeDate(msg.created_at)}</span>
                                    </div>
                                    <div style="color: #e5e7eb; line-height: 1.6; white-space: pre-wrap;">${msg.content}</div>
                                    ${metaHTML}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                // Stats
                const stats = data.stats;
                const totalSent = stats.sentiments.positive + stats.sentiments.neutral + stats.sentiments.negative;
                
                const modalContent = `
                    <div style="color: #e5e7eb;">
                        <div style="background: #252533; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                            <h4 style="color: #fff; margin-bottom: 15px; font-size: 18px;">
                                <i class="fas fa-info-circle" style="color: #4b3795;"></i> 
                                Informations
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                <div>
                                    <p style="margin-bottom: 5px; color: #9ca3af; font-size: 14px;">Titre</p>
                                    <p style="font-weight: 600;">${data.conversation.title}</p>
                                </div>
                                <div>
                                    <p style="margin-bottom: 5px; color: #9ca3af; font-size: 14px;">Utilisateur</p>
                                    <p style="font-family: monospace; font-size: 13px;">${data.conversation.user_id_short}</p>
                                </div>
                                <div>
                                    <p style="margin-bottom: 5px; color: #9ca3af; font-size: 14px;">Messages</p>
                                    <p style="font-weight: 600;">${data.conversation.message_count}</p>
                                </div>
                                <div>
                                    <p style="margin-bottom: 5px; color: #9ca3af; font-size: 14px;">Urgence Max</p>
                                    <p style="font-weight: 600; color: ${stats.max_urgency >= 4 ? '#ff6b6b' : '#51cf66'};">${stats.max_urgency}</p>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #3a3a4a;">
                                <p style="margin-bottom: 10px; color: #9ca3af; font-size: 14px;">Répartition Sentiments</p>
                                <div style="display: flex; gap: 20px;">
                                    <div>
                                        <span style="color: #51cf66;">●</span> 
                                        <strong>Positif:</strong> ${stats.sentiments.positive} 
                                        <span style="color: #9ca3af;">(${totalSent > 0 ? Math.round((stats.sentiments.positive / totalSent) * 100) : 0}%)</span>
                                    </div>
                                    <div>
                                        <span style="color: #ffd43b;">●</span> 
                                        <strong>Neutre:</strong> ${stats.sentiments.neutral} 
                                        <span style="color: #9ca3af;">(${totalSent > 0 ? Math.round((stats.sentiments.neutral / totalSent) * 100) : 0}%)</span>
                                    </div>
                                    <div>
                                        <span style="color: #ff6b6b;">●</span> 
                                        <strong>Négatif:</strong> ${stats.sentiments.negative} 
                                        <span style="color: #9ca3af;">(${totalSent > 0 ? Math.round((stats.sentiments.negative / totalSent) * 100) : 0}%)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h4 style="color: #fff; margin-bottom: 20px; font-size: 18px;">
                            <i class="fas fa-comments" style="color: #51c6e1;"></i> 
                            Timeline (${data.messages.length} messages)
                        </h4>
                        
                        <div style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                            ${timelineHTML}
                        </div>
                    </div>
                `;
                
                adminUtils.showModal(
                    `💬 Conversation #${data.conversation.id}`,
                    modalContent,
                    [
                        {
                            text: 'Fermer',
                            class: 'btn-primary',
                            onClick: () => {}
                        }
                    ]
                );
                
            } catch (error) {
                adminUtils.hideLoader();
                console.error('Erreur:', error);
                adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
            }
        }
        
        // ============================================
        // SUPPRESSION CONVERSATION
        // ============================================
        
        function deleteConversation(conversationId, title) {
            adminUtils.confirmAction(
                `Êtes-vous sûr de vouloir supprimer la conversation <strong>"${adminUtils.truncateText(title, 50)}"</strong> ?<br><br>Cette action est irréversible et supprimera tous les messages associés.`,
                async () => {
                    adminUtils.showLoader('Suppression en cours...');
                    
                    try {
                        const response = await fetch(
                            '<?= apiUrl('conversations-management', ['action' => 'delete']) ?>&id=' + conversationId,
                            { method: 'POST' }
                        );
                        
                        const result = await response.json();
                        
                        adminUtils.hideLoader();
                        
                        if (!result.success) {
                            throw new Error(result.error || 'Erreur lors de la suppression');
                        }
                        
                        adminUtils.showNotification('✅ Conversation supprimée avec succès', 'success');
                        
                        // Recharger les données
                        await Promise.all([loadStats(), loadConversations(currentPage)]);
                        
                    } catch (error) {
                        adminUtils.hideLoader();
                        console.error('Erreur suppression:', error);
                        adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
                    }
                }
            );
        }
        
        // ============================================
        // EXPORT CSV
        // ============================================
        
        async function exportConversations() {
            adminUtils.showLoader('Export en cours...');
            
            try {
                const params = new URLSearchParams({
                    action: 'export',
                    search: currentSearch,
                    sentiment: currentSentiment,
                    urgency: currentUrgency
                });
                
                const response = await fetch('<?= apiUrl('conversations-management') ?>?' + params);
                const result = await response.json();
                
                adminUtils.hideLoader();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors de l\'export');
                }
                
                const filename = `conversations_export_${new Date().toISOString().split('T')[0]}.csv`;
                adminUtils.exportToCSV(result.data.conversations, filename);
                
            } catch (error) {
                adminUtils.hideLoader();
                console.error('Erreur export:', error);
                adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
            }
        }
        
        // ============================================
        // REFRESH DONNÉES
        // ============================================
        
        async function refreshData() {
            adminUtils.showNotification('🔄 Actualisation...', 'info', 1500);
            await Promise.all([loadStats(), loadConversations(currentPage)]);
            adminUtils.showNotification('✅ Données actualisées', 'success');
        }
        
        // ============================================
        // GESTION RECHERCHE & FILTRES
        // ============================================
        
        // Recherche avec debounce
        const searchInput = document.getElementById('search-input');
        searchInput.addEventListener('input', adminUtils.debounce((e) => {
            currentSearch = e.target.value.trim();
            currentPage = 1;
            loadConversations(1);
        }, 500));
        
        // Filtre sentiment
        document.getElementById('filter-sentiment').addEventListener('change', (e) => {
            currentSentiment = e.target.value;
            currentPage = 1;
            loadConversations(1);
        });
        
        // Filtre urgence
        document.getElementById('filter-urgency').addEventListener('change', (e) => {
            currentUrgency = parseInt(e.target.value);
            currentPage = 1;
            loadConversations(1);
        });
        
        // Tri
        document.getElementById('sort-by').addEventListener('change', (e) => {
            currentSortBy = e.target.value;
            currentPage = 1;
            loadConversations(1);
        });
        
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
            loadStats();
            loadConversations(1);
        });
    </script>
</body>
</html>