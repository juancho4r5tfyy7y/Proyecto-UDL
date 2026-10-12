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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_resource':
                createResource();
                break;
            case 'update_resource':
                updateResource();
                break;
            case 'delete_resource':
                deleteResource();
                break;
            case 'create_category':
                createCategory();
                break;
            case 'share_resource':
                shareResource();
                break;
        }
    }
}

function createResource() {
    global $conexion, $userId;
    
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $grado = $_POST['grado'] ?? '';
    $contenido = $_POST['contenido'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $publico = isset($_POST['publico']) ? 1 : 0;
    
    // Crear tabla si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS recursos_educativos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        descripcion TEXT,
        categoria ENUM('lectura', 'escritura', 'gramatica', 'literatura', 'evaluacion', 'juegos', 'multimedia', 'otros') NOT NULL,
        tipo ENUM('documento', 'presentacion', 'video', 'audio', 'imagen', 'enlace', 'actividad', 'evaluacion') NOT NULL,
        grado VARCHAR(10),
        contenido LONGTEXT,
        archivo_url VARCHAR(500),
        tags VARCHAR(500),
        publico BOOLEAN DEFAULT 0,
        descargas INT DEFAULT 0,
        valoracion DECIMAL(3,2) DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    // Manejar archivo si se subió
    $archivoUrl = '';
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/recursos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . $_FILES['archivo']['name'];
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $uploadPath)) {
            $archivoUrl = 'uploads/recursos/' . $fileName;
        }
    }
    
    $sql = "INSERT INTO recursos_educativos (docente_id, titulo, descripcion, categoria, tipo, grado, contenido, archivo_url, tags, publico) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("issssssssi", $userId, $titulo, $descripcion, $categoria, $tipo, $grado, $contenido, $archivoUrl, $tags, $publico);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Recurso educativo creado exitosamente';
    } else {
        $_SESSION['error'] = 'Error al crear el recurso educativo';
    }
}

function updateResource() {
    global $conexion, $userId;
    
    $id = $_POST['resource_id'] ?? 0;
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $grado = $_POST['grado'] ?? '';
    $contenido = $_POST['contenido'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $publico = isset($_POST['publico']) ? 1 : 0;
    
    $sql = "UPDATE recursos_educativos SET titulo = ?, descripcion = ?, categoria = ?, tipo = ?, grado = ?, contenido = ?, tags = ?, publico = ? 
            WHERE id = ? AND docente_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssssssssii", $titulo, $descripcion, $categoria, $tipo, $grado, $contenido, $tags, $publico, $id, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Recurso educativo actualizado exitosamente';
    } else {
        $_SESSION['error'] = 'Error al actualizar el recurso educativo';
    }
}

function deleteResource() {
    global $conexion, $userId;
    
    $id = $_POST['resource_id'] ?? 0;
    
    // Obtener información del archivo para eliminarlo
    $sql = "SELECT archivo_url FROM recursos_educativos WHERE id = ? AND docente_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && !empty($result['archivo_url'])) {
        $filePath = '../../' . $result['archivo_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    $sql = "DELETE FROM recursos_educativos WHERE id = ? AND docente_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Recurso educativo eliminado exitosamente';
    } else {
        $_SESSION['error'] = 'Error al eliminar el recurso educativo';
    }
}

