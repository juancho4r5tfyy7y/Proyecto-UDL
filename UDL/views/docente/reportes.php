<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: ../../index.php');
    exit();
}

// Obtener datos del docente
$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información completa del docente
$sql = "SELECT u.*, d.especialidad, d.telefono 
        FROM usuarios u 
        LEFT JOIN docentes d ON u.id = d.usuario_id 
        WHERE u.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$docente = $stmt->get_result()->fetch_assoc();

// Procesar generación de reportes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_student_report':
                generateStudentReport();
                break;
            case 'generate_grades_report':
                generateGradesReport();
                break;
            case 'generate_activities_report':
                generateActivitiesReport();
                break;
            case 'generate_attendance_report':
                generateAttendanceReport();
                break;
            case 'generate_performance_report':
                generatePerformanceReport();
                break;
        }
    }
}

function generateStudentReport() {
    global $conexion, $userId;
    
    $grado = $_POST['grado'] ?? '';
    $formato = $_POST['formato'] ?? 'html';
    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';
    
    // Crear tabla de reportes si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS reportes_generados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        tipo_reporte ENUM('estudiantes', 'calificaciones', 'actividades', 'asistencia', 'rendimiento') NOT NULL,
        parametros JSON,
        fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        formato ENUM('html', 'pdf', 'excel') DEFAULT 'html',
        ruta_archivo VARCHAR(500),
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    // Guardar registro del reporte
    $parametros = json_encode([
        'grado' => $grado,
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin
    ]);
    
    $sql = "INSERT INTO reportes_generados (docente_id, tipo_reporte, parametros, formato) VALUES (?, 'estudiantes', ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $userId, $parametros, $formato);
    $stmt->execute();
    
    $_SESSION['success'] = 'Reporte de estudiantes generado exitosamente';
}

function generateGradesReport() {
    global $conexion, $userId;
    
    $grado = $_POST['grado'] ?? '';
    $periodo = $_POST['periodo'] ?? '';
    $formato = $_POST['formato'] ?? 'html';
    
    $parametros = json_encode([
        'grado' => $grado,
        'periodo' => $periodo
    ]);
    
    $sql = "INSERT INTO reportes_generados (docente_id, tipo_reporte, parametros, formato) VALUES (?, 'calificaciones', ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $userId, $parametros, $formato);
    $stmt->execute();
    
    $_SESSION['success'] = 'Reporte de calificaciones generado exitosamente';
}

// Obtener filtros
$filtroTipo = $_GET['tipo'] ?? '';
$filtroFecha = $_GET['fecha'] ?? '';
$filtroFormato = $_GET['formato'] ?? '';

// Obtener estadísticas generales
$statsSQL = "SELECT 
    COUNT(DISTINCT e.id) as total_estudiantes,
    COUNT(DISTINCT g.id) as total_grupos,
    AVG(CASE WHEN ev.calificacion IS NOT NULL THEN ev.calificacion END) as promedio_general,
    COUNT(DISTINCT a.id) as total_actividades
    FROM estudiantes e
    LEFT JOIN grado g ON e.id = g.estudiante_id
    LEFT JOIN evaluaciones ev ON e.id = ev.estudiante_id
    LEFT JOIN actividades a ON a.docente_id = ?
    WHERE g.docente_id = ?";
$stmt = $conexion->prepare($statsSQL);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Obtener reportes generados anteriormente
$sql = "SELECT r.*, COUNT(*) as total_generados
        FROM reportes_generados r
        WHERE r.docente_id = ?";

$params = [$userId];
$types = "i";

if (!empty($filtroTipo)) {
    $sql .= " AND r.tipo_reporte = ?";
    $params[] = $filtroTipo;
    $types .= "s";
}

if (!empty($filtroFecha)) {
    $sql .= " AND DATE(r.fecha_generacion) = ?";
    $params[] = $filtroFecha;
    $types .= "s";
}

if (!empty($filtroFormato)) {
    $sql .= " AND r.formato = ?";
    $params[] = $filtroFormato;
    $types .= "s";
}

$sql .= " GROUP BY r.tipo_reporte, r.formato ORDER BY r.fecha_generacion DESC LIMIT 10";

