<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Indicadores del Docente</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>📚 Docente</h2>
        <p>Indicadores</p>
      </div>
      <ul class="nav-menu">
         <li><a href="docente_dashboard.php" class="btn-panel">Panel</a></li>
        <li><a href="gestionar_guias.php" class="nav-link"><i></i>📄 Guías</a></li>
        <li><a href="gestionar_estudiantes.php" class="nav-link"><i></i>👥 Estudiantes</a></li>
        <li><a href="reportes_docente.php" class="nav-link"><i></i>📊 Reportes</a></li>
        <li><a href="indicadores_docente.php" class="nav-link"><i></i>📝 Indicadores</a></li>
        <li><a href="gestionar_archivos.php" class="nav-link"><i ></i>📁 Archivos</a></li>
        <li><a href="configuracion_docente.php" class="nav-link"><i></i>👤 Perfil</a></li>
      </ul>
    </div>
    <div class="main-content">
      <div class="header">
        <h1>Indicadores</h1>
        <p>Métricas de desempeño académico.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-success">🧮</div>
          <h3 class="card-title">Panel</h3>
        </div>
        <div class="card-content">
          <p>En construcción. Aquí se visualizarán indicadores (tabla indicadores_periodo).</p>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>