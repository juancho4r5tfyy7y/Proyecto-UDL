<?php
session_start();
require_once 'php/conexion.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información completa del docente
$sql_docente = "SELECT u.*, d.titulos, d.area_desempeno, d.biografia 
                FROM usuarios u 
                LEFT JOIN docentes d ON u.id = d.usuario_id 
                WHERE u.id = ?";
$stmt_docente = $conexion->prepare($sql_docente);
$stmt_docente->bind_param("i", $userId);
$stmt_docente->execute();
$docente = $stmt_docente->get_result()->fetch_assoc();

if (!$docente) {
    header('Location: index.php');
    exit();
}

// --- OBTENER ESTADÍSTICAS REALES ---
$stats = [
    'total_estudiantes' => 0,
    'actividades_creadas' => 0,
    'entregas_pendientes' => 0,
    'recursos_subidos' => 0 // Se mantiene en 0 por ahora
];

// Contar estudiantes asignados al docente
$sql_estudiantes = "SELECT COUNT(DISTINCT ge.estudiante_id) as total 
                    FROM grupo_docente gd
                    JOIN grupo_estudiante ge ON gd.grupo_id = ge.grupo_id
                    WHERE gd.docente_id = ?";
$stmt_estudiantes = $conexion->prepare($sql_estudiantes);
$stmt_estudiantes->bind_param("i", $userId);
$stmt_estudiantes->execute();
$stats['total_estudiantes'] = $stmt_estudiantes->get_result()->fetch_assoc()['total'] ?? 0;

// Contar actividades creadas por el docente
$sql_actividades = "SELECT COUNT(a.id) as total 
                    FROM actividades a
                    JOIN grupos g ON a.grupo_id = g.id
                    WHERE g.docente_id = ?";
$stmt_actividades = $conexion->prepare($sql_actividades);
$stmt_actividades->bind_param("i", $userId);
$stmt_actividades->execute();
$stats['actividades_creadas'] = $stmt_actividades->get_result()->fetch_assoc()['total'] ?? 0;

// Contar entregas pendientes de calificar
$sql_pendientes = "SELECT COUNT(e.id) as total 
                   FROM entregas e
                   JOIN actividades a ON e.actividad_id = a.id
                   JOIN grupos g ON a.grupo_id = g.id
                   WHERE g.docente_id = ? AND e.calificacion IS NULL";
$stmt_pendientes = $conexion->prepare($sql_pendientes);
$stmt_pendientes->bind_param("i", $userId);
$stmt_pendientes->execute();
$stats['entregas_pendientes'] = $stmt_pendientes->get_result()->fetch_assoc()['total'] ?? 0;

// --- OBTENER ACTIVIDADES RECIENTES ---
$actividades_recientes = [];
$sql_recientes = "SELECT a.titulo, a.fecha_creacion 
                  FROM actividades a
                  JOIN grupos g ON a.grupo_id = g.id
                  WHERE g.docente_id = ? 
                  ORDER BY a.fecha_creacion DESC LIMIT 5";
$stmt_recientes = $conexion->prepare($sql_recientes);
$stmt_recientes->bind_param("i", $userId);
$stmt_recientes->execute();
$result_recientes = $stmt_recientes->get_result();
while ($row = $result_recientes->fetch_assoc()) {
    $actividades_recientes[] = [
        'descripcion' => 'Nueva actividad creada: ' . htmlspecialchars($row['titulo']),
        'fecha' => $row['fecha_creacion'],
        'tipo' => 'creacion'
    ];
}

// --- OBTENER ESTUDIANTES DESTACADOS ---
$estudiantes_destacados = [];
$sql_destacados = "SELECT u.nombre, u.apellidos, e.calificacion, g.nombre as grado
                   FROM entregas e
                   JOIN actividades a ON e.actividad_id = a.id
                   JOIN grupos g_act ON a.grupo_id = g_act.id
                   JOIN usuarios u ON e.estudiante_id = u.id
                   JOIN grupo_estudiante ge ON u.id = ge.estudiante_id
                   JOIN grupos g ON ge.grupo_id = g.id
                   WHERE g_act.docente_id = ? AND e.calificacion IS NOT NULL
                   ORDER BY e.calificacion DESC
                   LIMIT 3";
$stmt_destacados = $conexion->prepare($sql_destacados);
$stmt_destacados->bind_param("i", $userId);
$stmt_destacados->execute();
$result_destacados = $stmt_destacados->get_result();
while ($row = $result_destacados->fetch_assoc()) {
    $estudiantes_destacados[] = [
        'nombre' => htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']),
        'progreso' => $row['calificacion'],
        'grado' => htmlspecialchars($row['grado'])
    ];
}

