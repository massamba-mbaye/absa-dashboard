/**
 * SCRIPT ADMIN GLOBAL
 * Fonctions utilitaires réutilisables pour le back-office ABSA
 */

// ============================================
// CONFIGURATION GLOBALE
// ============================================

const ADMIN_CONFIG = {
    API_BASE: '../api/admin/',
    SESSION_TIMEOUT: 7200000, // 2 heures en millisecondes
    SESSION_CHECK_INTERVAL: 300000, // Vérifier toutes les 5 minutes
    SESSION_WARNING_TIME: 900000, // Warning 15 minutes avant expiration
    NOTIFICATION_DURATION: 3000, // 3 secondes
};

// ============================================
// GESTION DE LA SESSION
// ============================================

/**
 * Vérifie périodiquement si la session est toujours active
 */
function initSessionMonitoring() {
    // Vérification immédiate
    checkSession();

    // Vérifier toutes les 5 minutes
    setInterval(checkSession, ADMIN_CONFIG.SESSION_CHECK_INTERVAL);
}

/**
 * Vérifie l'état de la session
 */
async function checkSession() {
    try {
        const response = await fetch(ADMIN_CONFIG.API_BASE + 'auth.php?action=check-session');
        const data = await response.json();

        if (!data.success || !data.data.logged_in) {
            // Session expirée
            showSessionExpiredModal();
        }
        // Suppression de l'alerte d'expiration - notification désactivée
    } catch (error) {
        console.error('Erreur vérification session:', error);
    }
}

/**
 * Affiche une alerte de session expirée
 */
function showSessionExpiredModal() {
    showModal(
        '⏱️ Session Expirée',
        '<p style="color: #9ca3af; line-height: 1.6;">Votre session a expiré pour des raisons de sécurité. Veuillez vous reconnecter.</p>',
        [
            {
                text: 'Se reconnecter',
                class: 'btn-primary',
                onClick: () => {
                    window.location.href = 'index.php?error=session_expired';
                }
            }
        ]
    );
}

/**
 * Affiche un warning que la session va expirer
 */
function showSessionWarning(timeRemaining) {
    const minutes = Math.ceil(timeRemaining / 60);
    
    showNotification(
        `⚠️ Votre session expire dans ${minutes} minutes`,
        'warning',
        5000
    );
}

/**
 * Prolonge la session
 */
async function refreshSession() {
    try {
        const response = await fetch(
            ADMIN_CONFIG.API_BASE + 'auth.php?action=refresh-session',
            { method: 'POST' }
        );
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Session prolongée', 'success');
            return true;
        }
    } catch (error) {
        console.error('Erreur prolongation session:', error);
    }
    return false;
}

// ============================================
// NAVIGATION MOBILE
// ============================================

/**
 * Initialise le menu mobile
 */
function initMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    
    if (!sidebar) return;
    
    // Créer le bouton burger s'il n'existe pas
    if (window.innerWidth <= 768 && !document.getElementById('mobile-menu-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'mobile-menu-toggle';
        toggleBtn.className = 'mobile-menu-btn';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: linear-gradient(135deg, #4b3795, #6b57b5);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            cursor: pointer;
            display: none;
            box-shadow: 0 4px 12px rgba(75, 55, 149, 0.4);
            transition: all 0.3s;
        `;
        
        document.body.appendChild(toggleBtn);
        
        // Afficher sur mobile
        if (window.innerWidth <= 768) {
            toggleBtn.style.display = 'block';
        }
        
        // Toggle sidebar
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            const icon = toggleBtn.querySelector('i');
            icon.className = sidebar.classList.contains('active') 
                ? 'fas fa-times' 
                : 'fas fa-bars';
        });
        
        // Fermer en cliquant sur un lien
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    toggleBtn.querySelector('i').className = 'fas fa-bars';
                }
            });
        });
        
        // Fermer en cliquant en dehors
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 
                && sidebar.classList.contains('active')
                && !sidebar.contains(e.target)
                && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                toggleBtn.querySelector('i').className = 'fas fa-bars';
            }
        });
    }
}

// Initialiser au chargement et au resize
window.addEventListener('DOMContentLoaded', initMobileMenu);
window.addEventListener('resize', initMobileMenu);

// ============================================
// REQUÊTES API
// ============================================

/**
 * Effectue une requête API sécurisée avec gestion d'erreurs
 * 
 * @param {string} endpoint - Endpoint API (ex: 'stats.php')
 * @param {object} options - Options fetch (method, body, etc.)
 * @returns {Promise<object>} Réponse JSON
 */
async function apiRequest(endpoint, options = {}) {
    try {
        const url = ADMIN_CONFIG.API_BASE + endpoint;
        
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        // Redirection si non authentifié
        if (response.status === 401) {
            window.location.href = 'index.php?error=session_expired';
            return null;
        }
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `Erreur HTTP: ${response.status}`);
        }
        
        return data;
        
    } catch (error) {
        console.error('Erreur API:', error);
        showNotification('❌ Erreur: ' + error.message, 'error');
        throw error;
    }
}

// ============================================
// NOTIFICATIONS / TOASTS
// ============================================

/**
 * Affiche une notification toast
 * 
 * @param {string} message - Message à afficher
 * @param {string} type - Type: 'success', 'error', 'warning', 'info'
 * @param {number} duration - Durée en ms (défaut: 3000)
 */
function showNotification(message, type = 'info', duration = ADMIN_CONFIG.NOTIFICATION_DURATION) {
    // Créer le conteneur s'il n'existe pas
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    // Créer la notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const colors = {
        success: '#51cf66',
        error: '#ff6b6b',
        warning: '#ffd43b',
        info: '#51c6e1'
    };
    
    notification.innerHTML = `
        <i class="fas fa-${icons[type]}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    notification.style.cssText = `
        background: #1e1e2e;
        color: #e5e7eb;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 300px;
        border-left: 4px solid ${colors[type]};
        animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    `;
    
    // Icône
    const icon = notification.querySelector('i');
    icon.style.cssText = `
        font-size: 22px;
        color: ${colors[type]};
    `;
    
    // Texte
    const text = notification.querySelector('span');
    text.style.cssText = `
        flex: 1;
        line-height: 1.4;
    `;
    
    // Bouton fermer
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #9ca3af;
        padding: 0;
        margin-left: 10px;
        transition: color 0.2s;
    `;
    
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    });
    
    closeBtn.addEventListener('mouseenter', () => {
        closeBtn.style.color = '#e5e7eb';
    });
    
    closeBtn.addEventListener('mouseleave', () => {
        closeBtn.style.color = '#9ca3af';
    });
    
    // Ajouter au conteneur
    container.appendChild(notification);
    
    // Auto-suppression
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, duration);
}

