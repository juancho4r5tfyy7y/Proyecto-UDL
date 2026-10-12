php
<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id_usuario'];

// Obtener información del estudiante y grupo
$sql = "SELECT e.*, g.nombre as grupo_nombre, u.nombre, u.apellido
        FROM estudiantes_grupos e
        JOIN usuarios u ON e.estudiante_id = u.id
        LEFT JOIN grupos g ON e.grupo_id = g.id
        WHERE e.estudiante_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$estudiante = $stmt->get_result()->fetch_assoc();

// Obtener estadísticas de actividades
$sql = "SELECT 
            COUNT(*) as total_actividades,
            COUNT(CASE WHEN e.estado = 'calificada' THEN 1 END) as actividades_calificadas,
            COUNT(CASE WHEN e.estado = 'entregada' THEN 1 END) as actividades_entregadas,
            COUNT(CASE WHEN e.estado = 'pendiente' OR e.estado IS NULL THEN 1 END) as actividades_pendientes,
            AVG(CASE WHEN e.calificacion IS NOT NULL THEN e.calificacion END) as promedio_general
        FROM actividades a
        LEFT JOIN entregas e ON a.id = e.actividad_id AND e.estudiante_id = ?
        WHERE a.grupo_id = (SELECT grupo_id FROM estudiantes_grupos WHERE estudiante_id = ?)";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$estadisticas = $stmt->get_result()->fetch_assoc();

// Obtener calificaciones recientes
$sql = "SELECT a.titulo, a.tipo, e.calificacion, e.fecha_entrega, a.fecha_limite,
               u.nombre as docente_nombre, a.descripcion
        FROM entregas e
        JOIN actividades a ON e.actividad_id = a.id
        JOIN usuarios u ON a.docente_id = u.id
        WHERE e.estudiante_id = ? AND e.calificacion IS NOT NULL
        ORDER BY e.fecha_entrega DESC
        LIMIT 10";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$calificaciones_recientes = [];
while ($row = $result->fetch_assoc()) {
    $calificaciones_recientes[] = $row;
}

// Obtener progreso por tipo de actividad
$sql = "SELECT a.tipo, 
               COUNT(*) as total,
               AVG(CASE WHEN e.calificacion IS NOT NULL THEN e.calificacion END) as promedio,
               COUNT(CASE WHEN e.calificacion IS NOT NULL THEN 1 END) as calificadas
        FROM actividades a
        LEFT JOIN entregas e ON a.id = e.actividad_id AND e.estudiante_id = ?
        WHERE a.grupo_id = (SELECT grupo_id FROM estudiantes_grupos WHERE estudiante_id = ?)
        GROUP BY a.tipo
        ORDER BY promedio DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$progreso_por_tipo = [];
while ($row = $result->fetch_assoc()) {
    $progreso_por_tipo[] = $row;
}

