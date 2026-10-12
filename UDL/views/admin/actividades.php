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

// Obtener filtros
$filtro_docente = $_GET['docente'] ?? '';
$filtro_grupo = $_GET['grupo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];
$types = '';

if ($filtro_docente) {
    $where_conditions[] = "a.docente_id = ?";
    $params[] = $filtro_docente;
    $types .= 'i';
}

if ($filtro_grupo) {
    $where_conditions[] = "a.grupo_id = ?";
    $params[] = $filtro_grupo;
    $types .= 'i';
}

if ($filtro_estado) {
    $where_conditions[] = "a.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if ($filtro_fecha) {
    $where_conditions[] = "DATE(a.fecha_limite) = ?";
    $params[] = $filtro_fecha;
    $types .= 's';
}

if ($busqueda) {
    $where_conditions[] = "(a.titulo LIKE ? OR a.descripcion LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener actividades con información relacionada
$sql = "SELECT a.*, 
               u.nombre as docente_nombre,
               g.nombre as grupo_nombre,
               (SELECT COUNT(*) FROM entregas e WHERE e.actividad_id = a.id) as total_entregas,
               (SELECT COUNT(*) FROM estudiantes_grupos eg WHERE eg.grupo_id = a.grupo_id) as total_estudiantes,
               (SELECT COUNT(*) FROM entregas e WHERE e.actividad_id = a.id AND e.calificacion IS NOT NULL) as entregas_calificadas
        FROM actividades a
        LEFT JOIN usuarios u ON a.docente_id = u.id
        LEFT JOIN grupos g ON a.grupo_id = g.id
        $where_clause
        ORDER BY a.fecha_creacion DESC";

if (!empty($params)) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $actividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $actividades = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Obtener docentes para filtros
$docentes = $conexion->query("SELECT id, nombre FROM usuarios WHERE rol = 'docente' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Obtener grupos para filtros
$grupos = $conexion->query("SELECT id, nombre FROM grupos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Calcular estadísticas
$stats = [
    'total_actividades' => count($actividades),
    'activas' => 0,
    'vencidas' => 0,
    'completadas' => 0,
    'promedio_entregas' => 0
];

$total_entregas = 0;
$total_estudiantes = 0;

foreach ($actividades as $actividad) {
    $fecha_limite = new DateTime($actividad['fecha_limite']);
    $ahora = new DateTime();
    
    if ($actividad['estado'] === 'activa') {
        if ($fecha_limite > $ahora) {
            $stats['activas']++;
        } else {
            $stats['vencidas']++;
        }
    } else {
        $stats['completadas']++;
    }
    
    $total_entregas += $actividad['total_entregas'];
    $total_estudiantes += $actividad['total_estudiantes'];
}

if ($total_estudiantes > 0) {
    $stats['promedio_entregas'] = round(($total_entregas / $total_estudiantes) * 100, 1);
}

// Obtener actividades recientes para el dashboard
$actividades_recientes = $conexion->query("
    SELECT a.titulo, a.fecha_creacion, u.nombre as docente_nombre, g.nombre as grupo_nombre
    FROM actividades a
    LEFT JOIN usuarios u ON a.docente_id = u.id
    LEFT JOIN grupos g ON a.grupo_id = g.id
    ORDER BY a.fecha_creacion DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisión de Actividades - Admin UDL</title>
    <link rel="stylesheet" href="../../assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .stats-row {
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

        .controls-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
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

        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .activities-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-activa {
            background: #D1FAE5;
            color: #059669;
        }

        .status-completada {
            background: #DBEAFE;
            color: #1D4ED8;
        }

        .status-vencida {
            background: #FEE2E2;
            color: #DC2626;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #E9ECEF;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #C97E28;
            transition: width 0.3s ease;
        }

        .activity-actions {
            display: flex;
            gap: 0.5rem;
        }

        .recent-activities {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        .recent-activities h3 {
            margin: 0 0 1rem 0;
            color: #2C3E50;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #E9ECEF;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            color: #2C3E50;
        }

        .activity-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #7F8C8D;
        }

        .activity-date {
            font-size: 0.8rem;
            color: #7F8C8D;
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

            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-row {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
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
                <li class="active">
                    <a href="actividades.php">
                        <i class="fas fa-tasks"></i>
                        <span>Supervisión de Actividades</span>
                    </a>
                </li>
                <li>
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
                <h1>Supervisión de Actividades</h1>
                <p>Monitorea y supervisa todas las actividades del sistema</p>
            </div>

            <!-- Estadísticas -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_actividades'] ?></div>
                    <div class="stat-label">Total Actividades</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['activas'] ?></div>
                    <div class="stat-label">Activas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['vencidas'] ?></div>
                    <div class="stat-label">Vencidas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['promedio_entregas'] ?>%</div>
                    <div class="stat-label">Promedio Entregas</div>
                </div>
            </div>

            <!-- Controles -->
            <div class="controls-section">
                <div class="controls-row">
                    <div class="filters">
                        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <div class="filter-group">
                                <label>Buscar:</label>
                                <input type="text" name="busqueda" class="form-control" 
                                       placeholder="Título o descripción..." 
                                       value="<?= htmlspecialchars($busqueda) ?>">
                            </div>
                            <div class="filter-group">
                                <label>Docente:</label>
                                <select name="docente" class="form-control">
                                    <option value="">Todos los docentes</option>
                                    <?php foreach ($docentes as $docente): ?>
                                        <option value="<?= $docente['id'] ?>" <?= $filtro_docente == $docente['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($docente['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Grupo:</label>
                                <select name="grupo" class="form-control">
                                    <option value="">Todos los grupos</option>
                                    <?php foreach ($grupos as $grupo): ?>
                                        <option value="<?= $grupo['id'] ?>" <?= $filtro_grupo == $grupo['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($grupo['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Estado:</label>
                                <select name="estado" class="form-control">
                                    <option value="">Todos los estados</option>
                                    <option value="activa" <?= $filtro_estado === 'activa' ? 'selected' : '' ?>>Activa</option>
                                    <option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>Completada</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Fecha Límite:</label>
                                <input type="date" name="fecha" class="form-control" 
                                       value="<?= htmlspecialchars($filtro_fecha) ?>">
                            </div>
                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                    <div>
                        <a href="../../views/docente/actividades.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva Actividad
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tabla de Actividades -->
            <div class="activities-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Docente</th>
                            <th>Grupo</th>
                            <th>Fecha Límite</th>
                            <th>Estado</th>
                            <th>Progreso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actividades as $actividad): ?>
                            <?php
                            $fecha_limite = new DateTime($actividad['fecha_limite']);
                            $ahora = new DateTime();
                            $estado_visual = $actividad['estado'];
                            
                            if ($actividad['estado'] === 'activa' && $fecha_limite < $ahora) {
                                $estado_visual = 'vencida';
                            }
                            
                            $progreso = 0;
                            if ($actividad['total_estudiantes'] > 0) {
                                $progreso = round(($actividad['total_entregas'] / $actividad['total_estudiantes']) * 100);
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($actividad['titulo']) ?></strong><br>
                                    <small style="color: #7F8C8D;">
                                        <?= htmlspecialchars(substr($actividad['descripcion'], 0, 100)) ?>
                                        <?= strlen($actividad['descripcion']) > 100 ? '...' : '' ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($actividad['docente_nombre'] ?? 'Sin asignar') ?></td>
                                <td><?= htmlspecialchars($actividad['grupo_nombre'] ?? 'Sin grupo') ?></td>
                                <td>
                                    <?= $fecha_limite->format('d/m/Y H:i') ?><br>
                                    <small style="color: <?= $fecha_limite < $ahora ? '#DC2626' : '#7F8C8D' ?>;">
                                        <?php
                                        $diff = $ahora->diff($fecha_limite);
                                        if ($fecha_limite < $ahora) {
                                            echo "Vencida hace " . $diff->days . " días";
                                        } else {
                                            echo "Faltan " . $diff->days . " días";
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $estado_visual ?>">
                                        <?= ucfirst($estado_visual) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="min-width: 100px;">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $progreso ?>%"></div>
                                        </div>
                                        <small style="color: #7F8C8D;">
                                            <?= $actividad['total_entregas'] ?>/<?= $actividad['total_estudiantes'] ?> 
                                            (<?= $progreso ?>%)
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="activity-actions">
                                        <a href="ver_actividad.php?id=<?= $actividad['id'] ?>" 
                                           class="btn btn-sm btn-secondary" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="entregas_actividad.php?id=<?= $actividad['id'] ?>" 
                                           class="btn btn-sm btn-primary" title="Ver entregas">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        <?php if ($actividad['entregas_calificadas'] < $actividad['total_entregas']): ?>
                                            <span class="btn btn-sm btn-warning" title="Entregas pendientes de calificar">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($actividades)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-tasks" style="font-size: 3rem; color: #C97E28; margin-bottom: 1rem;"></i>
                                    <h3>No hay actividades registradas</h3>
                                    <p>No se encontraron actividades con los filtros aplicados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Actividades Recientes -->
            <div class="recent-activities">
                <h3>Actividades Recientes</h3>
                <?php foreach ($actividades_recientes as $actividad): ?>
                    <div class="activity-item">
                        <div class="activity-info">
                            <h4><?= htmlspecialchars($actividad['titulo']) ?></h4>
                            <p>
                                Por <?= htmlspecialchars($actividad['docente_nombre']) ?> 
                                - Grupo: <?= htmlspecialchars($actividad['grupo_nombre']) ?>
                            </p>
                        </div>
                        <div class="activity-date">
                            <?= date('d/m/Y H:i', strtotime($actividad['fecha_creacion'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($actividades_recientes)): ?>
                    <p style="text-align: center; color: #7F8C8D; padding: 2rem;">
                        No hay actividades recientes
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh cada 5 minutos para mantener datos actualizados
        setTimeout(() => {
            location.reload();
        }, 300000);

        // Función para exportar datos
        function exportActivities() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = '?' + params.toString();
        }
    </script>
</body>
</html>