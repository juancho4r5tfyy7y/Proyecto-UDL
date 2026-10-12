<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <div class="logo">
    <h2>📚 UDL Estudiante</h2>
    <p>Unidad Didáctica de Lenguaje</p>
  </div>
  <ul class="nav-menu">
    <li class="nav-item">
      <a href="/UDL/estudiante_dashboard.php" class="nav-link <?php echo ($current_page == 'estudiante_dashboard.php') ? 'active' : ''; ?>">
        🏠 Panel
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/views/estudiante/actividades.php" class="nav-link <?php echo ($current_page == 'actividades.php') ? 'active' : ''; ?>">
        📝 Actividades
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/views/estudiante/material.php" class="nav-link <?php echo ($current_page == 'material.php') ? 'active' : ''; ?>">
        📖 Material
      </a>
    </li>
     <li class="nav-item">
      <a href="/UDL/views/estudiante/grado_estudiante.php" class="nav-link <?php echo ($current_page == 'grado_estudiante.php') ? 'active' : ''; ?>">
        🎓 Mi Grado
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/personalizacion_estudiante.php" class="nav-link <?php echo ($current_page == 'personalizacion_estudiante.php') ? 'active' : ''; ?>">
        👤 Perfil
      </a>
    </li>
  </ul>
</div>
