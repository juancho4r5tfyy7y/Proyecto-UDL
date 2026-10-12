<?php
session_start();
header('Content-Type: application/json');

require_once 'conexion.php';

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'No hay sesión activa']);
    exit;
}

// Verificar ID de usuario
$usuario_id = $_SESSION['id'] ?? null;

if (!$usuario_id) {
    error_log('ID de usuario no encontrado en sesión: ' . print_r($_SESSION, true));
    echo json_encode(['error' => 'ID de usuario no encontrado']);
    exit;
}

// Obtener conexión
$conn = conexion();
if (!$conn) {
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    // Consulta para obtener datos incluyendo el grado más reciente
    $query = "SELECT u.nombre, u.apellidos, u.email, u.contacto as telefono,
                     u.nacimiento as fecha_nacimiento, g.grado, e.nombre as nombre_estudiante,
                     e.apellido1, e.apellido2
              FROM usuarios u
              LEFT JOIN estudiantes e ON u.id = e.usuario_id
              LEFT JOIN (
                  SELECT usuario_id, grado
                  FROM grado
                  WHERE usuario_id = ?
                  ORDER BY creado_en DESC
                  LIMIT 1
              ) g ON u.id = g.usuario_id
              WHERE u.id = ?";
    
    if (!($stmt = $conn->prepare($query))) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $usuario_id, $usuario_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    
    if ($usuario) {
        // Usar los datos de la tabla estudiantes si están disponibles (tienen mejor estructura)
        if (!empty($usuario['apellido1']) || !empty($usuario['apellido2'])) {
            // Los datos vienen de la tabla estudiantes (mejor estructurados)
            $usuario['apellido1'] = $usuario['apellido1'] ?? '';
            $usuario['apellido2'] = $usuario['apellido2'] ?? '';
        } else {
            // Fallback: separar apellidos de la tabla usuarios
            if (!empty($usuario['apellidos'])) {
                $apellidos = explode(' ', $usuario['apellidos'], 2);
                $usuario['apellido1'] = $apellidos[0] ?? '';
                $usuario['apellido2'] = $apellidos[1] ?? '';
            } else {
                $usuario['apellido1'] = '';
                $usuario['apellido2'] = '';
            }
        }

        // Limpiar campos que no necesitamos en el frontend
        unset($usuario['apellidos']);

        // Formatear la fecha de nacimiento
        if (!empty($usuario['fecha_nacimiento'])) {
            $usuario['fecha_nacimiento'] = date('Y-m-d', strtotime($usuario['fecha_nacimiento']));
        }

        error_log('Datos obtenidos: ' . print_r($usuario, true));
        echo json_encode($usuario);
    } else {
        echo json_encode(['error' => 'Usuario no encontrado']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log('Error en obtener_datos_usuario.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al obtener los datos: ' . $e->getMessage()]);
}

$conn->close();
?>
