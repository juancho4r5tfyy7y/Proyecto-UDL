<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit();
}

try {
    $conexion = conexion();
    $userId = $_SESSION['id'];
    $actividad_id = $_POST['actividad_id'] ?? null;
    $comentarios = $_POST['comentarios'] ?? '';

    // Validar que se proporcionó una actividad
    if (!$actividad_id) {
        throw new Exception('ID de actividad requerido');
    }

    // Verificar que el archivo fue subido
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo');
    }

    $archivo = $_FILES['archivo'];
    
    // Validaciones del archivo
    $maxSize = 10 * 1024 * 1024; // 10MB
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    if ($archivo['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande. Máximo 10MB permitido.');
    }

    if (!in_array($archivo['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten PDF, Word, texto e imágenes.');
    }

    // Verificar que la actividad existe y el estudiante tiene acceso
    $sql = "SELECT a.*, g.nombre as grupo_nombre, g.docente_id
            FROM actividades a 
            JOIN grupos g ON a.grupo_id = g.id 
            JOIN estudiantes_grupos eg ON g.id = eg.grupo_id 
            WHERE a.id = ? AND eg.estudiante_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $actividad_id, $userId);
    $stmt->execute();
    $actividad = $stmt->get_result()->fetch_assoc();

    if (!$actividad) {
        throw new Exception('Actividad no encontrada o sin acceso');
    }

    // Verificar si ya existe una entrega para esta actividad
    $sql = "SELECT id FROM entregas WHERE id_usuario = ? AND actividad_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $userId, $actividad_id);
    $stmt->execute();
    $entrega_existente = $stmt->get_result()->fetch_assoc();

    // Crear directorio de uploads si no existe
    $uploadDir = '../storage/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Crear subdirectorio por actividad
    $actividadDir = $uploadDir . 'actividad_' . $actividad_id . '/';
    if (!is_dir($actividadDir)) {
        mkdir($actividadDir, 0755, true);
    }

    // Generar nombre único para el archivo
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = sprintf(
        "%s_%s_%s_%s.%s",
        $_SESSION['usuario'],
        preg_replace('/[^a-zA-Z0-9_-]/', '_', $actividad['titulo']),
        date('Y-m-d_H-i-s'),
        uniqid(),
        $extension
    );

    // Limpiar nombre de archivo
    $nombreArchivo = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreArchivo);
    $rutaArchivo = $actividadDir . $nombreArchivo;

    // Mover archivo al directorio de destino
    if (!move_uploaded_file($archivo['tmp_name'], $rutaArchivo)) {
        throw new Exception("Error al guardar el archivo");
    }

    // Guardar información en la base de datos
    if ($entrega_existente) {
        // Eliminar archivo anterior si existe
        $sqlOld = "SELECT ruta_archivo FROM entregas WHERE id = ?";
        $stmtOld = $conexion->prepare($sqlOld);
        $stmtOld->bind_param("i", $entrega_existente['id']);
        $stmtOld->execute();
        $oldFile = $stmtOld->get_result()->fetch_assoc();
        if ($oldFile && file_exists($oldFile['ruta_archivo'])) {
            unlink($oldFile['ruta_archivo']);
        }

        // Actualizar entrega existente
        $sql = "UPDATE entregas SET 
                nombre_archivo = ?, 
                ruta_archivo = ?, 
                comentarios = ?, 
                fecha_subida = NOW(),
                calificacion = NULL,
                comentarios_docente = NULL
                WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssi", $nombreArchivo, $rutaArchivo, $comentarios, $entrega_existente['id']);
        $accion = 'actualizada';
    } else {
        // Crear nueva entrega
        $sql = "INSERT INTO entregas (
                    id_usuario, actividad_id, nombre_archivo, ruta_archivo, 
                    comentarios, fecha_subida
                ) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iisss", $userId, $actividad_id, $nombreArchivo, $rutaArchivo, $comentarios);
        $accion = 'creada';
    }

    if (!$stmt->execute()) {
        // Si falla la BD, eliminar archivo local
        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo);
        }
        throw new Exception('Error al guardar en la base de datos');
    }

    // Registrar en logs
    $logMessage = sprintf(
        "[%s] Usuario %s (%d) %s entrega para actividad %d (%s). Archivo: %s",
        date('Y-m-d H:i:s'),
        $_SESSION['usuario'],
        $userId,
        $accion,
        $actividad_id,
        $actividad['titulo'],
        $nombreArchivo
    );

    file_put_contents('../storage/logs/entregas.log', $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);

    // Notificar al docente (opcional - implementar sistema de notificaciones)
    // notificarDocente($actividad['docente_id'], $userId, $actividad_id, $accion);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => "Entrega $accion exitosamente",
        'data' => [
            'archivo_nombre' => $nombreArchivo,
            'ruta_archivo' => $rutaArchivo,
            'fecha_subida' => date('Y-m-d H:i:s'),
            'actividad' => $actividad['titulo'],
            'accion' => $accion
        ]
    ]);

} catch (Exception $e) {
    // Log del error
    $errorMessage = sprintf(
        "[%s] ERROR - Usuario %s (%d): %s",
        date('Y-m-d H:i:s'),
        $_SESSION['usuario'] ?? 'desconocido',
        $_SESSION['id'] ?? 0,
        $e->getMessage()
    );

    file_put_contents('../storage/logs/errores.log', $errorMessage . PHP_EOL, FILE_APPEND | LOCK_EX);

    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>