// Styles d'animation
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        .notification:hover {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.6);
        }
    `;
    document.head.appendChild(style);
}

// ============================================
// MODAL GÉNÉRIQUE
// ============================================

/**
 * Affiche une modal générique
 * 
 * @param {string} title - Titre de la modal
 * @param {string} content - Contenu HTML
 * @param {array} buttons - Tableau de boutons [{text, onClick, class}]
 */
function showModal(title, content, buttons = []) {
    // Créer l'overlay
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s;
    `;
    
    // Créer la modal
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.cssText = `
        background: #1e1e2e;
        border: 1px solid #3a3a4a;
        border-radius: 16px;
        max-width: 600px;
        width: 90%;
        max-height: 85vh;
        overflow: auto;
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
    `;
    
    // Header
    const header = document.createElement('div');
    header.style.cssText = `
        padding: 25px;
        border-bottom: 1px solid #3a3a4a;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(180deg, rgba(75, 55, 149, 0.1) 0%, transparent 100%);
    `;
    header.innerHTML = `
        <h3 style="margin: 0; font-size: 22px; color: #ffffff; font-weight: 700;">${title}</h3>
        <button class="modal-close" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #9ca3af; transition: color 0.2s; padding: 0; line-height: 1;">&times;</button>
    `;
    
    // Body
    const body = document.createElement('div');
    body.style.cssText = 'padding: 25px; color: #e5e7eb;';
    body.innerHTML = content;
    
    // Footer avec boutons
    const footer = document.createElement('div');
    footer.style.cssText = `
        padding: 20px 25px;
        border-top: 1px solid #3a3a4a;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    `;
    
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.textContent = btn.text;
        button.className = btn.class || 'btn-primary';
        
        let buttonStyle = `
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        `;
        
        if (btn.class === 'btn-danger') {
            buttonStyle += `
                background: linear-gradient(135deg, #ff6b6b, #fa5252);
                color: white;
            `;
        } else if (btn.class === 'btn-secondary') {
            buttonStyle += `
                background: #252533;
                color: #e5e7eb;
                border: 1px solid #3a3a4a;
            `;
        } else {
            buttonStyle += `
                background: linear-gradient(135deg, #4b3795, #6b57b5);
                color: white;
            `;
        }
        
        button.style.cssText = buttonStyle;
        
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'translateY(-2px)';
            button.style.boxShadow = '0 8px 25px rgba(75, 55, 149, 0.4)';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translateY(0)';
            button.style.boxShadow = 'none';
        });
        
        button.addEventListener('click', () => {
            if (btn.onClick) btn.onClick();
            closeModal();
        });
        
        footer.appendChild(button);
    });
    
    // Assembler
    modal.appendChild(header);
    modal.appendChild(body);
    if (buttons.length > 0) {
        modal.appendChild(footer);
    }
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Fermeture
    function closeModal() {
        overlay.style.animation = 'fadeOut 0.3s';
        modal.style.animation = 'scaleOut 0.3s';
        setTimeout(() => overlay.remove(), 300);
    }
    
    header.querySelector('.modal-close').addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });
    
    // ESC pour fermer
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

