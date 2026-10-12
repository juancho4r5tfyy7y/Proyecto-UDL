<?php
session_start();
require_once __DIR__ . '/conexion.php';
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    // Obtener la conexión a la base de datos
    $conn = conexion();
    if (!$conn) {
        $error = 'Error de conexión a la base de datos';
        error_log('Error de conexión en login.php');
        header("Location: ../index.php?error=" . urlencode($error));
        exit;
    }

    // Traemos también el nombre real para mostrar en la sesión
    $stmt = $conn->prepare("SELECT id, usuario, nombre, clave, rol FROM usuarios WHERE usuario = ? OR email = ?");
    $stmt->bind_param("ss", $nombre_usuario, $nombre_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
        $usuarioDB = $row['usuario'];
        $nombreReal = $row['nombre'];
        $hash = $row['clave'];
        // Normalizar texto de rol desde BD (minúsculas, sin espacios)
        $rol = strtolower(trim($row['rol']));
        // 'admin' debe tratarse como 'docente'
        $rol_normalizado = ($rol === 'admin') ? 'docente' : $rol;

        $loginExitoso = false;

        // Por ahora, comparación directa (TEMPORAL, debe cambiarse a password_verify en producción)
        if ($clave === $hash) {
            $loginExitoso = true;
            error_log("Login exitoso para usuario: $usuarioDB con rol: $rol (normalizado a: $rol_normalizado)");
        } else {
            error_log("Error de contraseña para usuario: $usuarioDB");
        }

        if ($loginExitoso) {
            // Limpiar sesión anterior si existe
            session_unset();

            // Establecer datos de sesión básicos
            $_SESSION['usuario'] = $usuarioDB;
            $_SESSION['nombre'] = $nombreReal;
            $_SESSION['rol'] = $rol_normalizado;
            $_SESSION['id'] = $id; // ID principal
            $_SESSION['usuario_id'] = $id;
            $_SESSION['user_id'] = $id;

            // Verificar si hay un redirect solicitado
            $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? $_REQUEST['redirect'] ?? '';

            // Redirección según el rol y el redirect solicitado
            $destino = match ($rol_normalizado) {
                'estudiante' => match ($redirect) {
                    'actividades' => '../views/estudiante/actividades.php',
                    'material' => '../views/estudiante/material.php',
                    'progreso' => '../views/estudiante/progreso.php',
                    default => '../estudiante_dashboard.php'
                },
                'docente' => '../docente_dashboard.php',
                'superadmin' => '../superadmin_dashboard.php',
                default => '../index.php'
            };

            error_log("Redirigiendo a: $destino con método de reemplazo de historial.");
            // Usamos una redirección 303 para asegurar que la siguiente petición sea GET
            // y reemplazamos la cabecera para evitar conflictos.
            header("Location: $destino", true, 303);
            exit();

            // Si es estudiante, obtener el grado directamente de la tabla grado
            if ($rol_normalizado === 'estudiante') {
                error_log("Buscando grado para estudiante ID: " . $id);

                // Consulta mejorada para obtener el grado
                $stmt_grado = $conn->prepare("
                     SELECT g.grado 
                     FROM usuarios u 
                     JOIN grado g ON u.id = g.usuario_id 
                     WHERE u.id = ? 
                     ORDER BY g.creado_en DESC 
                     LIMIT 1
                 ");

                if ($stmt_grado) {
                    $stmt_grado->bind_param("i", $id);
                    if ($stmt_grado->execute()) {
                        $result_grado = $stmt_grado->get_result();
                        if ($row_grado = $result_grado->fetch_assoc()) {
                            $_SESSION['grado'] = $row_grado['grado'];
                            error_log("Grado encontrado: " . $row_grado['grado']);
                        } else {
                            error_log("No se encontró grado para el estudiante ID: " . $id);
                            $_SESSION['grado'] = 'No asignado';
                        }
                    } else {
                        error_log("Error ejecutando consulta de grado: " . $stmt_grado->error);
                        $_SESSION['grado'] = 'Error al obtener grado';
                    }
                    $stmt_grado->close();
                } else {
                    error_log("Error preparando consulta de grado: " . $conn->error);
                    $_SESSION['grado'] = 'Error al obtener grado';
                }
            }

            // Ya se ha redirigido anteriormente con el match, este bloque es redundante
            // y causa conflictos. Lo eliminamos para evitar redirecciones duplicadas.
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Usuario no encontrado.';
    }

    // Cerrar el statement y la conexión
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div class="login-container">
        <h1>Iniciar Sesión</h1>
        <?php if (!empty($error)): ?>
            <div style="background:#ffd6d6; color:#8a0000; padding:10px 12px; border-radius:6px; margin-bottom:12px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="input-box">
                <span class="details">Usuario o Email</span>
                <input type="text" name="usuario" required>
            </div>
            <div class="input-box">
                <span class="details">Contraseña</span>
                <input type="password" name="clave" required>
            </div>
            <div class="button">
                <input type="submit" value="Entrar">
            </div>
        </form>
        <p>¿No tienes cuenta? <a href="../form_reg.html">Regístrate</a></p>
    </div>
</body>

</html>
