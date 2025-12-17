<?php
/**
 * PAGE DÉTAILS D'UNE CONVERSATION
 * Affiche la timeline complète d'une conversation avec ses statistiques
 */

session_start();

// Charger les configurations
require_once __DIR__ . '/config-path.php';
require_once __DIR__ . '/../config/auth.php';

// Vérifier l'authentification
checkAdminAuth(true);

// Récupérer l'ID de la conversation
$conversationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($conversationId <= 0) {
    header('Location: conversations.php');
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
    <title>Détails Conversation - ABSA Admin</title>

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
                    <a href="conversations.php" class="btn-secondary" style="padding: 10px 16px; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i>
                        <span>Retour</span>
                    </a>
                    <h1 id="page-title">Conversation #<?= $conversationId ?></h1>
                </div>
                <p class="subtitle">Timeline complète et statistiques</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button class="btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    <span>Imprimer</span>
                </button>
                <button class="btn-danger" onclick="deleteConversation()" style="display: none;" id="btn-delete">
                    <i class="fas fa-trash"></i>
                    <span>Supprimer</span>
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div id="loading" class="loading-container" style="display: flex;">
            <div class="spinner"></div>
            <p>Chargement de la conversation...</p>
        </div>

        <!-- Contenu principal -->
        <div id="content" style="display: none;">

            <!-- ========================================
                 INFORMATIONS GÉNÉRALES
                 ======================================== -->
            <div class="chart-card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Informations
                    </h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px;">
                        <div>
                            <p style="color: #9ca3af; font-size: 14px; margin-bottom: 8px;">Titre</p>
                            <p id="conv-title" style="font-weight: 600; font-size: 16px;">-</p>
                        </div>
                        <div>
                            <p style="color: #9ca3af; font-size: 14px; margin-bottom: 8px;">Utilisateur (UUID)</p>
                            <p id="conv-user-id" style="font-family: monospace; font-size: 14px;">-</p>
                        </div>
                        <div>
                            <p style="color: #9ca3af; font-size: 14px; margin-bottom: 8px;">Messages</p>
                            <p id="conv-messages" style="font-weight: 600; font-size: 16px;">-</p>
                        </div>
                        <div>
                            <p style="color: #9ca3af; font-size: 14px; margin-bottom: 8px;">Urgence Max</p>
                            <p id="conv-urgency" style="font-weight: 600; font-size: 16px;">-</p>
                        </div>
                        <div>
                            <p style="color: #9ca3af; font-size: 14px; margin-bottom: 8px;">Date création</p>
                            <p id="conv-created" style="font-weight: 600; font-size: 14px;">-</p>
                        </div>
                        <div>
                            <p style="color: #9ca3af; font-size: 14px; margin-bottom: 8px;">Dernière activité</p>
                            <p id="conv-updated" style="font-weight: 600; font-size: 14px;">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 STATISTIQUES SENTIMENTS
                 ======================================== -->
            <div class="chart-card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-chart-pie"></i>
                        Répartition des Sentiments
                    </h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                                <div style="width: 12px; height: 12px; border-radius: 50%; background: #51cf66;"></div>
                                <span style="font-weight: 600;">Positif</span>
                            </div>
                            <p id="stat-positive" style="font-size: 24px; font-weight: 700; color: #51cf66; margin-left: 24px;">0 (0%)</p>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                                <div style="width: 12px; height: 12px; border-radius: 50%; background: #ffd43b;"></div>
                                <span style="font-weight: 600;">Neutre</span>
                            </div>
                            <p id="stat-neutral" style="font-size: 24px; font-weight: 700; color: #ffd43b; margin-left: 24px;">0 (0%)</p>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                                <div style="width: 12px; height: 12px; border-radius: 50%; background: #ff6b6b;"></div>
                                <span style="font-weight: 600;">Négatif</span>
                            </div>
                            <p id="stat-negative" style="font-size: 24px; font-weight: 700; color: #ff6b6b; margin-left: 24px;">0 (0%)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 TIMELINE DES MESSAGES
                 ======================================== -->
            <div class="chart-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-comments"></i>
                        Timeline (<span id="timeline-count">0</span> messages)
                    </h3>
                </div>
                <div class="card-body" id="timeline-container">
                    <!-- Généré dynamiquement -->
                </div>
            </div>

        </div>

    </main>

    <!-- ========================================
         SCRIPTS
         ======================================== -->
    <script src="script-admin.js"></script>
    <script>
        const conversationId = <?= $conversationId ?>;

        // ============================================
        // CHARGEMENT DES DONNÉES
        // ============================================

        async function loadConversationDetails() {
            try {
                const response = await fetch('<?= apiUrl('conversations-management') ?>?action=details&id=' + conversationId);
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }

                const data = result.data;

                // Mettre à jour le titre de la page
                document.getElementById('page-title').textContent = data.conversation.title;
                document.title = data.conversation.title + ' - ABSA Admin';

                // Informations générales
                document.getElementById('conv-title').textContent = data.conversation.title;
                document.getElementById('conv-user-id').textContent = data.conversation.user_id;
                document.getElementById('conv-messages').textContent = data.conversation.message_count;

                const urgencyEl = document.getElementById('conv-urgency');
                urgencyEl.textContent = data.stats.max_urgency;
                urgencyEl.style.color = data.stats.max_urgency >= 4 ? '#ff6b6b' : '#51cf66';

                document.getElementById('conv-created').textContent = adminUtils.formatRelativeDate(data.conversation.created_at);
                document.getElementById('conv-updated').textContent = adminUtils.formatRelativeDate(data.conversation.updated_at);

                // Statistiques sentiments
                const totalSent = data.stats.sentiments.positive + data.stats.sentiments.neutral + data.stats.sentiments.negative;

                const posPercent = totalSent > 0 ? Math.round((data.stats.sentiments.positive / totalSent) * 100) : 0;
                const neuPercent = totalSent > 0 ? Math.round((data.stats.sentiments.neutral / totalSent) * 100) : 0;
                const negPercent = totalSent > 0 ? Math.round((data.stats.sentiments.negative / totalSent) * 100) : 0;

                document.getElementById('stat-positive').textContent = `${data.stats.sentiments.positive} (${posPercent}%)`;
                document.getElementById('stat-neutral').textContent = `${data.stats.sentiments.neutral} (${neuPercent}%)`;
                document.getElementById('stat-negative').textContent = `${data.stats.sentiments.negative} (${negPercent}%)`;

                // Timeline
                document.getElementById('timeline-count').textContent = data.messages.length;
                displayTimeline(data.messages);

                // Afficher le bouton supprimer
                document.getElementById('btn-delete').style.display = 'flex';

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
                        <a href="conversations.php" class="btn-primary" style="margin-top: 20px; display: inline-block; text-decoration: none;">
                            Retour aux conversations
                        </a>
                    </div>
                `;
            }
        }

        // ============================================
        // AFFICHAGE TIMELINE
        // ============================================

        function displayTimeline(messages) {
            const container = document.getElementById('timeline-container');
            let html = '';

            messages.forEach((msg, index) => {
                const isUser = msg.role === 'user';
                const bgColor = isUser ? '#2a2a3a' : 'rgba(75, 55, 149, 0.15)';
                const borderColor = isUser ? '#3a3a4a' : 'rgba(75, 55, 149, 0.4)';
                const icon = isUser ? 'user' : 'robot';
                const iconColor = isUser ? '#51c6e1' : '#4b3795';

                // Alternance gauche (user) / droite (assistant)
                const flexDirection = isUser ? 'row' : 'row-reverse';
                const alignSelf = isUser ? 'flex-start' : 'flex-end';
                const textAlign = isUser ? 'left' : 'right';
                const maxWidth = '75%'; // Limite la largeur pour un effet chat

                // Métadonnées (pour les messages utilisateur uniquement)
                let metaHTML = '';
                if (isUser && (msg.sentiment || msg.emotion || msg.urgency_level > 0 || msg.violence_type)) {
                    metaHTML = '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #3a3a4a; display: flex; gap: 20px; flex-wrap: wrap;">';

                    if (msg.sentiment) {
                        const sentColor = msg.sentiment === 'positive' ? '#51cf66' : (msg.sentiment === 'negative' ? '#ff6b6b' : '#ffd43b');
                        metaHTML += `
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-heart" style="color: ${sentColor};"></i>
                                <span style="font-size: 14px; color: #e5e7eb;">Sentiment: <strong style="color: ${sentColor};">${msg.sentiment}</strong></span>
                            </div>
                        `;
                    }

                    if (msg.emotion) {
                        metaHTML += `
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-smile" style="color: #9ca3af;"></i>
                                <span style="font-size: 14px; color: #e5e7eb;">Émotion: <strong>${msg.emotion}</strong></span>
                            </div>
                        `;
                    }

                    if (msg.urgency_level > 0) {
                        const urgColor = msg.urgency_level >= 4 ? '#ff6b6b' : (msg.urgency_level >= 3 ? '#ffd43b' : '#51cf66');
                        metaHTML += `
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-exclamation-triangle" style="color: ${urgColor};"></i>
                                <span style="font-size: 14px; color: #e5e7eb;">Urgence: <strong style="color: ${urgColor};">${msg.urgency_level}/5</strong></span>
                            </div>
                        `;
                    }

                    if (msg.violence_type) {
                        metaHTML += `
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-exclamation-circle" style="color: #ff6b6b;"></i>
                                <span style="font-size: 14px; color: #e5e7eb;">Violence: <strong style="color: #ff6b6b;">${msg.violence_type}</strong></span>
                            </div>
                        `;
                    }

                    metaHTML += '</div>';
                }

                html += `
                    <div style="display: flex; gap: 20px; margin-bottom: 25px; flex-direction: ${flexDirection}; align-items: flex-start;">
                        <div style="flex-shrink: 0;">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: ${iconColor}; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 12px ${isUser ? 'rgba(81, 198, 225, 0.3)' : 'rgba(75, 55, 149, 0.3)'};">
                                <i class="fas fa-${icon}" style="font-size: 20px;"></i>
                            </div>
                        </div>
                        <div style="flex: 1; max-width: ${maxWidth};">
                            <div style="background: ${bgColor}; padding: 20px; border-radius: ${isUser ? '16px 16px 16px 4px' : '16px 16px 4px 16px'}; border: 2px solid ${borderColor}; box-shadow: 0 2px 8px ${isUser ? 'rgba(81, 198, 225, 0.1)' : 'rgba(75, 55, 149, 0.2)'};">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-direction: ${isUser ? 'row' : 'row-reverse'};">
                                    <strong style="color: ${iconColor}; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px; font-weight: 700;">${msg.role}</strong>
                                    <span style="color: #6b7280; font-size: 12px;">${adminUtils.formatRelativeDate(msg.created_at)}</span>
                                </div>
                                <div style="color: #e5e7eb; line-height: 1.7; font-size: 15px; white-space: pre-wrap; text-align: ${textAlign};">${escapeHtml(msg.content)}</div>
                                ${metaHTML}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // ============================================
        // UTILITAIRES
        // ============================================

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // SUPPRESSION
        // ============================================

        function deleteConversation() {
            adminUtils.confirmAction(
                'Êtes-vous sûr de vouloir supprimer cette conversation ?<br><br>Cette action est irréversible et supprimera tous les messages associés.',
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

                        // Rediriger vers la liste
                        setTimeout(() => {
                            window.location.href = 'conversations.php';
                        }, 1500);

                    } catch (error) {
                        adminUtils.hideLoader();
                        console.error('Erreur suppression:', error);
                        adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
                    }
                }
            );
        }

        // ============================================
        // INITIALISATION
        // ============================================

        window.addEventListener('DOMContentLoaded', () => {
            loadConversationDetails();
        });
    </script>
</body>
</html>
