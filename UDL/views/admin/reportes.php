<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'general';

// Estadísticas generales
$stats_generales = [
    'total_usuarios' => $conexion->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'],
    'total_docentes' => $conexion->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'docente'")->fetch_assoc()['total'],
    'total_estudiantes' => $conexion->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'estudiante'")->fetch_assoc()['total'],
    'total_grupos' => $conexion->query("SELECT COUNT(*) as total FROM grupos")->fetch_assoc()['total'],
    'total_actividades' => $conexion->query("SELECT COUNT(*) as total FROM actividades")->fetch_assoc()['total'],
    'total_entregas' => $conexion->query("SELECT COUNT(*) as total FROM entregas")->fetch_assoc()['total']
];

// Actividades por mes (últimos 6 meses)
$actividades_por_mes = $conexion->query("
    SELECT 
        DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
        COUNT(*) as total
    FROM actividades 
    WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m')
    ORDER BY mes
")->fetch_all(MYSQLI_ASSOC);

// Entregas por mes (últimos 6 meses)
$entregas_por_mes = $conexion->query("
    SELECT 
        DATE_FORMAT(fecha_entrega, '%Y-%m') as mes,
        COUNT(*) as total
    FROM entregas 
    WHERE fecha_entrega >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_entrega, '%Y-%m')
    ORDER BY mes
")->fetch_all(MYSQLI_ASSOC);

// Top 10 docentes más activos
$docentes_activos = $conexion->query("
    SELECT 
        u.nombre,
        COUNT(a.id) as total_actividades,
        COUNT(DISTINCT a.grupo_id) as grupos_asignados,
        AVG(CASE WHEN e.calificacion IS NOT NULL THEN e.calificacion END) as promedio_calificaciones
    FROM usuarios u
    LEFT JOIN actividades a ON u.id = a.docente_id
    LEFT JOIN entregas e ON a.id = e.actividad_id
    WHERE u.rol = 'docente'
    GROUP BY u.id, u.nombre
    HAVING total_actividades > 0
    ORDER BY total_actividades DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Estudiantes más participativos
$estudiantes_activos = $conexion->query("
    SELECT 
        u.nombre,
        COUNT(e.id) as total_entregas,
        AVG(e.calificacion) as promedio_calificacion,
        COUNT(DISTINCT e.actividad_id) as actividades_participadas
    FROM usuarios u
    LEFT JOIN entregas e ON u.id = e.estudiante_id
    WHERE u.rol = 'estudiante'
    GROUP BY u.id, u.nombre
    HAVING total_entregas > 0
    ORDER BY total_entregas DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Grupos más activos
$grupos_activos = $conexion->query("
    SELECT 
        g.nombre,
        u.nombre as docente_nombre,
        COUNT(a.id) as total_actividades,
        COUNT(e.id) as total_entregas,
        (SELECT COUNT(*) FROM estudiantes_grupos eg WHERE eg.grupo_id = g.id) as total_estudiantes
    FROM grupos g
    LEFT JOIN usuarios u ON g.docente_id = u.id
    LEFT JOIN actividades a ON g.id = a.grupo_id
    LEFT JOIN entregas e ON a.id = e.actividad_id
    GROUP BY g.id, g.nombre, u.nombre
    ORDER BY total_actividades DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Rendimiento académico por grupo
$rendimiento_grupos = $conexion->query("
    SELECT 
        g.nombre as grupo_nombre,
        u.nombre as docente_nombre,
        COUNT(e.id) as total_entregas,
        AVG(e.calificacion) as promedio_calificacion,
        COUNT(CASE WHEN e.calificacion >= 7 THEN 1 END) as entregas_aprobadas,
        COUNT(CASE WHEN e.calificacion < 7 AND e.calificacion IS NOT NULL THEN 1 END) as entregas_reprobadas
    FROM grupos g
    LEFT JOIN usuarios u ON g.docente_id = u.id
    LEFT JOIN actividades a ON g.id = a.grupo_id
    LEFT JOIN entregas e ON a.id = e.actividad_id
    WHERE e.calificacion IS NOT NULL
    GROUP BY g.id, g.nombre, u.nombre
    HAVING total_entregas > 0
    ORDER BY promedio_calificacion DESC
")->fetch_all(MYSQLI_ASSOC);

// Actividades más populares (con más entregas)
$actividades_populares = $conexion->query("
    SELECT 
        a.titulo,
        u.nombre as docente_nombre,
        g.nombre as grupo_nombre,
        COUNT(e.id) as total_entregas,
        AVG(e.calificacion) as promedio_calificacion,
        a.fecha_creacion
    FROM actividades a
    LEFT JOIN usuarios u ON a.docente_id = u.id
    LEFT JOIN grupos g ON a.grupo_id = g.id
    LEFT JOIN entregas e ON a.id = e.actividad_id
    GROUP BY a.id, a.titulo, u.nombre, g.nombre, a.fecha_creacion
    HAVING total_entregas > 0
    ORDER BY total_entregas DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Usuarios registrados por mes (últimos 12 meses)
$usuarios_por_mes = $conexion->query("
    SELECT 
        DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
        COUNT(*) as total,
        SUM(CASE WHEN rol = 'docente' THEN 1 ELSE 0 END) as docentes,
        SUM(CASE WHEN rol = 'estudiante' THEN 1 ELSE 0 END) as estudiantes
    FROM usuarios 
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
    ORDER BY mes
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes del Sistema - Admin UDL</title>
    <link rel="stylesheet" href="../../assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .admin-info h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .admin-info p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu .active a {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #C97E28;
        }

        .sidebar-menu i {
            width: 20px;
            margin-right: 1rem;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            margin: 0 0 0.5rem 0;
            color: #2C3E50;
            font-size: 2rem;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            color: #7F8C8D;
            font-size: 1.1rem;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #C97E28;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #7F8C8D;
            font-size: 0.9rem;
        }

        .reports-tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid #E9ECEF;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #7F8C8D;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: #C97E28;
            border-bottom-color: #C97E28;
        }

        .tab-content {
            padding: 2rem;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-container h3 {
            margin: 0 0 1rem 0;
            color: #2C3E50;
            font-size: 1.2rem;
        }

        .chart-canvas {
            position: relative;
            height: 300px;
        }

        .data-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            background: #F8F9FA;
            border-bottom: 1px solid #E9ECEF;
        }

        .table-header h3 {
            margin: 0;
            color: #2C3E50;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #E9ECEF;
        }

        .table th {
            background: #F8F9FA;
            font-weight: 600;
            color: #2C3E50;
        }

        .table tbody tr:hover {
            background: #F8F9FA;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #C97E28;
            color: white;
        }

        .btn-primary:hover {
            background: #996515;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: #2C3E50;
            font-weight: 500;
        }

        .export-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
            }

            .main-content {
                padding: 1rem;
            }

            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .tabs-header {
                flex-wrap: wrap;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="admin-sidebar">
            <div class="sidebar-header">
                <div class="admin-info">
                    <h3><?= htmlspecialchars($_SESSION['nombre']) ?></h3>
                    <p>Administrador del Sistema</p>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="usuarios.php">
                        <i class="fas fa-users"></i>
                        <span>Gestión de Usuarios</span>
                    </a>
                </li>
                <li>
                    <a href="grupos.php">
                        <i class="fas fa-layer-group"></i>
                        <span>Gestión de Grupos</span>
                    </a>
                </li>
                <li>
                    <a href="actividades.php">
                        <i class="fas fa-tasks"></i>
                        <span>Supervisión de Actividades</span>
                    </a>
                </li>
                <li class="active">
                    <a href="reportes.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reportes del Sistema</span>
                    </a>
                </li>
                <li>
                    <a href="configuracion.php">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </li>
                <li>
                    <a href="../../php/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Reportes del Sistema</h1>
                <p>Análisis y estadísticas detalladas del sistema educativo</p>
            </div>

            <!-- Estadísticas Generales -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats_generales['total_usuarios'] ?></div>
                    <div class="stat-label">Total Usuarios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats_generales['total_docentes'] ?></div>
                    <div class="stat-label">Docentes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats_generales['total_estudiantes'] ?></div>
                    <div class="stat-label">Estudiantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats_generales['total_grupos'] ?></div>
                    <div class="stat-label">Grupos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats_generales['total_actividades'] ?></div>
                    <div class="stat-label">Actividades</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats_generales['total_entregas'] ?></div>
                    <div class="stat-label">Entregas</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label>Fecha Inicio:</label>
                            <input type="date" name="fecha_inicio" class="form-control" 
                                   value="<?= htmlspecialchars($fecha_inicio) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Fecha Fin:</label>
                            <input type="date" name="fecha_fin" class="form-control" 
                                   value="<?= htmlspecialchars($fecha_fin) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Tipo de Reporte:</label>
                            <select name="tipo_reporte" class="form-control">
                                <option value="general" <?= $tipo_reporte === 'general' ? 'selected' : '' ?>>General</option>
                                <option value="academico" <?= $tipo_reporte === 'academico' ? 'selected' : '' ?>>Académico</option>
                                <option value="usuarios" <?= $tipo_reporte === 'usuarios' ? 'selected' : '' ?>>Usuarios</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Aplicar Filtros
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Acciones de Exportación -->
            <div class="export-actions">
                <button class="btn btn-success" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn btn-secondary" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>

            <!-- Tabs de Reportes -->
            <div class="reports-tabs">
                <div class="tabs-header">
                    <button class="tab-button active" onclick="showTab('overview')">
                        <i class="fas fa-chart-pie"></i> Resumen General
                    </button>
                    <button class="tab-button" onclick="showTab('academic')">
                        <i class="fas fa-graduation-cap"></i> Rendimiento Académico
                    </button>
                    <button class="tab-button" onclick="showTab('activity')">
                        <i class="fas fa-tasks"></i> Actividad del Sistema
                    </button>
                    <button class="tab-button" onclick="showTab('users')">
                        <i class="fas fa-users"></i> Análisis de Usuarios
                    </button>
                </div>

                <div class="tab-content">
                    <!-- Tab Resumen General -->
                    <div id="overview" class="tab-pane active">
                        <div class="charts-grid">
                            <div class="chart-container">
                                <h3>Actividades por Mes</h3>
                                <div class="chart-canvas">
                                    <canvas id="actividadesChart"></canvas>
                                </div>
                            </div>
                            <div class="chart-container">
                                <h3>Entregas por Mes</h3>
                                <div class="chart-canvas">
                                    <canvas id="entregasChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="data-table">
                            <div class="table-header">
                                <h3>Grupos Más Activos</h3>
                            </div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Docente</th>
                                        <th>Estudiantes</th>
                                        <th>Actividades</th>
                                        <th>Entregas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grupos_activos as $grupo): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($grupo['nombre']) ?></td>
                                            <td><?= htmlspecialchars($grupo['docente_nombre'] ?? 'Sin asignar') ?></td>
                                            <td><?= $grupo['total_estudiantes'] ?></td>
                                            <td><?= $grupo['total_actividades'] ?></td>
                                            <td><?= $grupo['total_entregas'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Rendimiento Académico -->
                    <div id="academic" class="tab-pane">
                        <div class="data-table">
                            <div class="table-header">
                                <h3>Rendimiento por Grupo</h3>
                            </div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Docente</th>
                                        <th>Total Entregas</th>
                                        <th>Promedio</th>
                                        <th>Aprobadas</th>
                                        <th>Reprobadas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rendimiento_grupos as $grupo): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($grupo['grupo_nombre']) ?></td>
                                            <td><?= htmlspecialchars($grupo['docente_nombre'] ?? 'Sin asignar') ?></td>
                                            <td><?= $grupo['total_entregas'] ?></td>
                                            <td>
                                                <span style="color: <?= $grupo['promedio_calificacion'] >= 7 ? '#28a745' : '#dc3545' ?>;">
                                                    <?= number_format($grupo['promedio_calificacion'], 2) ?>
                                                </span>
                                            </td>
                                            <td style="color: #28a745;"><?= $grupo['entregas_aprobadas'] ?></td>
                                            <td style="color: #dc3545;"><?= $grupo['entregas_reprobadas'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="data-table">
                            <div class="table-header">
                                <h3>Estudiantes Más Participativos</h3>
                            </div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Total Entregas</th>
                                        <th>Actividades Participadas</th>
                                        <th>Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes_activos as $estudiante): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($estudiante['nombre']) ?></td>
                                            <td><?= $estudiante['total_entregas'] ?></td>
                                            <td><?= $estudiante['actividades_participadas'] ?></td>
                                            <td>
                                                <span style="color: <?= $estudiante['promedio_calificacion'] >= 7 ? '#28a745' : '#dc3545' ?>;">
                                                    <?= number_format($estudiante['promedio_calificacion'], 2) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Actividad del Sistema -->
                    <div id="activity" class="tab-pane">
                        <div class="data-table">
                            <div class="table-header">
                                <h3>Docentes Más Activos</h3>
                            </div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Docente</th>
                                        <th>Total Actividades</th>
                                        <th>Grupos Asignados</th>
                                        <th>Promedio Calificaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($docentes_activos as $docente): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($docente['nombre']) ?></td>
                                            <td><?= $docente['total_actividades'] ?></td>
                                            <td><?= $docente['grupos_asignados'] ?></td>
                                            <td><?= number_format($docente['promedio_calificaciones'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="data-table">
                            <div class="table-header">
                                <h3>Actividades Más Populares</h3>
                            </div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Actividad</th>
                                        <th>Docente</th>
                                        <th>Grupo</th>
                                        <th>Total Entregas</th>
                                        <th>Promedio</th>
                                        <th>Fecha Creación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($actividades_populares as $actividad): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($actividad['titulo']) ?></td>
                                            <td><?= htmlspecialchars($actividad['docente_nombre'] ?? 'Sin asignar') ?></td>
                                            <td><?= htmlspecialchars($actividad['grupo_nombre'] ?? 'Sin grupo') ?></td>
                                            <td><?= $actividad['total_entregas'] ?></td>
                                            <td><?= number_format($actividad['promedio_calificacion'], 2) ?></td>
                                            <td><?= date('d/m/Y', strtotime($actividad['fecha_creacion'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Análisis de Usuarios -->
                    <div id="users" class="tab-pane">
                        <div class="chart-container">
                            <h3>Registros de Usuarios por Mes</h3>
                            <div class="chart-canvas">
                                <canvas id="usuariosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Función para cambiar tabs
        function showTab(tabName) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar tab seleccionado
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Datos para gráficos
        const actividadesData = <?= json_encode($actividades_por_mes) ?>;
        const entregasData = <?= json_encode($entregas_por_mes) ?>;
        const usuariosData = <?= json_encode($usuarios_por_mes) ?>;

        // Configurar gráfico de actividades
        const actividadesCtx = document.getElementById('actividadesChart').getContext('2d');
        new Chart(actividadesCtx, {
            type: 'line',
            data: {
                labels: actividadesData.map(item => item.mes),
                datasets: [{
                    label: 'Actividades Creadas',
                    data: actividadesData.map(item => item.total),
                    borderColor: '#C97E28',
                    backgroundColor: 'rgba(201, 126, 40, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Configurar gráfico de entregas
        const entregasCtx = document.getElementById('entregasChart').getContext('2d');
        new Chart(entregasCtx, {
            type: 'bar',
            data: {
                labels: entregasData.map(item => item.mes),
                datasets: [{
                    label: 'Entregas Realizadas',
                    data: entregasData.map(item => item.total),
                    backgroundColor: '#C97E28',
                    borderColor: '#996515',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Configurar gráfico de usuarios
        const usuariosCtx = document.getElementById('usuariosChart').getContext('2d');
        new Chart(usuariosCtx, {
            type: 'line',
            data: {
                labels: usuariosData.map(item => item.mes),
                datasets: [
                    {
                        label: 'Total Usuarios',
                        data: usuariosData.map(item => item.total),
                        borderColor: '#C97E28',
                        backgroundColor: 'rgba(201, 126, 40, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Docentes',
                        data: usuariosData.map(item => item.docentes),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Estudiantes',
                        data: usuariosData.map(item => item.estudiantes),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Función para exportar reportes
        function exportReport(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            window.open('?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>