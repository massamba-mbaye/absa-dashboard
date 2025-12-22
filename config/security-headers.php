<?php
/**
 * HEADERS DE SÉCURITÉ HTTP
 * Définit les headers de sécurité pour toutes les pages admin
 */

// ============================================
// HEADERS DE SÉCURITÉ
// ============================================

// Empêcher le clickjacking (iframe embedding)
header("X-Frame-Options: DENY");

// Protection contre le sniffing MIME
header("X-Content-Type-Options: nosniff");

// Protection XSS intégrée au navigateur
header("X-XSS-Protection: 1; mode=block");

// Content Security Policy (CSP)
// Permet uniquement les ressources du même domaine + CDNs autorisés
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
    "font-src 'self' https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: https:; " .
    "connect-src 'self' https://cdn.jsdelivr.net; " .
    "frame-ancestors 'none';"
);

// HSTS - Force HTTPS (à activer en production avec HTTPS)
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Politique de référent - ne pas envoyer le referrer vers des domaines externes
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions Policy - Désactiver les fonctionnalités non nécessaires
header("Permissions-Policy: " .
    "geolocation=(), " .
    "microphone=(), " .
    "camera=(), " .
    "payment=(), " .
    "usb=(), " .
    "magnetometer=(), " .
    "gyroscope=(), " .
    "accelerometer=()"
);

// ============================================
// CONFIGURATION CACHE
// ============================================

// Empêcher le cache des pages admin pour éviter l'accès après déconnexion
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
