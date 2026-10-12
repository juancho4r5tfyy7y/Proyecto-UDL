<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'estudiante') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mi Progreso</title>
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
        <h1>Mi Progreso</h1>
        <p>Visualiza tu progreso por unidades y criterios.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-warning">📊</div>
          <h3 class="card-title">Progreso</h3>
        </div>
        <div class="card-content">
          <div class="progress-bar">
            <div class="progress-fill" style="width: 60%"></div>
          </div>
          <p class="stat-number">60%</p>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>