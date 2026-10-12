<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: ../../index.php');
    exit();
}

// Obtener datos del docente
$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información completa del docente
$sql = "SELECT u.*, d.area_desempeno as especialidad, d.telefono 
        FROM usuarios u 
        LEFT JOIN docentes d ON u.id = d.usuario_id 
        WHERE u.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$docente = $stmt->get_result()->fetch_assoc();

// Obtener estadísticas del docente
$stats = [
    'grupos_activos' => 0,
    'estudiantes_total' => 0,
    'actividades_creadas' => 0,
    'entregas_pendientes' => 0
];

// Contar grupos activos
$sql = "SELECT COUNT(*) as total FROM grupos WHERE docente_id = ? AND activo = 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['grupos_activos'] = $result['total'];

// Contar estudiantes total
$sql = "SELECT COUNT(DISTINCT eg.estudiante_id) as total 
        FROM grupos g 
        JOIN estudiantes_grupos eg ON g.id = eg.grupo_id 
        WHERE g.docente_id = ? AND g.activo = 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['estudiantes_total'] = $result['total'];

// Contar actividades creadas
$sql = "SELECT COUNT(*) as total 
        FROM actividades a 
        JOIN grupos g ON a.grupo_id = g.id 
        WHERE g.docente_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['actividades_creadas'] = $result['total'];

// Contar entregas pendientes de revisión
$sql = "SELECT COUNT(*) as total 
        FROM entregas_estudiantes ee 
        JOIN actividades a ON ee.actividad_id = a.id 
        JOIN grupos g ON a.grupo_id = g.id 
        WHERE g.docente_id = ? AND ee.calificacion IS NULL";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['entregas_pendientes'] = $result['total'];

// Obtener actividades recientes
$sql = "SELECT a.titulo, a.fecha_creacion, g.nombre as grupo_nombre,
               COUNT(ee.id) as entregas_recibidas
        FROM actividades a 
        JOIN grupos g ON a.grupo_id = g.id 
        LEFT JOIN entregas_estudiantes ee ON a.id = ee.actividad_id
        WHERE g.docente_id = ? 
        GROUP BY a.id, a.titulo, a.fecha_creacion, g.nombre
        ORDER BY a.fecha_creacion DESC 
        LIMIT 5";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$actividades_recientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener grupos activos
$sql = "SELECT g.*, COUNT(eg.estudiante_id) as total_estudiantes
        FROM grupos g 
        LEFT JOIN estudiantes_grupos eg ON g.id = eg.grupo_id
        WHERE g.docente_id = ? AND g.activo = 1
        GROUP BY g.id
        ORDER BY g.nombre";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$grupos_activos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php require_once '../../includes/header.php'; ?>
<div class="dashboard-container">
    <?php include '../../includes/navigation_docente.php'; ?>
        <!-- Main Content -->
        <main class="main-content" id="mainContent" style="padding-top: 20px;">

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= $stats['grupos_activos'] ?></div>
                    <div class="stat-label">Grupos Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-number"><?= $stats['estudiantes_total'] ?></div>
                    <div class="stat-label">Total Estudiantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <div class="stat-number"><?= $stats['actividades_creadas'] ?></div>
                    <div class="stat-label">Actividades Creadas</div>
                </div>
                <div class="stat-card urgent">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?= $stats['entregas_pendientes'] ?></div>
                    <div class="stat-label">Entregas por Revisar</div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Actividades Recientes -->
                <div class="card">
                    <div class="card-header">
                        <h3>Actividades Recientes</h3>
                        <a href="gestionar_actividades.php" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($actividades_recientes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <h4>No has creado actividades aún</h4>
                                <a href="crear_actividad.php" class="btn btn-primary mt-2">Crear Primera Actividad</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($actividades_recientes as $actividad): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <h4><?= htmlspecialchars($actividad['titulo']) ?></h4>
                                        <p>
                                            <span class="group-badge"><?= htmlspecialchars($actividad['grupo_nombre']) ?></span>
                                            • Creada: <?= date('d/m/Y', strtotime($actividad['fecha_creacion'])) ?>
                                        </p>
                                    </div>
                                    <span class="entregas-count">
                                        <?= $actividad['entregas_recibidas'] ?> entregas
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Grupos Activos -->
                <div class="card">
                    <div class="card-header">
                        <h3>Mis Grupos</h3>
                        <a href="estudiantes.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grupos_activos)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h4>No tienes grupos asignados</h4>
                            </div>
                        <?php else: ?>
                            <?php foreach ($grupos_activos as $grupo): ?>
                                <div class="group-item">
                                    <div class="group-info">
                                        <h4><?= htmlspecialchars($grupo['nombre']) ?></h4>
                                        <p><?= htmlspecialchars($grupo['descripcion'] ?? 'Sin descripción') ?></p>
                                    </div>
                                    <span class="student-count">
                                        <i class="fas fa-user"></i>
                                        <?= $grupo['total_estudiantes'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Accesos Rápidos -->
            <div class="quick-actions">
                <h2>Acciones Rápidas</h2>
                <div class="actions-grid">
                    <a href="crear_actividad.php" class="action-card">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Nueva Actividad</h3>
                        <p>Crea tareas y proyectos para tus estudiantes.</p>
                    </a>
                    <a href="calificar_entregas.php" class="action-card">
                        <i class="fas fa-star"></i>
                        <h3>Calificar Entregas</h3>
                        <p>Revisa y califica los trabajos pendientes.</p>
                    </a>
                    <a href="estudiantes.php" class="action-card">
                        <i class="fas fa-users"></i>
                        <h3>Ver Estudiantes</h3>
                        <p>Gestiona la información de tus estudiantes.</p>
                    </a>
                    <a href="reportes.php" class="action-card">
                        <i class="fas fa-chart-bar"></i>
                        <h3>Generar Reportes</h3>
                        <p>Crea reportes de progreso y estadísticas.</p>
                    </a>
                </div>
            </div>
        </main>
</div>

    <script src="../../assets/js/Dashboard.js"></script>
</body>
</html>
