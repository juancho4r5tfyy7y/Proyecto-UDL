<?php
session_start();
require_once '../../php/conexion.php';

// Verifica que el usuario esté autenticado y tenga el rol de 'superadmin'.
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: ../../index.php');
    exit;
}

$conexion = conexion();
$sql = "SELECT id, nombre, apellidos, email, rol FROM usuarios";
$result = $conexion->query($sql);

$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - UDL</title>
    <link rel="stylesheet" href="../../assets/css/Dashboard.css">
    <style>
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th, .table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .table th {
            background-color: #f4f4f4;
        }
        .btn {
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
        }
        .btn-primary {
            background-color: #007bff;
        }
        .btn-danger {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/navigation_superadmin.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Gestionar Usuarios</h1>
                <p>Administra todos los usuarios del sistema.</p>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['rol']); ?></td>
                            <td>
                                <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">Editar</a>
                                <a href="../../controllers/eliminar_usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar a este usuario?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
