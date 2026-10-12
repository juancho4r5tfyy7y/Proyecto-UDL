<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/google_api_config.php';

// AJAX handler for Google Drive file saving
if (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['action']) && $_POST['action'] === 'save_google_drive_file') {
    $conexion = conexion();
    $usuarioId = $_SESSION['id'];

    // Obtener el ID de la tabla 'docentes' correspondiente al usuario_id
    $sql_docente_id = "SELECT id FROM docentes WHERE usuario_id = ? LIMIT 1";
    $stmt_docente_id = $conexion->prepare($sql_docente_id);
    $stmt_docente_id->bind_param("i", $usuarioId);
    $stmt_docente_id->execute();
    $result_docente_id = $stmt_docente_id->get_result()->fetch_assoc();
    
    if (!$result_docente_id) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No se encontró el perfil de docente para este usuario.']);
        exit;
    }
    $docenteId = $result_docente_id['id']; // ID de la tabla 'docentes'
    
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $grado = $_POST['grado'] ?? '';
    $carpetaId = !empty($_POST['carpeta_id']) ? (int)$_POST['carpeta_id'] : null;
    $publico = isset($_POST['publico']) ? 1 : 0;
    
    $file_id = $_POST['file_id'] ?? '';
    $file_name = $_POST['file_name'] ?? '';
    $file_url = $_POST['file_url'] ?? '';
    $file_mime = $_POST['file_mime'] ?? '';
    $file_size = $_POST['file_size'] ?? 0;

    if (empty($titulo) || empty($file_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos del archivo de Google Drive.']);
        exit;
    }

    if (empty($categoria)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'La categoría es obligatoria.']);
        exit;
    }

    // Corregido para usar la tabla 'material_didactico' y el ID de docente correcto
    $sql = "INSERT INTO material_didactico
            (docente_id, titulo, descripcion, tipo, enlace_url, archivo_adjunto_url, grado)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    // Asumiendo que los archivos de Drive usan enlace_url y los locales archivo_adjunto_url
    $enlace = $file_url;
    $archivo_adjunto = ''; // La subida local está deshabilitada
    $stmt->bind_param("issssss", $docenteId, $titulo, $descripcion, $categoria, $enlace, $archivo_adjunto, $grado);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Material de Google Drive guardado exitosamente';
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Archivo guardado.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar en la base de datos: ' . $stmt->error]);
    }
    exit;
}


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

// Obtener grados asignados al docente
$sql_grados = "SELECT DISTINCT grado FROM docente_grados WHERE docente_id = ? ORDER BY grado";
$stmt_grados = $conexion->prepare($sql_grados);
$stmt_grados->bind_param("i", $userId);
$stmt_grados->execute();
$grados_asignados = $stmt_grados->get_result()->fetch_all(MYSQLI_ASSOC);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_material':
                handleUploadMaterial();
                break;
            case 'create_folder':
                handleCreateFolder();
                break;
            case 'delete_material':
                handleDeleteMaterial();
                break;
            case 'update_material':
                handleUpdateMaterial();
                break;
            case 'share_material':
                handleShareMaterial();
                break;
        }
    }
}

function handleUploadMaterial() {
    // Deshabilitado para forzar la subida a través de Google Drive.
    $_SESSION['error'] = 'La subida de archivos locales está deshabilitada. Por favor, utiliza la opción de Google Drive.';
    return;

    global $conexion, $userId;
    
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $grado = $_POST['grado'] ?? '';
    $carpetaId = $_POST['carpeta_id'] ?? null;
    $publico = isset($_POST['publico']) ? 1 : 0;
    
    if (empty($titulo) || !isset($_FILES['archivo'])) {
        $_SESSION['error'] = 'Título y archivo son obligatorios';
        return;
    }
    
    $archivo = $_FILES['archivo'];
    
    // Validar archivo
    $allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov', 'zip', 'rar'];
    $fileExtension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        $_SESSION['error'] = 'Tipo de archivo no permitido';
        return;
    }
    
    if ($archivo['size'] > 50 * 1024 * 1024) { // 50MB
        $_SESSION['error'] = 'El archivo es demasiado grande (máximo 50MB)';
        return;
    }
    
    // Crear directorio si no existe
    $uploadDir = '../../uploads/material_didactico/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generar nombre único
    $fileName = uniqid() . '_' . $archivo['name'];
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($archivo['tmp_name'], $filePath)) {
        // Crear tabla si no existe (se hace más abajo de forma general)
        
        // Guardar en base de datos (CORREGIDO, aunque la función está deshabilitada)
        $sql = "INSERT INTO material_didactico
                (docente_id, titulo, descripcion, tipo, archivo_adjunto_url, grado)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("isssss", $userId, $titulo, $descripcion, $categoria, $fileName, $grado);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Material subido exitosamente';
        } else {
            $_SESSION['error'] = 'Error al guardar en la base de datos';
            unlink($filePath); // Eliminar archivo si falla la BD
        }
    } else {
        $_SESSION['error'] = 'Error al subir el archivo';
    }
}

