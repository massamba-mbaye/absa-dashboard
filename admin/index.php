<?php
/**
 * PAGE DE CONNEXION ADMIN
 * Interface de login pour accéder au back-office ABSA
 */

// Démarrer la session
session_start();

// Charger la configuration des chemins
require_once __DIR__ . '/config-path.php';

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Récupérer les messages d'erreur depuis l'URL
$errorMessage = '';
if (isset($_GET['error'])) {
    $errors = [
        'not_logged_in' => 'Vous devez vous connecter pour accéder à cette page.',
        'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
        'invalid_credentials' => 'Nom d\'utilisateur ou mot de passe incorrect.',
        'blocked' => 'Trop de tentatives échouées. Veuillez réessayer plus tard.'
    ];
    
    $errorMessage = $errors[$_GET['error']] ?? 'Une erreur est survenue.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Connexion - ABSA Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ========================================
           VARIABLES & RESET
           ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4b3795;
            --primary-light: #6b57b5;
            --cyan: #51c6e1;
            --bg-dark: #0f0e17;
            --bg-card: #1e1e2e;
            --text-primary: #ffffff;
            --text-secondary: #d1d5db;
            --text-tertiary: #9ca3af;
            --danger: #ff6b6b;
            --success: #51cf66;
            --border: #3a3a4a;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f0e17 0%, #1a1625 50%, #2d1b4e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(75, 55, 149, 0.15) 0%, transparent 70%);
            top: -250px;
            right: -250px;
            animation: float 20s infinite ease-in-out;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(81, 198, 225, 0.1) 0%, transparent 70%);
            bottom: -200px;
            left: -200px;
            animation: float 15s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 30px) scale(1.1); }
        }

        /* ========================================
           CONTAINER
           ======================================== */
        .login-container {
            background: var(--bg-card);
            border: 1px solid rgba(75, 55, 149, 0.3);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(75, 55, 149, 0.1);
            width: 100%;
            max-width: 440px;
            padding: 50px 40px;
            position: relative;
            z-index: 1;
            animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ========================================
           HEADER
           ======================================== */
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-logo {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary), var(--cyan));
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(75, 55, 149, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 10px 30px rgba(75, 55, 149, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 15px 40px rgba(75, 55, 149, 0.6); }
        }

        .login-logo i {
            font-size: 45px;
            color: white;
        }

        .login-title {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 15px;
            color: var(--text-tertiary);
            font-weight: 400;
        }

        /* ========================================
           MESSAGES
           ======================================== */
        .message {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: none;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .message.show {
            display: flex;
        }

        .message.error {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: var(--danger);
        }

        .message.success {
            background: rgba(81, 207, 102, 0.1);
            border: 1px solid rgba(81, 207, 102, 0.3);
            color: var(--success);
        }

        .message i {
            font-size: 18px;
        }

        /* ========================================
           FORMULAIRE
           ======================================== */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            background: rgba(42, 42, 58, 0.6);
            border: 2px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(42, 42, 58, 0.9);
            box-shadow: 0 0 0 3px rgba(75, 55, 149, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-tertiary);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            font-size: 16px;
            pointer-events: none;
            transition: color 0.3s;
        }

        .form-group input:focus + .input-icon {
            color: var(--primary-light);
        }

        /* ========================================
           BOUTON
           ======================================== */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(75, 55, 149, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            background: var(--border);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Loading spinner */
        .loading {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading .loading {
            display: inline-block;
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        /* ========================================
           FOOTER
           ======================================== */
        .login-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 13px;
            color: var(--text-tertiary);
        }

        .login-footer a {
            color: var(--primary-light);
            text-decoration: none;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: var(--cyan);
        }

        /* ========================================
           RESPONSIVE
           ======================================== */
        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
            }

            .login-title {
                font-size: 24px;
            }

            .login-logo {
                width: 80px;
                height: 80px;
            }

            .login-logo i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">ABSA Admin</h1>
            <p class="login-subtitle">Tableau de bord administrateur</p>
        </div>

        <!-- Message d'erreur depuis URL -->
        <?php if ($errorMessage): ?>
        <div class="message error show">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
        <?php endif; ?>

        <!-- Message dynamique (AJAX) -->
        <div id="message" class="message">
            <i class="fas fa-exclamation-circle"></i>
            <span id="message-text"></span>
        </div>

        <!-- Formulaire de connexion -->
        <form id="login-form" class="login-form">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        placeholder="Entrez votre nom d'utilisateur"
                        required 
                        autocomplete="username"
                        autofocus
                    >
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        placeholder="Entrez votre mot de passe"
                        required 
                        autocomplete="current-password"
                    >
                    <i class="fas fa-lock input-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btn-submit">
                <div class="btn-content">
                    <span class="btn-text">Se connecter</span>
                    <span class="loading"></span>
                </div>
            </button>
        </form>

        <!-- Footer -->
        <div class="login-footer">
            <p>© 2025 ABSA by Polaris Asso - Tous droits réservés</p>
        </div>
    </div>

    <script>
        // ========================================
        // GESTION DU FORMULAIRE DE CONNEXION
        // ========================================

        const loginForm = document.getElementById('login-form');
        const btnSubmit = document.getElementById('btn-submit');
        const messageDiv = document.getElementById('message');
        const messageText = document.getElementById('message-text');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');

        /**
         * Affiche un message (erreur ou succès)
         */
        function showMessage(text, type = 'error') {
            messageDiv.className = `message ${type} show`;
            messageText.textContent = text;
            
            const icon = messageDiv.querySelector('i');
            icon.className = type === 'error' 
                ? 'fas fa-exclamation-circle' 
                : 'fas fa-check-circle';
        }

        /**
         * Cache le message
         */
        function hideMessage() {
            messageDiv.classList.remove('show');
        }

        /**
         * Validation côté client
         */
        function validateForm() {
            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            if (!username) {
                showMessage('Veuillez entrer votre nom d\'utilisateur', 'error');
                usernameInput.focus();
                return false;
            }

            if (username.length < 3) {
                showMessage('Le nom d\'utilisateur doit contenir au moins 3 caractères', 'error');
                usernameInput.focus();
                return false;
            }

            if (!password) {
                showMessage('Veuillez entrer votre mot de passe', 'error');
                passwordInput.focus();
                return false;
            }

            if (password.length < 4) {
                showMessage('Le mot de passe doit contenir au moins 4 caractères', 'error');
                passwordInput.focus();
                return false;
            }

            return true;
        }

        /**
         * Gestion de la soumission du formulaire
         */
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            hideMessage();

            // Validation
            if (!validateForm()) {
                return;
            }
            
            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            // Désactiver le bouton et afficher le loading
            btnSubmit.disabled = true;
            btnSubmit.classList.add('loading');

            try {
                // Appel API
                const response = await fetch('<?= apiUrl('auth', ['action' => 'login']) ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Connexion réussie
                    showMessage('✅ Connexion réussie ! Redirection...', 'success');
                    
                    // Rediriger après 1 seconde
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    // Erreur de connexion
                    const errorMsg = data.error || 'Identifiants incorrects';
                    showMessage(errorMsg, 'error');
                    
                    // Réactiver le bouton
                    btnSubmit.disabled = false;
                    btnSubmit.classList.remove('loading');
                    
                    // Focus sur le champ username pour réessayer
                    usernameInput.select();
                }

            } catch (error) {
                console.error('Erreur:', error);
                showMessage('❌ Erreur de connexion au serveur. Veuillez réessayer.', 'error');
                
                // Réactiver le bouton
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('loading');
            }
        });

        // Focus automatique sur le champ username au chargement
        usernameInput.focus();

        // Cacher le message au clic sur un input
        usernameInput.addEventListener('focus', hideMessage);
        passwordInput.addEventListener('focus', hideMessage);
    </script>
</body>
</html>