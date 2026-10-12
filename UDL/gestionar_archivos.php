<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/php/conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id'];
$userRole = $_SESSION['rol'] ?? '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                handleFileUpload();
                break;
            case 'delete':
                handleFileDelete();
                break;
            case 'create_folder':
                handleFolderCreate();
                break;
        }
    }
}

function handleFileUpload() {
    global $conexion, $userId;
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Error al subir el archivo';
        return;
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpName = $file['tmp_name'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validar tipo de archivo
    $allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];
    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['error'] = 'Tipo de archivo no permitido';
        return;
    }
    
    // Validar tamaño (10MB máximo)
    if ($fileSize > MAX_FILE_SIZE) {
        $_SESSION['error'] = 'El archivo es demasiado grande (máximo 10MB)';
        return;
    }
    
    // Crear directorio si no existe
    $uploadDir = UPLOAD_PATH;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generar nombre único
    $uniqueName = uniqid() . '_' . $fileName;
    $uploadPath = $uploadDir . $uniqueName;
    
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        // Guardar en base de datos
        $sql = "INSERT INTO archivos_sistema (usuario_id, nombre_original, nombre_archivo, ruta_archivo, tipo_archivo, tamaño, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $descripcion = $_POST['descripcion'] ?? '';
        $stmt->bind_param("issssds", $userId, $fileName, $uniqueName, $uploadPath, $fileType, $fileSize, $descripcion);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Archivo subido exitosamente';
        } else {
            $_SESSION['error'] = 'Error al guardar en la base de datos';
            unlink($uploadPath); // Eliminar archivo si falla la BD
        }
    } else {
        $_SESSION['error'] = 'Error al mover el archivo';
    }
}

function handleFileDelete() {
    global $conexion, $userId, $userRole;
    
    $fileId = $_POST['file_id'] ?? 0;
    
    // Obtener información del archivo
    $sql = "SELECT * FROM archivos_sistema WHERE id = ?";
    if ($userRole !== 'superadmin') {
        $sql .= " AND usuario_id = ?";
    }
    
    $stmt = $conexion->prepare($sql);
    if ($userRole === 'superadmin') {
        $stmt->bind_param("i", $fileId);
    } else {
        $stmt->bind_param("ii", $fileId, $userId);
    }
    
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    
    if ($file) {
        // Eliminar archivo físico
        if (file_exists($file['ruta_archivo'])) {
            unlink($file['ruta_archivo']);
        }
        
        // Eliminar de la base de datos
        $sql = "DELETE FROM archivos_sistema WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $fileId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Archivo eliminado exitosamente';
        } else {
            $_SESSION['error'] = 'Error al eliminar el archivo';
        }
    } else {
        $_SESSION['error'] = 'Archivo no encontrado';
    }
}

// Crear tabla si no existe
$createTableSQL = "
CREATE TABLE IF NOT EXISTS archivos_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_archivo VARCHAR(10) NOT NULL,
    tamaño INT NOT NULL,
    descripcion TEXT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
$conexion->query($createTableSQL);

// Obtener archivos
$sql = "SELECT a.*, u.nombre as usuario_nombre FROM archivos_sistema a 
        JOIN usuarios u ON a.usuario_id = u.id";

if ($userRole !== 'superadmin') {
    $sql .= " WHERE a.usuario_id = ?";
}

$sql .= " ORDER BY a.fecha_subida DESC";

