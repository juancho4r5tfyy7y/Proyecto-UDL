<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Superadmin - Usuarios</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>👥 Usuarios</h2>
        <p>Superadmin</p>
      </div>
      <ul class="nav-menu">
        <li class="nav-item"><a href="superadmin_dashboard.php" class="nav-link">⬅ Volver al Panel</a></li>
        <li class="nav-item"><a href="index.php" class="nav-link">🏠 Inicio</a></li>
      </ul>
    </div>
    <div class="main-content">
      <div class="header">
        <h1>Gestión de Usuarios</h1>
        <p>Crear, editar y eliminar usuarios.</p>
      </div>
      <div class="dashboard-grid">
        <div class="card">
          <div class="card-header">
            <div class="card-icon bg-primary">➕</div>
            <h3 class="card-title">Nuevo Usuario</h3>
          </div>
          <div class="card-content">
            <p>Crear cuentas para docentes o estudiantes.</p>
            <a class="btn-primary" href="#">Crear</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>