function handleCreateFolder() {
    global $conexion, $userId;
    
    $nombre = $_POST['nombre_carpeta'] ?? '';
    $descripcion = $_POST['descripcion_carpeta'] ?? '';
    $color = $_POST['color_carpeta'] ?? '#c97e28';
    
    if (empty($nombre)) {
        $_SESSION['error'] = 'El nombre de la carpeta es obligatorio';
        return;
    }
    
    // Crear tabla carpetas si no existe
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS carpetas_material (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        color VARCHAR(7) DEFAULT '#c97e28',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conexion->query($createTableSQL);
    
    $sql = "INSERT INTO carpetas_material (docente_id, nombre, descripcion, color) VALUES (?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isss", $userId, $nombre, $descripcion, $color);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Carpeta creada exitosamente';
    } else {
        $_SESSION['error'] = 'Error al crear la carpeta';
    }
}

function handleDeleteMaterial() {
    global $conexion;
    
    $materialId = $_POST['material_id'] ?? 0;
    
    // Obtener información del archivo (CORREGIDO)
    $sql = "SELECT archivo_adjunto_url as ruta_archivo FROM material_didactico WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $materialId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        // Eliminar archivo físico
        $filePath = '../../uploads/material_didactico/' . $result['ruta_archivo'];
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar de base de datos (CORREGIDO)
        $sql = "DELETE FROM material_didactico WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $materialId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Material eliminado exitosamente';
        } else {
            $_SESSION['error'] = 'Error al eliminar el material';
        }
    } else {
        $_SESSION['error'] = 'Material no encontrado';
    }
}

