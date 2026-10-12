<?php
require_once 'conexion.php';

$conn = conexion();
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Consultar tabla estudiantes
$result = $conn->query("DESCRIBE estudiantes");
echo "Estructura de la tabla estudiantes:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n";

// Consultar datos
$stmt = $conn->prepare("SELECT u.id, u.nombre, u.rol, e.grado, g.grado as grado_tabla 
                       FROM usuarios u 
                       LEFT JOIN estudiantes e ON e.usuario_id = u.id 
                       LEFT JOIN grado g ON g.usuario_id = u.id 
                       WHERE u.rol = 'estudiante'");
$stmt->execute();
$result = $stmt->get_result();

echo "Datos de estudiantes:\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . 
         ", Nombre: " . $row['nombre'] . 
         ", Grado (estudiantes): " . ($row['grado'] ?? 'NULL') . 
         ", Grado (tabla grado): " . ($row['grado_tabla'] ?? 'NULL') . "\n";
}

$conn->close();
?>