$stmt = $conexion->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reportesAnteriores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener datos para gráficos
$gradesSQL = "SELECT 
    g.grado,
    AVG(ev.calificacion) as promedio,
    COUNT(ev.id) as total_evaluaciones
    FROM grado g
    LEFT JOIN evaluaciones ev ON g.estudiante_id = ev.estudiante_id
    WHERE g.docente_id = ?
    GROUP BY g.grado
    ORDER BY g.grado";
$stmt = $conexion->prepare($gradesSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$datosGraficos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener actividades recientes
$activitiesSQL = "SELECT 
    a.titulo,
    a.fecha_creacion,
    COUNT(s.id) as total_entregas,
    AVG(s.calificacion) as promedio_actividad
    FROM actividades a
    LEFT JOIN submissions s ON a.id = s.actividad_id
    WHERE a.docente_id = ?
    GROUP BY a.id
    ORDER BY a.fecha_creacion DESC
    LIMIT 5";
$stmt = $conexion->prepare($activitiesSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$actividadesRecientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="dashboard-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($docente['nombre'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($docente['nombre']) ?></h3>
                    <p>Docente - <?= htmlspecialchars($docente['especialidad'] ?? 'Lenguaje') ?></p>
                </div>
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
                <a href="gestionar_actividades.php">
                    <i class="fas fa-tasks"></i>
                    <span>Gestionar Actividades</span>
                </a>
            </li>
            <li>
                <a href="estudiantes.php">
                    <i class="fas fa-users"></i>
                    <span>Mis Estudiantes</span>
                </a>
            </li>
            <li>
                <a href="calificaciones.php">
                    <i class="fas fa-star"></i>
                    <span>Calificaciones</span>
                </a>
            </li>
            <li>
                <a href="material.php">
                    <i class="fas fa-book"></i>
                    <span>Material Didáctico</span>
                </a>
            </li>
            <li class="active">
                <a href="reportes.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
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
        <!-- Alertas -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="content-header">
            <h1><i class="fas fa-chart-bar"></i> Reportes Académicos</h1>
            <p>Genera y analiza reportes detallados del rendimiento académico</p>
        </div>

        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_estudiantes'] ?? 0 ?></div>
                    <div class="stat-label">Total Estudiantes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_grupos'] ?? 0 ?></div>
                    <div class="stat-label">Grupos Activos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['promedio_general'] ?? 0, 1) ?></div>
                    <div class="stat-label">Promedio General</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_actividades'] ?? 0 ?></div>
                    <div class="stat-label">Actividades Creadas</div>
                </div>
            </div>
        </div>

        <!-- Generadores de Reportes -->
        <div class="reports-section">
            <h3><i class="fas fa-file-alt"></i> Generar Nuevos Reportes</h3>
            
            <div class="reports-grid">
                <!-- Reporte de Estudiantes -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="report-info">
                            <h4>Reporte de Estudiantes</h4>
                            <p>Lista detallada de estudiantes con información académica</p>
                        </div>
                    </div>
                    <div class="report-actions">
                        <button class="btn btn-primary" onclick="openModal('studentReportModal')">
                            <i class="fas fa-file-alt"></i> Generar
                        </button>
                    </div>
                </div>
                
                <!-- Reporte de Calificaciones -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="report-info">
                            <h4>Reporte de Calificaciones</h4>
                            <p>Análisis detallado de calificaciones por período</p>
                        </div>
                    </div>
                    <div class="report-actions">
                        <button class="btn btn-success" onclick="openModal('gradesReportModal')">
                            <i class="fas fa-chart-line"></i> Generar
                        </button>
                    </div>
                </div>
                
                <!-- Reporte de Actividades -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="report-info">
                            <h4>Reporte de Actividades</h4>
                            <p>Estadísticas de actividades y entregas</p>
                        </div>
                    </div>
                    <div class="report-actions">
                        <button class="btn btn-info" onclick="openModal('activitiesReportModal')">
                            <i class="fas fa-clipboard-list"></i> Generar
                        </button>
                    </div>
                </div>
                
                <!-- Reporte de Asistencia -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="report-info">
                            <h4>Reporte de Asistencia</h4>
                            <p>Control de asistencia y puntualidad</p>
                        </div>
                    </div>
                    <div class="report-actions">
                        <button class="btn btn-warning" onclick="openModal('attendanceReportModal')">
                            <i class="fas fa-user-check"></i> Generar
                        </button>
                    </div>
                </div>
                
                <!-- Reporte de Rendimiento -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="report-info">
                            <h4>Reporte de Rendimiento</h4>
                            <p>Análisis integral del rendimiento académico</p>
                        </div>
                    </div>
                    <div class="report-actions">
                        <button class="btn btn-danger" onclick="openModal('performanceReportModal')">
                            <i class="fas fa-analytics"></i> Generar
                        </button>
                    </div>
                </div>
                
                <!-- Reporte Personalizado -->
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="report-info">
                            <h4>Reporte Personalizado</h4>
                            <p>Crea reportes con parámetros específicos</p>
                        </div>
                    </div>
                    <div class="report-actions">
                        <button class="btn btn-secondary" onclick="openModal('customReportModal')">
                            <i class="fas fa-wrench"></i> Personalizar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos y Análisis -->
        <div class="analytics-section">
            <div class="analytics-grid">
                <!-- Gráfico de Promedios por Grado -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h4><i class="fas fa-chart-bar"></i> Promedios por Grado</h4>
                    </div>
                    <div class="chart-container">
                        <canvas id="gradesChart"></canvas>
                    </div>
                </div>
                
                <!-- Actividades Recientes -->
                <div class="activities-card">
                    <div class="activities-header">
                        <h4><i class="fas fa-clock"></i> Actividades Recientes</h4>
                    </div>
                    <div class="activities-list">
                        <?php if (empty($actividadesRecientes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <p>No hay actividades recientes</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($actividadesRecientes as $actividad): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <h5><?= htmlspecialchars($actividad['titulo']) ?></h5>
                                        <p><?= date('d/m/Y', strtotime($actividad['fecha_creacion'])) ?></p>
                                    </div>
                                    <div class="activity-stats">
                                        <span class="stat">
                                            <i class="fas fa-users"></i> <?= $actividad['total_entregas'] ?>
                                        </span>
                                        <span class="stat">
                                            <i class="fas fa-star"></i> <?= number_format($actividad['promedio_actividad'] ?? 0, 1) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Reportes -->
        <div class="history-section">
            <div class="section-header">
                <h3><i class="fas fa-history"></i> Historial de Reportes</h3>
                
                <!-- Filtros -->
                <div class="filters-bar">
                    <form method="GET" class="filters-form">
                        <select name="tipo" class="form-control">
                            <option value="">Todos los tipos</option>
                            <option value="estudiantes" <?= $filtroTipo === 'estudiantes' ? 'selected' : '' ?>>Estudiantes</option>
                            <option value="calificaciones" <?= $filtroTipo === 'calificaciones' ? 'selected' : '' ?>>Calificaciones</option>
                            <option value="actividades" <?= $filtroTipo === 'actividades' ? 'selected' : '' ?>>Actividades</option>
                            <option value="asistencia" <?= $filtroTipo === 'asistencia' ? 'selected' : '' ?>>Asistencia</option>
                            <option value="rendimiento" <?= $filtroTipo === 'rendimiento' ? 'selected' : '' ?>>Rendimiento</option>
                        </select>
                        
                        <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($filtroFecha) ?>">
                        
                        <select name="formato" class="form-control">
                            <option value="">Todos los formatos</option>
                            <option value="html" <?= $filtroFormato === 'html' ? 'selected' : '' ?>>HTML</option>
                            <option value="pdf" <?= $filtroFormato === 'pdf' ? 'selected' : '' ?>>PDF</option>
                            <option value="excel" <?= $filtroFormato === 'excel' ? 'selected' : '' ?>>Excel</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($reportesAnteriores)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h4>No hay reportes generados</h4>
                    <p>Genera tu primer reporte para comenzar a analizar los datos</p>
                </div>
            <?php else: ?>
                <div class="reports-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipo de Reporte</th>
                                <th>Formato</th>
                                <th>Fecha de Generación</th>
                                <th>Veces Generado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportesAnteriores as $reporte): ?>
                                <tr>
                                    <td>
                                        <span class="report-type-badge type-<?= $reporte['tipo_reporte'] ?>">
                                            <?= ucfirst($reporte['tipo_reporte']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="format-badge format-<?= $reporte['formato'] ?>">
                                            <?= strtoupper($reporte['formato']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($reporte['fecha_generacion'])) ?></td>
                                    <td><?= $reporte['total_generados'] ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="viewReport(<?= $reporte['id'] ?>)">
                                            <i class="fas fa-eye"></i> Ver
                                        </button>
                                        <button class="btn btn-success btn-sm" onclick="downloadReport(<?= $reporte['id'] ?>)">
                                            <i class="fas fa-download"></i> Descargar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal Reporte de Estudiantes -->
<div id="studentReportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-users"></i> Generar Reporte de Estudiantes</h3>
            <button class="close-modal" onclick="closeModal('studentReportModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="generate_student_report">
            <div class="modal-body">
                <div class="form-group">
                    <label for="grado">Grado:</label>
                    <select name="grado" id="grado" class="form-control" required>
                        <option value="">Seleccionar grado</option>
                        <option value="6">6°</option>
                        <option value="7">7°</option>
                        <option value="8">8°</option>
                        <option value="9">9°</option>
                        <option value="10">10°</option>
                        <option value="11">11°</option>
                        <option value="todos">Todos los grados</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio:</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin:</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="formato">Formato de Salida:</label>
                    <select name="formato" id="formato" class="form-control" required>
                        <option value="html">HTML (Vista Web)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="incluir_fotos" id="incluir_fotos">
                        <span class="checkmark"></span>
                        Incluir fotografías de estudiantes
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="incluir_contacto" id="incluir_contacto">
                        <span class="checkmark"></span>
                        Incluir información de contacto
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('studentReportModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Generar Reporte</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reporte de Calificaciones -->
<div id="gradesReportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-star"></i> Generar Reporte de Calificaciones</h3>
            <button class="close-modal" onclick="closeModal('gradesReportModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="generate_grades_report">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="grado_cal">Grado:</label>
                        <select name="grado" id="grado_cal" class="form-control" required>
                            <option value="">Seleccionar grado</option>
                            <option value="6">6°</option>
                            <option value="7">7°</option>
                            <option value="8">8°</option>
                            <option value="9">9°</option>
                            <option value="10">10°</option>
                            <option value="11">11°</option>
                            <option value="todos">Todos los grados</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="periodo">Período:</label>
                        <select name="periodo" id="periodo" class="form-control" required>
                            <option value="">Seleccionar período</option>
                            <option value="1">Primer Período</option>
                            <option value="2">Segundo Período</option>
                            <option value="3">Tercer Período</option>
                            <option value="4">Cuarto Período</option>
                            <option value="todos">Todos los períodos</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="formato_cal">Formato de Salida:</label>
                    <select name="formato" id="formato_cal" class="form-control" required>
                        <option value="html">HTML (Vista Web)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="incluir_graficos" id="incluir_graficos">
                        <span class="checkmark"></span>
                        Incluir gráficos estadísticos
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="incluir_promedios" id="incluir_promedios">
                        <span class="checkmark"></span>
                        Incluir cálculos de promedios
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('gradesReportModal')">Cancelar</button>
                <button type="submit" class="btn btn-success">Generar Reporte</button>
            </div>
        </form>
    </div>
</div>

<style>
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--gradient-bg);
}

.sidebar {
    width: 280px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-right: 1px solid rgba(201, 126, 40, 0.1);
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    padding: 30px 20px;
    border-bottom: 1px solid rgba(201, 126, 40, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.user-details h3 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.user-details p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.sidebar-menu {
    list-style: none;
    padding: 20px 0;
    margin: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    color: var(--text-dark);
    text-decoration: none;
    transition: all 0.3s ease;
}

.sidebar-menu a:hover,
.sidebar-menu li.active a {
    background: rgba(201, 126, 40, 0.1);
    color: var(--primary-color);
    border-right: 3px solid var(--primary-color);
}

.main-content {
    margin-left: 280px;
    padding: 30px;
    flex: 1;
}

.content-header {
    margin-bottom: 30px;
}

.content-header h1 {
    color: var(--text-dark);
    margin-bottom: 10px;
}

.content-header p {
    color: var(--text-muted);
    font-size: 1.1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(201, 126, 40, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary-color);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-dark);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.reports-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.report-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.report-card:hover {
    transform: translateY(-3px);
}

.report-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.report-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(201, 126, 40, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: var(--primary-color);
}

.report-info h4 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.report-info p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.analytics-section {
    margin-bottom: 30px;
}

.analytics-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.chart-card,
.activities-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.chart-header,
.activities-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(201, 126, 40, 0.1);
}

.chart-header h4,
.activities-header h4 {
    margin: 0;
    color: var(--text-dark);
}

.chart-container {
    height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(201, 126, 40, 0.05);
    border-radius: 10px;
    color: var(--text-muted);
}

.activities-list {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid rgba(201, 126, 40, 0.1);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-info h5 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.activity-info p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.activity-stats {
    display: flex;
    gap: 15px;
}

.activity-stats .stat {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.history-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 20px;
}

.section-header h3 {
    margin: 0;
    color: var(--text-dark);
}

.filters-bar {
    display: flex;
    gap: 15px;
    align-items: center;
}

.filters-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.form-control {
    padding: 8px 12px;
    border: 2px solid rgba(201, 126, 40, 0.2);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

.reports-table {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(201, 126, 40, 0.1);
}

.table th {
    background: rgba(201, 126, 40, 0.1);
    font-weight: 600;
    color: var(--text-dark);
}

.report-type-badge,
.format-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.type-estudiantes { background: rgba(0, 123, 255, 0.1); color: #007bff; }
.type-calificaciones { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.type-actividades { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.type-asistencia { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.type-rendimiento { background: rgba(102, 16, 242, 0.1); color: #6610f2; }

.format-html { background: rgba(0, 123, 255, 0.1); color: #007bff; }
.format-pdf { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.format-excel { background: rgba(40, 167, 69, 0.1); color: #28a745; }

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: var(--primary-color);
    opacity: 0.5;
}

.alert {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.alert-error {
    background: rgba(220, 53, 69, 0.1);
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: white;
    margin: 5% auto;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid rgba(201, 126, 40, 0.1);
}

.modal-header h3 {
    margin: 0;
    color: var(--text-dark);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
    transition: color 0.3s ease;
}

.close-modal:hover {
    color: var(--text-dark);
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid rgba(201, 126, 40, 0.1);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-dark);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .analytics-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filters-form {
        flex-direction: column;
        width: 100%;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function viewReport(reportId) {
    window.open(`view_report.php?id=${reportId}`, '_blank');
}

function downloadReport(reportId) {
    window.location.href = `download_report.php?id=${reportId}`;
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Auto-hide alerts
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Gráfico de promedios (simulado)
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('gradesChart');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        
        // Datos simulados para el gráfico
        const data = {
            labels: ['6°', '7°', '8°', '9°', '10°', '11°'],
            datasets: [{
                label: 'Promedio',
                data: [4.2, 4.0, 3.8, 4.1, 3.9, 4.3],
                backgroundColor: 'rgba(201, 126, 40, 0.2)',
                borderColor: 'rgba(201, 126, 40, 1)',
                borderWidth: 2
            }]
        };
        
        // Configuración básica del gráfico
        const config = {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        };
        
        // Crear gráfico simple con canvas
        drawSimpleChart(ctx, data);
    }
});

function drawSimpleChart(ctx, data) {
    const canvas = ctx.canvas;
    const width = canvas.width;
    const height = canvas.height;
    
    // Limpiar canvas
    ctx.clearRect(0, 0, width, height);
    
    // Configuración
    const padding = 40;
    const chartWidth = width - 2 * padding;
    const chartHeight = height - 2 * padding;
    const barWidth = chartWidth / data.labels.length;
    const maxValue = 5;
    
    // Dibujar ejes
    ctx.strokeStyle = '#ccc';
    ctx.lineWidth = 1;
    
    // Eje Y
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.stroke();
    
    // Eje X
    ctx.beginPath();
    ctx.moveTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();
    
    // Dibujar barras
    ctx.fillStyle = 'rgba(201, 126, 40, 0.7)';
    data.datasets[0].data.forEach((value, index) => {
        const barHeight = (value / maxValue) * chartHeight;
        const x = padding + index * barWidth + barWidth * 0.1;
        const y = height - padding - barHeight;
        const width = barWidth * 0.8;
        
        ctx.fillRect(x, y, width, barHeight);
        
        // Etiquetas
        ctx.fillStyle = '#333';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(data.labels[index], x + width/2, height - padding + 20);
        ctx.fillText(value.toFixed(1), x + width/2, y - 5);
        
        ctx.fillStyle = 'rgba(201, 126, 40, 0.7)';
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>