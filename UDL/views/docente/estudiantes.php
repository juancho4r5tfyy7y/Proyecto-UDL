<?php
session_start();
require_once '../../config/database.php';
$additionalCSS = ['Dashboard.css'];
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
            case 'create_group':
                handleCreateGroup();
                break;
            case 'assign_student':
                handleAssignStudent();
                break;
            case 'remove_student':
                handleRemoveStudent();
                break;
            case 'update_student_grade':
                handleUpdateStudentGrade();
                break;
        }
    }
}

function handleCreateGroup() {
    global $conexion, $userId;
    
    $nombre = $_POST['nombre_grupo'] ?? '';
    $grado = $_POST['grado'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    if (empty($nombre) || empty($grado)) {
        $_SESSION['error'] = 'Nombre del grupo y grado son obligatorios';
        return;
    }
    
    // Crear tabla grupos si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        grado ENUM('6','7','8','9','10','11') NOT NULL,
        descripcion TEXT,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    $sql = "INSERT INTO grupos (docente_id, nombre, grado, descripcion) VALUES (?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isss", $userId, $nombre, $grado, $descripcion);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Grupo creado exitosamente';
    } else {
        $_SESSION['error'] = 'Error al crear el grupo';
    }
}

function handleAssignStudent() {
    global $conexion;
    
    $grupoId = $_POST['grupo_id'] ?? 0;
    $estudianteId = $_POST['estudiante_id'] ?? 0;
    
    // Crear tabla estudiantes_grupos si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS estudiantes_grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo_id INT NOT NULL,
        estudiante_id INT NOT NULL,
        fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
        FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment (grupo_id, estudiante_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    $sql = "INSERT INTO estudiantes_grupos (grupo_id, estudiante_id) VALUES (?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $grupoId, $estudianteId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Estudiante asignado al grupo exitosamente';
    } else {
        $_SESSION['error'] = 'Error al asignar estudiante (puede que ya esté en el grupo)';
    }
}

function handleRemoveStudent() {
    global $conexion;
    
    $grupoId = $_POST['grupo_id'] ?? 0;
    $estudianteId = $_POST['estudiante_id'] ?? 0;
    
    $sql = "DELETE FROM estudiantes_grupos WHERE grupo_id = ? AND estudiante_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $grupoId, $estudianteId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Estudiante removido del grupo exitosamente';
    } else {
        $_SESSION['error'] = 'Error al remover estudiante del grupo';
    }
}

