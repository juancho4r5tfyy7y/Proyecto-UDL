<?php
require_once '../../config/database.php';

// Autenticación y autorización (descomentar cuando esté listo)
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: ../../index.php');
//     exit;
// }

$userId = null;
$user = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT nombre, apellidos, email, rol FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$user) {
    // Si no se encuentra el usuario, redirigir o mostrar un error
    // $_SESSION['error'] = "Usuario no encontrado.";
    header('Location: usuarios.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <?php include '../../includes/navigation_component.php'; ?>

    <main class="container">
        <h1>Editar Usuario</h1>

        <form action="../../controllers/actualizar_usuario.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($userId); ?>">

            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
            </div>

            <div class="form-group">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($user['apellidos']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" class="form-control" required>
                    <option value="estudiante" <?php echo ($user['rol'] === 'estudiante') ? 'selected' : ''; ?>>Estudiante</option>
                    <option value="admin" <?php echo ($user['rol'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="superadmin" <?php echo ($user['rol'] === 'superadmin') ? 'selected' : ''; ?>>Superadmin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </main>

</body>
</html>