// Crear tablas necesarias
$createTablesSQL = [
    "CREATE TABLE IF NOT EXISTS carpetas_material (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        color VARCHAR(7) DEFAULT '#c97e28',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS material_didactico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        tipo ENUM('video', 'imagen', 'pdf', 'otro', 'documento', 'presentacion', 'audio', 'plan_apoyo') DEFAULT 'otro',
        titulo VARCHAR(100),
        descripcion VARCHAR(255),
        enlace_url VARCHAR(255),
        archivo_adjunto_url VARCHAR(255),
        carpeta_id INT NULL,
        grado VARCHAR(20) DEFAULT 'todos',
        publico BOOLEAN DEFAULT FALSE,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (carpeta_id) REFERENCES carpetas_material(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

// Crear tablas si no existen
foreach ($createTablesSQL as $sql) {
    $conexion->query($sql);
}

// Verificar y agregar columnas faltantes en material_didactico
$checkColumnsSQL = "SHOW COLUMNS FROM material_didactico LIKE 'carpeta_id'";
$result = $conexion->query($checkColumnsSQL);
if ($result->num_rows == 0) {
    $alterTableSQL = "ALTER TABLE material_didactico ADD COLUMN carpeta_id INT NULL";
    $conexion->query($alterTableSQL);

    $alterTableSQL = "ALTER TABLE material_didactico ADD COLUMN grado VARCHAR(20) DEFAULT 'todos'";
    $conexion->query($alterTableSQL);

    $alterTableSQL = "ALTER TABLE material_didactico ADD COLUMN publico BOOLEAN DEFAULT FALSE";
    $conexion->query($alterTableSQL);

    $alterTableSQL = "ALTER TABLE material_didactico ADD FOREIGN KEY (carpeta_id) REFERENCES carpetas_material(id) ON DELETE SET NULL";
    $conexion->query($alterTableSQL);
}

// Obtener filtros
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroGrado = $_GET['grado'] ?? '';
$filtroCarpeta = $_GET['carpeta'] ?? '';
$filtroBusqueda = $_GET['busqueda'] ?? '';

// Obtener carpetas del docente
$sql = "SELECT c.*, COUNT(m.id) as total_archivos
        FROM carpetas_material c
        LEFT JOIN material_didactico m ON c.id = m.carpeta_id
        WHERE c.docente_id = ?
        GROUP BY c.id
        ORDER BY c.fecha_creacion DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$carpetas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener material didáctico con filtros (CORREGIDO para usar la relación correcta)
$sql = "SELECT m.id, m.titulo, m.descripcion, m.tipo as categoria, m.enlace_url, m.archivo_adjunto_url, m.creado_en as fecha_subida,
               m.grado, m.publico, m.carpeta_id,
               'local' as storage_type, 0 as descargas, 0 as tamaño_archivo, '' as nombre_archivo,
               c.nombre as carpeta_nombre, c.color as carpeta_color
        FROM material_didactico m
        JOIN docentes d ON m.docente_id = d.id
        LEFT JOIN carpetas_material c ON m.carpeta_id = c.id
        WHERE d.usuario_id = ?";

$params = [$userId];
$types = "i";

if (!empty($filtroCategoria)) {
    $sql .= " AND m.categoria = ?";
    $params[] = $filtroCategoria;
    $types .= "s";
}

if (!empty($filtroGrado)) {
    $sql .= " AND m.grado = ?";
    $params[] = $filtroGrado;
    $types .= "s";
}

if (!empty($filtroCarpeta)) {
    if ($filtroCarpeta === 'sin_carpeta') {
        $sql .= " AND m.carpeta_id IS NULL";
    } else {
        $sql .= " AND m.carpeta_id = ?";
        $params[] = $filtroCarpeta;
        $types .= "i";
    }
}

if (!empty($filtroBusqueda)) {
    $sql .= " AND (m.titulo LIKE ? OR m.descripcion LIKE ? OR m.nombre_archivo LIKE ?)";
    $searchTerm = "%$filtroBusqueda%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

$sql .= " ORDER BY m.creado_en DESC";

$stmt = $conexion->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$materiales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Estadísticas
$statsSQL = "SELECT
    COUNT(*) as total_materiales,
    SUM(CASE WHEN publico = 1 THEN 1 ELSE 0 END) as materiales_publicos
    FROM material_didactico m
    JOIN docentes d ON m.docente_id = d.id
    WHERE d.usuario_id = ?";
$stmt = $conexion->prepare($statsSQL);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Convertir bytes a MB
$espacioUsadoMB = round(($stats['espacio_usado'] ?? 0) / (1024 * 1024), 2);
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
            <h1><i class="fas fa-book"></i> Material Didáctico</h1>
            <p>Gestiona y organiza tus recursos educativos</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total_materiales'] ?? 0 ?></div>
                    <div class="stat-label">Total Materiales</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= count($carpetas) ?></div>
                    <div class="stat-label">Carpetas</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $espacioUsadoMB ?> MB</div>
                    <div class="stat-label">Espacio Usado</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['materiales_publicos'] ?? 0 ?></div>
                    <div class="stat-label">Materiales Públicos</div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="actions-bar">
            <button class="btn btn-primary" onclick="openModal('uploadMaterialModal')">
                <i class="fas fa-upload"></i> Subir Material
            </button>
            <button class="btn btn-success" onclick="openModal('createFolderModal')">
                <i class="fas fa-folder-plus"></i> Nueva Carpeta
            </button>
            <button class="btn btn-info" onclick="toggleView()">
                <i class="fas fa-th" id="viewToggleIcon"></i> <span id="viewToggleText">Vista Lista</span>
            </button>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="categoria">Categoría:</label>
                    <select name="categoria" id="categoria" class="form-control">
                        <option value="">Todas las categorías</option>
                        <option value="documento" <?= $filtroCategoria === 'documento' ? 'selected' : '' ?>>Documento</option>
                        <option value="presentacion" <?= $filtroCategoria === 'presentacion' ? 'selected' : '' ?>>Presentación</option>
                        <option value="video" <?= $filtroCategoria === 'video' ? 'selected' : '' ?>>Video</option>
                        <option value="audio" <?= $filtroCategoria === 'audio' ? 'selected' : '' ?>>Audio</option>
                        <option value="imagen" <?= $filtroCategoria === 'imagen' ? 'selected' : '' ?>>Imagen</option>
                        <option value="plan_apoyo" <?= $filtroCategoria === 'plan_apoyo' ? 'selected' : '' ?>>Plan de Apoyo</option>
                        <option value="otro" <?= $filtroCategoria === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="grado">Grado:</label>
                    <select name="grado" id="grado" class="form-control">
                        <option value="">Todos los grados</option>
                        <?php foreach ($grados_asignados as $grado): ?>
                            <option value="<?= $grado['grado'] ?>" <?= $filtroGrado === $grado['grado'] ? 'selected' : '' ?>>Grado <?= $grado['grado'] ?>°</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="carpeta">Carpeta:</label>
                    <select name="carpeta" id="carpeta" class="form-control">
                        <option value="">Todas las carpetas</option>
                        <option value="sin_carpeta" <?= $filtroCarpeta === 'sin_carpeta' ? 'selected' : '' ?>>Sin carpeta</option>
                        <?php foreach ($carpetas as $carpeta): ?>
                            <option value="<?= $carpeta['id'] ?>" <?= $filtroCarpeta == $carpeta['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($carpeta['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="busqueda">Buscar:</label>
                    <input type="text" name="busqueda" id="busqueda" class="form-control" 
                           placeholder="Título, descripción o archivo..." value="<?= htmlspecialchars($filtroBusqueda) ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="material.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Carpetas -->
        <?php if (!empty($carpetas)): ?>
            <div class="folders-section">
                <h3><i class="fas fa-folder"></i> Carpetas</h3>
                <div class="folders-grid">
                    <?php foreach ($carpetas as $carpeta): ?>
                        <div class="folder-card" style="border-left-color: <?= $carpeta['color'] ?>">
                            <div class="folder-header">
                                <div class="folder-icon" style="color: <?= $carpeta['color'] ?>">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div class="folder-info">
                                    <h4><?= htmlspecialchars($carpeta['nombre']) ?></h4>
                                    <p><?= htmlspecialchars($carpeta['descripcion'] ?? 'Sin descripción') ?></p>
                                </div>
                            </div>
                            <div class="folder-stats">
                                <span><i class="fas fa-file"></i> <?= $carpeta['total_archivos'] ?> archivos</span>
                                <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($carpeta['fecha_creacion'])) ?></span>
                            </div>
                            <div class="folder-actions">
                                <a href="?carpeta=<?= $carpeta['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <button class="btn btn-secondary btn-sm" onclick="editFolder(<?= $carpeta['id'] ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Material Didáctico -->
        <div class="materials-section">
            <h3><i class="fas fa-list"></i> Material Didáctico</h3>
            
            <?php if (empty($materiales)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h4>No hay material didáctico</h4>
                    <p>Sube tu primer material para comenzar a organizar tus recursos</p>
                    <button class="btn btn-primary" onclick="openModal('uploadMaterialModal')">
                        <i class="fas fa-upload"></i> Subir Material
                    </button>
                </div>
            <?php else: ?>
                <div class="materials-grid" id="materialsGrid">
                    <?php foreach ($materiales as $material): ?>
                        <?php 
                        $iconClass = getFileIcon($material['categoria']);
                        $fileSize = formatFileSize($material['tamaño_archivo']);
                        ?>
                        <div class="material-card">
                            <div class="material-header">
                                <div class="material-icon">
                                    <i class="<?= $iconClass ?>"></i>
                                </div>
                                <div class="material-info">
                                    <h4><?= htmlspecialchars($material['titulo']) ?></h4>
                                    <p class="material-filename"><?= htmlspecialchars($material['nombre_archivo']) ?></p>
                                </div>
                                <?php if ($material['publico']): ?>
                                    <div class="public-badge">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($material['descripcion']): ?>
                                <div class="material-description">
                                    <p><?= htmlspecialchars($material['descripcion']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="material-meta">
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span class="category-badge category-<?= $material['categoria'] ?>">
                                        <?= ucfirst($material['categoria']) ?>
                                    </span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span>Grado: <?= $material['grado'] === 'todos' ? 'Todos' : $material['grado'] . '°' ?></span>
                                </div>
                                
                                <?php if ($material['carpeta_nombre']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-folder" style="color: <?= $material['carpeta_color'] ?>"></i>
                                        <span><?= htmlspecialchars($material['carpeta_nombre']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="meta-item">
                                    <i class="fas fa-hdd"></i>
                                    <span><?= $fileSize ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-download"></i>
                                    <span><?= $material['descargas'] ?> descargas</span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('d/m/Y', strtotime($material['fecha_subida'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="material-actions">
                                <?php if ($material['storage_type'] === 'google_drive'): ?>
                                    <a href="<?= htmlspecialchars($material['ruta_archivo']) ?>" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fab fa-google-drive"></i> Abrir en Drive
                                    </a>
                                <?php else: ?>
                                    <a href="download_material.php?id=<?= $material['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download"></i> Descargar
                                    </a>
                                    <button class="btn btn-info btn-sm" onclick="previewMaterial(<?= $material['id'] ?>)">
                                        <i class="fas fa-eye"></i> Vista Previa
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-secondary btn-sm" onclick="editMaterial(<?= $material['id'] ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                
                                <button class="btn btn-success btn-sm" onclick="shareMaterial(<?= $material['id'] ?>)">
                                    <i class="fas fa-share"></i> Compartir
                                </button>
                                
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('¿Eliminar este material?')">
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
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

<!-- Modal Subir Material -->
<div id="uploadMaterialModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-upload"></i> Subir Material Didáctico</h3>
            <button class="close-modal" onclick="closeModal('uploadMaterialModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_material">
            <div class="modal-body">
                <div class="form-group">
                    <label for="titulo">Título:</label>
                    <input type="text" name="titulo" id="titulo" class="form-control" required>
                </div>
                
                <!-- La subida de archivos locales ha sido deshabilitada. -->

                <div class="form-group">
                     <button type="button" class="btn btn-google" id="googleDriveBtn" disabled>
                        <i class="fas fa-spinner fa-spin"></i> Cargando API de Google...
                    </button>
                    <small class="form-text" id="google-drive-selection">No has seleccionado ningún archivo de Google Drive.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="categoria">Categoría:</label>
                        <select name="categoria" id="categoria" class="form-control" required>
                            <option value="">Seleccionar categoría</option>
                            <option value="documento">Documento</option>
                            <option value="presentacion">Presentación</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="imagen">Imagen</option>
                            <option value="plan_apoyo">Plan de Apoyo</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="grado">Grado:</label>
                        <select name="grado" id="grado" class="form-control">
                            <option value="todos">Todos los grados asignados</option>
                            <?php foreach ($grados_asignados as $grado): ?>
                                <option value="<?= $grado['grado'] ?>">Grado <?= $grado['grado'] ?>°</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="carpeta_id">Carpeta (opcional):</label>
                    <select name="carpeta_id" id="carpeta_id" class="form-control">
                        <option value="">Sin carpeta</option>
                        <?php foreach ($carpetas as $carpeta): ?>
                            <option value="<?= $carpeta['id'] ?>">
                                <?= htmlspecialchars($carpeta['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción (opcional):</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="publico" id="publico">
                        <span class="checkmark"></span>
                        Hacer público (visible para otros docentes)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadMaterialModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">Subir Material</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Crear Carpeta -->
<div id="createFolderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-folder-plus"></i> Crear Nueva Carpeta</h3>
            <button class="close-modal" onclick="closeModal('createFolderModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_folder">
            <div class="modal-body">
                <div class="form-group">
                    <label for="nombre_carpeta">Nombre de la Carpeta:</label>
                    <input type="text" name="nombre_carpeta" id="nombre_carpeta" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="color_carpeta">Color:</label>
                    <div class="color-picker">
                        <input type="color" name="color_carpeta" id="color_carpeta" class="form-control" value="#c97e28">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descripcion_carpeta">Descripción (opcional):</label>
                    <textarea name="descripcion_carpeta" id="descripcion_carpeta" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createFolderModal')">Cancelar</button>
                <button type="submit" class="btn btn-success">Crear Carpeta</button>
            </div>
        </form>
    </div>
</div>

<?php
function getFileIcon($categoria) {
    switch ($categoria) {
        case 'documento': return 'fas fa-file-alt';
        case 'presentacion': return 'fas fa-file-powerpoint';
        case 'video': return 'fas fa-file-video';
        case 'audio': return 'fas fa-file-audio';
        case 'imagen': return 'fas fa-file-image';
        case 'plan_apoyo': return 'fas fa-file-medical-alt';
        default: return 'fas fa-file';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<style>
/* Estilos específicos para la página de material - sin conflictos con Dashboard.css */

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

.folders-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.folders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.folder-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.folder-card:hover {
    transform: translateY(-3px);
}

.folder-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.folder-icon {
    font-size: 2rem;
}

.folder-info h4 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.folder-info p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.folder-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.folder-actions {
    display: flex;
    gap: 10px;
}

.materials-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.materials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.material-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.material-card:hover {
    transform: translateY(-3px);
}

.material-header {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
    position: relative;
}

.material-icon {
    font-size: 2rem;
    color: var(--primary-color);
    flex-shrink: 0;
}

.material-info h4 {
    margin: 0 0 5px 0;
    color: var(--text-dark);
}

.material-filename {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
    word-break: break-all;
}

.public-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
}

.material-description {
    margin-bottom: 15px;
}

.material-description p {
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.4;
    margin: 0;
}

.material-meta {
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

.category-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.category-documento { background: rgba(0, 123, 255, 0.1); color: #007bff; }
.category-presentacion { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.category-video { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.category-audio { background: rgba(102, 16, 242, 0.1); color: #6610f2; }
.category-imagen { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.category-plan_apoyo { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
.category-otro { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

.btn-google {
    background-color: #4285F4;
    color: white;
    width: 100%;
    padding: 12px;
}

.btn-google:hover {
    background-color: #357ae8;
}

.separator {
    display: block;
    margin: 15px 0;
    font-weight: bold;
    color: var(--text-muted);
}

.material-actions {
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
    overflow-y: scroll;
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

.color-picker input[type="color"] {
    width: 50px;
    height: 40px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 20px !important;
        width: 100% !important;
        overflow-x: hidden !important;
    }

    .stats-grid {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }

    .materials-grid,
    .folders-grid {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }

    .filters-form {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }

    .actions-bar {
        flex-direction: column !important;
        gap: 10px !important;
    }

    .form-row {
        grid-template-columns: 1fr !important;
        gap: 10px !important;
    }

    .material-actions {
        justify-content: center !important;
        flex-wrap: wrap !important;
    }

    .modal-content {
        width: 95% !important;
        margin: 10% auto !important;
    }
}
</style>

<script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
<script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>

<script>
// --- Variables Globales ---
const GOOGLE_CLIENT_ID = '<?= GOOGLE_CLIENT_ID ?>';
const GOOGLE_API_KEY = '<?= GOOGLE_API_KEY ?>';
const SCOPES = 'https://www.googleapis.com/auth/drive.readonly';

let tokenClient;
let googleDriveFile = null;
let isGridView = true;

// --- Funciones de UI Generales ---
function openModal(modalId) { document.getElementById(modalId).style.display = 'block'; }
function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

function toggleView() {
    const grid = document.getElementById('materialsGrid');
    const icon = document.getElementById('viewToggleIcon');
    const text = document.getElementById('viewToggleText');
    isGridView = !isGridView;
    if (!isGridView) {
        grid.style.display = 'block';
        grid.style.gridTemplateColumns = '1fr';
        icon.className = 'fas fa-th-large';
        text.textContent = 'Vista Cuadrícula';
    } else {
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(350px, 1fr))';
        icon.className = 'fas fa-th';
        text.textContent = 'Vista Lista';
    }
}

// --- Funciones de Material (placeholders) ---
function previewMaterial(id) { window.open(`preview_material.php?id=${id}`, '_blank'); }
function editMaterial(id) { alert('Funcionalidad de edición en desarrollo'); }
function shareMaterial(id) { alert('Funcionalidad de compartir en desarrollo'); }
function editFolder(id) { alert('Funcionalidad de edición de carpeta en desarrollo'); }

// --- Lógica de Carga de la API de Google (Robusta) ---
let gapiReady = false;
let gisReady = false;

function gapiLoaded() {
    gapi.load('client:picker', () => {
        gapiReady = true;
        tryInitGooglePicker();
    });
}

function gisLoaded() {
    gisReady = true;
    tryInitGooglePicker();
}

function tryInitGooglePicker() {
    if (gapiReady && gisReady) {
        const googleDriveBtn = document.getElementById('googleDriveBtn');
        tokenClient = google.accounts.oauth2.initTokenClient({
            client_id: GOOGLE_CLIENT_ID,
            scope: SCOPES,
            callback: (tokenResponse) => {
                if (tokenResponse && tokenResponse.access_token) {
                    createPicker(tokenResponse.access_token);
                }
            },
        });
        
        if (googleDriveBtn) {
            googleDriveBtn.disabled = false;
            googleDriveBtn.innerHTML = '<i class="fab fa-google-drive"></i> Seleccionar de Google Drive';
            googleDriveBtn.addEventListener('click', () => {
                tokenClient.requestAccessToken({ prompt: 'consent' });
            });
        }
    }
}

function createPicker(accessToken) {
    const view = new google.picker.View(google.picker.ViewId.DOCS);
    view.setMimeTypes("application/pdf,application/vnd.google-apps.document,application/vnd.google-apps.presentation,application/vnd.google-apps.spreadsheet,image/jpeg,image/png,video/mp4");
    
    const picker = new google.picker.PickerBuilder()
        .setAppId('<?= explode('-', GOOGLE_CLIENT_ID)[0] ?>')
        .setOAuthToken(accessToken)
        .addView(view)
        .addView(new google.picker.DocsUploadView())
        .setDeveloperKey(GOOGLE_API_KEY)
        .setCallback(handlePickerCallback)
        .build();
    picker.setVisible(true);
}

function handlePickerCallback(data) {
    if (data.docs && data.docs.length > 0) {
        const file = data.docs[0];
        googleDriveFile = {
            id: file.id, name: file.name, url: file.url,
            mime: file.mimeType, size: file.sizeBytes
        };
        
        document.getElementById('google-drive-selection').textContent = `Archivo seleccionado: ${file.name}`;
        
        const titleInput = document.getElementById('titulo');
        if (!titleInput.value) {
            titleInput.value = file.name.substring(0, file.name.lastIndexOf('.'));
        }
        autoDetectCategory(file.name);
    }
}

// --- Lógica del Formulario de Subida ---
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.querySelector('#uploadMaterialModal form');
    const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');

    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            if (!googleDriveFile) {
                alert('Por favor, selecciona un archivo de Google Drive.');
                e.preventDefault();
                return;
            }
            
            e.preventDefault();
            
            const formData = new FormData(uploadForm);
            const data = Object.fromEntries(formData.entries());
            
            Object.assign(data, {
                action: 'save_google_drive_file',
                file_id: googleDriveFile.id,
                file_name: googleDriveFile.name,
                file_url: googleDriveFile.url,
                file_mime: googleDriveFile.mime,
                file_size: googleDriveFile.size
            });

            uploadSubmitBtn.disabled = true;
            uploadSubmitBtn.textContent = 'Guardando...';

            fetch('material.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    window.location.href = 'material.php';
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error inesperado.');
            })
            .finally(() => {
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.textContent = 'Subir Material';
            });
        });
    }
});

function autoDetectCategory(filename) {
    const categorySelect = document.getElementById('categoria');
    const extension = filename.split('.').pop().toLowerCase();
    
    const categories = {
        documento: ['pdf', 'doc', 'docx', 'txt'],
        presentacion: ['ppt', 'pptx'],
        video: ['mp4', 'avi', 'mov', 'wmv'],
        audio: ['mp3', 'wav', 'ogg'],
        imagen: ['jpg', 'jpeg', 'png', 'gif', 'bmp']
    };

    let detectedCategory = 'otro';
    for (const category in categories) {
        if (categories[category].includes(extension)) {
            detectedCategory = category;
            break;
        }
    }

    if (detectedCategory === 'documento' && (filename.toLowerCase().includes('plan de apoyo'))) {
        categorySelect.value = 'plan_apoyo';
    } else {
        categorySelect.value = detectedCategory;
    }
}

// --- Lógica Adicional de UI ---
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

<?php require_once '../../includes/footer.php'; ?>