// Calcular estadísticas adicionales
$promedio_general = $estadisticas['promedio_general'] ?? 0;
$total_actividades = $estadisticas['total_actividades'] ?? 0;
$actividades_calificadas = $estadisticas['actividades_calificadas'] ?? 0;
$porcentaje_completado = $total_actividades > 0 ? ($actividades_calificadas / $total_actividades) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Progreso - UDL</title>
    <link rel="stylesheet" href="../../assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .student-container {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .student-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            background: #C97E28;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }

        .student-info h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .student-info p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu .active a {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #C97E28;
        }

        .sidebar-menu i {
            width: 20px;
            margin-right: 1rem;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            margin: 0 0 0.5rem 0;
            color: #2C3E50;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header p {
            margin: 0;
            color: #7F8C8D;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color, #C97E28);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #7F8C8D;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #E9ECEF;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #C97E28, #E67E22);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E9ECEF;
        }

        .chart-title {
            margin: 0;
            color: #2C3E50;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-canvas {
            position: relative;
            height: 300px;
        }

        .activity-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .type-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .type-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .type-name {
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 0.5rem;
            text-transform: capitalize;
        }

        .type-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #7F8C8D;
        }

        .type-average {
            font-size: 1.5rem;
            font-weight: 700;
            color: #C97E28;
        }

        .recent-grades {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .recent-grades-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E9ECEF;
            background: #F8F9FA;
        }

        .recent-grades-title {
            margin: 0;
            color: #2C3E50;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .grades-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .grade-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #E9ECEF;
            transition: background-color 0.3s ease;
        }

        .grade-item:hover {
            background-color: #F8F9FA;
        }

        .grade-item:last-child {
            border-bottom: none;
        }

        .grade-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .grade-title {
            font-weight: 600;
            color: #2C3E50;
            margin: 0;
            flex: 1;
        }

        .grade-score {
            font-size: 1.2rem;
            font-weight: 700;
            color: #C97E28;
            margin-left: 1rem;
        }

        .grade-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #7F8C8D;
            flex-wrap: wrap;
        }

        .grade-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #7F8C8D;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: #2C3E50;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 1.2rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            .student-container {
                flex-direction: column;
            }

            .student-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                transform: translateX(-100%);
            }

            .student-sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .main-content {
                padding: 1rem;
                margin-top: 4rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .activity-types-grid {
                grid-template-columns: 1fr;
            }

            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .grade-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .grade-score {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-icon {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="student-container">
        <!-- Sidebar -->
        <nav class="student-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="student-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="student-info">
                    <h3><?= htmlspecialchars($_SESSION['nombre']) ?></h3>
                    <p>Estudiante</p>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="actividades.php">
                        <i class="fas fa-tasks"></i>
                        <span>Mis Actividades</span>
                    </a>
                </li>
                <li class="active">
                    <a href="progreso.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Mi Progreso</span>
                    </a>
                </li>
                <li>
                    <a href="material.php">
                        <i class="fas fa-book"></i>
                        <span>Material de Estudio</span>
                    </a>
                </li>
                <li>
                    <a href="mis_entregas.php">
                        <i class="fas fa-upload"></i>
                        <span>Mis Entregas</span>
                    </a>
                </li>
                <li>
                    <a href="subir_entrega.php">
                        <i class="fas fa-file-upload"></i>
                        <span>Subir Entrega</span>
                    </a>
                </li>
                <li>
                    <a href="../../php/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>
                    <i class="fas fa-chart-line"></i>
                    Mi Progreso Académico
                </h1>
                <p>Seguimiento detallado de tu rendimiento académico</p>
            </div>

            <!-- Estadísticas Generales -->
            <div class="stats-grid">
                <div class="stat-card" style="--accent-color: #C97E28;">
                    <div class="stat-icon" style="color: #C97E28;">📊</div>
                    <div class="stat-number" style="color: #C97E28;"><?= number_format($promedio_general, 1) ?></div>
                    <div class="stat-label">Promedio General</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= ($promedio_general / 10) * 100 ?>%;"></div>
                    </div>
                </div>

                <div class="stat-card" style="--accent-color: #28A745;">
                    <div class="stat-icon" style="color: #28A745;">✅</div>
                    <div class="stat-number" style="color: #28A745;"><?= $actividades_calificadas ?></div>
                    <div class="stat-label">Actividades Calificadas</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $porcentaje_completado ?>%; background: #28A745;"></div>
                    </div>
                </div>

                <div class="stat-card" style="--accent-color: #17A2B8;">
                    <div class="stat-icon" style="color: #17A2B8;">📈</div>
                    <div class="stat-number" style="color: #17A2B8;"><?= number_format($porcentaje_completado, 1) ?>%</div>
                    <div class="stat-label">Progreso Completado</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $porcentaje_completado ?>%; background: #17A2B8;"></div>
                    </div>
                </div>

                <div class="stat-card" style="--accent-color: #FFC107;">
                    <div class="stat-icon" style="color: #FFC107;">📝</div>
                    <div class="stat-number" style="color: #FFC107;"><?= $total_actividades ?></div>
                    <div class="stat-label">Total Actividades</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 100%; background: #FFC107;"></div>
                    </div>
                </div>
            </div>

            <!-- Progreso por Tipo de Actividad -->
            <?php if (!empty($progreso_por_tipo)): ?>
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i>
                            Rendimiento por Tipo de Actividad
                        </h3>
                    </div>
                    <div class="activity-types-grid">
                        <?php foreach ($progreso_por_tipo as $tipo): ?>
                            <?php
                            $iconos = [
                                'taller' => '🔧',
                                'ensayo' => '📝',
                                'lectura' => '📖',
                                'evaluacion' => '📊',
                                'proyecto' => '🚀'
                            ];
                            $icono = $iconos[$tipo['tipo']] ?? '📋';
                            $promedio = $tipo['promedio'] ?? 0;
                            ?>
                            <div class="type-card">
                                <div class="type-icon"><?= $icono ?></div>
                                <div class="type-name"><?= ucfirst($tipo['tipo']) ?></div>
                                <div class="type-stats">
                                    <span><?= $tipo['calificadas'] ?>/<?= $tipo['total'] ?></span>
                                    <span><?= number_format(($tipo['calificadas'] / $tipo['total']) * 100, 1) ?>%</span>
                                </div>
                                <div class="type-average"><?= number_format($promedio, 1) ?></div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= ($promedio / 10) * 100 ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Gráfico de Progreso -->
            <?php if (!empty($calificaciones_recientes)): ?>
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Evolución de Calificaciones
                        </h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Calificaciones Recientes -->
            <div class="recent-grades">
                <div class="recent-grades-header">
                    <h3 class="recent-grades-title">
                        <i class="fas fa-history"></i>
                        Calificaciones Recientes
                    </h3>
                </div>
                <div class="grades-list">
                    <?php if (!empty($calificaciones_recientes)): ?>
                        <?php foreach ($calificaciones_recientes as $calificacion): ?>
                            <div class="grade-item">
                                <div class="grade-header">
                                    <h4 class="grade-title"><?= htmlspecialchars($calificacion['titulo']) ?></h4>
                                    <div class="grade-score"><?= number_format($calificacion['calificacion'], 1) ?>/10</div>
                                </div>
                                <div class="grade-meta">
                                    <span>
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($calificacion['docente_nombre']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-tag"></i>
                                        <?= ucfirst($calificacion['tipo']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($calificacion['fecha_entrega'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <h4>No hay calificaciones disponibles</h4>
                            <p>Cuando completes y califiquen tus actividades, aparecerán aquí.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar en móvil
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Cerrar sidebar al hacer clic en enlaces en móvil
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Gráfico de progreso
        <?php if (!empty($calificaciones_recientes)): ?>
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach (array_reverse($calificaciones_recientes) as $cal): ?>
                        '<?= date('d/m', strtotime($cal['fecha_entrega'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Calificaciones',
                    data: [
                        <?php foreach (array_reverse($calificaciones_recientes) as $cal): ?>
                            <?= $cal['calificacion'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#C97E28',
                    backgroundColor: 'rgba(201, 126, 40, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#C97E28',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Animación de barras de progreso
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>