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
$sql = "SELECT u.*, d.area_desempeno as especialidad, u.contacto as telefono 
        FROM usuarios u 
        LEFT JOIN docentes d ON u.id = d.usuario_id 
        WHERE u.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$docente = $stmt->get_result()->fetch_assoc();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_evaluation':
                handleCreateEvaluation();
                break;
            case 'update_grade':
                handleUpdateGrade();
                break;
            case 'delete_evaluation':
                handleDeleteEvaluation();
                break;
            case 'bulk_grade':
                handleBulkGrade();
                break;
        }
    }
}

function handleCreateEvaluation() {
    global $conexion, $userId;
    
    $nombre = $_POST['nombre_evaluacion'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $tipo = $_POST['tipo_evaluacion'] ?? '';
    $grupoId = $_POST['grupo_id'] ?? 0;
    $fechaLimite = $_POST['fecha_limite'] ?? '';
    $valorMaximo = $_POST['valor_maximo'] ?? 5.0;
    $peso = $_POST['peso'] ?? 1.0;
    
    if (empty($nombre) || empty($tipo) || $grupoId == 0) {
        $_SESSION['error'] = 'Nombre, tipo de evaluación y grupo son obligatorios';
        return;
    }
    
    // Crear tabla evaluaciones si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS evaluaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        grupo_id INT NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        tipo ENUM('quiz', 'examen', 'tarea', 'proyecto', 'participacion', 'otro') NOT NULL,
        fecha_limite DATETIME,
        valor_maximo DECIMAL(4,2) DEFAULT 5.00,
        peso DECIMAL(3,2) DEFAULT 1.00,
        activa TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    $sql = "INSERT INTO evaluaciones (docente_id, grupo_id, nombre, descripcion, tipo, fecha_limite, valor_maximo, peso) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iissssdd", $userId, $grupoId, $nombre, $descripcion, $tipo, $fechaLimite, $valorMaximo, $peso);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Evaluación creada exitosamente';
    } else {
        $_SESSION['error'] = 'Error al crear la evaluación';
    }
}

