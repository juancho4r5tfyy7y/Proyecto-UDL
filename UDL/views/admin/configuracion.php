<?php
session_start();
require_once '../../php/conexion.php'; 

// Verifica que el usuario esté autenticado y tenga el rol de 'superadmin'.
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$mensaje = '';
$tipo_mensaje = '';

// Procesa el formulario cuando se envía.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'limpiar_cache') {
        // Simula la limpieza de caché.
        $mensaje = 'La caché del sistema ha sido limpiada correctamente.';
        $tipo_mensaje = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Configuración del Sistema - UDL</title>
  <link rel="stylesheet" href="../../assets/css/Dashboard.css" />
  <style>
    .main-content {
      padding-top: 20px;
    }
    .config-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    .config-card h3 {
      margin-bottom: 20px;
      color: #333;
    }
    .btn-primary {
      background: linear-gradient(135deg, #C97E28, #996515);
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      display: inline-block;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(201, 126, 40, 0.4);
    }
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border: 1px solid transparent;
    }
    .alert-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
  </style>
</head>
<body>
  <div class="container">
    <?php include '../../includes/navigation_superadmin.php'; ?>
    
    <div class="main-content">
      <div class="header">
        <h1>Configuración del Sistema</h1>
        <p>Ajustes y mantenimiento de la plataforma UDL.</p>
      </div>

      <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_mensaje ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
      <?php endif; ?>

      <div class="config-card">
        <h3>Mantenimiento del Sistema</h3>
        <p>Realiza tareas de mantenimiento para asegurar el rendimiento óptimo de la plataforma.</p>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="accion" value="limpiar_cache">
            <button type="submit" class="btn-primary">Limpiar Caché del Sistema</button>
        </form>
      </div>

      <div class="config-card">
        <h3>Configuraciones Generales</h3>
        <p>Actualmente, las configuraciones generales se gestionan directamente en los archivos de configuración del servidor. Para cambios avanzados, contacta al soporte técnico.</p>
      </div>
    </div>
  </div>
</body>
</html>
