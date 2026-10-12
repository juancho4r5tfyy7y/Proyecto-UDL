<?php
session_start();

// Iniciar sesión y verificar permisos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die("Acceso denegado. Se requiere rol de superadministrador.");
}

// Incluir configuración y conexión
require_once __DIR__ . '/../config/database.php';
$conn = getDatabaseConnection();

$format = $_GET['format'] ?? 'csv';
$report_type = $_GET['type'] ?? 'general';

// Generar reporte en PDF
if ($format === 'pdf') {
    // Cargar Dompdf manualmente
    $dompdf_autoload = __DIR__ . '/../libs/dompdf/autoload.inc.php';
    if (!file_exists($dompdf_autoload)) {
        http_response_code(500);
        die("Error: No se encontró el archivo de autoload de Dompdf en 'libs/dompdf/autoload.inc.php'. Verifique que la librería fue extraída correctamente.");
    }
    require_once $dompdf_autoload;

    try {
        // Obtener lista de docentes
        $sql_docentes = "SELECT nombre, apellidos, email FROM usuarios WHERE rol = 'admin' ORDER BY apellidos, nombre";
        $res_docentes = $conn->query($sql_docentes);
        $docentes = $res_docentes ? $res_docentes->fetch_all(MYSQLI_ASSOC) : [];

        // Obtener lista de estudiantes y agruparlos por grado
        $sql_estudiantes = "SELECT nombre, apellido1, apellido2, grado FROM estudiantes ORDER BY grado, apellido1, nombre";
        $res_estudiantes = $conn->query($sql_estudiantes);
        $estudiantes_por_grado = [];
        if ($res_estudiantes) {
            while ($estudiante = $res_estudiantes->fetch_assoc()) {
                $estudiantes_por_grado[$estudiante['grado']][] = $estudiante;
            }
        }

        // Generar HTML para el PDF
        $html = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Reporte Detallado del Sistema</title>
            <style>
                body { font-family: sans-serif; margin: 20px; }
                h1, h2 { color: #333; }
                h1 { text-align: center; border-bottom: 2px solid #f2f2f2; padding-bottom: 10px;}
                h2 { margin-top: 30px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .footer { text-align: center; margin-top: 30px; font-size: 0.8em; color: #777; }
                .page-break { page-break-after: always; }
            </style>
        </head>
        <body>
            <h1>Reporte Detallado del Sistema</h1>
            <p>Fecha de generación: ' . date('d/m/Y H:i:s') . '</p>
            
            <h2>Docentes</h2>
            <table>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Email</th>
                </tr>';
        if (empty($docentes)) {
            $html .= '<tr><td colspan="2">No hay docentes registrados.</td></tr>';
        } else {
            foreach ($docentes as $docente) {
                $html .= '<tr>
                            <td>' . htmlspecialchars($docente['nombre'] . ' ' . $docente['apellidos']) . '</td>
                            <td>' . htmlspecialchars($docente['email']) . '</td>
                          </tr>';
            }
        }
        $html .= '</table>';

        if (!empty($estudiantes_por_grado)) {
            foreach ($estudiantes_por_grado as $grado => $estudiantes) {
                $html .= '<h2>Estudiantes de Grado ' . htmlspecialchars($grado) . '°</h2>
                          <table>
                            <tr>
                                <th>Nombre Completo</th>
                            </tr>';
                foreach ($estudiantes as $estudiante) {
                    $nombre_completo = htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido1'] . ' ' . $estudiante['apellido2']);
                    $html .= '<tr><td>' . $nombre_completo . '</td></tr>';
                }
                $html .= '</table>';
            }
        } else {
            $html .= '<h2>Estudiantes</h2><p>No hay estudiantes registrados.</p>';
        }

        $html .= '<div class="footer">Reporte generado por UDL - Unidad Didáctica de Lenguaje</div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("reporte_detallado_UDL.pdf", ["Attachment" => false]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        die("Error al generar el PDF: " . $e->getMessage());
    }
}

// Generar reporte en CSV (por defecto)
try {
    $sql = "SELECT id, nombre, apellidos, usuario, email, rol, creado_en FROM usuarios ORDER BY creado_en DESC";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error en la consulta de usuarios: " . $conn->error);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_usuarios_UDL.csv"');
    
    // BOM para UTF-8 en Excel
    echo "\xEF\xBB\xBF"; 
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, ['ID', 'Nombre', 'Apellidos', 'Usuario', 'Email', 'Rol', 'Fecha de Creación']);
    
    // Datos
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Error al generar el CSV: " . $e->getMessage());
}
?>
