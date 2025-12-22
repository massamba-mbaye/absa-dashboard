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
    <link rel="stylesheet" href="skeleton-loader.css">
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
                    <h3 class="stat-skeleton" id="stat-total-conversations">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Total Conversations</p>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-total-messages">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Total Messages</p>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-smile"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-positive-rate">
                        <div class="skeleton-loader"></div>
                    </h3>
                    <p>Taux Positifs</p>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-urgent-conversations">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
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

                    const statTotalConversations = document.getElementById('stat-total-conversations');
                    statTotalConversations.textContent = adminUtils.formatNumber(stats.total_conversations);
                    statTotalConversations.classList.add('loaded');

                    const statTotalMessages = document.getElementById('stat-total-messages');
                    statTotalMessages.textContent = adminUtils.formatNumber(stats.total_messages);
                    statTotalMessages.classList.add('loaded');

                    const statPositiveRate = document.getElementById('stat-positive-rate');
                    statPositiveRate.textContent = stats.positive_rate + '%';
                    statPositiveRate.classList.add('loaded');

                    const statUrgentConversations = document.getElementById('stat-urgent-conversations');
                    statUrgentConversations.textContent = adminUtils.formatNumber(stats.urgent_conversations);
                    statUrgentConversations.classList.add('loaded');
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
                
                // Violence (échappé pour prévenir XSS)
                const violence = conv.violence_type
                    ? `<span class="badge danger">${adminUtils.escapeHtml(conv.violence_type)}</span>`
                    : '<span style="color: #6b7280;">-</span>';

                // Émotion (échappé pour prévenir XSS)
                const emotion = conv.emotion
                    ? adminUtils.escapeHtml(conv.emotion)
                    : '<span style="color: #6b7280;">-</span>';
                
                // Échapper le titre pour prévenir les attaques XSS
                const escapedTitle = adminUtils.escapeHtml(conv.title);
                const truncatedTitle = adminUtils.truncateText(escapedTitle, 40);

                html += `
                    <tr>
                        <td><strong>#${conv.id}</strong></td>
                        <td class="text-truncate" title="${escapedTitle}">${truncatedTitle}</td>
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
                            <a href="conversation-details.php?id=${conv.id}" class="btn-action" title="Voir la timeline">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="btn-icon" style="color: #ff6b6b;" onclick="deleteConversation(${conv.id}, '${escapedTitle.replace(/'/g, "\\'")}')" title="Supprimer">
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