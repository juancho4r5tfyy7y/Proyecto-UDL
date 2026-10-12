<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

// Obtener datos del estudiante
$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información completa del estudiante
$sql = "SELECT u.*, g.nombre as grupo_nombre, g.grado 
        FROM usuarios u 
        LEFT JOIN estudiantes_grupos eg ON u.id = eg.estudiante_id
        LEFT JOIN grupos g ON eg.grupo_id = g.id
        WHERE u.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$estudiante = $stmt->get_result()->fetch_assoc();

// Obtener estadísticas del estudiante
$stats = [
    'actividades_asignadas' => 0,
    'actividades_completadas' => 0,
    'actividades_pendientes' => 0,
    'promedio_calificaciones' => 0
];

// Obtener actividades del estudiante
$sql = "SELECT COUNT(*) as total FROM actividades a 
        JOIN grupos g ON a.grupo_id = g.id 
        JOIN estudiantes_grupos eg ON g.id = eg.grupo_id 
        WHERE eg.estudiante_id = ? AND a.activa = 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['actividades_asignadas'] = $result['total'];

// Contar entregas realizadas
$sql = "SELECT COUNT(*) as completadas FROM entregas_estudiantes ee
        JOIN actividades a ON ee.actividad_id = a.id
        JOIN grupos g ON a.grupo_id = g.id 
        JOIN estudiantes_grupos eg ON g.id = eg.grupo_id 
        WHERE eg.estudiante_id = ? AND ee.estudiante_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['actividades_completadas'] = $result['completadas'];

$stats['actividades_pendientes'] = $stats['actividades_asignadas'] - $stats['actividades_completadas'];

// Calcular promedio de calificaciones
$sql = "SELECT AVG(ee.calificacion) as promedio FROM entregas_estudiantes ee
        JOIN actividades a ON ee.actividad_id = a.id
        JOIN grupos g ON a.grupo_id = g.id 
        JOIN estudiantes_grupos eg ON g.id = eg.grupo_id 
        WHERE eg.estudiante_id = ? AND ee.estudiante_id = ? AND ee.calificacion IS NOT NULL";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['promedio_calificaciones'] = round($result['promedio'] ?? 0, 1);

// Obtener actividades recientes
$sql = "SELECT a.titulo, a.descripcion, a.fecha_limite, a.fecha_creacion,
               ee.id as entrega_id, ee.calificacion, ee.estado as estado_entrega
        FROM actividades a 
        JOIN grupos g ON a.grupo_id = g.id 
        JOIN estudiantes_grupos eg ON g.id = eg.grupo_id 
        LEFT JOIN entregas_estudiantes ee ON a.id = ee.actividad_id AND ee.estudiante_id = ?
        WHERE eg.estudiante_id = ? AND a.activa = 1
        ORDER BY a.fecha_creacion DESC 
        LIMIT 5";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$actividades_recientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Estudiante - UDL</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/Dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($estudiante['nombre'], 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <h3><?= htmlspecialchars($estudiante['nombre']) ?></h3>
                        <p>Estudiante - <?= htmlspecialchars($estudiante['grado'] ?? 'Sin grado') ?>°</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                <li><a href="actividades.php"><i class="fas fa-tasks"></i><span>Mis Actividades</span></a></li>
                <li><a href="progreso.php"><i class="fas fa-chart-line"></i><span>Mi Progreso</span></a></li>
                <li><a href="material.php"><i class="fas fa-book"></i><span>Material de Estudio</span></a></li>
                <li><a href="grupo_estudiante.php"><i class="fas fa-users"></i><span>Mi Grupo</span></a></li>
                <li><a href="mis_entregas.php"><i class="fas fa-upload"></i><span>Mis Entregas</span></a></li>
                <li><a href="../../index.php"><i class="fas fa-home"></i><span>Volver al Inicio</span></a></li>
                <li><a href="../../php/logout.php"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <div class="content-header">
                <div>
                    <h1>¡Bienvenido, <?= htmlspecialchars($estudiante['nombre']) ?>!</h1>
                    <p>Aquí tienes un resumen de tu progreso académico.</p>
                </div>
                <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
            </div>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon assigned"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-number"><?= $stats['actividades_asignadas'] ?></div>
                    <div class="stat-label">Actividades Asignadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?= $stats['actividades_completadas'] ?></div>
                    <div class="stat-label">Actividades Completadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?= $stats['actividades_pendientes'] ?></div>
                    <div class="stat-label">Actividades Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon average"><i class="fas fa-star"></i></div>
                    <div class="stat-number"><?= $stats['promedio_calificaciones'] ?></div>
                    <div class="stat-label">Promedio</div>
                </div>
            </div>

            <!-- Actividades Recientes -->
            <div class="activities-section">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-history"></i>
                        Actividades Recientes
                    </h3>
                </div>
                <div class="section-body">
                    <?php if (empty($actividades_recientes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <h4>No hay actividades recientes</h4>
                            <p>Cuando tu docente asigne actividades, aparecerán aquí</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($actividades_recientes as $actividad): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <h4><?= htmlspecialchars($actividad['titulo']) ?></h4>
                                    <p><?= htmlspecialchars(substr($actividad['descripcion'], 0, 100)) ?>...</p>
                                    <small>
                                        <i class="fas fa-calendar"></i>
                                        Fecha límite: <?= date('d/m/Y', strtotime($actividad['fecha_limite'])) ?>
                                    </small>
                                </div>
                                <div>
                                    <?php if ($actividad['entrega_id']): ?>
                                        <?php if ($actividad['calificacion']): ?>
                                            <span class="activity-status status-graded">
                                                Calificada: <?= $actividad['calificacion'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="activity-status status-completed">
                                                Entregada
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="activity-status status-pending">
                                            Pendiente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="quick-actions">
                <h2>Acciones Rápidas</h2>
                <div class="actions-grid">
                    <a href="actividades.php" class="action-card">
                        <i class="fas fa-tasks"></i>
                        <h3>Ver Actividades</h3>
                        <p>Revisa tus tareas y fechas de entrega.</p>
                    </a>
                    <a href="progreso.php" class="action-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Mi Progreso</h3>
                        <p>Consulta tus calificaciones y avance.</p>
                    </a>
                    <a href="material.php" class="action-card">
                        <i class="fas fa-book"></i>
                        <h3>Material de Estudio</h3>
                        <p>Accede a guías y recursos de clase.</p>
                    </a>
                    <a href="grupo_estudiante.php" class="action-card">
                        <i class="fas fa-users"></i>
                        <h3>Mi Grupo</h3>
                        <p>Ve información sobre tu grupo y compañeros.</p>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/Dashboard.js"></script>
</body>
</html>
