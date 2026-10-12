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
  <title>Reportes del Docente</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>📚 Docente</h2>
        <p>Reportes</p>
      </div>
      <ul class="nav-menu">
            <li><a href="docente_dashboard.php" class="btn-panel"><i></i> Panel</a></li>
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
        <h1>Reportes</h1>
        <p>Visualiza reportes y estadísticas.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-info">📈</div>
          <h3 class="card-title">Resumen</h3>
        </div>
        <div class="card-content">
          <p>En construcción. Aquí se mostrarán indicadores clave.</p>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>