// Animations modal
if (!document.getElementById('modal-styles')) {
    const modalStyle = document.createElement('style');
    modalStyle.id = 'modal-styles';
    modalStyle.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        @keyframes scaleOut {
            from {
                transform: scale(1);
                opacity: 1;
            }
            to {
                transform: scale(0.9);
                opacity: 0;
            }
        }
        
        .modal-close:hover {
            color: #e5e7eb !important;
        }
    `;
    document.head.appendChild(modalStyle);
}

// ============================================
// FORMATAGE DE DONNÉES
// ============================================

/**
 * Formate une date relative (il y a X minutes/heures/jours)
 * 
 * @param {string} dateStr - Date au format ISO
 * @returns {string} Date formatée
 */
function formatRelativeDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);
    
    if (diffMin < 1) return 'À l\'instant';
    if (diffMin < 60) return `Il y a ${diffMin} min`;
    if (diffHour < 24) return `Il y a ${diffHour}h`;
    if (diffDay < 7) return `Il y a ${diffDay}j`;
    if (diffDay < 30) return `Il y a ${Math.floor(diffDay / 7)} sem.`;
    
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Formate un nombre avec séparateur de milliers
 * 
 * @param {number} num - Nombre à formater
 * @returns {string} Nombre formaté
 */
function formatNumber(num) {
    return num.toLocaleString('fr-FR');
}

/**
 * Tronque un texte avec ellipse
 * 
 * @param {string} text - Texte à tronquer
 * @param {number} maxLength - Longueur maximale
 * @returns {string} Texte tronqué
 */
function truncateText(text, maxLength = 50) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Formate une durée en format lisible
 * 
 * @param {number} seconds - Secondes
 * @returns {string} Format "Xh Ym Zs"
 */
function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    }
    return `${secs}s`;
}

// ============================================
// COPIER DANS LE PRESSE-PAPIER
// ============================================

/**
 * Copie du texte dans le presse-papier
 * 
 * @param {string} text - Texte à copier
 * @param {string} successMessage - Message de succès
 */
async function copyToClipboard(text, successMessage = '📋 Copié !') {
    try {
        await navigator.clipboard.writeText(text);
        showNotification(successMessage, 'success', 2000);
    } catch (error) {
        console.error('Erreur copie:', error);
        showNotification('❌ Erreur lors de la copie', 'error');
    }
}

// ============================================
// EXPORT CSV
// ============================================

/**
 * Exporte des données en CSV
 * 
 * @param {array} data - Tableau d'objets
 * @param {string} filename - Nom du fichier
 */
function exportToCSV(data, filename = 'export.csv') {
    if (data.length === 0) {
        showNotification('⚠️ Aucune donnée à exporter', 'warning');
        return;
    }
    
    try {
        // Créer les headers
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => {
                    let value = row[header] || '';
                    // Échapper les guillemets et virgules
                    if (typeof value === 'string' && (value.includes(',') || value.includes('"') || value.includes('\n'))) {
                        value = '"' + value.replace(/"/g, '""') + '"';
                    }
                    return value;
                }).join(',')
            )
        ].join('\n');
        
        // Ajouter BOM pour Excel UTF-8
        const bom = '\uFEFF';
        const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        
        showNotification('✅ Export CSV réussi', 'success');
    } catch (error) {
        console.error('Erreur export CSV:', error);
        showNotification('❌ Erreur lors de l\'export', 'error');
    }
}

// ============================================
// CONFIRMATION
// ============================================

/**
 * Affiche une confirmation avant action
 * 
 * @param {string} message - Message de confirmation
 * @param {function} onConfirm - Callback si confirmé
 * @param {function} onCancel - Callback si annulé (optionnel)
 */
function confirmAction(message, onConfirm, onCancel = null) {
    showModal(
        '⚠️ Confirmation',
        `<p style="color: #9ca3af; line-height: 1.6;">${message}</p>`,
        [
            {
                text: 'Annuler',
                class: 'btn-secondary',
                onClick: () => {
                    if (onCancel) onCancel();
                }
            },
            {
                text: 'Confirmer',
                class: 'btn-danger',
                onClick: onConfirm
            }
        ]
    );
}

// ============================================
// DEBOUNCE
// ============================================

/**
 * Fonction de debounce pour optimiser les recherches
 * 
 * @param {function} func - Fonction à debouncer
 * @param {number} wait - Délai en ms
 * @returns {function} Fonction debouncée
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// LOADER
// ============================================

/**
 * Affiche un loader full-screen
 */
function showLoader(message = 'Chargement...') {
    let loader = document.getElementById('global-loader');
    
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 14, 23, 0.95);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        `;
        
        loader.innerHTML = `
            <div class="spinner" style="
                width: 60px;
                height: 60px;
                border: 5px solid #3a3a4a;
                border-top-color: #4b3795;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            "></div>
            <p style="color: #9ca3af; font-size: 16px;">${message}</p>
        `;
        
        document.body.appendChild(loader);
    } else {
        loader.style.display = 'flex';
        loader.querySelector('p').textContent = message;
    }
}

/**
 * Cache le loader
 */
function hideLoader() {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.style.display = 'none';
    }
}

// ============================================
// DÉCONNEXION
// ============================================

/**
 * Déconnecte l'utilisateur admin
 */
async function logout() {
    try {
        const response = await fetch(ADMIN_CONFIG.API_BASE + 'auth.php?action=logout', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = 'index.php';
        }
    } catch (error) {
        console.error('Erreur déconnexion:', error);
        // Rediriger vers index.php même en cas d'erreur
        window.location.href = 'index.php';
    }
}

// ============================================
// EXPORT DES FONCTIONS (NAMESPACE)
// ============================================

window.adminUtils = {
    apiRequest,
    showNotification,
    showModal,
    confirmAction,
    formatRelativeDate,
    formatNumber,
    formatDuration,
    truncateText,
    copyToClipboard,
    exportToCSV,
    debounce,
    showLoader,
    hideLoader,
    refreshSession,
    logout
};

// ============================================
// INITIALISATION AU CHARGEMENT
// ============================================

window.addEventListener('DOMContentLoaded', () => {
    // Démarrer le monitoring de session
    initSessionMonitoring();
    
    // Initialiser le menu mobile
    initMobileMenu();
    
    console.log('✅ Script admin chargé');
});