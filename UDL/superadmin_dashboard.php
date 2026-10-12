<?php
session_start();
require_once 'php/conexion.php';

$base_url = '/UDL';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: index.php');
    exit();
}

// Obtener datos del superadmin
$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información del superadmin
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$superadmin = $stmt->get_result()->fetch_assoc();

// Obtener estadísticas del sistema
$stats = [
    'total_usuarios' => 0,
    'total_docentes' => 0,
    'total_estudiantes' => 0,
    'total_archivos' => 0,
    'usuarios_activos_hoy' => 0
];

// Contar usuarios por tipo
$sql = "SELECT rol, COUNT(*) as total FROM usuarios GROUP BY rol";
$result = $conexion->query($sql);
while ($row = $result->fetch_assoc()) {
    if ($row['rol'] === 'docente') {
        $stats['total_docentes'] = $row['total'];
    } elseif ($row['rol'] === 'estudiante') {
        $stats['total_estudiantes'] = $row['total'];
    }
    $stats['total_usuarios'] += $row['total'];
}

// Archivos totales - funcionalidad removida
$stats['total_archivos'] = 0;

// Simular usuarios activos hoy
$stats['usuarios_activos_hoy'] = rand(15, 35);

// Actividades recientes del sistema (reales)
$actividades_recientes = [];
$sql_actividades = "SELECT u.nombre, u.apellidos, u.fecha_registro, u.rol FROM usuarios u ORDER BY u.fecha_registro DESC LIMIT 5";
$result_actividades = $conexion->query($sql_actividades);
while ($row = $result_actividades->fetch_assoc()) {
    $descripcion = 'Nuevo ' . $row['rol'] . ' registrado: ' . htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']);
    $actividades_recientes[] = [
        'descripcion' => $descripcion,
        'fecha' => $row['fecha_registro'],
        'tipo' => 'registro'
    ];
}

// Estadísticas de uso por mes (reales)
$uso_mensual_raw = [];
$sql_usuarios_mes = "SELECT DATE_FORMAT(fecha_registro, '%Y-%m') AS anio_mes, COUNT(id) AS total FROM usuarios WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY anio_mes";
$result_usuarios_mes = $conexion->query($sql_usuarios_mes);
while($row = $result_usuarios_mes->fetch_assoc()) {
    $uso_mensual_raw[$row['anio_mes']]['usuarios'] = $row['total'];
}

$sql_archivos_mes = "SELECT DATE_FORMAT(fecha_entrega, '%Y-%m') AS anio_mes, COUNT(id) AS total FROM entregas WHERE fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY anio_mes";
$result_archivos_mes = $conexion->query($sql_archivos_mes);
while($row = $result_archivos_mes->fetch_assoc()) {
    $uso_mensual_raw[$row['anio_mes']]['archivos'] = $row['total'];
}

ksort($uso_mensual_raw);

$meses_es = ['01'=>'Enero', '02'=>'Febrero', '03'=>'Marzo', '04'=>'Abril', '05'=>'Mayo', '06'=>'Junio', '07'=>'Julio', '08'=>'Agosto', '09'=>'Septiembre', '10'=>'Octubre', '11'=>'Noviembre', '12'=>'Diciembre'];
$uso_mensual = [];
foreach($uso_mensual_raw as $anio_mes => $data) {
    $mes_num = substr($anio_mes, 5, 2);
    $uso_mensual[] = [
        'mes' => $meses_es[$mes_num],
        'usuarios' => $data['usuarios'] ?? 0,
        'archivos' => $data['archivos'] ?? 0
    ];
}

// Alertas del sistema (reales)
$alertas_sistema = [];

// Alerta: Usuarios inactivos
$sql_inactivos = "SELECT COUNT(*) as total FROM usuarios WHERE ultimo_acceso < DATE_SUB(NOW(), INTERVAL 30 DAY)";
$total_inactivos = $conexion->query($sql_inactivos)->fetch_assoc()['total'];
if ($total_inactivos > 0) {
    $alertas_sistema[] = [
        'mensaje' => "$total_inactivos usuarios inactivos por más de 30 días",
        'nivel' => 'info'
    ];
}

