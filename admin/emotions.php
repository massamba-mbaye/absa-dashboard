<?php
/**
 * PAGE ANALYSE DES ÉMOTIONS
 * Graphiques et statistiques émotionnelles
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
    <title>Analyse Émotions - ABSA Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- CSS Admin -->
    <link rel="stylesheet" href="style-admin.css">
    <link rel="stylesheet" href="skeleton-loader.css">

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
                <h1>Analyse des Émotions</h1>
                <p class="subtitle">Statistiques et graphiques émotionnels</p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <div class="filter-group" style="margin: 0;">
                    <label for="period-select" style="margin-right: 10px;">Période :</label>
                    <select id="period-select" style="padding: 12px 18px; background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 10px; color: var(--text-primary); font-size: 14px; cursor: pointer; font-weight: 500;">
                        <option value="7">7 derniers jours</option>
                        <option value="30">30 derniers jours</option>
                        <option value="0">Toute période</option>
                    </select>
                </div>
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
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-total-analyses">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Analyses Effectuées</p>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-avg-urgency">
                        <div class="skeleton-loader"></div>
                    </h3>
                    <p>Urgence Moyenne</p>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-urgent-cases">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Cas Urgents</p>
                    <span class="stat-badge danger">Niveau ≥ 4</span>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-skeleton" id="stat-violence-reported">
                        <div class="skeleton-loader skeleton-loader-wide"></div>
                    </h3>
                    <p>Violences Signalées</p>
                </div>
            </div>
        </div>
        
        <!-- Loading -->
        <div id="loading" class="loading-container" style="display: none;">
            <div class="spinner"></div>
            <p>Chargement des analyses...</p>
        </div>
        
        <!-- ========================================
             GRAPHIQUES
             ======================================== -->
        <div id="charts-container" style="display: none;">
            
            <div class="charts-grid">
                
                <!-- Répartition Sentiments (Donut Chart) -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-chart-pie"></i>
                            Répartition des Sentiments
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="sentiments-chart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Top 10 Émotions (Bar Chart Horizontal) -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-heart"></i>
                            Top 10 Émotions
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="emotions-chart" height="300"></canvas>
                    </div>
                </div>
                
            </div>
            
            <div class="charts-grid">
                
                <!-- Distribution Urgence (Bar Chart) -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-exclamation-triangle"></i>
                            Distribution des Niveaux d'Urgence
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="urgency-chart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Évolution Sentiments (Line Chart) -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-chart-line"></i>
                            Évolution des Sentiments (30 jours)
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="evolution-chart" height="250"></canvas>
                    </div>
                </div>
                
            </div>
            
            <!-- ========================================
                 TYPES DE VIOLENCE
                 ======================================== -->
            <div class="chart-card">
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
        
        let currentPeriod = 7;
        let charts = {
            sentiments: null,
            emotions: null,
            urgency: null,
            evolution: null
        };
        
        // ============================================
        // CHARGEMENT DES DONNÉES
        // ============================================
        
        async function loadEmotionData() {
            const loading = document.getElementById('loading');
            const chartsContainer = document.getElementById('charts-container');
            
            loading.style.display = 'flex';
            chartsContainer.style.display = 'none';
            
            try {
                const response = await fetch('<?= apiUrl('emotions-analysis') ?>?action=overview&period=' + currentPeriod);
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Erreur lors du chargement');
                }
                
                const data = result.data;
                
                // Afficher les statistiques
                displayStats(data.global_stats);
                
                // Créer/Mettre à jour les graphiques
                createSentimentsChart(data.sentiment_distribution);
                createEmotionsChart(data.top_emotions);
                createUrgencyChart(data.urgency_distribution);
                createEvolutionChart(data.sentiment_evolution);
                
                // Afficher les types de violence
                displayViolenceTypes(data.violence_types);
                
                loading.style.display = 'none';
                chartsContainer.style.display = 'block';
                
            } catch (error) {
                console.error('Erreur:', error);
                loading.style.display = 'none';
                adminUtils.showNotification('❌ Erreur: ' + error.message, 'error');
            }
        }
        
        // ============================================
        // AFFICHAGE STATISTIQUES
        // ============================================
        
        function displayStats(stats) {
            const statTotalAnalyses = document.getElementById('stat-total-analyses');
            statTotalAnalyses.textContent = adminUtils.formatNumber(stats.total_analyses);
            statTotalAnalyses.classList.add('loaded');

            const statAvgUrgency = document.getElementById('stat-avg-urgency');
            statAvgUrgency.textContent = stats.avg_urgency.toFixed(2);
            statAvgUrgency.classList.add('loaded');

            const statUrgentCases = document.getElementById('stat-urgent-cases');
            statUrgentCases.textContent = adminUtils.formatNumber(stats.urgent_cases);
            statUrgentCases.classList.add('loaded');

            const statViolenceReported = document.getElementById('stat-violence-reported');
            statViolenceReported.textContent = adminUtils.formatNumber(stats.violence_reported);
            statViolenceReported.classList.add('loaded');
        }
        
        // ============================================
        // GRAPHIQUE SENTIMENTS (Donut Chart)
        // ============================================
        
        function createSentimentsChart(sentimentsData) {
            const ctx = document.getElementById('sentiments-chart').getContext('2d');
            
            // Détruire le graphique existant
            if (charts.sentiments) {
                charts.sentiments.destroy();
            }
            
            // sentimentsData est déjà l'objet {positive, neutral, negative}
            const data = sentimentsData;
            
            charts.sentiments = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Positif', 'Neutre', 'Négatif'],
                    datasets: [{
                        data: [data.positive, data.neutral, data.negative],
                        backgroundColor: [
                            '#51cf66',
                            '#ffd43b',
                            '#ff6b6b'
                        ],
                        borderColor: '#1e1e2e',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#e5e7eb',
                                padding: 20,
                                font: {
                                    size: 14,
                                    weight: '600'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#252533',
                            titleColor: '#e5e7eb',
                            bodyColor: '#e5e7eb',
                            borderColor: '#3a3a4a',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = sentimentsData.total;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${adminUtils.formatNumber(value)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // ============================================
        // GRAPHIQUE ÉMOTIONS (Bar Chart Horizontal)
        // ============================================
        
        function createEmotionsChart(emotionsData) {
            const ctx = document.getElementById('emotions-chart').getContext('2d');
            
            // Détruire le graphique existant
            if (charts.emotions) {
                charts.emotions.destroy();
            }
            
            if (emotionsData.length === 0) {
                ctx.canvas.parentElement.innerHTML = '<p class="no-data">Aucune émotion détectée</p>';
                return;
            }
            
            const labels = emotionsData.map(e => e.emotion);
            const values = emotionsData.map(e => e.count);
            
            charts.emotions = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre d\'occurrences',
                        data: values,
                        backgroundColor: 'rgba(75, 55, 149, 0.8)',
                        borderColor: '#4b3795',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#252533',
                            titleColor: '#e5e7eb',
                            bodyColor: '#e5e7eb',
                            borderColor: '#3a3a4a',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: '#3a3a4a'
                            },
                            ticks: {
                                color: '#9ca3af',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#e5e7eb',
                                font: {
                                    size: 13,
                                    weight: '500'
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // ============================================
        // GRAPHIQUE URGENCE (Bar Chart)
        // ============================================
        
        function createUrgencyChart(urgencyData) {
            const ctx = document.getElementById('urgency-chart').getContext('2d');
            
            // Détruire le graphique existant
            if (charts.urgency) {
                charts.urgency.destroy();
            }
            
            const labels = urgencyData.map(u => u.label);
            const values = urgencyData.map(u => u.count);
            const colors = [
                '#51cf66', // Niveau 1
                '#51cf66', // Niveau 2
                '#ffd43b', // Niveau 3
                '#ff6b6b', // Niveau 4
                '#ff6b6b'  // Niveau 5
            ];
            
            charts.urgency = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre de cas',
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#252533',
                            titleColor: '#e5e7eb',
                            bodyColor: '#e5e7eb',
                            borderColor: '#3a3a4a',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#e5e7eb',
                                font: {
                                    size: 13,
                                    weight: '500'
                                }
                            }
                        },
                        y: {
                            grid: {
                                color: '#3a3a4a'
                            },
                            ticks: {
                                color: '#9ca3af',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // ============================================
        // GRAPHIQUE ÉVOLUTION (Line Chart)
        // ============================================
        
        function createEvolutionChart(evolutionData) {
            const ctx = document.getElementById('evolution-chart').getContext('2d');
            
            // Détruire le graphique existant
            if (charts.evolution) {
                charts.evolution.destroy();
            }
            
            const labels = evolutionData.map(d => {
                const date = new Date(d.date);
                return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
            });
            
            const positiveData = evolutionData.map(d => d.positive);
            const neutralData = evolutionData.map(d => d.neutral);
            const negativeData = evolutionData.map(d => d.negative);
            
            charts.evolution = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Positif',
                            data: positiveData,
                            borderColor: '#51cf66',
                            backgroundColor: 'rgba(81, 207, 102, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Neutre',
                            data: neutralData,
                            borderColor: '#ffd43b',
                            backgroundColor: 'rgba(255, 212, 59, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Négatif',
                            data: negativeData,
                            borderColor: '#ff6b6b',
                            backgroundColor: 'rgba(255, 107, 107, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#e5e7eb',
                                padding: 15,
                                font: {
                                    size: 13,
                                    weight: '600'
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#252533',
                            titleColor: '#e5e7eb',
                            bodyColor: '#e5e7eb',
                            borderColor: '#3a3a4a',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: '#3a3a4a'
                            },
                            ticks: {
                                color: '#9ca3af',
                                font: {
                                    size: 11
                                },
                                maxRotation: 45,
                                minRotation: 45
                            }
                        },
                        y: {
                            grid: {
                                color: '#3a3a4a'
                            },
                            ticks: {
                                color: '#9ca3af',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
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
        // REFRESH DONNÉES
        // ============================================
        
        async function refreshData() {
            adminUtils.showNotification('🔄 Actualisation...', 'info', 1500);
            await loadEmotionData();
            adminUtils.showNotification('✅ Données actualisées', 'success');
        }
        
        // ============================================
        // GESTION PÉRIODE
        // ============================================
        
        document.getElementById('period-select').addEventListener('change', (e) => {
            currentPeriod = parseInt(e.target.value);
            loadEmotionData();
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
            loadEmotionData();
        });
    </script>
</body>
</html>