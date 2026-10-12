<?php
require_once '../config/database.php';

// session_start();
//
// // Verificar autenticación y rol de administrador
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     // Si no es admin, redirigir a la página de inicio o de login
//     header('Location: ../../index.php');
//     exit;
// }

// Verificar si se ha proporcionado un ID de usuario
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = (int)$_GET['id'];

    $conn = getDatabaseConnection();

    // Preparar la consulta para evitar inyección SQL
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Éxito: redirigir a la página de usuarios con un mensaje de éxito
        // $_SESSION['message'] = "Usuario eliminado correctamente.";
    } else {
        // Error: redirigir con un mensaje de error
        // $_SESSION['error'] = "Error al eliminar el usuario: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    // Si no se proporciona un ID válido, redirigir con un mensaje de error
    // $_SESSION['error'] = "ID de usuario no válido.";
}

// Redirigir siempre de vuelta a la lista de usuarios
header('Location: ../views/admin/usuarios.php');
exit;
?>