// Obtener filtros
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroGrado = $_GET['grado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';
$ordenar = $_GET['ordenar'] ?? 'fecha_creacion';
$direccion = $_GET['direccion'] ?? 'DESC';

// Obtener estadísticas
$statsSQL = "SELECT 
    COUNT(*) as total_recursos,
    COUNT(CASE WHEN publico = 1 THEN 1 END) as recursos_publicos,
    SUM(descargas) as total_descargas,
    AVG(valoracion) as valoracion_promedio
    FROM recursos_educativos 
    WHERE docente_id = ?";
$stmt = $conexion->prepare($statsSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Construir consulta de recursos
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM recursos_educativos WHERE categoria = r.categoria AND docente_id = ?) as categoria_count
        FROM recursos_educativos r 
        WHERE r.docente_id = ?";

$params = [$userId, $userId];
$types = "ii";

if (!empty($filtroCategoria)) {
    $sql .= " AND r.categoria = ?";
    $params[] = $filtroCategoria;
    $types .= "s";
}

if (!empty($filtroTipo)) {
    $sql .= " AND r.tipo = ?";
    $params[] = $filtroTipo;
    $types .= "s";
}

if (!empty($filtroGrado)) {
    $sql .= " AND r.grado = ?";
    $params[] = $filtroGrado;
    $types .= "s";
}

if (!empty($busqueda)) {
    $sql .= " AND (r.titulo LIKE ? OR r.descripcion LIKE ? OR r.tags LIKE ?)";
    $searchTerm = "%$busqueda%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

$sql .= " ORDER BY r.$ordenar $direccion LIMIT 20";

$stmt = $conexion->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$recursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener categorías con conteos
$categoriasSQL = "SELECT categoria, COUNT(*) as total 
                FROM recursos_educativos 
                WHERE docente_id = ? 
                GROUP BY categoria 
                ORDER BY total DESC";
$stmt = $conexion->prepare($categoriasSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener recursos más populares
$popularesSQL = "SELECT titulo, descargas, valoracion 
                FROM recursos_educativos 
                WHERE docente_id = ? AND descargas > 0 
                ORDER BY descargas DESC, valoracion DESC 
                LIMIT 5";
$stmt = $conexion->prepare($popularesSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recursosPopulares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
            <li>
                <a href="reportes.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li class="active">
                <a href="material_didactico.php">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Recursos Educativos</span>
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
            <h1><i class="fas fa-graduation-cap"></i> Recursos Educativos</h1>
            <p>Crea, organiza y comparte recursos educativos para enriquecer el aprendizaje</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_recursos'] ?? 0 ?></div>
                    <div class="stat-label">Total Recursos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['recursos_publicos'] ?? 0 ?></div>
                    <div class="stat-label">Recursos Públicos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_descargas'] ?? 0 ?></div>
                    <div class="stat-label">Total Descargas</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($stats['valoracion_promedio'] ?? 0, 1) ?></div>
                    <div class="stat-label">Valoración Promedio</div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="quick-actions">
            <button class="btn btn-primary" onclick="openModal('createResourceModal')">
                <i class="fas fa-plus"></i> Crear Recurso
            </button>
            <button class="btn btn-success" onclick="openModal('createCategoryModal')">
                <i class="fas fa-folder-plus"></i> Nueva Categoría
            </button>
            <button class="btn btn-info" onclick="openModal('importResourceModal')">
                <i class="fas fa-file-import"></i> Importar Recursos
            </button>
            <button class="btn btn-warning" onclick="exportResources()">
                <i class="fas fa-file-export"></i> Exportar Todo
            </button>
        </div>

        <div class="content-grid">
            <!-- Panel Izquierdo -->
            <div class="left-panel">
                <!-- Filtros y Búsqueda -->
                <div class="filters-section">
                    <h3><i class="fas fa-filter"></i> Filtros y Búsqueda</h3>
                    
                    <form method="GET" class="filters-form">
                        <div class="search-group">
                            <input type="text" name="busqueda" placeholder="Buscar recursos..." 
                                   value="<?= htmlspecialchars($busqueda) ?>" class="form-control">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <label>Categoría:</label>
                            <select name="categoria" class="form-control">
                                <option value="">Todas las categorías</option>
                                <option value="lectura" <?= $filtroCategoria === 'lectura' ? 'selected' : '' ?>>Lectura</option>
                                <option value="escritura" <?= $filtroCategoria === 'escritura' ? 'selected' : '' ?>>Escritura</option>
                                <option value="gramatica" <?= $filtroCategoria === 'gramatica' ? 'selected' : '' ?>>Gramática</option>
                                <option value="literatura" <?= $filtroCategoria === 'literatura' ? 'selected' : '' ?>>Literatura</option>
                                <option value="evaluacion" <?= $filtroCategoria === 'evaluacion' ? 'selected' : '' ?>>Evaluación</option>
                                <option value="juegos" <?= $filtroCategoria === 'juegos' ? 'selected' : '' ?>>Juegos</option>
                                <option value="multimedia" <?= $filtroCategoria === 'multimedia' ? 'selected' : '' ?>>Multimedia</option>
                                <option value="otros" <?= $filtroCategoria === 'otros' ? 'selected' : '' ?>>Otros</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Tipo:</label>
                            <select name="tipo" class="form-control">
                                <option value="">Todos los tipos</option>
                                <option value="documento" <?= $filtroTipo === 'documento' ? 'selected' : '' ?>>Documento</option>
                                <option value="presentacion" <?= $filtroTipo === 'presentacion' ? 'selected' : '' ?>>Presentación</option>
                                <option value="video" <?= $filtroTipo === 'video' ? 'selected' : '' ?>>Video</option>
                                <option value="audio" <?= $filtroTipo === 'audio' ? 'selected' : '' ?>>Audio</option>
                                <option value="imagen" <?= $filtroTipo === 'imagen' ? 'selected' : '' ?>>Imagen</option>
                                <option value="enlace" <?= $filtroTipo === 'enlace' ? 'selected' : '' ?>>Enlace</option>
                                <option value="actividad" <?= $filtroTipo === 'actividad' ? 'selected' : '' ?>>Actividad</option>
                                <option value="evaluacion" <?= $filtroTipo === 'evaluacion' ? 'selected' : '' ?>>Evaluación</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Grado:</label>
                            <select name="grado" class="form-control">
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
                            <label>Ordenar por:</label>
                            <select name="ordenar" class="form-control">
                                <option value="fecha_creacion" <?= $ordenar === 'fecha_creacion' ? 'selected' : '' ?>>Fecha de creación</option>
                                <option value="titulo" <?= $ordenar === 'titulo' ? 'selected' : '' ?>>Título</option>
                                <option value="descargas" <?= $ordenar === 'descargas' ? 'selected' : '' ?>>Descargas</option>
                                <option value="valoracion" <?= $ordenar === 'valoracion' ? 'selected' : '' ?>>Valoración</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="material_didactico.php" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </form>
                </div>
                
                <!-- Categorías -->
                <div class="categories-section">
                    <h3><i class="fas fa-folder"></i> Categorías</h3>
                    <?php if (empty($categorias)): ?>
                        <div class="empty-state-small">
                            <p>No hay categorías creadas</p>
                        </div>
                    <?php else: ?>
                        <div class="categories-list">
                            <?php foreach ($categorias as $categoria): ?>
                                <div class="category-item">
                                    <a href="?categoria=<?= $categoria['categoria'] ?>" 
                                       class="category-link <?= $filtroCategoria === $categoria['categoria'] ? 'active' : '' ?>">
                                        <span class="category-name"><?= ucfirst($categoria['categoria']) ?></span>
                                        <span class="category-count"><?= $categoria['total'] ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recursos Populares -->
                <div class="popular-section">
                    <h3><i class="fas fa-fire"></i> Más Populares</h3>
                    <?php if (empty($recursosPopulares)): ?>
                        <div class="empty-state-small">
                            <p>No hay recursos con descargas</p>
                        </div>
                    <?php else: ?>
                        <div class="popular-list">
                            <?php foreach ($recursosPopulares as $popular): ?>
                                <div class="popular-item">
                                    <h5><?= htmlspecialchars($popular['titulo']) ?></h5>
                                    <div class="popular-stats">
                                        <span><i class="fas fa-download"></i> <?= $popular['descargas'] ?></span>
                                        <span><i class="fas fa-star"></i> <?= number_format($popular['valoracion'], 1) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panel Principal -->
            <div class="main-panel">
                <div class="resources-header">
                    <h3><i class="fas fa-book-open"></i> Mis Recursos Educativos</h3>
                    <div class="view-options">
                        <button class="view-btn active" data-view="grid">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" data-view="list">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
                
                <?php if (empty($recursos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h4>No hay recursos educativos</h4>
                        <p>Crea tu primer recurso educativo para comenzar a enriquecer el aprendizaje</p>
                        <button class="btn btn-primary" onclick="openModal('createResourceModal')">
                            <i class="fas fa-plus"></i> Crear Primer Recurso
                        </button>
                    </div>
                <?php else: ?>
                    <div class="resources-grid" id="resourcesContainer">
                        <?php foreach ($recursos as $recurso): ?>
                            <div class="resource-card">
                                <div class="resource-header">
                                    <div class="resource-type">
                                        <i class="fas fa-<?= getResourceIcon($recurso['tipo']) ?>"></i>
                                        <span class="type-badge type-<?= $recurso['tipo'] ?>">
                                            <?= ucfirst($recurso['tipo']) ?>
                                        </span>
                                    </div>
                                    <div class="resource-actions">
                                        <button class="btn-icon" onclick="editResource(<?= $recurso['id'] ?>)" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon" onclick="shareResource(<?= $recurso['id'] ?>)" title="Compartir">
                                            <i class="fas fa-share"></i>
                                        </button>
                                        <button class="btn-icon btn-danger" onclick="deleteResource(<?= $recurso['id'] ?>)" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="resource-content">
                                    <h4><?= htmlspecialchars($recurso['titulo']) ?></h4>
                                    <p class="resource-description"><?= htmlspecialchars(substr($recurso['descripcion'], 0, 100)) ?>...</p>
                                    
                                    <div class="resource-meta">
                                        <span class="category-badge category-<?= $recurso['categoria'] ?>">
                                            <?= ucfirst($recurso['categoria']) ?>
                                        </span>
                                        <?php if (!empty($recurso['grado'])): ?>
                                            <span class="grade-badge">Grado <?= $recurso['grado'] ?>°</span>
                                        <?php endif; ?>
                                        <?php if ($recurso['publico']): ?>
                                            <span class="public-badge">
                                                <i class="fas fa-globe"></i> Público
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($recurso['tags'])): ?>
                                        <div class="resource-tags">
                                            <?php 
                                            $tags = explode(',', $recurso['tags']);
                                            foreach (array_slice($tags, 0, 3) as $tag): 
                                            ?>
                                                <span class="tag">#<?= trim($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="resource-footer">
                                    <div class="resource-stats">
                                        <span><i class="fas fa-download"></i> <?= $recurso['descargas'] ?></span>
                                        <span><i class="fas fa-star"></i> <?= number_format($recurso['valoracion'], 1) ?></span>
                                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($recurso['fecha_creacion'])) ?></span>
                                    </div>
                                    
                                    <div class="resource-buttons">
                                        <?php if (!empty($recurso['archivo_url'])): ?>
                                            <a href="../../<?= $recurso['archivo_url'] ?>" class="btn btn-sm btn-primary" target="_blank">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-success" onclick="downloadResource(<?= $recurso['id'] ?>)">
                                            <i class="fas fa-download"></i> Descargar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal Crear Recurso -->
<div id="createResourceModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Crear Nuevo Recurso Educativo</h3>
            <button class="close-modal" onclick="closeModal('createResourceModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_resource">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="titulo">Título del Recurso:</label>
                        <input type="text" name="titulo" id="titulo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="categoria">Categoría:</label>
                        <select name="categoria" id="categoria" class="form-control" required>
                            <option value="">Seleccionar categoría</option>
                            <option value="lectura">Lectura</option>
                            <option value="escritura">Escritura</option>
                            <option value="gramatica">Gramática</option>
                            <option value="literatura">Literatura</option>
                            <option value="evaluacion">Evaluación</option>
                            <option value="juegos">Juegos</option>
                            <option value="multimedia">Multimedia</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo">Tipo de Recurso:</label>
                        <select name="tipo" id="tipo" class="form-control" required>
                            <option value="">Seleccionar tipo</option>
                            <option value="documento">Documento</option>
                            <option value="presentacion">Presentación</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="imagen">Imagen</option>
                            <option value="enlace">Enlace</option>
                            <option value="actividad">Actividad</option>
                            <option value="evaluacion">Evaluación</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="grado">Grado:</label>
                        <select name="grado" id="grado" class="form-control">
                            <option value="">Seleccionar grado</option>
                            <option value="6">6°</option>
                            <option value="7">7°</option>
                            <option value="8">8°</option>
                            <option value="9">9°</option>
                            <option value="10">10°</option>
                            <option value="11">11°</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contenido">Contenido del Recurso:</label>
                    <textarea name="contenido" id="contenido" class="form-control" rows="6" 
                              placeholder="Describe el contenido detallado del recurso educativo..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="archivo">Archivo (opcional):</label>
                    <input type="file" name="archivo" id="archivo" class="form-control" 
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.mp3,.zip">
                    <small class="form-text">Formatos permitidos: PDF, DOC, PPT, imágenes, videos, audio, ZIP</small>
                </div>
                
                <div class="form-group">
                    <label for="tags">Etiquetas (separadas por comas):</label>
                    <input type="text" name="tags" id="tags" class="form-control" 
                           placeholder="lectura, comprensión, básico, interactivo">
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="publico" id="publico">
                        <span class="checkmark"></span>
                        Hacer público (otros docentes pueden ver y descargar)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createResourceModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Recurso</button>
            </div>
        </form>
    </div>
</div>

<?php
function getResourceIcon($tipo) {
    $icons = [
        'documento' => 'file-alt',
        'presentacion' => 'file-powerpoint',
        'video' => 'play-circle',
        'audio' => 'volume-up',
        'imagen' => 'image',
        'enlace' => 'link',
        'actividad' => 'tasks',
        'evaluacion' => 'clipboard-check'
    ];
    return $icons[$tipo] ?? 'file';
}
?>

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

.quick-actions {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.content-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
}

.left-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.filters-section,
.categories-section,
.popular-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.filters-section h3,
.categories-section h3,
.popular-section h3 {
    margin: 0 0 15px 0;
    color: var(--text-dark);
    font-size: 1.1rem;
}

.search-group {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.search-group input {
    flex: 1;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--text-dark);
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.categories-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.category-item {
    border-radius: 8px;
    overflow: hidden;
}

.category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.3s ease;
    border-radius: 8px;
}

.category-link:hover,
.category-link.active {
    background: rgba(201, 126, 40, 0.1);
    color: var(--primary-color);
}

.category-count {
    background: rgba(201, 126, 40, 0.2);
    color: var(--primary-color);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.popular-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.popular-item {
    padding: 12px;
    background: rgba(201, 126, 40, 0.05);
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
}

.popular-item h5 {
    margin: 0 0 8px 0;
    color: var(--text-dark);
    font-size: 0.9rem;
}

.popular-stats {
    display: flex;
    gap: 15px;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.main-panel {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.resources-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(201, 126, 40, 0.1);
}

.resources-header h3 {
    margin: 0;
    color: var(--text-dark);
}

.view-options {
    display: flex;
    gap: 5px;
}

.view-btn {
    padding: 8px 12px;
    border: 2px solid rgba(201, 126, 40, 0.2);
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    color: var(--text-muted);
    transition: all 0.3s ease;
}

.view-btn.active,
.view-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: rgba(201, 126, 40, 0.1);
}

.resources-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.resource-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.resource-card:hover {
    transform: translateY(-3px);
}

.resource-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.resource-type {
    display: flex;
    align-items: center;
    gap: 10px;
}

.resource-type i {
    font-size: 1.2rem;
    color: var(--primary-color);
}

.type-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.type-documento { background: rgba(0, 123, 255, 0.1); color: #007bff; }
.type-presentacion { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.type-video { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.type-audio { background: rgba(102, 16, 242, 0.1); color: #6610f2; }
.type-imagen { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.type-enlace { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
.type-actividad { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.type-evaluacion { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

.resource-actions {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 50%;
    background: rgba(201, 126, 40, 0.1);
    color: var(--primary-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: var(--primary-color);
    color: white;
}

.btn-icon.btn-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.btn-icon.btn-danger:hover {
    background: #dc3545;
    color: white;
}

.resource-content h4 {
    margin: 0 0 10px 0;
    color: var(--text-dark);
}

.resource-description {
    color: var(--text-muted);
    margin-bottom: 15px;
    line-height: 1.5;
}

.resource-meta {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.category-badge,
.grade-badge,
.public-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.category-lectura { background: rgba(0, 123, 255, 0.1); color: #007bff; }
.category-escritura { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.category-gramatica { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.category-literatura { background: rgba(102, 16, 242, 0.1); color: #6610f2; }
.category-evaluacion { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.category-juegos { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
.category-multimedia { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.category-otros { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

.grade-badge {
    background: rgba(201, 126, 40, 0.1);
    color: var(--primary-color);
}

.public-badge {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.resource-tags {
    display: flex;
    gap: 5px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.tag {
    background: rgba(201, 126, 40, 0.1);
    color: var(--primary-color);
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.7rem;
}

.resource-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid rgba(201, 126, 40, 0.1);
}

.resource-stats {
    display: flex;
    gap: 15px;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.resource-buttons {
    display: flex;
    gap: 8px;
}

.form-control {
    padding: 10px 12px;
    border: 2px solid rgba(201, 126, 40, 0.2);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
    width: 100%;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

.btn {
    padding: 10px 16px;
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

.empty-state-small {
    text-align: center;
    padding: 20px;
    color: var(--text-muted);
    font-size: 0.9rem;
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
    overflow-y: auto;
    padding: 20px 0;
}

.modal-content {
    background: white;
    margin: 0 auto;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.modal-large {
    max-width: 800px;
}

.modal-header {
    flex-shrink: 0;
}

.modal-footer {
    flex-shrink: 0;
    margin-top: auto;
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

.form-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 5px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .left-panel {
        order: 2;
    }
    
    .main-panel {
        order: 1;
    }
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

    .resources-grid {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        flex-direction: column;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .resource-footer {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }

    /* Mejoras para modal en móviles */
    .modal-content {
        width: 95%;
        max-height: calc(100vh - 20px);
        margin: 10px auto;
    }

    .modal-body {
        padding: 15px;
        max-height: calc(100vh - 140px);
        overflow-y: auto;
    }

    .modal-header,
    .modal-footer {
        padding: 15px;
    }

    .form-group {
        margin-bottom: 15px;
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

function editResource(resourceId) {
    // Implementar edición de recurso
    console.log('Editar recurso:', resourceId);
}

function shareResource(resourceId) {
    // Implementar compartir recurso
    console.log('Compartir recurso:', resourceId);
}

function deleteResource(resourceId) {
    if (confirm('¿Estás seguro de que deseas eliminar este recurso?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_resource">
            <input type="hidden" name="resource_id" value="${resourceId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function downloadResource(resourceId) {
    window.location.href = `download_resource.php?id=${resourceId}`;
}

function exportResources() {
    window.location.href = 'export_resources.php';
}

// Cambiar vista
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-btn');
    const resourcesContainer = document.getElementById('resourcesContainer');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            viewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const view = this.dataset.view;
            if (view === 'list') {
                resourcesContainer.classList.add('list-view');
            } else {
                resourcesContainer.classList.remove('list-view');
            }
        });
    });
});

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

// Sugerir categoría basada en el tipo
document.getElementById('tipo').addEventListener('change', function() {
    const categoria = document.getElementById('categoria');
    const suggestions = {
        'documento': 'lectura',
        'presentacion': 'multimedia',
        'video': 'multimedia',
        'audio': 'multimedia',
        'imagen': 'multimedia',
        'enlace': 'otros',
        'actividad': 'escritura',
        'evaluacion': 'evaluacion'
    };
    
    if (suggestions[this.value] && !categoria.value) {
        categoria.value = suggestions[this.value];
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