// Crear tablas necesarias
$createTablesSQL = [
    "CREATE TABLE IF NOT EXISTS grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        grado ENUM('6','7','8','9','10','11') NOT NULL,
        descripcion TEXT,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS estudiantes_grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo_id INT NOT NULL,
        estudiante_id INT NOT NULL,
        fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
        FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment (grupo_id, estudiante_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($createTablesSQL as $sql) {
    $conexion->query($sql);
}

// Obtener filtros
$filtroGrado = $_GET['grado'] ?? '';
$filtroBusqueda = $_GET['busqueda'] ?? '';
$filtroGrupo = $_GET['grupo'] ?? '';

// Obtener grupos del docente
$sql = "SELECT * FROM grupos WHERE docente_id = ? AND activo = 1 ORDER BY nombre";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$grupos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener estudiantes con filtros
$sql = "SELECT DISTINCT u.id, u.nombre, u.apellidos, u.email, u.documento, 
               g.grado, eg.grupo_id, gr.nombre as grupo_nombre,
               COUNT(DISTINCT a.id) as total_actividades,
               COUNT(DISTINCT ee.id) as entregas_realizadas
        FROM usuarios u 
        LEFT JOIN grado g ON u.id = g.usuario_id 
        LEFT JOIN estudiantes_grupos eg ON u.id = eg.estudiante_id
        LEFT JOIN grupos gr ON eg.grupo_id = gr.id AND gr.docente_id = ?
        LEFT JOIN actividades a ON gr.id = a.grupo_id
        LEFT JOIN entregas ee ON a.id = ee.actividad_id AND ee.estudiante_id = u.id
        WHERE u.rol = 'estudiante'";

$params = [$userId];
$types = "i";

if (!empty($filtroGrado)) {
    $sql .= " AND g.grado = ?";
    $params[] = $filtroGrado;
    $types .= "s";
}

if (!empty($filtroBusqueda)) {
    $sql .= " AND (u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ? OR u.documento LIKE ?)";
    $searchTerm = "%$filtroBusqueda%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

if (!empty($filtroGrupo)) {
    $sql .= " AND eg.grupo_id = ?";
    $params[] = $filtroGrupo;
    $types .= "i";
}

$sql .= " GROUP BY u.id ORDER BY u.nombre, u.apellidos";

$stmt = $conexion->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$estudiantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener estudiantes no asignados para el modal
$sql = "SELECT u.id, u.nombre, u.apellidos, u.email, g.grado 
        FROM usuarios u 
        LEFT JOIN grado g ON u.id = g.usuario_id 
        WHERE u.rol = 'estudiante' 
        AND u.id NOT IN (
            SELECT DISTINCT eg.estudiante_id 
            FROM estudiantes_grupos eg 
            JOIN grupos gr ON eg.grupo_id = gr.id 
            WHERE gr.docente_id = ?
        )
        ORDER BY u.nombre, u.apellidos";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$estudiantesDisponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Estadísticas
$stats = [
    'total_estudiantes' => count($estudiantes),
    'total_grupos' => count($grupos),
    'estudiantes_activos' => count(array_filter($estudiantes, function($e) { return !empty($e['grupo_id']); })),
    'promedio_por_grupo' => count($grupos) > 0 ? round(count($estudiantes) / count($grupos), 1) : 0
];
?>

<div class="dashboard-container">
    <?php include '../../includes/navigation_docente.php'; ?>

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
            <h1><i class="fas fa-users"></i> Gestión de Estudiantes</h1>
            <p>Administra tus estudiantes, grupos y asignaciones</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_estudiantes'] ?></div>
                    <div class="stat-label">Total Estudiantes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_grupos'] ?></div>
                    <div class="stat-label">Grupos Activos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['estudiantes_activos'] ?></div>
                    <div class="stat-label">Estudiantes Asignados</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['promedio_por_grupo'] ?></div>
                    <div class="stat-label">Promedio por Grupo</div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="actions-bar">
            <button class="btn btn-primary" onclick="openModal('createGroupModal')">
                <i class="fas fa-plus"></i> Crear Grupo
            </button>
            <button class="btn btn-success" onclick="openModal('assignStudentModal')">
                <i class="fas fa-user-plus"></i> Asignar Estudiante
            </button>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="grado">Grado:</label>
                    <select name="grado" id="grado" class="form-control">
                        <option value="">Todos los grados</option>
                        <option value="6" <?= $filtroGrado === '6' ? 'selected' : '' ?>>6°</option>
                        <option value="7" <?= $filtroGrado === '7' ? 'selected' : '' ?>>7°</option>
                        <option value="8" <?= $filtroGrado === '8' ? 'selected' : '' ?>>8°</option>
                        <option value="9" <?= $filtroGrado === '9' ? 'selected' : '' ?>>9°</option>
                        <option value="10" <?= $filtroGrado === '10' ? 'selected' : '' ?>>10°</option>
                        <option value="11" <?= $filtroGrado === '11' ? 'selected' : '' ?>>11°</option>
                    </select>
                </div>
                
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
                    <label for="busqueda">Buscar:</label>
                    <input type="text" name="busqueda" id="busqueda" class="form-control" 
                           placeholder="Nombre, email o documento..." value="<?= htmlspecialchars($filtroBusqueda) ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="estudiantes.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de Estudiantes -->
        <div class="students-section">
            <h3><i class="fas fa-list"></i> Lista de Estudiantes</h3>
            
            <?php if (empty($estudiantes)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <h4>No se encontraron estudiantes</h4>
                    <p>No hay estudiantes que coincidan con los filtros aplicados</p>
                </div>
            <?php else: ?>
                <div class="students-grid">
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-avatar">
                                    <?= strtoupper(substr($estudiante['nombre'], 0, 1)) ?>
                                </div>
                                <div class="student-info">
                                    <h4><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellidos']) ?></h4>
                                    <p class="student-email"><?= htmlspecialchars($estudiante['email']) ?></p>
                                    <p class="student-document">Doc: <?= htmlspecialchars($estudiante['documento']) ?></p>
                                </div>
                            </div>
                            
                            <div class="student-details">
                                <div class="detail-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span>Grado: <?= $estudiante['grado'] ?? 'No asignado' ?>°</span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span>Grupo: <?= htmlspecialchars($estudiante['grupo_nombre'] ?? 'Sin asignar') ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-tasks"></i>
                                    <span>Actividades: <?= $estudiante['total_actividades'] ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Entregas: <?= $estudiante['entregas_realizadas'] ?></span>
                                </div>
                            </div>
                            
                            <div class="student-actions">
                                <?php if (!empty($estudiante['grupo_id'])): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Remover estudiante del grupo?')">
                                        <input type="hidden" name="action" value="remove_student">
                                        <input type="hidden" name="grupo_id" value="<?= $estudiante['grupo_id'] ?>">
                                        <input type="hidden" name="estudiante_id" value="<?= $estudiante['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-user-minus"></i> Remover
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-success btn-sm" 
                                            onclick="assignStudentToGroup(<?= $estudiante['id'] ?>, '<?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellidos']) ?>')">
                                        <i class="fas fa-user-plus"></i> Asignar
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-primary btn-sm" 
                                        onclick="viewStudentDetails(<?= $estudiante['id'] ?>)">
                                    <i class="fas fa-eye"></i> Ver Detalles
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal Crear Grupo -->
<div id="createGroupModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Crear Nuevo Grupo</h3>
            <button class="close-modal" onclick="closeModal('createGroupModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_group">
            <div class="modal-body">
                <div class="form-group">
                    <label for="nombre_grupo">Nombre del Grupo:</label>
                    <input type="text" name="nombre_grupo" id="nombre_grupo" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="grado_grupo">Grado:</label>
                    <select name="grado" id="grado_grupo" class="form-control" required>
                        <option value="">Seleccionar grado</option>
                        <option value="6">6°</option>
                        <option value="7">7°</option>
                        <option value="8">8°</option>
                        <option value="9">9°</option>
                        <option value="10">10°</option>
                        <option value="11">11°</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="descripcion_grupo">Descripción (opcional):</label>
                    <textarea name="descripcion" id="descripcion_grupo" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createGroupModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Grupo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Asignar Estudiante -->
<div id="assignStudentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Asignar Estudiante a Grupo</h3>
            <button class="close-modal" onclick="closeModal('assignStudentModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="assign_student">
            <div class="modal-body">
                <div class="form-group">
                    <label for="grupo_asignar">Seleccionar Grupo:</label>
                    <select name="grupo_id" id="grupo_asignar" class="form-control" required>
                        <option value="">Seleccionar grupo</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= $grupo['id'] ?>">
                                <?= htmlspecialchars($grupo['nombre']) ?> (<?= $grupo['grado'] ?>°)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="estudiante_asignar">Seleccionar Estudiante:</label>
                    <select name="estudiante_id" id="estudiante_asignar" class="form-control" required>
                        <option value="">Seleccionar estudiante</option>
                        <?php foreach ($estudiantesDisponibles as $estudiante): ?>
                            <option value="<?= $estudiante['id'] ?>">
                                <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellidos']) ?> 
                                (<?= $estudiante['grado'] ?? 'Sin grado' ?>°)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignStudentModal')">Cancelar</button>
                <button type="submit" class="btn btn-success">Asignar Estudiante</button>
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

.students-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.student-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.student-card:hover {
    transform: translateY(-3px);
}

.student-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--secondary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.student-info h4 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.student-email {
    margin: 0 0 3px 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.student-document {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.student-details {
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-item i {
    width: 16px;
    color: var(--primary-color);
}

.student-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
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
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .actions-bar {
        flex-direction: column;
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

function assignStudentToGroup(studentId, studentName) {
    document.getElementById('estudiante_asignar').value = studentId;
    openModal('assignStudentModal');
}

function viewStudentDetails(studentId) {
    // Implementar vista de detalles del estudiante
    alert('Funcionalidad de detalles del estudiante en desarrollo');
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
