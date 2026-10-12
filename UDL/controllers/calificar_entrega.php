<?php
session_start();
require_once '../config/database.php';

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
    $entrega_id = intval($_POST['entrega_id'] ?? 0);
    $calificacion = floatval($_POST['calificacion'] ?? 0);
    $comentarios = trim($_POST['comentarios'] ?? '');
    $retroalimentacion = trim($_POST['retroalimentacion'] ?? '');
    $estado_entrega = $_POST['estado_entrega'] ?? 'calificada';

    // Validaciones básicas
    if ($entrega_id <= 0) {
        throw new Exception('ID de entrega inválido');
    }

    if ($calificacion < 0) {
        throw new Exception('La calificación no puede ser negativa');
    }

    // Obtener información de la entrega y verificar permisos
    $sql = "SELECT e.*, a.titulo as actividad_titulo, a.puntos_maximos, a.grupo_id,
                   est.nombre as estudiante_nombre, est.apellido as estudiante_apellido,
                   g.docente_id, g.nombre as grupo_nombre
            FROM entregas e
            JOIN actividades a ON e.actividad_id = a.id
            JOIN estudiantes est ON e.estudiante_id = est.id
            JOIN grupos g ON a.grupo_id = g.id
            WHERE e.id = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $entrega_id);
    $stmt->execute();
    $entrega = $stmt->get_result()->fetch_assoc();

    if (!$entrega) {
        throw new Exception('Entrega no encontrada');
    }

    // Verificar que el docente tiene permisos sobre esta entrega
    if ($entrega['docente_id'] != $docente_id) {
        throw new Exception('No tiene permisos para calificar esta entrega');
    }

    // Validar que la calificación no exceda los puntos máximos
    if ($calificacion > $entrega['puntos_maximos']) {
        throw new Exception("La calificación no puede exceder {$entrega['puntos_maximos']} puntos");
    }

    // Validar estado de entrega
    $estados_validos = ['calificada', 'revisada', 'aprobada', 'rechazada', 'necesita_revision'];
    if (!in_array($estado_entrega, $estados_validos)) {
        $estado_entrega = 'calificada';
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Actualizar la entrega con la calificación
        $sql = "UPDATE entregas SET 
                    calificacion = ?, 
                    comentarios_docente = ?, 
                    retroalimentacion = ?, 
                    estado = ?, 
                    fecha_calificacion = NOW(),
                    calificado_por = ?
                WHERE id = ?";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(
            "dsssii",
            $calificacion, $comentarios, $retroalimentacion, 
            $estado_entrega, $docente_id, $entrega_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar la calificación');
        }

        // Registrar en historial de calificaciones
        $sql_historial = "INSERT INTO historial_calificaciones (
                            entrega_id, calificacion_anterior, calificacion_nueva,
                            comentarios, docente_id, fecha_cambio, motivo
                          ) VALUES (?, ?, ?, ?, ?, NOW(), 'Calificación inicial')";

        $stmt_historial = $conexion->prepare($sql_historial);
        $calificacion_anterior = $entrega['calificacion'] ?? 0;
        $stmt_historial->bind_param(
            "iddsi",
            $entrega_id, $calificacion_anterior, $calificacion,
            $comentarios, $docente_id
        );

        if (!$stmt_historial->execute()) {
            throw new Exception('Error al registrar historial de calificación');
        }

        // Actualizar estadísticas del estudiante
        $sql_stats = "UPDATE estudiantes SET 
                        actividades_completadas = (
                            SELECT COUNT(*) FROM entregas 
                            WHERE estudiante_id = ? AND estado IN ('calificada', 'aprobada')
                        ),
                        promedio_calificaciones = (
                            SELECT AVG(calificacion) FROM entregas 
                            WHERE estudiante_id = ? AND calificacion IS NOT NULL
                        )
                      WHERE id = ?";

        $stmt_stats = $conexion->prepare($sql_stats);
        $stmt_stats->bind_param("iii", $entrega['estudiante_id'], $entrega['estudiante_id'], $entrega['estudiante_id']);
        $stmt_stats->execute();

        // Crear notificación para el estudiante
        $mensaje_notificacion = sprintf(
            "Tu entrega para '%s' ha sido calificada. Calificación: %.1f/%.1f puntos.",
            $entrega['actividad_titulo'],
            $calificacion,
            $entrega['puntos_maximos']
        );

        $sql_notif = "INSERT INTO notificaciones (
                        usuario_id, tipo, titulo, mensaje, 
                        fecha_creacion, leida, relacionado_tipo, relacionado_id
                      ) VALUES (?, 'calificacion', 'Entrega Calificada', ?, NOW(), 0, 'entrega', ?)";

        $stmt_notif = $conexion->prepare($sql_notif);
        $stmt_notif->bind_param("isi", $entrega['estudiante_id'], $mensaje_notificacion, $entrega_id);
        $stmt_notif->execute();

        // Confirmar transacción
        $conexion->commit();

        // Registrar en logs
        $logMessage = sprintf(
            "[%s] Docente %s (%d) calificó entrega ID %d del estudiante %s %s con %.1f/%.1f puntos - Estado: %s",
            date('Y-m-d H:i:s'),
            $_SESSION['usuario'],
            $docente_id,
            $entrega_id,
            $entrega['estudiante_nombre'],
            $entrega['estudiante_apellido'],
            $calificacion,
            $entrega['puntos_maximos'],
            $estado_entrega
        );

        file_put_contents('../storage/logs/calificaciones.log', $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Calcular porcentaje de calificación
        $porcentaje = ($calificacion / $entrega['puntos_maximos']) * 100;

        // Determinar nivel de desempeño
        $nivel_desempeno = 'Insuficiente';
        if ($porcentaje >= 90) $nivel_desempeno = 'Excelente';
        elseif ($porcentaje >= 80) $nivel_desempeno = 'Sobresaliente';
        elseif ($porcentaje >= 70) $nivel_desempeno = 'Satisfactorio';
        elseif ($porcentaje >= 60) $nivel_desempeno = 'Aceptable';

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Entrega calificada exitosamente',
            'data' => [
                'entrega_id' => $entrega_id,
                'calificacion' => $calificacion,
                'puntos_maximos' => $entrega['puntos_maximos'],
                'porcentaje' => round($porcentaje, 1),
                'nivel_desempeno' => $nivel_desempeno,
                'estado' => $estado_entrega,
                'estudiante' => $entrega['estudiante_nombre'] . ' ' . $entrega['estudiante_apellido'],
                'actividad' => $entrega['actividad_titulo'],
                'grupo' => $entrega['grupo_nombre'],
                'fecha_calificacion' => date('d/m/Y H:i'),
                'comentarios' => $comentarios,
                'retroalimentacion' => $retroalimentacion
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
        "[%s] ERROR - Docente %s (%d) al calificar entrega %d: %s",
        date('Y-m-d H:i:s'),
        $_SESSION['usuario'] ?? 'desconocido',
        $_SESSION['id'] ?? 0,
        $entrega_id ?? 0,
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