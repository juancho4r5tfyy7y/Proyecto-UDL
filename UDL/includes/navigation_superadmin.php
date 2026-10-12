<?php
// Determina la página actual para resaltar el enlace activo en el menú.
$current_page = basename($_SERVER['PHP_SELF']);

// Define la URL base del proyecto para construir las rutas de los enlaces.
$base_url = '/UDL'; 
?>
<div class="sidebar">
  <div class="logo">
    <h2>🔧 UDL SuperAdmin</h2>
    <p>Panel de Control del Sistema</p>
  </div>
  <ul class="nav-menu">
    <li class="nav-item">
      <a href="<?= $base_url ?>/superadmin_dashboard.php" class="nav-link <?= ($current_page == 'superadmin_dashboard.php') ? 'active' : '' ?>">🏠 Panel</a>
    </li>
    <li class="nav-item">
      <a href="<?= $base_url ?>/views/admin/usuarios.php" class="nav-link <?= ($current_page == 'usuarios.php') ? 'active' : '' ?>">👥 Usuarios</a>
    </li>
    <li class="nav-item">
      <a href="<?= $base_url ?>/views/admin/grupos.php" class="nav-link <?= ($current_page == 'grupos.php') ? 'active' : '' ?>">🏫 Grados</a>
    </li>
    <li class="nav-item">
      <a href="<?= $base_url ?>/views/admin/configuracion.php" class="nav-link <?= ($current_page == 'configuracion.php') ? 'active' : '' ?>">⚙️ Configuración</a>
    </li>
  </ul>
</div>
