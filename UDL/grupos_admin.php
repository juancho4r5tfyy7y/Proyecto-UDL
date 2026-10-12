<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/php/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Grupos - Superadmin</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>🔧 Superadmin</h2>
        <p>Gestión de Grupos</p>
      </div>
      <ul class="nav-menu">
        <li class="nav-item"><a href="superadmin_dashboard.php" class="nav-link">🏠 Panel</a></li>
        <li class="nav-item"><a href="index.php" class="nav-link">⬅ Inicio</a></li>
      </ul>
    </div>
    <div class="main-content">
      <div class="header">
        <h1>Grupos</h1>
        <p>Crear y administrar grupos.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-info">👥</div>
          <h3 class="card-title">Listado</h3>
        </div>
        <div class="card-content">
          <p>En construcción. Integrar con tabla grado.</p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>