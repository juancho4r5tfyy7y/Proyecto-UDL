<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verificar autenticación y rol de docente
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'docente') {
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
    $docente_id = $_SESSION['id'];

    // Obtener y validar datos del formulario
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $grado = trim($_POST['grado'] ?? ''); // Cambiado de grupo_id a grado
    $unidad_tematica_id = intval($_POST['unidad_tematica_id'] ?? 0);
    $fecha_limite = $_POST['fecha_limite'] ?? '';
    $tipo_actividad = $_POST['tipo_actividad'] ?? 'tarea';
    $puntos_maximos = floatval($_POST['puntos_maximos'] ?? 100);
    $instrucciones = trim($_POST['instrucciones'] ?? '');
    $recursos_necesarios = trim($_POST['recursos_necesarios'] ?? '');
    $criterios_evaluacion = trim($_POST['criterios_evaluacion'] ?? '');
    $google_drive_link = trim($_POST['google_drive_link'] ?? '');

    // Validaciones
    if (empty($titulo)) {
        throw new Exception('El título es requerido');
    }

    if (empty($descripcion)) {
        throw new Exception('La descripción es requerida');
    }

    if (empty($grado)) {
        throw new Exception('Debe seleccionar un grado válido');
    }

    if (empty($fecha_limite)) {
        throw new Exception('La fecha límite es requerida');
    }

    // Validar que la fecha límite no sea en el pasado
    $fecha_limite_timestamp = strtotime($fecha_limite);
    if ($fecha_limite_timestamp <= time()) {
        throw new Exception('La fecha límite debe ser futura');
    }

    if ($puntos_maximos <= 0 || $puntos_maximos > 1000) {
        throw new Exception('Los puntos máximos deben estar entre 1 y 1000');
    }

    // Verificar que el docente tiene asignado el grado seleccionado
    $sql = "SELECT grado FROM docente_grados WHERE docente_id = ? AND grado = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $docente_id, $grado);
    $stmt->execute();
    $grado_asignado = $stmt->get_result()->fetch_assoc();

    if (!$grado_asignado) {
        throw new Exception('Grado no encontrado o sin permisos para este docente');
    }

    // Buscar un grupo que corresponda al grado y al docente.
    // Se asume que el nombre del grupo contiene el grado (ej: "Grado 11 - A")
    $sql_grupo = "SELECT id FROM grupos WHERE nombre LIKE ? AND docente_id = ? LIMIT 1";
    $stmt_grupo = $conexion->prepare($sql_grupo);
    $grado_like = "%" . $grado . "%"; // Búsqueda flexible
    $stmt_grupo->bind_param("si", $grado_like, $docente_id);
    $stmt_grupo->execute();
    $resultado_grupo = $stmt_grupo->get_result()->fetch_assoc();

    if (!$resultado_grupo) {
        throw new Exception('No se encontró un grupo asociado al grado seleccionado. Por favor, cree o asigne un grupo para el grado ' . $grado . '.');
    }
    $grupo_id = $resultado_grupo['id'];

    // Verificar unidad temática si se proporcionó
    if ($unidad_tematica_id > 0) {
        $sql = "SELECT id, nombre FROM unidades_tematicas WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $unidad_tematica_id);
        $stmt->execute();
        $unidad = $stmt->get_result()->fetch_assoc();

        if (!$unidad) {
            throw new Exception('Unidad temática no encontrada');
        }
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Insertar la actividad
        // Se busca el grupo_id correspondiente al grado y se usa para la inserción.
        $sql = "INSERT INTO actividades (
                    titulo, descripcion, grupo_id, unidad_tematica_id, 
                    fecha_limite, tipo_actividad, puntos_maximos, 
                    instrucciones, recursos_necesarios, criterios_evaluacion,
                    google_drive_link, fecha_creacion, activa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(
            "ssiisdsssss", // Se corrigió la cadena de tipos y se usa grupo_id
            $titulo, $descripcion, $grupo_id, $unidad_tematica_id,
            $fecha_limite, $tipo_actividad, $puntos_maximos,
            $instrucciones, $recursos_necesarios, $criterios_evaluacion,
            $google_drive_link
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al crear la actividad');
        }

        $actividad_id = $conexion->insert_id;


        // Confirmar transacción
        $conexion->commit();

        // Registrar en logs
        $logMessage = sprintf(
            "[%s] Docente %s (%d) creó actividad '%s' (ID: %d) para grado '%s'",
            date('Y-m-d H:i:s'),
            $_SESSION['usuario'],
            $docente_id,
            $titulo,
            $actividad_id,
            $grado
        );

        file_put_contents('../storage/logs/actividades.log', $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Obtener información completa de la actividad creada
        $sql = "SELECT a.*, g.nombre as grupo_nombre, ut.nombre as unidad_nombre
                FROM actividades a
                JOIN grupos g ON a.grupo_id = g.id
                LEFT JOIN unidades_tematicas ut ON a.unidad_tematica_id = ut.id
                WHERE a.id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $actividad_id);
        $stmt->execute();
        $actividad_completa = $stmt->get_result()->fetch_assoc();

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Actividad creada exitosamente',
            'data' => [
                'actividad_id' => $actividad_id,
                'titulo' => $titulo,
                'grado' => $grado,
                'fecha_limite' => date('d/m/Y H:i', strtotime($fecha_limite)),
                'puntos_maximos' => $puntos_maximos,
                'archivos_adjuntos' => $archivos_subidos ?? [],
                'actividad_completa' => $actividad_completa
            ]
        ]);

    } catch (Exception $e) {
        // Revertir transacción
        $conexion->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log del error
    $errorMessage = sprintf(
        "[%s] ERROR - Docente %s (%d) al crear actividad: %s",
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