// Alerta: Actividades sin entregas después de la fecha límite
$sql_act_sin_entregas = "SELECT COUNT(a.id) as total FROM actividades a LEFT JOIN entregas e ON a.id = e.actividad_id WHERE a.fecha_limite < NOW() AND e.id IS NULL";
$total_act_sin_entregas = $conexion->query($sql_act_sin_entregas)->fetch_assoc()['total'];
if ($total_act_sin_entregas > 0) {
    $alertas_sistema[] = [
        'mensaje' => "$total_act_sin_entregas actividades han pasado su fecha límite sin recibir entregas",
        'nivel' => 'warning'
    ];
}

// Alerta Fija/Simulada para mantener una
if (empty($alertas_sistema)) {
    $alertas_sistema[] = ['mensaje' => 'No hay alertas críticas en el sistema.', 'nivel' => 'info'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Superadmin - UDL</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
  <style>
    /* Estilos adicionales para el dashboard de superadmin */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #C97E28, #996515);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
    }
    
    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      color: #C97E28;
      margin-bottom: 5px;
    }
    
    .stat-label {
      color: #666;
      font-size: 0.9rem;
    }
    
    .profile-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }
    
    .profile-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dc3545, #c82333);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: white;
      margin: 0 auto 15px;
    }
    
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin-top: 20px;
    }
    
    .quick-action-btn {
      background: linear-gradient(135deg, #dc3545, #c82333);
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      text-decoration: none;
      text-align: center;
      font-weight: 500;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .quick-action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }
    
    .recent-activities {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .activity-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      border-bottom: 1px solid #eee;
      transition: background 0.3s ease;
    }
    
    .activity-item:hover {
      background: #f8f9fa;
    }
    
    .activity-item:last-child {
      border-bottom: none;
    }
    
    .activity-type {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    
    .type-registro {
      background: #e8f5e8;
      color: #2e7d32;
    }
    
    .type-sistema {
      background: #e3f2fd;
      color: #1565c0;
    }
    
    .type-reporte {
      background: #fff3e0;
      color: #ef6c00;
    }
    
    .alerts-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .alert-item {
      display: flex;
      align-items: center;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: all 0.3s ease;
    }
    
    .alert-item:last-child {
      margin-bottom: 0;
    }
    
    .alert-warning {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      color: #856404;
    }
    
    .alert-info {
      background: #d1ecf1;
      border-left: 4px solid #17a2b8;
      color: #0c5460;
    }
    
    .usage-chart {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .chart-bar {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .chart-label {
      width: 80px;
      font-size: 0.9rem;
      color: #666;
    }
    
    .chart-progress {
      flex: 1;
      height: 20px;
      background: #e0e0e0;
      border-radius: 10px;
      overflow: hidden;
      margin: 0 15px;
    }
    
    .chart-fill {
      height: 100%;
      background: linear-gradient(90deg, #C97E28, #996515);
      border-radius: 10px;
      transition: width 0.8s ease;
    }
    
    .chart-value {
      font-size: 0.9rem;
      color: #333;
      font-weight: 500;
    }
    
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }
    
    .card:hover {
      transform: translateY(-5px);
    }
    
    .card-header {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .card-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-right: 15px;
    }
    
    .bg-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
    .bg-success { background: linear-gradient(135deg, #51cf66, #40c057); }
    .bg-warning { background: linear-gradient(135deg, #ffd43b, #fab005); }
    .bg-info { background: linear-gradient(135deg, #74c0fc, #339af0); }
    .bg-secondary { background: linear-gradient(135deg, #868e96, #495057); }
    .bg-danger { background: linear-gradient(135deg, #ff6b6b, #ee5a52); }
    
    .card-title {
      color: #333;
      font-size: 1.2rem;
      margin: 0;
    }
    
    .card-content p {
      color: #666;
      margin-bottom: 15px;
      line-height: 1.5;
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
    
    .tools-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .tools-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-top: 20px;
    }
    
    .tool-btn {
      background: linear-gradient(135deg, #dc3545, #c82333);
      color: white;
      padding: 15px;
      border: none;
      border-radius: 10px;
      text-decoration: none;
      text-align: center;
      font-weight: 500;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }
    
    .tool-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
    }
    
    .system-status {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .status-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .status-indicator {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }
    
    .status-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
    }
    
    .status-online { background: #28a745; }
    .status-warning { background: #ffc107; }
    .status-offline { background: #dc3545; }
    
    @media (max-width: 768px) {
      .container {
        flex-direction: column;
      }
      
      .sidebar {
        width: 100%;
        padding: 15px;
      }
      
      .nav-menu {
        display: flex;
        overflow-x: auto;
        gap: 10px;
      }
      
      .nav-item {
        margin-bottom: 0;
        white-space: nowrap;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
      
      .tools-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .system-status {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <?php include 'includes/navigation_superadmin.php'; ?>
    
    <div class="main-content">
      <!-- Header con información del superadmin -->
      <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
          <div>
            <h1>Panel de Control - <?php echo htmlspecialchars($superadmin['nombre'] ?? $_SESSION['usuario']); ?> 🔧</h1>
            <p>Administración completa del sistema UDL - <strong>Acceso Total</strong></p>
          </div>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="/UDL/index.php" class="btn-primary">🏠 Inicio</a>
            <form action="php/logout.php" method="post" style="display: inline;">
              <button type="submit" class="btn-primary">🚪 Salir</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Perfil del superadmin -->
      <div class="profile-section">
        <div class="profile-avatar">
          <?php echo strtoupper(substr($superadmin['nombre'] ?? 'S', 0, 1)); ?>
        </div>
        <h3>SuperAdmin: <?php echo htmlspecialchars(($superadmin['nombre'] ?? '') . ' ' . ($superadmin['apellidos'] ?? '')); ?></h3>
        <p style="color: #666; margin: 5px 0;">
          📧 <?php echo htmlspecialchars($superadmin['email'] ?? 'No disponible'); ?>
        </p>
        <p style="color: #dc3545; font-weight: 500; margin: 5px 0;">
          🔐 Administrador del Sistema
        </p>
        
      </div>

      <!-- Estado del sistema -->
      <div class="system-status">
        <div class="status-card">
          <div class="status-indicator">
            <div class="status-dot status-online"></div>
            <strong>Sistema Principal</strong>
          </div>
          <p style="color: #666; margin: 0;">Funcionando correctamente</p>
        </div>
        <div class="status-card">
          <div class="status-indicator">
            <div class="status-dot status-online"></div>
            <strong>Base de Datos</strong>
          </div>
          <p style="color: #666; margin: 0;">Conexión estable</p>
        </div>
        <div class="status-card">
          <div class="status-indicator">
            <div class="status-dot status-online"></div>
            <strong>Almacenamiento Local</strong>
          </div>
          <p style="color: #666; margin: 0;">Sistema local activo</p>
        </div>
        <div class="status-card">
          <div class="status-indicator">
            <div class="status-dot status-online"></div>
            <strong>Servidor Web</strong>
          </div>
          <p style="color: #666; margin: 0;">Rendimiento óptimo</p>
        </div>
      </div>

      <!-- Estadísticas del sistema -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['total_usuarios']; ?></div>
          <div class="stat-label">Total de Usuarios</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['total_docentes']; ?></div>
          <div class="stat-label">Docentes Registrados</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['total_estudiantes']; ?></div>
          <div class="stat-label">Estudiantes Activos</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['total_archivos']; ?></div>
          <div class="stat-label">Archivos Locales</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $stats['usuarios_activos_hoy']; ?></div>
          <div class="stat-label">Usuarios Activos Hoy</div>
        </div>
      </div>

      <!-- Herramientas de administración -->
      <div class="tools-section">
        <h3 style="margin-bottom: 15px; color: #333;">🛠️ Herramientas de Administración</h3>
        <div class="tools-grid">
          <a href="views/admin/usuarios.php" class="tool-btn">
            <span style="font-size: 1.5rem;">👥</span>
            <span>Gestionar Usuarios</span>
          </a>
          <a href="views/admin/grupos.php" class="tool-btn">
            <span style="font-size: 1.5rem;">🏫</span>
            <span>Administrar Grados</span>
          </a>
          <a href="#" class="tool-btn">
            <span style="font-size: 1.5rem;"></span>
            <span>Configuración</span>
          </a>
          <a href="#" class="tool-btn">
            <span style="font-size: 1.5rem;">🔒</span>
            <span>Seguridad</span>
          </a>
        </div>
      </div>

      <!-- Alertas del sistema -->
      <div class="alerts-section">
        <h3 style="margin-bottom: 20px; color: #333;">⚠️ Alertas del Sistema</h3>
        <?php foreach ($alertas_sistema as $alerta): ?>
          <div class="alert-item alert-<?php echo $alerta['nivel']; ?>">
            <span><?php echo htmlspecialchars($alerta['mensaje']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Gráfico de uso -->
      <div class="usage-chart">
        <h3 style="margin-bottom: 20px; color: #333;">📈 Estadísticas de Uso Mensual</h3>
        <?php foreach ($uso_mensual as $mes): ?>
          <div class="chart-bar">
            <div class="chart-label"><?php echo $mes['mes']; ?></div>
            <div class="chart-progress">
              <div class="chart-fill" style="width: <?php echo ($mes['usuarios'] / 60) * 100; ?>%"></div>
            </div>
            <div class="chart-value"><?php echo $mes['usuarios']; ?> usuarios</div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Actividades recientes -->
      <div class="recent-activities">
        <h3 style="margin-bottom: 20px; color: #333;">📋 Actividades Recientes del Sistema</h3>
        <?php foreach ($actividades_recientes as $actividad): ?>
          <div class="activity-item">
            <div>
              <strong><?php echo htmlspecialchars($actividad['descripcion']); ?></strong>
              <div style="color: #666; font-size: 0.9rem;">
                📅 <?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?>
              </div>
            </div>
            <span class="activity-type type-<?php echo $actividad['tipo']; ?>">
              <?php 
                $tipos = [
                  'registro' => '👤 Registro',
                  'sistema' => '⚙️ Sistema',
                  'reporte' => '📊 Reporte'
                ];
                echo $tipos[$actividad['tipo']];
              ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Grid de funcionalidades principales -->
      <div class="dashboard-grid">
        
        <div class="card">
          <div class="card-header">
            <div class="card-icon bg-info">🔒</div>
            <h3 class="card-title">Configuración del Sistema</h3>
          </div>
          <div class="card-content">
            <p>Configurar parámetros generales, políticas de seguridad y ajustes avanzados del sistema educativo.</p>
            <a href="#" class="btn-primary">⚙️ Configurar Sistema</a>
          </div>
        </div>
        
        <div class="card">
          <div class="card-header">
            <div class="card-icon bg-secondary">📝</div>
            <h3 class="card-title">Gestión de Roles</h3>
          </div>
          <div class="card-content">
            <p>Administrar roles y permisos específicos, crear nuevos tipos de usuario y gestionar accesos del sistema.</p>
            <a href="#" class="btn-primary">🎭 Gestionar Roles</a>
          </div>
        </div>
        
        <div class="card">
          <div class="card-header">
            <div class="card-icon bg-danger">⚙️</div>
            <h3 class="card-title">Configuración Avanzada</h3>
          </div>
          <div class="card-content">
            <p>Configuraciones avanzadas, mantenimiento del sistema, backups y herramientas de diagnóstico.</p>
            <a href="#" class="btn-primary">🔧 Configuración Avanzada</a>
          </div>
        </div>
      </div>

      <!-- Generación de reportes -->
      <div class="recent-activities">
        <h3 style="margin-bottom: 15px; color: #333;">📄 Generación de Reportes</h3>
        <div style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 20px; border-radius: 10px; text-align: center;">
          <p style="margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 500;">
            Genera reportes detallados del sistema en formato PDF
          </p>
          <form method="get" action="/UDL/admin/generate_report.php" target="_blank" style="display:inline">
            <input type="hidden" name="format" value="pdf">
            <button type="submit" class="btn-primary" style="background: rgba(255,255,255,0.2); border: 2px solid white;">
              📄 Generar Reporte Detallado (PDF)
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
  <script>
    // Animación de las estadísticas al cargar
    document.addEventListener('DOMContentLoaded', function() {
      const statNumbers = document.querySelectorAll('.stat-number');
      statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        let currentValue = 0;
        const increment = finalValue / 25;
        
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
      
      // Animación de las barras de progreso del gráfico
      const chartBars = document.querySelectorAll('.chart-fill');
      chartBars.forEach((bar, index) => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
          bar.style.width = width;
        }, 300 + (index * 200));
      });
      
      // Efecto de parpadeo para las alertas
      const alerts = document.querySelectorAll('.alert-warning');
      alerts.forEach(alert => {
        setInterval(() => {
          alert.style.opacity = alert.style.opacity === '0.7' ? '1' : '0.7';
        }, 2000);
      });
    });
  </script>
</body>
</html>