function handleUpdateGrade() {
    global $conexion;
    
    $evaluacionId = $_POST['evaluacion_id'] ?? 0;
    $estudianteId = $_POST['estudiante_id'] ?? 0;
    $calificacion = $_POST['calificacion'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Crear tabla calificaciones si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS calificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evaluacion_id INT NOT NULL,
        estudiante_id INT NOT NULL,
        calificacion DECIMAL(4,2),
        observaciones TEXT,
        fecha_calificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE CASCADE,
        FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_grade (evaluacion_id, estudiante_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    // Verificar si ya existe la calificación
    $checkSQL = "SELECT id FROM calificaciones WHERE evaluacion_id = ? AND estudiante_id = ?";
    $checkStmt = $conexion->prepare($checkSQL);
    $checkStmt->bind_param("ii", $evaluacionId, $estudianteId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    
    if ($exists) {
        // Actualizar calificación existente
        $sql = "UPDATE calificaciones SET calificacion = ?, observaciones = ? WHERE evaluacion_id = ? AND estudiante_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("dsii", $calificacion, $observaciones, $evaluacionId, $estudianteId);
    } else {
        // Insertar nueva calificación
        $sql = "INSERT INTO calificaciones (evaluacion_id, estudiante_id, calificacion, observaciones) VALUES (?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iids", $evaluacionId, $estudianteId, $calificacion, $observaciones);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Calificación actualizada exitosamente';
    } else {
        $_SESSION['error'] = 'Error al actualizar la calificación';
    }
}

function handleDeleteEvaluation() {
    global $conexion;
    
    $evaluacionId = $_POST['evaluacion_id'] ?? 0;
    
    $sql = "DELETE FROM evaluaciones WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $evaluacionId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Evaluación eliminada exitosamente';
    } else {
        $_SESSION['error'] = 'Error al eliminar la evaluación';
    }
}

// Crear tablas necesarias
$createTablesSQL = [
    "CREATE TABLE IF NOT EXISTS evaluaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        grupo_id INT NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        tipo ENUM('quiz', 'examen', 'tarea', 'proyecto', 'participacion', 'otro') NOT NULL,
        fecha_limite DATETIME,
        valor_maximo DECIMAL(4,2) DEFAULT 5.00,
        peso DECIMAL(3,2) DEFAULT 1.00,
        activa TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS calificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evaluacion_id INT NOT NULL,
        estudiante_id INT NOT NULL,
        calificacion DECIMAL(4,2),
        observaciones TEXT,
        fecha_calificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE CASCADE,
        FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_grade (evaluacion_id, estudiante_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($createTablesSQL as $sql) {
    $conexion->query($sql);
}

// Obtener filtros
$filtroGrupo = $_GET['grupo'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

// Obtener grupos del docente
$sql = "SELECT * FROM grupos WHERE docente_id = ? AND activo = 1 ORDER BY nombre";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$grupos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener evaluaciones con filtros
$sql = "SELECT e.*, g.nombre as grupo_nombre, g.grado,
               COUNT(DISTINCT eg.estudiante_id) as total_estudiantes,
               COUNT(DISTINCT c.id) as calificaciones_registradas,
               AVG(c.calificacion) as promedio_calificaciones
        FROM evaluaciones e
        LEFT JOIN grupos g ON e.grupo_id = g.id
        LEFT JOIN estudiantes_grupos eg ON g.id = eg.grupo_id
        LEFT JOIN calificaciones c ON e.id = c.evaluacion_id
        WHERE e.docente_id = ?";

$params = [$userId];
$types = "i";

if (!empty($filtroGrupo)) {
    $sql .= " AND e.grupo_id = ?";
    $params[] = $filtroGrupo;
    $types .= "i";
}

if (!empty($filtroTipo)) {
    $sql .= " AND e.tipo = ?";
    $params[] = $filtroTipo;
    $types .= "s";
}

if (!empty($filtroEstado)) {
    if ($filtroEstado === 'activa') {
        $sql .= " AND e.activa = 1";
    } elseif ($filtroEstado === 'vencida') {
        $sql .= " AND e.fecha_limite < NOW()";
    } elseif ($filtroEstado === 'pendiente') {
        $sql .= " AND e.fecha_limite > NOW()";
    }
}

$sql .= " GROUP BY e.id ORDER BY e.fecha_creacion DESC";

$stmt = $conexion->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$evaluaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Estadísticas generales
$statsSQL = "SELECT 
    COUNT(DISTINCT e.id) as total_evaluaciones,
    COUNT(DISTINCT c.id) as total_calificaciones,
    AVG(c.calificacion) as promedio_general,
    COUNT(DISTINCT CASE WHEN e.fecha_limite > NOW() THEN e.id END) as evaluaciones_pendientes
    FROM evaluaciones e
    LEFT JOIN calificaciones c ON e.id = c.evaluacion_id
    WHERE e.docente_id = ?";
$stmt = $conexion->prepare($statsSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
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
            <li class="active">
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
            <li>
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
            <h1><i class="fas fa-star"></i> Gestión de Calificaciones</h1>
            <p>Administra evaluaciones, calificaciones y seguimiento académico</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_evaluaciones'] ?? 0 ?></div>
                    <div class="stat-label">Total Evaluaciones</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_calificaciones'] ?? 0 ?></div>
                    <div class="stat-label">Calificaciones Registradas</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['promedio_general'] ?? 0, 1) ?></div>
                    <div class="stat-label">Promedio General</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['evaluaciones_pendientes'] ?? 0 ?></div>
                    <div class="stat-label">Evaluaciones Pendientes</div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="actions-bar">
            <button class="btn btn-primary" onclick="openModal('createEvaluationModal')">
                <i class="fas fa-plus"></i> Nueva Evaluación
            </button>
            <button class="btn btn-success" onclick="openModal('bulkGradeModal')">
                <i class="fas fa-edit"></i> Calificación Masiva
            </button>
            <button class="btn btn-info" onclick="exportGrades()">
                <i class="fas fa-download"></i> Exportar Calificaciones
            </button>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="grupo">Grupo:</label>
                    <select name="grupo" id="grupo" class="form-control">
                        <option value="">Todos los grupos</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= $grupo['id'] ?>" <?= $filtroGrupo == $grupo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grupo['nombre']) ?> (<?= $grupo['grado'] ?>°)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="tipo">Tipo de Evaluación:</label>
                    <select name="tipo" id="tipo" class="form-control">
                        <option value="">Todos los tipos</option>
                        <option value="quiz" <?= $filtroTipo === 'quiz' ? 'selected' : '' ?>>Quiz</option>
                        <option value="examen" <?= $filtroTipo === 'examen' ? 'selected' : '' ?>>Examen</option>
                        <option value="tarea" <?= $filtroTipo === 'tarea' ? 'selected' : '' ?>>Tarea</option>
                        <option value="proyecto" <?= $filtroTipo === 'proyecto' ? 'selected' : '' ?>>Proyecto</option>
                        <option value="participacion" <?= $filtroTipo === 'participacion' ? 'selected' : '' ?>>Participación</option>
                        <option value="otro" <?= $filtroTipo === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="estado">Estado:</label>
                    <select name="estado" id="estado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="activa" <?= $filtroEstado === 'activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="vencida" <?= $filtroEstado === 'vencida' ? 'selected' : '' ?>>Vencida</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="calificaciones.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de Evaluaciones -->
        <div class="evaluations-section">
            <h3><i class="fas fa-list"></i> Evaluaciones</h3>
            
            <?php if (empty($evaluaciones)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h4>No hay evaluaciones</h4>
                    <p>Crea tu primera evaluación para comenzar a calificar</p>
                    <button class="btn btn-primary" onclick="openModal('createEvaluationModal')">
                        <i class="fas fa-plus"></i> Crear Evaluación
                    </button>
                </div>
            <?php else: ?>
                <div class="evaluations-grid">
                    <?php foreach ($evaluaciones as $evaluacion): ?>
                        <?php 
                        $porcentajeCalificado = $evaluacion['total_estudiantes'] > 0 
                            ? round(($evaluacion['calificaciones_registradas'] / $evaluacion['total_estudiantes']) * 100) 
                            : 0;
                        $estadoEvaluacion = '';
                        if ($evaluacion['fecha_limite'] && strtotime($evaluacion['fecha_limite']) < time()) {
                            $estadoEvaluacion = 'vencida';
                        } elseif ($evaluacion['fecha_limite'] && strtotime($evaluacion['fecha_limite']) > time()) {
                            $estadoEvaluacion = 'pendiente';
                        } else {
                            $estadoEvaluacion = 'activa';
                        }
                        ?>
                        <div class="evaluation-card <?= $estadoEvaluacion ?>">
                            <div class="evaluation-header">
                                <div class="evaluation-info">
                                    <h4><?= htmlspecialchars($evaluacion['nombre']) ?></h4>
                                    <p class="evaluation-group"><?= htmlspecialchars($evaluacion['grupo_nombre']) ?> (<?= $evaluacion['grado'] ?>°)</p>
                                    <span class="evaluation-type type-<?= $evaluacion['tipo'] ?>">
                                        <?= ucfirst($evaluacion['tipo']) ?>
                                    </span>
                                </div>
                                <div class="evaluation-status">
                                    <span class="status-badge status-<?= $estadoEvaluacion ?>">
                                        <?= ucfirst($estadoEvaluacion) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="evaluation-details">
                                <?php if ($evaluacion['descripcion']): ?>
                                    <p class="evaluation-description"><?= htmlspecialchars($evaluacion['descripcion']) ?></p>
                                <?php endif; ?>
                                
                                <div class="evaluation-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Fecha límite: <?= $evaluacion['fecha_limite'] ? date('d/m/Y H:i', strtotime($evaluacion['fecha_limite'])) : 'Sin límite' ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-star"></i>
                                        <span>Valor máximo: <?= $evaluacion['valor_maximo'] ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-weight-hanging"></i>
                                        <span>Peso: <?= $evaluacion['peso'] ?>%</span>
                                    </div>
                                </div>
                                
                                <div class="progress-section">
                                    <div class="progress-info">
                                        <span>Progreso de calificación: <?= $porcentajeCalificado ?>%</span>
                                        <span><?= $evaluacion['calificaciones_registradas'] ?>/<?= $evaluacion['total_estudiantes'] ?> estudiantes</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $porcentajeCalificado ?>%"></div>
                                    </div>
                                </div>
                                
                                <?php if ($evaluacion['promedio_calificaciones']): ?>
                                    <div class="average-grade">
                                        <i class="fas fa-chart-line"></i>
                                        <span>Promedio: <?= number_format($evaluacion['promedio_calificaciones'], 1) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evaluation-actions">
                                <button class="btn btn-primary btn-sm" 
                                        onclick="gradeEvaluation(<?= $evaluacion['id'] ?>, '<?= htmlspecialchars($evaluacion['nombre']) ?>')">
                                    <i class="fas fa-edit"></i> Calificar
                                </button>
                                
                                <button class="btn btn-info btn-sm" 
                                        onclick="viewGrades(<?= $evaluacion['id'] ?>)">
                                    <i class="fas fa-eye"></i> Ver Calificaciones
                                </button>
                                
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="editEvaluation(<?= $evaluacion['id'] ?>)">
                                    <i class="fas fa-cog"></i> Editar
                                </button>
                                
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('¿Eliminar esta evaluación y todas sus calificaciones?')">
                                    <input type="hidden" name="action" value="delete_evaluation">
                                    <input type="hidden" name="evaluacion_id" value="<?= $evaluacion['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal Crear Evaluación -->
<div id="createEvaluationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Crear Nueva Evaluación</h3>
            <button class="close-modal" onclick="closeModal('createEvaluationModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_evaluation">
            <div class="modal-body">
                <div class="form-group">
                    <label for="nombre_evaluacion">Nombre de la Evaluación:</label>
                    <input type="text" name="nombre_evaluacion" id="nombre_evaluacion" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="grupo_evaluacion">Grupo:</label>
                    <select name="grupo_id" id="grupo_evaluacion" class="form-control" required>
                        <option value="">Seleccionar grupo</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= $grupo['id'] ?>">
                                <?= htmlspecialchars($grupo['nombre']) ?> (<?= $grupo['grado'] ?>°)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="tipo_evaluacion">Tipo de Evaluación:</label>
                    <select name="tipo_evaluacion" id="tipo_evaluacion" class="form-control" required>
                        <option value="">Seleccionar tipo</option>
                        <option value="quiz">Quiz</option>
                        <option value="examen">Examen</option>
                        <option value="tarea">Tarea</option>
                        <option value="proyecto">Proyecto</option>
                        <option value="participacion">Participación</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="valor_maximo">Valor Máximo:</label>
                        <input type="number" name="valor_maximo" id="valor_maximo" class="form-control" 
                               min="1" max="10" step="0.1" value="5.0">
                    </div>
                    
                    <div class="form-group">
                        <label for="peso">Peso (%):</label>
                        <input type="number" name="peso" id="peso" class="form-control" 
                               min="0.1" max="100" step="0.1" value="1.0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="fecha_limite">Fecha Límite (opcional):</label>
                    <input type="datetime-local" name="fecha_limite" id="fecha_limite" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="descripcion_evaluacion">Descripción (opcional):</label>
                    <textarea name="descripcion" id="descripcion_evaluacion" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createEvaluationModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Evaluación</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Calificar -->
<div id="gradeModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Calificar Evaluación</h3>
            <button class="close-modal" onclick="closeModal('gradeModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="gradeContent">
                <!-- Contenido dinámico de calificación -->
            </div>
        </div>
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

.actions-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.filters-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.filters-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-dark);
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid rgba(201, 126, 40, 0.2);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.evaluations-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.evaluations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.evaluation-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.evaluation-card.vencida {
    border-left-color: #dc3545;
}

.evaluation-card.pendiente {
    border-left-color: #ffc107;
}

.evaluation-card:hover {
    transform: translateY(-3px);
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.evaluation-info h4 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.evaluation-group {
    margin: 0 0 10px 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.evaluation-type {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.type-quiz { background: rgba(0, 123, 255, 0.1); color: #007bff; }
.type-examen { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.type-tarea { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.type-proyecto { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.type-participacion { background: rgba(102, 16, 242, 0.1); color: #6610f2; }
.type-otro { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-activa { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.status-pendiente { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.status-vencida { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

.evaluation-description {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 15px;
    line-height: 1.4;
}

.evaluation-meta {
    margin-bottom: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.meta-item i {
    width: 16px;
    color: var(--primary-color);
}

.progress-section {
    margin-bottom: 15px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.progress-bar {
    height: 8px;
    background: rgba(201, 126, 40, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--primary-color);
    transition: width 0.3s ease;
}

.average-grade {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-color);
    font-weight: 500;
    margin-bottom: 15px;
}

.evaluation-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

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

.modal-large {
    max-width: 800px;
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
    
    .evaluations-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .actions-bar {
        flex-direction: column;
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

function gradeEvaluation(evaluationId, evaluationName) {
    // Cargar interfaz de calificación
    fetch(`grade_evaluation.php?id=${evaluationId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('gradeContent').innerHTML = html;
            openModal('gradeModal');
        })
        .catch(error => {
            alert('Error al cargar la interfaz de calificación');
        });
}

function viewGrades(evaluationId) {
    // Implementar vista de calificaciones
    window.location.href = `view_grades.php?evaluation_id=${evaluationId}`;
}

function editEvaluation(evaluationId) {
    // Implementar edición de evaluación
    alert('Funcionalidad de edición en desarrollo');
}

function exportGrades() {
    // Implementar exportación de calificaciones
    alert('Funcionalidad de exportación en desarrollo');
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
</script>

</body>
</html>
