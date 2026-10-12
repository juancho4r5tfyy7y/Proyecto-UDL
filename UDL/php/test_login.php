<?php
require_once __DIR__ . '/conexion.php';

echo "<h2>Prueba de Usuarios y Roles</h2>";

// Verificar conexión
if ($conn->connect_error) {
    echo "<p style='color: red;'>Error de conexión: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>✓ Conexión exitosa</p>";
}

// Consultar usuarios existentes
$result = $conn->query("SELECT id, nombre, usuario, email, rol FROM usuarios ORDER BY rol, nombre");

if ($result && $result->num_rows > 0) {
    echo "<h3>Usuarios disponibles para pruebas:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Dashboard</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $dashboard = '';
        switch ($row['rol']) {
            case 'superadmin':
                $dashboard = 'superadmin_dashboard.php';
                break;
            case 'admin':
                $dashboard = 'docente_dashboard.php';
                break;
            case 'estudiante':
                $dashboard = 'estudiante_dashboard.php';
                break;
            default:
                $dashboard = 'index.php';
        }
        
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['usuario']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rol']) . "</td>";
        echo "<td><a href='../$dashboard' target='_blank'>Ir al Dashboard</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Instrucciones de prueba:</h3>";
    echo "<ol>";
    echo "<li>Ve a <a href='../php/login.php' target='_blank'>php/login.php</a></li>";
    echo "<li>Usa cualquiera de los usuarios de la tabla arriba</li>";
    echo "<li>La contraseña para todos es: <strong>pass123</strong></li>";
    echo "<li>Deberías ser redirigido automáticamente al dashboard correspondiente</li>";
    echo "</ol>";
    
} else {
    echo "<p style='color: red;'>No se encontraron usuarios en la base de datos.</p>";
}

$conn->close();
?>
