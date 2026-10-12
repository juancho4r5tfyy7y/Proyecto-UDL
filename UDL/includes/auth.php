<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario actual tiene permisos para subir archivos.
 *
 * @return bool True si el usuario puede subir archivos, false en caso contrario.
 */
function can_upload_files() {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }

    $role = $_SESSION['rol'];

    // Docentes y superadministradores siempre pueden subir archivos.
    if ($role === 'docente' || $role === 'superadmin') {
        return true;
    }

    // Los estudiantes no pueden subir archivos.
    if ($role === 'estudiante') {
        return false;
    }

    return false;
}

/**
 * Verifica si un docente tiene permiso para gestionar un grupo específico.
 *
 * @param int $docente_id El ID del docente.
 * @param int $grupo_id El ID del grupo.
 * @return bool True si el docente puede gestionar el grupo, false en caso contrario.
 */
function can_manage_group($docente_id, $grupo_id) {
    $conexion = getDatabaseConnection();
    $sql = "SELECT id FROM grupos WHERE id = ? AND docente_id = ? AND activo = 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $grupo_id, $docente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows === 1;
}
?>
