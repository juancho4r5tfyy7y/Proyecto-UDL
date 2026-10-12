<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener grupo del estudiante
$sql = "SELECT g.id as grupo_id FROM grupos g JOIN inscripciones i ON g.id = i.grupo_id WHERE i.estudiante_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$estudiante = $result->fetch_assoc();
$grupoId = $estudiante['grupo_id'] ?? null;

// Obtener actividades asignadas
$actividades = [];
if ($grupoId) {
    $sql = "SELECT a.*,
                   e.calificacion, e.fecha_entrega as fecha_entrega_real,
                   CASE
                       WHEN e.id IS NULL THEN 'pendiente'
                       WHEN e.calificacion IS NOT NULL THEN 'calificada'
                       ELSE 'entregada'
                   END as estado_entrega
            FROM actividades a
            LEFT JOIN entregas e ON a.id = e.actividad_id AND e.estudiante_id = ?
            WHERE a.grupo_id = ?
            ORDER BY a.fecha_limite ASC, a.fecha_creacion DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $userId, $grupoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $actividades[] = $row;
    }
}

// Calcular estadísticas
$pendientes = array_filter($actividades, fn($a) => !$a['estado_entrega'] || $a['estado_entrega'] === 'pendiente');
$entregadas = array_filter($actividades, fn($a) => $a['estado_entrega'] === 'entregada' || $a['estado_entrega'] === 'calificada');
$calificadas = array_filter($actividades, fn($a) => $a['estado_entrega'] === 'calificada' && $a['calificacion']);
$promedio = count($calificadas) > 0 ? array_sum(array_column($calificadas, 'calificacion')) / count($calificadas) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Actividades - UDL</title>
    <link rel="stylesheet" href="../../assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .student-container {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .student-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            background: #C97E28;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }

        .student-info h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .student-info p {
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header p {
            margin: 0;
            color: #7F8C8D;
            font-size: 1.1rem;
        }

        .stats-grid {
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
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #7F8C8D;
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
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
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
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #C97E28;
            box-shadow: 0 0 0 2px rgba(201, 126, 40, 0.2);
        }

        .activities-grid {
            display: grid;
            gap: 1rem;
        }

        .activity-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .activity-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E9ECEF;
        }

        .activity-title {
            margin: 0 0 0.5rem 0;
            color: #2C3E50;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .activity-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-pending {
            background: #FFF3CD;
            color: #856404;
        }

        .badge-submitted {
            background: #D1ECF1;
            color: #0C5460;
        }

        .badge-graded {
            background: #D4EDDA;
            color: #155724;
        }

        .badge-overdue {
            background: #F8D7DA;
            color: #721C24;
        }

        .activity-description {
            color: #7F8C8D;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.9rem;
            color: #7F8C8D;
        }

        .activity-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .activity-body {
            padding: 1.5rem;
        }

        .activity-actions {
            padding: 1rem 1.5rem;
            background: #F8F9FA;
            border-top: 1px solid #E9ECEF;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .grade-display {
            text-align: center;
        }

        .grade-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #C97E28;
        }

        .grade-max {
            font-size: 0.9rem;
            color: #7F8C8D;
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

        .btn-outline {
            background: transparent;
            color: #C97E28;
            border: 1px solid #C97E28;
        }

        .btn-outline:hover {
            background: #C97E28;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-icon {
            font-size: 4rem;
            color: #E9ECEF;
            margin-bottom: 1rem;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: #2C3E50;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 1.2rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            .student-container {
                flex-direction: column;
            }

            .student-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                transform: translateX(-100%);
            }

            .student-sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .main-content {
                padding: 1rem;
                margin-top: 4rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                min-width: auto;
            }

            .activity-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .activity-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="student-container">
        <?php include '../../includes/navigation_estudiante_component.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>
                    <i class="fas fa-tasks"></i>
                    Mis Actividades
                </h1>
                <p>Gestiona tus tareas y entregas académicas</p>
            </div>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #FFC107;">⏳</div>
                    <div class="stat-number" style="color: #FFC107;"><?= count($pendientes) ?></div>
                    <div class="stat-label">Por Ver</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #17A2B8;">📤</div>
                    <div class="stat-number" style="color: #17A2B8;"><?= count($entregadas) ?></div>
                    <div class="stat-label">Vistas</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Estado:</label>
                        <select id="filtroEstado" class="form-control" onchange="filtrarActividades()">
                            <option value="">Todos los estados</option>
                            <option value="pendiente">Por Ver</option>
                            <option value="entregada">Vistas</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Tipo:</label>
                        <select id="filtroTipo" class="form-control" onchange="filtrarActividades()">
                            <option value="">Todos los tipos</option>
                            <option value="taller">Talleres</option>
                            <option value="ensayo">Ensayos</option>
                            <option value="lectura">Lecturas</option>
                            <option value="evaluacion">Evaluaciones</option>
                            <option value="proyecto">Proyectos</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Buscar:</label>
                        <input type="text" id="buscarActividad" class="form-control" 
                               placeholder="Buscar por título..." onkeyup="filtrarActividades()">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-outline" onclick="limpiarFiltros()">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lista de Actividades -->
            <div class="activities-grid" id="listaActividades">
                <?php if (empty($actividades)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3>No hay actividades asignadas</h3>
                        <p>Cuando tu docente asigne actividades, aparecerán aquí.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($actividades as $actividad): ?>
                        <?php
                        $estado = $actividad['estado_entrega'] ?: 'pendiente';
                        $fechaLimite = strtotime($actividad['fecha_limite']);
                        $ahora = time();
                        $vencida = $fechaLimite < $ahora && $estado === 'pendiente';
                        ?>
                        <div class="activity-card" 
                             data-estado="<?= $estado ?>"
                             data-tipo="<?= $actividad['tipo'] ?>"
                             data-titulo="<?= strtolower($actividad['titulo']) ?>">
                            
                            <div class="activity-header">
                                <h3 class="activity-title"><?= htmlspecialchars($actividad['titulo']) ?></h3>
                                
                                <div class="activity-badges">
                                    <?php if ($vencida): ?>
                                        <span class="badge badge-overdue">
                                            <i class="fas fa-exclamation-triangle"></i> Vencida
                                        </span>
                                    <?php elseif ($estado === 'pendiente'): ?>
                                        <span class="badge badge-pending">
                                            <i class="fas fa-clock"></i> Por Ver
                                        </span>
                                    <?php elseif ($estado === 'entregada'): ?>
                                        <span class="badge badge-submitted">
                                            <i class="fas fa-check"></i> Vista
                                        </span>
                                    <?php elseif ($estado === 'calificada'): ?>
                                        <span class="badge badge-graded">
                                            <i class="fas fa-star"></i> Calificada
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="badge" style="background: #E9ECEF; color: #495057;">
                                        <?= ucfirst($actividad['tipo']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="activity-body">
                                <p class="activity-description">
                                    <?= htmlspecialchars($actividad['descripcion']) ?>
                                </p>

                                <div class="activity-meta">
                                    <span>
                                        <i class="fas fa-calendar-plus"></i>
                                        Asignada: <?= date('d/m/Y', strtotime($actividad['fecha_creacion'])) ?>
                                    </span>
                                    <?php if ($actividad['fecha_limite']): ?>
                                        <span class="<?= $vencida ? 'text-danger' : '' ?>">
                                            <i class="fas fa-calendar-times"></i>
                                            Límite: <?= date('d/m/Y H:i', strtotime($actividad['fecha_limite'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($actividad['fecha_entrega_real']): ?>
                                        <span>
                                            <i class="fas fa-upload"></i>
                                            Entregada: <?= date('d/m/Y H:i', strtotime($actividad['fecha_entrega_real'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="activity-actions">
                                <div>
                                    <?php if ($estado === 'pendiente'): ?>
                                        <a href="subir_entrega.php?actividad=<?= $actividad['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Entregar
                                        </a>
                                    <?php elseif ($estado === 'entregada'): ?>
                                        <a href="mis_entregas.php?actividad=<?= $actividad['id'] ?>" class="btn btn-outline">
                                            <i class="fas fa-eye"></i> Ver Entrega
                                        </a>
                                    <?php elseif ($estado === 'calificada'): ?>
                                        <a href="mis_entregas.php?actividad=<?= $actividad['id'] ?>" class="btn btn-success">
                                            <i class="fas fa-eye"></i> Ver Resultado
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($actividad['calificacion']): ?>
                                    <div class="grade-display">
                                        <div class="grade-number"><?= number_format($actividad['calificacion'], 1) ?></div>
                                        <div class="grade-max">/ 10</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar en móvil
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Filtrar actividades
        function filtrarActividades() {
            const estado = document.getElementById('filtroEstado').value.toLowerCase();
            const tipo = document.getElementById('filtroTipo').value.toLowerCase();
            const busqueda = document.getElementById('buscarActividad').value.toLowerCase();
            
            const actividades = document.querySelectorAll('.activity-card');
            
            actividades.forEach(actividad => {
                const actividadEstado = actividad.dataset.estado;
                const actividadTipo = actividad.dataset.tipo;
                const actividadTitulo = actividad.dataset.titulo;
                
                const coincideEstado = !estado || actividadEstado.includes(estado);
                const coincideTipo = !tipo || actividadTipo.includes(tipo);
                const coincideBusqueda = !busqueda || actividadTitulo.includes(busqueda);
                
                if (coincideEstado && coincideTipo && coincideBusqueda) {
                    actividad.style.display = 'block';
                } else {
                    actividad.style.display = 'none';
                }
            });
        }

        // Limpiar filtros
        function limpiarFiltros() {
            document.getElementById('filtroEstado').value = '';
            document.getElementById('filtroTipo').value = '';
            document.getElementById('buscarActividad').value = '';
            filtrarActividades();
        }

        // Cerrar sidebar al hacer clic en enlaces en móvil
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Actualizar automáticamente cada 5 minutos
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
