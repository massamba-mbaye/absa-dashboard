<?php
/**
 * PAGE GESTION DES UTILISATEURS ADMINS
 * Permet de gérer les comptes d'accès au dashboard
 */

require_once __DIR__ . '/config-path.php';
require_once __DIR__ . '/../config/auth.php';

checkAdminAuth();

// Récupérer les infos admin pour le sidebar
$adminName = getAdminName();
$adminRole = getAdminRole();
$isAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Admins - ABSA Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style-admin.css">
    <style>
        /* Skeleton loader pour les stats */
        .stat-skeleton {
            position: relative;
            overflow: hidden;
        }

        .skeleton-loader {
            width: 60px;
            height: 32px;
            background: linear-gradient(90deg, #2a2a3a 25%, #3a3a4a 50%, #2a2a3a 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s ease-in-out infinite;
            border-radius: 8px;
        }

        @keyframes skeleton-loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .stat-skeleton.loaded .skeleton-loader {
            display: none;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1>Gestion des Administrateurs</h1>
                <p class="subtitle">Gérer les comptes d'accès au dashboard</p>
            </div>
            <?php if ($isAdmin): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Nouvel admin
            </button>
            <?php endif; ?>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value stat-skeleton" id="stat-total">
                        <div class="skeleton-loader"></div>
                    </div>
                    <div class="stat-label">Total Admins</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value stat-skeleton" id="stat-admins">
                        <div class="skeleton-loader"></div>
                    </div>
                    <div class="stat-label">Administrateurs</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value stat-skeleton" id="stat-viewers">
                        <div class="skeleton-loader"></div>
                    </div>
                    <div class="stat-label">Lecture Seule</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value stat-skeleton" id="stat-active">
                        <div class="skeleton-loader"></div>
                    </div>
                    <div class="stat-label">Actifs</div>
                </div>
            </div>
        </div>

        <!-- Liste des admins -->
        <div class="card">
            <div class="card-header">
                <h3>Liste des Administrateurs</h3>
                <input type="text" id="search" class="input-field" placeholder="Rechercher par email..." style="max-width: 300px;">
            </div>
            <div class="card-body">
                <div id="loading" class="loading-container" style="display: none;">
                    <div class="spinner"></div>
                    <p>Chargement...</p>
                </div>

                <div id="users-table" style="display: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-list"></tbody>
                    </table>
                </div>

                <div id="no-data" style="display: none; text-align: center; padding: 40px; color: #9ca3af;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom: 20px; opacity: 0.3;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <p>Aucun administrateur trouvé</p>
                </div>
            </div>
        </div>

        <!-- Modal Créer/Modifier -->
        <div id="userModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Nouvel Administrateur</h2>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <form id="userForm">
                    <input type="hidden" id="user-id" name="id">
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="input-field" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Prénom</label>
                            <input type="text" id="first_name" name="first_name" class="input-field">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Nom</label>
                            <input type="text" id="last_name" name="last_name" class="input-field">
                        </div>
                    </div>

                    <div id="password-section">
                        <div class="form-group">
                            <label for="password">Mot de passe * <small>(minimum 8 caractères)</small></label>
                            <input type="password" id="password" name="password" class="input-field" minlength="8">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select id="role" name="role" class="input-field" required>
                            <option value="admin">Administrateur (accès complet)</option>
                            <option value="viewer">Lecture seule (consultation uniquement)</option>
                        </select>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="submit-btn">Créer</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script src="script-admin.js"></script>
    <script>
    // Rôle de l'utilisateur connecté
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    let users = [];
    let editMode = false;

    // Charger les données
    async function loadData() {
        try {
            document.getElementById('loading').style.display = 'flex';
            document.getElementById('users-table').style.display = 'none';
            document.getElementById('no-data').style.display = 'none';

            const [usersRes, statsRes] = await Promise.all([
                fetch('<?= apiUrl('admin-users-management', ['action' => 'list']) ?>'),
                fetch('<?= apiUrl('admin-users-management', ['action' => 'stats']) ?>')
            ]);

            const usersData = await usersRes.json();
            const statsData = await statsRes.json();

            if (usersData.success) {
                users = usersData.data.users;
                displayUsers(users);
            }

            if (statsData.success) {
                // Mettre à jour les stats et retirer le loader
                const statTotal = document.getElementById('stat-total');
                const statAdmins = document.getElementById('stat-admins');
                const statViewers = document.getElementById('stat-viewers');
                const statActive = document.getElementById('stat-active');

                statTotal.textContent = statsData.data.total;
                statAdmins.textContent = statsData.data.admins;
                statViewers.textContent = statsData.data.viewers;
                statActive.textContent = statsData.data.active;

                // Retirer le skeleton loader
                statTotal.classList.add('loaded');
                statAdmins.classList.add('loaded');
                statViewers.classList.add('loaded');
                statActive.classList.add('loaded');
            }

            document.getElementById('loading').style.display = 'none';
        } catch (error) {
            console.error('Erreur:', error);
            document.getElementById('loading').style.display = 'none';
            showNotification('Erreur de chargement', 'error');
        }
    }

    // Afficher les utilisateurs
    function displayUsers(data) {
        const tbody = document.getElementById('users-list');
        
        if (data.length === 0) {
            document.getElementById('no-data').style.display = 'block';
            document.getElementById('users-table').style.display = 'none';
            return;
        }

        document.getElementById('users-table').style.display = 'block';
        document.getElementById('no-data').style.display = 'none';

        tbody.innerHTML = data.map(user => `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #4b3795, #51c6e1); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                            ${user.first_name?.charAt(0) || user.email.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div style="font-weight: 600;">${user.full_name || 'Sans nom'}</div>
                        </div>
                    </div>
                </td>
                <td>${user.email}</td>
                <td>
                    <span class="badge ${user.role === 'admin' ? 'badge-primary' : 'badge-info'}">
                        ${user.role_label}
                    </span>
                </td>
                <td>
                    <span class="badge ${user.is_active ? 'badge-success' : 'badge-danger'}">
                        ${user.is_active ? 'Actif' : 'Inactif'}
                    </span>
                </td>
                <td>${user.last_login ? formatDate(user.last_login) : 'Jamais'}</td>
                <td>
                    ${isAdmin ? `
                    <div style="display: flex; gap: 8px;">
                        <button class="btn-icon" onclick="editUser(${user.id})" title="Modifier">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button class="btn-icon" onclick="toggleStatus(${user.id}, ${user.is_active})" title="${user.is_active ? 'Désactiver' : 'Activer'}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </button>
                        <button class="btn-icon btn-danger" onclick="deleteUser(${user.id}, '${user.email}')" title="Supprimer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                    ` : '<span style="color: #9ca3af; font-style: italic;">Lecture seule</span>'}
                </td>
            </tr>
        `).join('');
    }

    // Recherche
    document.getElementById('search').addEventListener('input', (e) => {
        const search = e.target.value.toLowerCase();
        const filtered = users.filter(u => 
            u.email.toLowerCase().includes(search) ||
            u.full_name.toLowerCase().includes(search)
        );
        displayUsers(filtered);
    });

    // Ouvrir modal création
    function openCreateModal() {
        editMode = false;
        document.getElementById('modal-title').textContent = 'Nouvel Administrateur';
        document.getElementById('userForm').reset();
        document.getElementById('user-id').value = '';
        document.getElementById('password-section').style.display = 'block';
        document.getElementById('password').required = true;
        document.getElementById('submit-btn').textContent = 'Créer';
        document.getElementById('userModal').classList.add('show');
    }

    // Modifier utilisateur
    async function editUser(id) {
        const user = users.find(u => u.id === id);
        if (!user) return;

        editMode = true;
        document.getElementById('modal-title').textContent = 'Modifier l\'Administrateur';
        document.getElementById('user-id').value = user.id;
        document.getElementById('email').value = user.email;
        document.getElementById('first_name').value = user.first_name || '';
        document.getElementById('last_name').value = user.last_name || '';
        document.getElementById('role').value = user.role;
        document.getElementById('password-section').style.display = 'none';
        document.getElementById('password').required = false;
        document.getElementById('submit-btn').textContent = 'Modifier';
        document.getElementById('userModal').classList.add('show');
    }

    // Fermer modal
    function closeModal() {
        document.getElementById('userModal').classList.remove('show');
        document.getElementById('userForm').reset();
    }

    // Soumettre formulaire
    document.getElementById('userForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const action = editMode ? 'update' : 'create';
        formData.append('action', action);

        try {
            const response = await fetch('<?= apiUrl('admin-users-management') ?>', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification(result.message || 'Opération réussie', 'success');
                closeModal();
                loadData();
            } else {
                showNotification(result.error || 'Erreur', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showNotification('Erreur serveur', 'error');
        }
    });

    // Activer/Désactiver
    async function toggleStatus(id, currentStatus) {
        if (!confirm(currentStatus ? 'Désactiver cet utilisateur ?' : 'Activer cet utilisateur ?')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', id);

            const response = await fetch('<?= apiUrl('admin-users-management') ?>', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                loadData();
            } else {
                showNotification(result.error, 'error');
            }
        } catch (error) {
            showNotification('Erreur serveur', 'error');
        }
    }

    // Supprimer
    async function deleteUser(id, email) {
        if (!confirm(`Supprimer définitivement l'utilisateur ${email} ?`)) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            const response = await fetch('<?= apiUrl('admin-users-management') ?>', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                loadData();
            } else {
                showNotification(result.error, 'error');
            }
        } catch (error) {
            showNotification('Erreur serveur', 'error');
        }
    }

    // Utilitaires
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleString('fr-FR', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 20px;border-radius:8px;z-index:10000;';
        
        if (type === 'success') notification.style.background = '#10b981';
        else if (type === 'error') notification.style.background = '#ef4444';
        else notification.style.background = '#3b82f6';
        
        notification.style.color = 'white';
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    }

    // Initialisation
    loadData();
    </script>
</body>
</html>