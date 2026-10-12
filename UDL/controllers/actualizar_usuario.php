<?php
require_once '../config/database.php';

// Autenticación y autorización (descomentar cuando esté listo)
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: ../../index.php');
//     exit;
// }

// Verificar que la solicitud sea de tipo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y sanitizar los datos de entrada
    $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $apellidos = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';

    // Validaciones básicas
    if ($userId > 0 && !empty($nombre) && !empty($apellidos) && filter_var($email, FILTER_VALIDATE_EMAIL) && in_array($rol, ['estudiante', 'admin', 'superadmin'])) {
        
        $conn = getDatabaseConnection();

        // Preparar la consulta para evitar inyección SQL
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, email = ?, rol = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $nombre, $apellidos, $email, $rol, $userId);

        // Ejecutar la consulta
        if ($stmt->execute()) {
            // Éxito: redirigir a la página de usuarios con un mensaje de éxito
            // $_SESSION['message'] = "Usuario actualizado correctamente.";
        } else {
            // Error: redirigir con un mensaje de error
            // $_SESSION['error'] = "Error al actualizar el usuario: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();

    } else {
        // Datos de entrada no válidos
        // $_SESSION['error'] = "Por favor, complete todos los campos correctamente.";
    }
} else {
    // Si no es una solicitud POST, redirigir
    // $_SESSION['error'] = "Método de solicitud no válido.";
}

// Redirigir siempre de vuelta a la lista de usuarios
header('Location: ../views/admin/usuarios.php');
exit;
?>
