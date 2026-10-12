<?php
session_start();
$rol = $_SESSION['rol'] ?? '';
if (!isset($_SESSION['usuario']) || !in_array($rol, ['admin', 'docente'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Docente - Reportes</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>📊 Reportes</h2>
        <p>Docente</p>
      </div>
      <ul class="nav-menu">
        <li class="nav-item"><a href="docente_dashboard.php" class="nav-link">⬅ Volver al Panel</a></li>
        <li class="nav-item"><a href="index.php" class="nav-link">🏠 Inicio</a></li>
      </ul>
    </div>
    <div class="main-content">
      <div class="header">
        <h1>Reportes y Estadísticas</h1>
        <p>Resumenes y gráficas de tus grupos.</p>
      </div>
      <div class="dashboard-grid">
        <div class="card">
          <div class="card-header">
            <div class="card-icon bg-primary">📈</div>
            <h3 class="card-title">Progreso por grupo</h3>
          </div>
          <div class="card-content">
            <p>Visualiza KPIs clave por grupo.</p>
            <a class="btn-primary" href="#">Abrir</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>


