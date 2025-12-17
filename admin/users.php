<?php
/**
 * PAGE GESTION DES UTILISATEURS
 * Liste, recherche, tri et détails des utilisateurs
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
    <title>Utilisateurs - ABSA Admin</title>
    
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
                <h1>Gestion des Utilisateurs</h1>
                <p class="subtitle">Liste et détails des utilisateurs ABSA</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button class="btn-secondary" onclick="exportUsers()">
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
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-total-users">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Total Utilisateurs</p>
                </div>
            </div>

            <div class="stat-card green">
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

            <div class="stat-card purple">
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

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-avg-messages">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Moy. Messages/User</p>
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
                    placeholder="Rechercher par UUID..."
                    autocomplete="off"
                >
            </div>
            
            <div class="filter-group">
                <label for="sort-by">Trier par :</label>
                <select id="sort-by">
                    <option value="last_activity">Dernière activité</option>
                    <option value="conversations">Nombre conversations</option>
                    <option value="messages">Nombre messages</option>
                    <option value="first_seen">Premier contact</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="sort-order">Ordre :</label>
                <select id="sort-order">
                    <option value="DESC">Décroissant</option>
                    <option value="ASC">Croissant</option>
                </select>
            </div>
        </div>
        
        <!-- ========================================
             TABLEAU UTILISATEURS
             ======================================== -->
        <div class="chart-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Liste des Utilisateurs
                </h3>
            </div>
            <div class="card-body">
                
                <!-- Loading -->
                <div id="loading" class="loading-container" style="display: none;">
                    <div class="spinner"></div>
                    <p>Chargement des utilisateurs...</p>
                </div>
                
                <!-- Tableau -->
                <div id="table-container" style="display: none;">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>UUID</th>
                                    <th>Conversations</th>
                                    <th>Messages</th>
                                    <th>Dernière Conv.</th>
                                    <th>Sentiment</th>
                                    <th>Urgence</th>
                                    <th>Première Vue</th>
                                    <th>Dernière Activité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table">
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
        let currentSortBy = 'last_activity';
        let currentSortOrder = 'DESC';
        
        // ============================================
        // CHARGEMENT DES STATISTIQUES
        // ============================================
        
        async function loadStats() {
            try {
                const response = await fetch('<?= apiUrl('users-management', ['action' => 'stats']) ?>');
                const result = await response.json();

                if (result.success) {
                    const stats = result.data;

                    const statTotalUsers = document.getElementById('stat-total-users');
                    statTotalUsers.textContent = adminUtils.formatNumber(stats.total_users);
                    statTotalUsers.classList.add('loaded');

                    const statTotalConversations = document.getElementById('stat-total-conversations');
                    statTotalConversations.textContent = adminUtils.formatNumber(stats.total_conversations);
                    statTotalConversations.classList.add('loaded');

                    const statTotalMessages = document.getElementById('stat-total-messages');
                    statTotalMessages.textContent = adminUtils.formatNumber(stats.total_messages);
                    statTotalMessages.classList.add('loaded');

                    const statAvgMessages = document.getElementById('stat-avg-messages');
                    statAvgMessages.textContent = stats.avg_messages_per_user;
                    statAvgMessages.classList.add('loaded');
                }
            } catch (error) {
                console.error('Erreur stats:', error);
            }
        }
        
        // ============================================
        // CHARGEMENT DES UTILISATEURS
        // ============================================
        
        async function loadUsers(page = 1) {
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
                    sort_by: currentSortBy,
                    sort_order: currentSortOrder
                });
                
                const response = await fetch('<?= apiUrl('users-management') ?>?' + params);
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }
                
                const data = result.data;
                
                // Mettre à jour les variables
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                
                // Afficher les utilisateurs
                displayUsers(data.users);
                
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
        // AFFICHAGE DES UTILISATEURS
        // ============================================
        
        function displayUsers(users) {
            const tbody = document.getElementById('users-table');
            
            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Aucun utilisateur trouvé</td></tr>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                // Badge sentiment
                let sentimentBadge = '';
                if (user.last_sentiment === 'positive') {
                    sentimentBadge = '<span class="badge success">Positif</span>';
                } else if (user.last_sentiment === 'negative') {
                    sentimentBadge = '<span class="badge danger">Négatif</span>';
                } else {
                    sentimentBadge = '<span class="badge">Neutre</span>';
                }
                
                // Badge urgence
                let urgencyBadge = '';
                if (user.max_urgency >= 4) {
                    urgencyBadge = `<span class="urgency-badge level-${user.max_urgency}">${user.max_urgency}</span>`;
                } else if (user.max_urgency > 0) {
                    urgencyBadge = `<span class="urgency-badge level-${user.max_urgency}">${user.max_urgency}</span>`;
                } else {
                    urgencyBadge = '<span style="color: #6b7280;">-</span>';
                }
                
                // Dernière conversation
                const lastConv = user.last_conversation 
                    ? adminUtils.truncateText(user.last_conversation, 30)
                    : '<span style="color: #6b7280;">-</span>';
                
                html += `
                    <tr>
                        <td>
                            <span class="uuid-short" title="${user.user_id}">${user.user_id_short}</span>
                        </td>
                        <td><strong>${user.conversation_count}</strong></td>
                        <td>${adminUtils.formatNumber(user.message_count)}</td>
                        <td class="text-truncate" title="${user.last_conversation || ''}">${lastConv}</td>
                        <td>${sentimentBadge}</td>
                        <td>${urgencyBadge}</td>
                        <td>${adminUtils.formatRelativeDate(user.first_seen)}</td>
                        <td>${adminUtils.formatRelativeDate(user.last_activity)}</td>
                        <td>
                            <a href="user-details.php?wa_id=${encodeURIComponent(user.user_id)}" class="btn-action" title="Voir les détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="btn-icon" onclick="copyUUID('${user.user_id}')" title="Copier UUID">
                                <i class="fas fa-copy"></i>
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
                `Page ${pagination.current_page} sur ${pagination.total_pages} (${adminUtils.formatNumber(pagination.total_users)} utilisateurs)`;
            
            document.getElementById('btn-prev').disabled = !pagination.has_prev;
            document.getElementById('btn-next').disabled = !pagination.has_next;
        }
        
        // ============================================
        // NAVIGATION PAGINATION
        // ============================================
        
        function nextPage() {
            if (currentPage < totalPages) {
                loadUsers(currentPage + 1);
            }
        }
        
        function previousPage() {
            if (currentPage > 1) {
                loadUsers(currentPage - 1);
            }
        }
        
        // ============================================
        // DÉTAILS UTILISATEUR (MODAL)
        // ============================================
        
        async function viewUserDetails(userId) {
            adminUtils.showLoader('Chargement des détails...');
            
            try {
                const response = await fetch('<?= apiUrl('users-management') ?>?action=details&user_id=' + encodeURIComponent(userId));
                const result = await response.json();
                
                adminUtils.hideLoader();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }
                
                const data = result.data;
                
                // Créer le contenu de la modal
                const sentimentsTotal = data.sentiments.positive + data.sentiments.neutral + data.sentiments.negative;
                
                let emotionsHTML = '';
                if (data.emotions.length > 0) {
                    emotionsHTML = data.emotions.map(e => 
                        `<li><strong>${e.emotion}</strong>: ${e.count}</li>`
                    ).join('');
                } else {
                    emotionsHTML = '<li style="color: #9ca3af;">Aucune émotion détectée</li>';
                }
                
                let violenceHTML = '';
                if (data.violence_types.length > 0) {
                    violenceHTML = data.violence_types.map(v => 
                        `<li style="color: #ff6b6b;"><strong>${v.type}</strong>: ${v.count}</li>`
                    ).join('');
                } else {
                    violenceHTML = '<li style="color: #9ca3af;">Aucune violence signalée</li>';
                }
                
                let conversationsHTML = '';
                if (data.conversations.length > 0) {
                    conversationsHTML = data.conversations.map(c => {
                        const sentBadge = c.last_sentiment === 'positive' ? 'success' : (c.last_sentiment === 'negative' ? 'danger' : '');
                        const urgClass = c.max_urgency >= 4 ? 'danger' : '';
                        return `
                            <tr>
                                <td><strong>#${c.id}</strong></td>
                                <td>${adminUtils.truncateText(c.title, 40)}</td>
                                <td>${c.message_count}</td>
                                <td><span class="badge ${sentBadge}">${c.last_sentiment}</span></td>
                                <td><span class="urgency-badge level-${c.max_urgency} ${urgClass}">${c.max_urgency}</span></td>
                                <td>${adminUtils.formatRelativeDate(c.updated_at)}</td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    conversationsHTML = '<tr><td colspan="6" style="text-align: center; color: #9ca3af;">Aucune conversation</td></tr>';
                }
                
                const modalContent = `
                    <div style="color: #e5e7eb; line-height: 1.6;">
                        <div style="margin-bottom: 30px;">
                            <h4 style="color: #fff; margin-bottom: 15px; font-size: 18px;">
                                <i class="fas fa-user" style="color: #4b3795;"></i> 
                                Utilisateur
                            </h4>
                            <p style="font-family: monospace; background: #252533; padding: 12px; border-radius: 8px; word-break: break-all;">
                                ${data.user_id}
                            </p>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                            <div>
                                <h4 style="color: #fff; margin-bottom: 10px;">📊 Statistiques</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin-bottom: 8px;"><strong>Conversations:</strong> ${data.stats.conversation_count}</li>
                                    <li style="margin-bottom: 8px;"><strong>Messages:</strong> ${data.stats.message_count}</li>
                                    <li style="margin-bottom: 8px;"><strong>Urgence moyenne:</strong> ${data.stats.avg_urgency}</li>
                                    <li style="margin-bottom: 8px;"><strong>Première visite:</strong> ${adminUtils.formatRelativeDate(data.stats.first_seen)}</li>
                                    <li><strong>Dernière activité:</strong> ${adminUtils.formatRelativeDate(data.stats.last_activity)}</li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 style="color: #fff; margin-bottom: 10px;">💭 Sentiments</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin-bottom: 8px;"><span style="color: #51cf66;">●</span> <strong>Positif:</strong> ${data.sentiments.positive} (${sentimentsTotal > 0 ? Math.round((data.sentiments.positive / sentimentsTotal) * 100) : 0}%)</li>
                                    <li style="margin-bottom: 8px;"><span style="color: #ffd43b;">●</span> <strong>Neutre:</strong> ${data.sentiments.neutral} (${sentimentsTotal > 0 ? Math.round((data.sentiments.neutral / sentimentsTotal) * 100) : 0}%)</li>
                                    <li><span style="color: #ff6b6b;">●</span> <strong>Négatif:</strong> ${data.sentiments.negative} (${sentimentsTotal > 0 ? Math.round((data.sentiments.negative / sentimentsTotal) * 100) : 0}%)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                            <div>
                                <h4 style="color: #fff; margin-bottom: 10px;">💖 Top Émotions</h4>
                                <ul style="list-style: none; padding: 0;">
                                    ${emotionsHTML}
                                </ul>
                            </div>
                            
                            <div>
                                <h4 style="color: #fff; margin-bottom: 10px;">⚠️ Violences Signalées</h4>
                                <ul style="list-style: none; padding: 0;">
                                    ${violenceHTML}
                                </ul>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="color: #fff; margin-bottom: 15px;">💬 Conversations</h4>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead style="background: #252533; position: sticky; top: 0;">
                                        <tr>
                                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #3a3a4a;">ID</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #3a3a4a;">Titre</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #3a3a4a;">Msgs</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #3a3a4a;">Sentiment</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #3a3a4a;">Urgence</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #3a3a4a;">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${conversationsHTML}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
                
                adminUtils.showModal(
                    `👤 Détails Utilisateur - ${data.user_id_short}`,
                    modalContent,
                    [
                        {
                            text: 'Copier UUID',
                            class: 'btn-secondary',
                            onClick: () => copyUUID(data.user_id)
                        },
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
        // COPIER UUID
        // ============================================
        
        function copyUUID(uuid) {
            adminUtils.copyToClipboard(uuid, '📋 UUID copié !');
        }
        
        // ============================================
        // EXPORT CSV
        // ============================================
        
        async function exportUsers() {
            adminUtils.showLoader('Export en cours...');
            
            try {
                const response = await fetch('<?= apiUrl('users-management', ['action' => 'export']) ?>');
                const result = await response.json();
                
                adminUtils.hideLoader();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors de l\'export');
                }
                
                const filename = `users_export_${new Date().toISOString().split('T')[0]}.csv`;
                adminUtils.exportToCSV(result.data.users, filename);
                
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
            await Promise.all([loadStats(), loadUsers(currentPage)]);
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
            loadUsers(1);
        }, 500));
        
        // Tri
        document.getElementById('sort-by').addEventListener('change', (e) => {
            currentSortBy = e.target.value;
            currentPage = 1;
            loadUsers(1);
        });
        
        document.getElementById('sort-order').addEventListener('change', (e) => {
            currentSortOrder = e.target.value;
            currentPage = 1;
            loadUsers(1);
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
            loadUsers(1);
        });
    </script>
</body>
</html>