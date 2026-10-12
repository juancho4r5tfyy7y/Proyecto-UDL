<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <div class="logo">
    <h2>👨‍🏫 UDL Docente</h2>
    <p>Panel de Gestión Educativa</p>
  </div>
  <ul class="nav-menu">
    <li class="nav-item">
      <a href="/UDL/docente_dashboard.php" class="nav-link <?php echo ($current_page == 'docente_dashboard.php') ? 'active' : ''; ?>">
        🏠 Panel
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/views/docente/gestionar_actividades.php" class="nav-link <?php echo ($current_page == 'gestionar_actividades.php' || $current_page == 'crear_actividad.php') ? 'active' : ''; ?>">
        📝 Actividades
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/views/docente/estudiantes.php" class="nav-link <?php echo ($current_page == 'estudiantes.php') ? 'active' : ''; ?>">
        👥 Estudiantes
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/views/docente/material.php" class="nav-link <?php echo ($current_page == 'material.php') ? 'active' : ''; ?>">
        📚 Material
      </a>
    </li>
    <li class="nav-item">
      <a href="/UDL/personalizacion_docente.php" class="nav-link <?php echo ($current_page == 'personalizacion_docente.php') ? 'active' : ''; ?>">
        👤 Perfil
      </a>
    </li>
  </ul>
</div>
