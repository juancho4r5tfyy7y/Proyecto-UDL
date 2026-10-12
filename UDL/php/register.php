<?php
require_once __DIR__ . '/conexion.php';

// DEBUG eliminado para producción
error_reporting(E_ALL);
ini_set('display_errors', 1);

// detectar petición AJAX desde el form JS
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

// Función para mostrar mensaje y redirigir (ahora soporta AJAX)
function mostrarMensaje($mensaje, $tipo = 'error')
{
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $tipo === 'error' ? 'error' : 'success',
            'message' => $mensaje,
            // opcional: 'redirect' => '../index.php' cuando success y quieras redirigir
        ]);
        exit;
    }

    // comportamiento antiguo (HTML)
    $color = $tipo === 'error' ? '#ffd6d6' : '#d6ffd6';
    $textColor = $tipo === 'error' ? '#8a0000' : '#006600';
    if ($tipo === 'success') {
        $enlace = '../index.php';
        $textoBoton = 'Ir al Inicio';
    } else {
        $enlace = 'javascript:history.back()';
        $textoBoton = 'Volver';
    }
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Registro</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; }
            .mensaje { padding: 15px; border-radius: 5px; margin: 20px 0; }
            .btn { padding: 10px 20px; background: #c70000; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='mensaje' style='background: $color; color: $textColor;'>
            $mensaje
        </div>
        <a href='$enlace' class='btn'>$textoBoton</a>
    </body>
    </html>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Obtener conexión a la base de datos
        $conn = conexion();
        if (!$conn) {
            mostrarMensaje("Error de conexión a la base de datos.");
        }

        // Verificar que todos los campos requeridos estén presentes
        $campos_requeridos = ['nombre', 'email', 'clave', 'rol', 'tipo'];
        foreach ($campos_requeridos as $campo) {
            if (empty($_POST[$campo])) {
                mostrarMensaje("El campo '$campo' es requerido.");
            }
        }

        $nombre = $_POST['nombre'];
        $apellidos = $_POST['apellidos'] ?? '';
        $usuario = $_POST['usuario'] ?? '';
        $email = $_POST['email'];
        $clave = $_POST['clave'];
        $rol = $_POST['rol'];
        $tipo = $_POST['tipo'];
        $documento = $_POST['documento'] ?? '';
        $contacto = $_POST['contacto'] ?? '';
        // Grado: recibimos el valor '6'..'11' cuando rol = estudiante
        $grado_raw = $_POST['grado'] ?? '';
        $grados_permitidos = ['6', '7', '8', '9', '10', '11'];
        $grado_db = null;

        // Nombre del campo que recibe la fecha en tu formulario: 'nacimiento'
        $nacimiento_raw = $_POST['nacimiento'] ?? '';

        // Conversión segura de la fecha a formato Y-m-d
        $nacimiento_db = null;
        if (trim($nacimiento_raw) !== '') {
            $d = DateTime::createFromFormat('d/m/Y', trim($nacimiento_raw)) ?: DateTime::createFromFormat('Y-m-d', trim($nacimiento_raw));
            if ($d) {
                $nacimiento_db = $d->format('Y-m-d');
                // Validación: mínimo 5 años
                $hoy = new DateTime();
                $edad = $hoy->diff($d)->y;
                if ($edad < 5) {
                    mostrarMensaje("Debes tener al menos 5 años para registrarte.");
                }
            } else {
                mostrarMensaje("Formato de fecha de nacimiento inválido. Usa YYYY-MM-DD o DD/MM/YYYY.");
            }
        } else {
            // Si la fecha es obligatoria para ciertos roles, validar aquí:
            if ($rol === 'estudiante') {
                mostrarMensaje("La fecha de nacimiento es obligatoria para estudiantes.");
            }
        }

        // Separar apellidos en dos partes
        $apellidos_array = explode(' ', trim($apellidos));
        $apellido1 = $apellidos_array[0] ?? '';
        $apellido2 = $apellidos_array[1] ?? '';

        // Guardar contraseña en texto plano (recomendado: usar password_hash)
        $passwordHash = $clave;

        // Mapear tipo de documento a los permitidos en la tabla estudiantes
        $tipoDocumento = $tipo;
        $tipoDocumentoPermitidos = ['TI', 'CC', 'CE', 'Pasaporte', 'PPT', 'PP', 'NIT'];
        if ($tipo === 'PP') {
            $tipoDocumento = 'Pasaporte';
        } elseif (!in_array($tipo, $tipoDocumentoPermitidos, true)) {
            // Valor no reconocido para estudiantes, usar 'CC' por defecto
            $tipoDocumento = 'CC';
        }

        // Verifica si el usuario o el email ya existen
        $check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ?");
        if (!$check) {
            mostrarMensaje("Error en la preparación de la consulta de verificación: " . $conn->error);
        }
        $check->bind_param("ss", $usuario, $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            mostrarMensaje("El usuario o el correo ya están registrados.");
        }
        $check->close();

        // Insert en usuarios (usar la fecha ya convertida)
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellidos, usuario, email, clave, rol, tipo, documento, contacto, nacimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            mostrarMensaje("Error en la preparación de la consulta de inserción: " . $conn->error);
        }
        // Si no hay fecha, enviamos cadena vacía para evitar NULL binding problems; ajusta según tu esquema
        $fecha_para_bd = $nacimiento_db ?? '';
        $stmt->bind_param("ssssssssss", $nombre, $apellidos, $usuario, $email, $passwordHash, $rol, $tipo, $documento, $contacto, $fecha_para_bd);

        // Iniciar transacción para asegurar integridad
        $conn->begin_transaction();

        if ($stmt->execute()) {
            $usuario_id = $stmt->insert_id;

            // Normalizar: 'admin' equivale a 'docente'
            if ($rol == 'admin' || $rol == 'docente') {
                 $nombre_completo = $nombre . ' ' . $apellidos;
                 $stmt2 = $conn->prepare("INSERT INTO docentes (usuario_id, nombre_completo) VALUES (?, ?)");
                 if (!$stmt2) {
                     $conn->rollback();
                     mostrarMensaje("Error al preparar la inserción en docentes: " . $conn->error);
                 }
                 $stmt2->bind_param("is", $usuario_id, $nombre_completo);
                 if (!$stmt2->execute()) {
                     $conn->rollback();
                     mostrarMensaje("Error al insertar en docentes: " . $stmt2->error);
                 }
                 $stmt2->close();
             } elseif ($rol == 'estudiante') {
                // validar grado elegido
                if (!isset($_POST['grado']) || trim($_POST['grado']) === '') {
                    mostrarMensaje("Debes seleccionar un grado para estudiantes.");
                }
                $grado_raw = $_POST['grado'];
                if (!in_array($grado_raw, $grados_permitidos, true)) {
                    mostrarMensaje("Grado inválido.");
                }
                $grado_db = $grado_raw;

                // Verificar que el usuario_id exista antes de insertar en estudiantes
                $check_user = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
                if (!$check_user) {
                    $conn->rollback();
                    mostrarMensaje("Error al verificar el usuario: " . $conn->error);
                }
                $check_user->bind_param("i", $usuario_id);
                $check_user->execute();
                $check_user->store_result();

                if ($check_user->num_rows === 0) {
                    $conn->rollback();
                    mostrarMensaje("Error: No se pudo crear el perfil de estudiante porque el usuario no existe.");
                }
                $check_user->close();

                // Insertar en estudiantes (usar la columna 'grado' existente en estudiantes)
                $nombre_est = $nombre;
                $stmt3 = $conn->prepare("INSERT INTO estudiantes (usuario_id, tipo_documento, documento, nombre, apellido1, apellido2, fecha_nacimiento, grado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt3) {
                    $conn->rollback();
                    mostrarMensaje("Error al preparar la inserción en estudiantes: " . $conn->error);
                }
                $stmt3->bind_param("isssssss", $usuario_id, $tipoDocumento, $documento, $nombre_est, $apellido1, $apellido2, $fecha_para_bd, $grado_db);
                if (!$stmt3->execute()) {
                    $conn->rollback();
                    mostrarMensaje("Error al insertar en estudiantes: " . $stmt3->error);
                }
                $stmt3->close();

                // También registrar en la tabla 'grado' (nombre completo, documento y tipo de documento)
                $nombre_completo_est = trim($nombre . ' ' . $apellidos);
                $insGrado = $conn->prepare("INSERT INTO grado (grado, nombre_completo, documento, tipo_documento, usuario_id) VALUES (?, ?, ?, ?, ?)");
                if ($insGrado) {
                    $insGrado->bind_param("ssssi", $grado_db, $nombre_completo_est, $documento, $tipoDocumento, $usuario_id);
                    if (!$insGrado->execute()) {
                        // Registrar el error pero no bloquear el registro
                        error_log("Error al registrar en tabla grado: " . $insGrado->error);
                    }
                    $insGrado->close();
                } else {
                    // Registrar el error pero no bloquear el registro
                    error_log("Error al preparar la inserción en tabla grado: " . $conn->error);
                }
            }

            // Confirmar la transacción si todo salió bien
            $conn->commit();

            $stmt->close();
            $conn->close();

            mostrarMensaje("Registro exitoso. Ya puedes iniciar sesión.", "success");
        } else {
            // Revertir la transacción en caso de error
            $conn->rollback();
            mostrarMensaje("Error al registrar: " . $stmt->error);
        }
    } catch (Exception $e) {
        // Asegurar que se revierta la transacción en caso de excepción
        if ($conn->connect_errno === 0) { // Verificar que la conexión esté activa
            $conn->rollback();
        }
        mostrarMensaje("Error inesperado: " . $e->getMessage());
    }
} else {
    mostrarMensaje("Método no permitido.");
}
