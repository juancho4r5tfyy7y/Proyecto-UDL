<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/php/conexion.php';
$uid = (int)($_SESSION['usuario_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gestión de Guías</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
  <style>
    /* Estilos adicionales para el dashboard de docente */
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
      background: linear-gradient(135deg, #C97E28, #996515);
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
      background: linear-gradient(135deg, #C97E28, #996515);
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
      box-shadow: 0 4px 12px rgba(201, 126, 40, 0.4);
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
    
    .type-revision {
      background: #e3f2fd;
      color: #1565c0;
    }
    
    .type-creacion {
      background: #e8f5e8;
      color: #2e7d32;
    }
    
    .type-evaluacion {
      background: #fff3e0;
      color: #ef6c00;
    }
    
    .type-feedback {
      background: #f3e5f5;
      color: #7b1fa2;
    }
    
    .students-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .student-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      border-bottom: 1px solid #eee;
      transition: background 0.3s ease;
    }
    
    .student-item:hover {
      background: #f8f9fa;
    }
    
    .student-item:last-child {
      border-bottom: none;
    }
    
    .progress-bar {
      width: 100px;
      height: 8px;
      background: #e0e0e0;
      border-radius: 4px;
      overflow: hidden;
    }
    
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #C97E28, #996515);
      border-radius: 4px;
      transition: width 0.3s ease;
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
    .bg-dark { background: linear-gradient(135deg, #495057, #212529); }
    
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
      background: linear-gradient(135deg, #667eea, #764ba2);
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
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }
    
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
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <h2>📚 Docente</h2>
        <p>Gestión de Guías</p>
      </div>
      <ul class="nav-menu">
        <li><a href="docente_dashboard.php" class="btn-panel"> Panel</a></li>
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
        <h1>Gestión de Guías</h1>
        <p>Sube y administra tus guías de trabajo.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-primary">📄</div>
          <h3 class="card-title">Tus Guías</h3>
        </div>
        <div class="card-content">
          <p>En construcción. Aquí podrás crear y listar guías (tabla guias_trabajo).</p>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/Dashboard.js"></script>
</body>
</html>