$stmt = $conexion->prepare($sql);
if ($userRole !== 'superadmin') {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$archivos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener estadísticas
$stats = [
    'total_archivos' => count($archivos),
    'espacio_usado' => array_sum(array_column($archivos, 'tamaño')),
    'tipos_archivo' => []
];

foreach ($archivos as $archivo) {
    $tipo = $archivo['tipo_archivo'];
    $stats['tipos_archivo'][$tipo] = ($stats['tipos_archivo'][$tipo] ?? 0) + 1;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getFileIcon($tipo) {
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'txt' => 'fas fa-file-alt text-secondary',
        'jpg' => 'fas fa-file-image text-success',
        'jpeg' => 'fas fa-file-image text-success',
        'png' => 'fas fa-file-image text-success',
        'gif' => 'fas fa-file-image text-success',
        'mp4' => 'fas fa-file-video text-warning',
        'avi' => 'fas fa-file-video text-warning',
        'mov' => 'fas fa-file-video text-warning'
    ];
    return $icons[$tipo] ?? 'fas fa-file text-muted';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Archivos - UDL</title>
    <link rel="stylesheet" href="assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .file-manager {
            background: var(--gradient-bg);
            min-height: 100vh;
            padding: 20px;
        }
        
        .file-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .upload-area {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .file-drop-zone {
            border: 2px dashed var(--primary-color);
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: rgba(201, 126, 40, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-drop-zone:hover {
            background: rgba(201, 126, 40, 0.1);
            border-color: var(--secondary-color);
        }
        
        .file-drop-zone.dragover {
            background: rgba(201, 126, 40, 0.15);
            border-color: var(--secondary-color);
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .file-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .file-card:hover {
            transform: translateY(-5px);
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .file-icon {
            font-size: 2rem;
        }
        
        .file-details h4 {
            margin: 0 0 5px 0;
            color: var(--text-dark);
            font-size: 1.1rem;
        }
        
        .file-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .file-actions {
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
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
            text-align: center;
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
        .sidebar-menu a.active {
            background: rgba(201, 126, 40, 0.1);
            color: var(--primary-color);
            border-right: 3px solid var(--primary-color);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
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
            }
            
            .files-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="file-manager">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-folder-open"></i> Gestión de Archivos</h2>
                <p><?= htmlspecialchars($_SESSION['usuario']) ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($userRole === 'superadmin'): ?>
                    <li><a href="superadmin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="usuarios_admin.php"><i class="fas fa-users"></i> Usuarios</a></li>
                    <li><a href="grupos_admin.php"><i class="fas fa-school"></i> Grupos</a></li>
                <?php elseif ($userRole === 'docente'): ?>
                    <li><a href="docente_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="views/docente/gestionar_actividades.php"><i class="fas fa-tasks"></i> Actividades</a></li>
                    <li><a href="gestionar_estudiantes.php"><i class="fas fa-user-graduate"></i> Estudiantes</a></li>
                <?php else: ?>
                    <li><a href="estudiante_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="views/estudiante/actividades.php"><i class="fas fa-tasks"></i> Actividades</a></li>
                    <li><a href="views/estudiante/progreso.php"><i class="fas fa-chart-line"></i> Progreso</a></li>
                <?php endif; ?>
                <li><a href="gestionar_archivos.php" class="active"><i class="fas fa-folder"></i> Archivos</a></li>
                <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
            </ul>
        </nav>
        
        <!-- Contenido Principal -->
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
            
            <!-- Header -->
            <div class="file-header">
                <h1><i class="fas fa-folder-open"></i> Gestión de Archivos</h1>
                <p>Administra todos los archivos del sistema de manera eficiente</p>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_archivos'] ?></div>
                    <div class="stat-label">Total de Archivos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= formatFileSize($stats['espacio_usado']) ?></div>
                    <div class="stat-label">Espacio Utilizado</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($stats['tipos_archivo']) ?></div>
                    <div class="stat-label">Tipos de Archivo</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= formatFileSize(MAX_FILE_SIZE) ?></div>
                    <div class="stat-label">Límite por Archivo</div>
                </div>
            </div>
            
            <!-- Área de Subida -->
            <div class="upload-area">
                <h3><i class="fas fa-cloud-upload-alt"></i> Subir Nuevo Archivo</h3>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="file-drop-zone" id="dropZone">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 15px;"></i>
                        <h4>Arrastra archivos aquí o haz clic para seleccionar</h4>
                        <p>Tipos permitidos: PDF, DOC, DOCX, TXT, JPG, PNG, GIF, MP4, AVI, MOV</p>
                        <p>Tamaño máximo: <?= formatFileSize(MAX_FILE_SIZE) ?></p>
                        <input type="file" name="file" id="fileInput" style="display: none;" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov">
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción (opcional)</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" placeholder="Describe el contenido del archivo..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Subir Archivo
                    </button>
                </form>
            </div>
            
            <!-- Lista de Archivos -->
            <div class="files-section">
                <h3><i class="fas fa-list"></i> Archivos del Sistema</h3>
                
                <?php if (empty($archivos)): ?>
                    <div class="stat-card" style="text-align: center; padding: 40px;">
                        <i class="fas fa-folder-open" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 20px;"></i>
                        <h4>No hay archivos</h4>
                        <p>Sube tu primer archivo usando el formulario de arriba</p>
                    </div>
                <?php else: ?>
                    <div class="files-grid">
                        <?php foreach ($archivos as $archivo): ?>
                            <div class="file-card">
                                <div class="file-info">
                                    <div class="file-icon">
                                        <i class="<?= getFileIcon($archivo['tipo_archivo']) ?>"></i>
                                    </div>
                                    <div class="file-details">
                                        <h4><?= htmlspecialchars($archivo['nombre_original']) ?></h4>
                                        <div class="file-meta">
                                            <div><i class="fas fa-user"></i> <?= htmlspecialchars($archivo['usuario_nombre']) ?></div>
                                            <div><i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?></div>
                                            <div><i class="fas fa-hdd"></i> <?= formatFileSize($archivo['tamaño']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($archivo['descripcion'])): ?>
                                    <p style="margin: 10px 0; color: var(--text-muted); font-size: 0.9rem;">
                                        <?= htmlspecialchars($archivo['descripcion']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="file-actions">
                                    <a href="storage/uploads/<?= htmlspecialchars($archivo['nombre_archivo']) ?>" 
                                       class="btn btn-primary" target="_blank">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    
                                    <a href="storage/uploads/<?= htmlspecialchars($archivo['nombre_archivo']) ?>" 
                                       class="btn btn-success" download="<?= htmlspecialchars($archivo['nombre_original']) ?>">
                                        <i class="fas fa-download"></i> Descargar
                                    </a>
                                    
                                    <?php if ($userRole === 'superadmin' || $archivo['usuario_id'] == $userId): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('¿Estás seguro de eliminar este archivo?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="file_id" value="<?= $archivo['id'] ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Drag and Drop functionality
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateDropZoneText(files[0].name);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateDropZoneText(e.target.files[0].name);
            }
        });
        
        function updateDropZoneText(fileName) {
            dropZone.innerHTML = `
                <i class="fas fa-file" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 15px;"></i>
                <h4>Archivo seleccionado: ${fileName}</h4>
                <p>Haz clic en "Subir Archivo" para continuar</p>
            `;
        }
        
        // Mobile menu toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
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