$base_url = '/UDL';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Docente - UDL</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
  <style>
    /* Estilos unificados del dashboard de superadmin */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); transition: transform 0.3s ease; position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #C97E28, #996515); }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-number { font-size: 2.5rem; font-weight: bold; color: #C97E28; margin-bottom: 5px; }
    .stat-label { color: #666; font-size: 0.9rem; }
    .profile-section { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); text-align: center; }
    .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #C97E28, #996515); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; margin: 0 auto 15px; }
    .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
    .quick-action-btn { background: linear-gradient(135deg, #C97E28, #996515); color: white; padding: 12px 20px; border: none; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .quick-action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(201, 126, 40, 0.4); }
    .recent-activities, .students-section, .tools-section { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
    .activity-item, .student-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background 0.3s ease; }
    .activity-item:hover, .student-item:hover { background: #f8f9fa; }
    .activity-item:last-child, .student-item:last-child { border-bottom: none; }
    .activity-type { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
    .type-creacion { background: #e8f5e8; color: #2e7d32; }
    .progress-bar { width: 100px; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #C97E28, #996515); border-radius: 4px; transition: width 0.3s ease; }
    .btn-primary { background: linear-gradient(135deg, #C97E28, #996515); color: white; padding: 10px 20px; border: none; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: inline-block; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(201, 126, 40, 0.4); }
    .tools-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
    .tool-btn { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 15px; border: none; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 500; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .tool-btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
  </style>
</head>
<body>
  <div class="container">
    <?php include 'includes/navigation_docente.php'; ?>
    
    <div class="main-content">
      <!-- Header -->
      <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h1>Bienvenido, Prof. <?php echo htmlspecialchars($docente['nombre'] ?? $_SESSION['usuario']); ?> 👨‍🏫</h1>
          <p>Panel de gestión educativa - <strong><?php echo htmlspecialchars($docente['area_desempeno'] ?? 'Área de Lenguaje'); ?></strong></p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
          <a href="/UDL/index.php" class="btn-primary">🏠 Inicio</a>
          <form action="php/logout.php" method="post" style="display: inline;">
            <button type="submit" class="btn-primary">🚪 Salir</button>
          </form>
        </div>
      </div>

      <!-- Perfil del docente -->
      <div class="profile-section">
        <div class="profile-avatar">
          <?php echo strtoupper(substr($docente['nombre'] ?? 'D', 0, 1)); ?>
        </div>
        <h3>Prof. <?php echo htmlspecialchars($docente['nombre'] . ' ' . ($docente['apellidos'] ?? '')); ?></h3>
        <p style="color: #666; margin: 5px 0;">
          📧 <?php echo htmlspecialchars($docente['email'] ?? 'No disponible'); ?>
        </p>
        <?php if (!empty($docente['titulos'])): ?>
          <p style="color: #666; font-weight: 500; margin: 5px 0;">
            🎓 <?php echo htmlspecialchars($docente['titulos']); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Estadísticas -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['total_estudiantes']; ?></div>
          <div class="stat-label">Estudiantes Asignados</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['actividades_creadas']; ?></div>
          <div class="stat-label">Actividades Creadas</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['recursos_subidos']; ?></div>
          <div class="stat-label">Recursos Subidos</div>
        </div>
      </div>

      <!-- Herramientas rápidas -->
      <div class="tools-section">
        <h3 style="margin-bottom: 15px; color: #333;">🛠️ Herramientas Rápidas</h3>
        <div class="tools-grid">
          <a href="views/docente/crear_actividad.php" class="tool-btn">
            <span style="font-size: 1.5rem;">📄</span>
            <span>Crear Actividad</span>
          </a>
          <a href="views/docente/estudiantes.php" class="tool-btn">
            <span style="font-size: 1.5rem;">👥</span>
            <span>Ver Estudiantes</span>
          </a>
          <a href="views/docente/material.php" class="tool-btn">
            <span style="font-size: 1.5rem;">📁</span>
            <span>Subir Material</span>
          </a>
        </div>
      </div>

      <!-- Actividades recientes y Estudiantes destacados -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <!-- Actividades recientes -->
        <div class="recent-activities">
          <h3 style="margin-bottom: 20px; color: #333;">📋 Actividades Recientes</h3>
          <?php if (empty($actividades_recientes)): ?>
              <p>No has creado actividades recientemente.</p>
          <?php else: ?>
              <?php foreach ($actividades_recientes as $actividad): ?>
                <div class="activity-item">
                  <div>
                    <strong><?php echo htmlspecialchars($actividad['descripcion']); ?></strong>
                    <div style="color: #666; font-size: 0.9rem;">
                      📅 <?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?>
                    </div>
                  </div>
                  <span class="activity-type type-<?php echo $actividad['tipo']; ?>">
                    ✏️ Creación
                  </span>
                </div>
              <?php endforeach; ?>
          <?php endif; ?>
        </div>
    </div>
  </div>
  
  <script src="assets/js/Dashboard.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Animación de números
      const statNumbers = document.querySelectorAll('.stat-number');
      statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        if (isNaN(finalValue)) return;
        let currentValue = 0;
        const increment = Math.max(1, finalValue / 30);
        
        const timer = setInterval(() => {
          currentValue += increment;
          if (currentValue >= finalValue) {
            stat.textContent = finalValue;
            clearInterval(timer);
          } else {
            stat.textContent = Math.floor(currentValue);
          }
        }, 40);
      });
      
      // Animación de barras de progreso
      const progressBars = document.querySelectorAll('.progress-fill');
      progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
          bar.style.width = width;
        }, 500);
      });
    });
  </script>
</body>
</html>
