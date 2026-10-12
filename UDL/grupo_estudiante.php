<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'estudiante') {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/php/conexion.php';
$userId = (int)($_SESSION['usuario_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mi Grupo</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>📚 Español</h2>
        <p>Unidad Didáctica</p>
      </div>
      <ul class="nav-menu">
        <li class="nav-item"><a href="estudiante_dashboard.php" class="nav-link">🏠 Panel</a></li>
        <li class="nav-item"><a href="material_didacti.html" class="nav-link">📖 Material Didáctico</a></li>
        <li class="nav-item"><a href="index.php" class="nav-link">⬅ Inicio</a></li>
      </ul>
    </div>
    <div class="main-content">
      <div class="header">
        <h1>Mi Grupo</h1>
        <p>Información básica de tu grupo.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-info">👥</div>
          <h3 class="card-title">Compañeros (ejemplo)</h3>
        </div>
        <div class="card-content">
          <ul>
            <li>Compañero A</li>
            <li>Compañero B</li>
            <li>Compañero C</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>