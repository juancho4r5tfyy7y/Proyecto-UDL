<?php
// Evitar que se muestre cualquier error en la salida
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Iniciar buffer de salida
ob_start();

// Función para enviar respuesta JSON y terminar
function enviar_respuesta($data) {
    // Limpiar cualquier salida anterior
    if (ob_get_length()) ob_clean();

    // Asegurar que se envíe como JSON
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Manejador de errores personalizado
function exception_error_handler($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr en $errfile:$errline");
    enviar_respuesta(['success' => false, 'message' => 'Error interno del servidor']);
}
set_error_handler("exception_error_handler");

// Iniciar sesión
session_start();

try {
    require_once 'conexion.php';

    if (!isset($_SESSION['usuario'])) {
        enviar_respuesta(['success' => false, 'message' => 'No hay sesión activa']);
    }

    $conn = conexion();
    if (!$conn) {
        enviar_respuesta(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    }

    // Validar y obtener datos del POST
    $nombre = trim($_POST['nombre'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validaciones básicas
    if (empty($nombre) || strlen($nombre) < 3) {
        enviar_respuesta(['success' => false, 'message' => 'El nombre debe tener al menos 3 caracteres']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        enviar_respuesta(['success' => false, 'message' => 'El formato del email no es válido']);
    }

    $usuario_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;

    if (!$usuario_id) {
        error_log('ID de usuario no encontrado en sesión: ' . print_r($_SESSION, true));
        enviar_respuesta(['success' => false, 'message' => 'Error: ID de usuario no encontrado']);
    }

    // Verificar email único
    $check_email = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
    if (!($stmt = $conn->prepare($check_email))) {
        enviar_respuesta(['success' => false, 'message' => 'Error al preparar la verificación de email']);
    }

    $stmt->bind_param("si", $email, $usuario_id);
    if (!$stmt->execute()) {
        enviar_respuesta(['success' => false, 'message' => 'Error al verificar el email']);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        enviar_respuesta(['success' => false, 'message' => 'El correo electrónico ya está en uso por otro usuario']);
    }
    $stmt->close();

    // Construir nombre completo y apellidos separados para usuarios
    $nombre_completo = trim($nombre);
    $apellido1 = trim($_POST['apellido1'] ?? '');
    $apellido2 = trim($_POST['apellido2'] ?? '');

    // Construir apellidos completos para la tabla usuarios
    $apellidos_completos = '';
    if (!empty($apellido1)) {
        $apellidos_completos .= $apellido1;
    }
    if (!empty($apellido2)) {
        $apellidos_completos .= ' ' . $apellido2;
    }
    $apellidos_completos = trim($apellidos_completos);

    // Preparar la actualización de usuarios
    $sql = "UPDATE usuarios SET nombre = ?, apellidos = ?, email = ?, contacto = ?";
    $params = [$nombre_completo, $apellidos_completos, $email, $telefono];
    $tipos = "ssss";

    // Agregar fecha de nacimiento si se proporcionó
    if (!empty($_POST['fecha_nacimiento'])) {
        $sql .= ", nacimiento = ?";
        $params[] = $_POST['fecha_nacimiento'];
        $tipos .= "s";
    }

    // Agregar contraseña si se proporcionó
    if (!empty($password)) {
        $sql .= ", clave = ?";
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $params[] = $hash;
        $tipos .= "s";
    }

    $sql .= " WHERE id = ?";
    $params[] = $usuario_id;
    $tipos .= "i";

    // Preparar y ejecutar la consulta
    if (!($stmt = $conn->prepare($sql))) {
        enviar_respuesta(['success' => false, 'message' => 'Error al preparar la actualización']);
    }

    // Bind parameters
    $stmt->bind_param($tipos, ...$params);

    if (!$stmt->execute()) {
        enviar_respuesta(['success' => false, 'message' => 'Error al actualizar los datos: ' . $stmt->error]);
    }

    if ($stmt->affected_rows >= 0) {
        $_SESSION['nombre'] = $nombre_completo;
        
        // Si el usuario es estudiante, actualizar también la tabla estudiantes
        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'estudiante') {
            $stmt->close(); // Cerrar el statement anterior
            
            // Actualizar la tabla estudiantes con todos los campos
            $sql_estudiante = "UPDATE estudiantes SET 
                nombre = ?, 
                apellido1 = ?, 
                apellido2 = ?,
                fecha_nacimiento = ?,
                grado = ?
                WHERE usuario_id = ?";
                
            if ($stmt = $conn->prepare($sql_estudiante)) {
                $fecha_nac = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
                $grado = !empty($_POST['grado']) ? $_POST['grado'] : null;
                $apellido1 = trim($_POST['apellido1'] ?? '');
                $apellido2 = trim($_POST['apellido2'] ?? '');
                
                $stmt->bind_param("sssssi", 
                    $nombre,
                    $apellido1,
                    $apellido2,
                    $fecha_nac,
                    $grado,
                    $usuario_id
                );
                
                if (!$stmt->execute()) {
                    error_log("Error al actualizar tabla estudiantes: " . $stmt->error);
                }
                
                $stmt->close();
            } else {
                error_log("Error al preparar la actualización en estudiantes: " . $conn->error);
            }
        }
        
        enviar_respuesta([
            'success' => true,
            'message' => 'Datos actualizados correctamente'
        ]);
    } else {
        enviar_respuesta(['success' => false, 'message' => 'No se pudo actualizar los datos']);
    }

    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    $conn->close();

} catch (Exception $e) {
    error_log('Error en actualizar_cuenta.php: ' . $e->getMessage());
    enviar_respuesta([
        'success' => false,
        'message' => 'Error al actualizar los datos: ' . $e->getMessage()
    ]);
}
?>
