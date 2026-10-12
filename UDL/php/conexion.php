<?php
function conexion() {
    try {
        // Datos de conexión
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $db = 'unidad_didactica';

        // Suprimir advertencias de mysqli
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // Crear conexión
        $conn = new mysqli($host, $user, $pass, $db);

        // Configurar charset
        if (!$conn->set_charset('utf8mb4')) {
            throw new Exception('Error al configurar charset: ' . $conn->error);
        }

        return $conn;
    } catch (Exception $e) {
        error_log('Error en conexion.php: ' . $e->getMessage());
        return null;
    }
}
?>