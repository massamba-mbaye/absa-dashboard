<?php
/**
 * PAGE DÉTAILS D'UN UTILISATEUR WHATSAPP
 * Affiche toutes les conversations d'un utilisateur avec statistiques
 */

session_start();

// Charger les configurations
require_once __DIR__ . '/config-path.php';
require_once __DIR__ . '/../config/auth.php';

// Vérifier l'authentification
checkAdminAuth(true);

// Récupérer l'ID utilisateur (wa_id)
$waId = isset($_GET['wa_id']) ? trim($_GET['wa_id']) : '';

if (empty($waId)) {
    header('Location: users.php');
    exit;
}

// Récupérer les infos admin
$adminName = getAdminName();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Détails Utilisateur - ABSA Admin</title>

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
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <a href="users.php" class="btn-secondary" style="padding: 10px 16px; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i>
                        <span>Retour</span>
                    </a>
                    <h1 id="page-title">Utilisateur <?= htmlspecialchars(substr($waId, 0, 12)) ?>...</h1>
                </div>
                <p class="subtitle">Historique complet des conversations</p>
            </div>
        </div>

        <!-- Loading -->
        <div id="loading" class="loading-container" style="display: flex;">
            <div class="spinner"></div>
            <p>Chargement des données utilisateur...</p>
        </div>

        <!-- Contenu principal -->
        <div id="content" style="display: none;">

            <!-- ========================================
                 STATISTIQUES UTILISATEUR
                 ======================================== -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4b3795, #6b57b1);">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-skeleton" id="stat-conversations">
                            <div class="skeleton-loader"></div>
                        </h3>
                        <p>Conversations</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #51c6e1, #41b6d1);">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-skeleton" id="stat-messages">
                            <div class="skeleton-loader"></div>
                        </h3>
                        <p>Messages Total</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ff6b6b, #fa5252);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-skeleton" id="stat-urgency">
                            <div class="skeleton-loader"></div>
                        </h3>
                        <p>Urgence Max</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #51cf66, #41bf56);">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="stat-skeleton" id="stat-sentiment">
                            <div class="skeleton-loader"></div>
                        </h3>
                        <p>Sentiment Dominant</p>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 LISTE DES CONVERSATIONS
                 ======================================== -->
            <div class="chart-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Conversations (<span id="conv-count">0</span>)
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Messages</th>
                                    <th>Sentiment</th>
                                    <th>Urgence</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="conversations-tbody">
                                <tr>
                                    <td colspan="7" class="text-center">Chargement...</td>
                                </tr>
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
        const waId = '<?= htmlspecialchars($waId, ENT_QUOTES) ?>';

        // ============================================
        // CHARGEMENT DES DONNÉES
        // ============================================

        async function loadUserData() {
            try {
                const response = await fetch('<?= apiUrl('users-management') ?>?action=user_details&wa_id=' + encodeURIComponent(waId));
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }

                const data = result.data;

                // Mettre à jour les statistiques
                document.getElementById('stat-conversations').textContent = data.stats.total_conversations;
                document.getElementById('stat-conversations').classList.add('loaded');

                document.getElementById('stat-messages').textContent = data.stats.total_messages;
                document.getElementById('stat-messages').classList.add('loaded');

                const urgencyEl = document.getElementById('stat-urgency');
                urgencyEl.textContent = data.stats.max_urgency;
                urgencyEl.style.color = data.stats.max_urgency >= 4 ? '#ff6b6b' : '#51cf66';
                urgencyEl.classList.add('loaded');

                document.getElementById('stat-sentiment').textContent = data.stats.dominant_sentiment || '-';
                document.getElementById('stat-sentiment').classList.add('loaded');

                // Afficher les conversations
                document.getElementById('conv-count').textContent = data.conversations.length;
                displayConversations(data.conversations);

                // Masquer loading, afficher contenu
                document.getElementById('loading').style.display = 'none';
                document.getElementById('content').style.display = 'block';

            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('loading').innerHTML = `
                    <div style="text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ff6b6b; margin-bottom: 20px;"></i>
                        <p style="color: #ff6b6b; font-size: 18px; font-weight: 600;">Erreur de chargement</p>
                        <p style="color: #9ca3af;">${error.message}</p>
                        <a href="users.php" class="btn-primary" style="margin-top: 20px; display: inline-block; text-decoration: none;">
                            Retour aux utilisateurs
                        </a>
                    </div>
                `;
            }
        }

        // ============================================
        // AFFICHAGE CONVERSATIONS
        // ============================================

        function displayConversations(conversations) {
            const tbody = document.getElementById('conversations-tbody');

            if (conversations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">Aucune conversation trouvée</td></tr>';
                return;
            }

            let html = '';

            conversations.forEach(conv => {
                // Sentiment badge
                const sentimentColors = {
                    'positive': '#51cf66',
                    'neutral': '#ffd43b',
                    'negative': '#ff6b6b'
                };
                const sentColor = sentimentColors[conv.sentiment] || '#9ca3af';
                const sentimentBadge = `<span class="badge" style="background: ${sentColor}20; color: ${sentColor}; border: 1px solid ${sentColor};">${conv.sentiment || '-'}</span>`;

                // Urgency badge
                let urgencyBadge = '-';
                if (conv.urgency > 0) {
                    const urgColor = conv.urgency >= 4 ? '#ff6b6b' : (conv.urgency >= 3 ? '#ffd43b' : '#51cf66');
                    urgencyBadge = `<span class="badge" style="background: ${urgColor}20; color: ${urgColor}; border: 1px solid ${urgColor};">Niveau ${conv.urgency}</span>`;
                }

                // Échapper le titre pour prévenir XSS
                const escapedTitle = adminUtils.escapeHtml(conv.title);

                html += `
                    <tr>
                        <td>#${conv.id}</td>
                        <td>${adminUtils.truncateText(escapedTitle, 50)}</td>
                        <td>${conv.message_count}</td>
                        <td>${sentimentBadge}</td>
                        <td>${urgencyBadge}</td>
                        <td>${adminUtils.formatRelativeDate(conv.updated_at)}</td>
                        <td>
                            <a href="conversation-details.php?id=${conv.id}" class="btn-action" title="Voir la timeline">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        // ============================================
        // INITIALISATION
        // ============================================

        window.addEventListener('DOMContentLoaded', () => {
            loadUserData();
        });
    </script>
</body>
</html>
