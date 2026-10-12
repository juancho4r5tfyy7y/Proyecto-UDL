<?php
session_start();

if (!isset($_SESSION['rol'])) {
    header('Location: index.php');
    exit();
}

// Redirigir según el rol
switch ($_SESSION['rol']) {
    case 'estudiante':
        header('Location: /UDL/estudiante');
        break;
    case 'docente':
        header('Location: /UDL/docente_dashboard.php');
        break;
    case 'superadmin':
        header('Location: /UDL/superadmin_dashboard.php');
        break;
    default:
        header('Location: index.php');
}
exit();
?>

<?php
include "includes/sesion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <header>
        <div class="container">
            <p class="logo">Universidad de Lideres</p>
            <nav>
                <a href="index.php">Inicio</a>
                <a href="logout.php">Cerrar Sesion</a>
            </nav>
        </div>
    </header>
    <main>
        <div class="container" style="padding-top: 100px; text-align: center;">
            <h1>Bienvenido al Panel de Administración, <?php echo $_SESSION['name']; ?></h1>
            <p>Aquí puedes gestionar tu sitio.</p>
        </div>
    </main>
</body>
</html>
