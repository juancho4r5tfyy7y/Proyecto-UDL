<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'estudiante') {
    header('Location: index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información del estudiante y su grupo
$sql_estudiante = "SELECT u.*, g.nombre as nombre_grupo, g.id as grupo_id, es.grado
                   FROM usuarios u
                   LEFT JOIN estudiantes es ON u.id = es.usuario_id
                   LEFT JOIN docente_grados dg ON es.grado = dg.grado
                   LEFT JOIN docentes d ON dg.docente_id = d.usuario_id
                   LEFT JOIN grupos g ON d.id = g.docente_id
                   WHERE u.id = ?
                   GROUP BY u.id";
$stmt_estudiante = $conexion->prepare($sql_estudiante);
$stmt_estudiante->bind_param("i", $userId);
$stmt_estudiante->execute();
$estudiante = $stmt_estudiante->get_result()->fetch_assoc();

if (!$estudiante) {
    header('Location: index.php');
    exit();
}

$grupo_id = $estudiante['grupo_id'];

// --- OBTENER ESTADÍSTICAS REALES ---
$stats = [
    'actividades_completadas' => 0,
    'actividades_pendientes' => 0,
    'progreso_general' => 0,
    'total_actividades' => 0
];

if ($grupo_id) {
    // Contar total de actividades para el grupo
    $sql_total = "SELECT COUNT(id) as total FROM actividades WHERE grupo_id = ?";
    $stmt_total = $conexion->prepare($sql_total);
    $stmt_total->bind_param("i", $grupo_id);
    $stmt_total->execute();
    $stats['total_actividades'] = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;

    // Contar actividades completadas (entregadas) por el estudiante
    $sql_completadas = "SELECT COUNT(e.id) as total 
                        FROM entregas e
                        JOIN actividades a ON e.actividad_id = a.id
                        WHERE e.estudiante_id = ? AND a.grupo_id = ?";
    $stmt_completadas = $conexion->prepare($sql_completadas);
    $stmt_completadas->bind_param("ii", $userId, $grupo_id);
    $stmt_completadas->execute();
    $stats['actividades_completadas'] = $stmt_completadas->get_result()->fetch_assoc()['total'] ?? 0;

    // Calcular pendientes y progreso
    $stats['actividades_pendientes'] = $stats['total_actividades'] - $stats['actividades_completadas'];
    if ($stats['total_actividades'] > 0) {
        $stats['progreso_general'] = round(($stats['actividades_completadas'] / $stats['total_actividades']) * 100);
    }
}

// --- OBTENER ACTIVIDADES RECIENTES ---
$actividades_recientes = [];
if ($grupo_id) {
    $sql_recientes = "SELECT a.id, a.titulo, a.fecha_creacion, e.id as entrega_id
                      FROM actividades a
                      LEFT JOIN entregas e ON a.id = e.actividad_id AND e.estudiante_id = ?
                      WHERE a.grupo_id = ?
                      ORDER BY a.fecha_creacion DESC
                      LIMIT 5";
    $stmt_recientes = $conexion->prepare($sql_recientes);
    $stmt_recientes->bind_param("ii", $userId, $grupo_id);
    $stmt_recientes->execute();
    $result_recientes = $stmt_recientes->get_result();
    while ($row = $result_recientes->fetch_assoc()) {
        $actividades_recientes[] = [
            'titulo' => htmlspecialchars($row['titulo']),
            'fecha' => $row['fecha_creacion'],
            'estado' => $row['entrega_id'] ? 'completada' : 'pendiente'
        ];
    }
}

$base_url = '/UDL';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Estudiante - UDL</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
  <style>
    /* Estilos unificados del dashboard */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); transition: transform 0.3s ease; position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #C97E28, #996515); }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-number { font-size: 2.5rem; font-weight: bold; color: #C97E28; margin-bottom: 5px; }
    .stat-label { color: #666; font-size: 0.9rem; }
    .profile-section { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); text-align: center; }
    .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #C97E28, #996515); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; margin: 0 auto 15px; }
    .recent-activities { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
    .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background 0.3s ease; }
    .activity-item:hover { background: #f8f9fa; }
    .activity-item:last-child { border-bottom: none; }
    .activity-status { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
    .status-completada { background: #d4edda; color: #155724; }
    .status-pendiente { background: #fff3cd; color: #856404; }
    .progress-bar-container { background: #e9ecef; border-radius: .25rem; }
    .progress-bar { height: 1.2rem; line-height: 1.2rem; background-color: #28a745; color: white; text-align: center; font-weight: bold; border-radius: .25rem; background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent); background-size: 1rem 1rem; transition: width .6s ease; }
    .btn-primary { background: linear-gradient(135deg, #C97E28, #996515); color: white; padding: 10px 20px; border: none; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: inline-block; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(201, 126, 40, 0.4); }
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); transition: transform 0.3s ease; }
    .card:hover { transform: translateY(-5px); }
    .card-header { display: flex; align-items: center; margin-bottom: 15px; }
    .card-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px; color: white; }
    .bg-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
    .bg-success { background: linear-gradient(135deg, #51cf66, #40c057); }
    .bg-info { background: linear-gradient(135deg, #74c0fc, #339af0); }
    .card-title { color: #333; font-size: 1.2rem; margin: 0; }
    .card-content p { color: #666; margin-bottom: 15px; line-height: 1.5; }
    body { background-color: #fdf5e6; }
    .dashboard-section { margin-bottom: 40px; }
    .bg-warning { background: linear-gradient(135deg, #ffd43b, #fab005); }
    .section-navigation {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    .section-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .section-tab {
      padding: 10px 20px;
      border: none;
      border-radius: 25px;
      background: #f8f9fa;
      color: #495057;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }
    .section-tab:hover {
      background: #e9ecef;
      transform: translateY(-2px);
    }
    .section-tab.active {
      background: linear-gradient(135deg, #C97E28, #996515);
      color: white;
    }
  </style>
</head>
<body>
  <div class="container">
    <?php include 'includes/navigation_estudiante.php'; ?>
    
    <div class="main-content">
      <!-- Header -->
      <div class="header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
          <h1>¡Hola, <?php echo htmlspecialchars($estudiante['nombre'] ?? $_SESSION['usuario']); ?>! 👋</h1>
          <p>Bienvenido a tu espacio de aprendizaje - <strong>Grado <?php echo htmlspecialchars($estudiante['grado'] ?? ''); ?></strong></p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="/UDL/index.php" class="btn-primary">🏠 Inicio</a>
            <form action="php/logout.php" method="post" style="display: inline;">
              <button type="submit" class="btn-primary">🚪 Salir</button>
            </form>
        </div>
      </div>

      <!-- Perfil del estudiante -->
      <div class="profile-section">
        <div class="profile-avatar">
          <?php echo strtoupper(substr($estudiante['nombre'] ?? 'E', 0, 1)); ?>
        </div>
        <h3><?php echo htmlspecialchars($estudiante['nombre'] . ' ' . ($estudiante['apellidos'] ?? '')); ?></h3>
        <p style="color: #666; margin: 5px 0;">
          📧 <?php echo htmlspecialchars($estudiante['email'] ?? 'No disponible'); ?>
        </p>
      </div>

      <!-- Navegación por pestañas -->
      <div class="section-navigation">
        <div class="section-tabs">
          <a href="#actividades" class="section-tab <?php echo (!isset($_GET['redirect']) || $_GET['redirect'] === 'actividades' ? 'active' : ''); ?>" onclick="showSection('actividades')">
            📝 Actividades
          </a>
          <a href="#material" class="section-tab <?php echo (isset($_GET['redirect']) && $_GET['redirect'] === 'material' ? 'active' : ''); ?>" onclick="showSection('material')">
            📚 Material
          </a>
          <a href="#progreso" class="section-tab <?php echo (isset($_GET['redirect']) && $_GET['redirect'] === 'progreso' ? 'active' : ''); ?>" onclick="showSection('progreso')">
            📈 Progreso
          </a>
        </div>
      </div>

      <!-- Sección de Actividades (ID: actividades) -->
      <div id="actividades" class="dashboard-section">
        <div class="dashboard-grid">
          <div class="card">
            <div class="card-header">
              <div class="card-icon bg-primary">📝</div>
              <h3 class="card-title">Mis Actividades</h3>
            </div>
            <div class="card-content">
              <p>Consulta todas tus actividades, revisa las fechas de entrega y sube tus trabajos.</p>
              <a href="views/estudiante/actividades.php" class="btn-primary">Ver Actividades</a>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon bg-success">📊</div>
              <h3 class="card-title">Mis Entregas</h3>
            </div>
            <div class="card-content">
              <p>Revisa el estado de tus entregas y las calificaciones obtenidas.</p>
              <a href="views/estudiante/mis_entregas.php" class="btn-primary">Ver Entregas</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Sección de Material (ID: material) -->
      <div id="material" class="dashboard-section" style="display: none;">
        <div class="dashboard-grid">
          <div class="card">
            <div class="card-header">
              <div class="card-icon bg-info">📚</div>
              <h3 class="card-title">Material de Estudio</h3>
            </div>
            <div class="card-content">
              <p>Accede a todos los recursos y materiales compartidos por tu docente.</p>
              <a href="views/estudiante/material.php" class="btn-primary">Ver Material</a>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon bg-warning">📖</div>
              <h3 class="card-title">Biblioteca Digital</h3>
            </div>
            <div class="card-content">
              <p>Explora recursos adicionales y materiales complementarios.</p>
              <a href="views/estudiante/material.php" class="btn-primary">Explorar</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Sección de Progreso (ID: progreso) -->
      <div id="progreso" class="dashboard-section" style="display: none;">
        <div class="dashboard-grid">
          <div class="card">
            <div class="card-header">
              <div class="card-icon bg-success">📈</div>
              <h3 class="card-title">Mi Progreso</h3>
            </div>
            <div class="card-content">
              <p>Visualiza tu rendimiento académico y evolución en el curso.</p>
              <a href="views/estudiante/progreso.php" class="btn-primary">Ver Progreso</a>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon bg-primary">🏆</div>
              <h3 class="card-title">Logros</h3>
            </div>
            <div class="card-content">
              <p>Revisa tus logros y reconocimientos obtenidos.</p>
              <a href="views/estudiante/progreso.php" class="btn-primary">Ver Logros</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Actividades recientes -->
      <div class="recent-activities">
        <h3 style="margin-bottom: 20px; color: #333;">📋 Últimas Actividades Asignadas</h3>
        <?php if (empty($actividades_recientes)): ?>
            <p>No tienes actividades asignadas en este momento.</p>
        <?php else: ?>
            <?php foreach ($actividades_recientes as $actividad): ?>
              <div class="activity-item">
                <div>
                  <strong><?php echo htmlspecialchars($actividad['titulo']); ?></strong>
                  <div style="color: #666; font-size: 0.9rem;">
                    📅 Asignada el <?php echo date('d/m/Y', strtotime($actividad['fecha'])); ?>
                  </div>
                </div>
                <span class="activity-status status-<?php echo $actividad['estado']; ?>">
                  <?php echo $actividad['estado'] === 'completada' ? '✅ Vista' : '⏳ Por Ver'; ?>
                </span>
              </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
  
  <script src="assets/js/Dashboard.js"></script>
  <script>
    // Función para mostrar una sección específica
    function showSection(sectionId) {
      // Ocultar todas las secciones
      const sections = ['actividades', 'material', 'progreso'];
      sections.forEach(section => {
        const element = document.getElementById(section);
        if (element) {
          element.style.display = 'none';
        }
      });

      // Mostrar la sección seleccionada
      const targetSection = document.getElementById(sectionId);
      if (targetSection) {
        targetSection.style.display = 'block';
        // Hacer scroll hacia la sección
        targetSection.scrollIntoView({ behavior: 'smooth' });
      }

      // Actualizar clases activas de las pestañas
      sections.forEach(section => {
        const tab = document.querySelector(`[href="#${section}"]`);
        if (tab) {
          if (section === sectionId) {
            tab.classList.add('active');
          } else {
            tab.classList.remove('active');
          }
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Manejar navegación por secciones según el hash en la URL
      const hash = window.location.hash;
      if (hash) {
        // Remover el # del hash
        const sectionId = hash.substring(1);
        showSection(sectionId);
      }

      // Animación de números
      const statNumbers = document.querySelectorAll('.stat-number');
      statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        if (isNaN(finalValue)) return;
        let currentValue = 0;
        const increment = Math.max(1, finalValue / 20);

        const timer = setInterval(() => {
          currentValue += increment;
          if (currentValue >= finalValue) {
            stat.textContent = finalValue;
            clearInterval(timer);
          } else {
            stat.textContent = Math.floor(currentValue);
          }
        }, 50);
      });

      // Animación de barra de progreso
      const progressBar = document.querySelector('.progress-bar');
      if(progressBar) {
        const finalWidth = progressBar.style.width;
        progressBar.style.width = '0%';
        setTimeout(() => {
          progressBar.style.width = finalWidth;
        }, 500);
      }
    });
  </script>
</body>
</html>
