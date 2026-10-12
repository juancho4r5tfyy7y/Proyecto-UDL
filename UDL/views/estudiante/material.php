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

// Obtener información del estudiante
$sql_student_info = "SELECT e.grado
                     FROM estudiantes e
                     WHERE e.usuario_id = ?";
$stmt_student_info = $conexion->prepare($sql_student_info);
$stmt_student_info->bind_param("i", $userId);
$stmt_student_info->execute();
$student_info = $stmt_student_info->get_result()->fetch_assoc();

$student_grade = $student_info['grado'] ?? null;

// Obtener materiales públicos de TODOS los docentes
// Primero verificar si hay materiales públicos, si no, mostrar algunos materiales de ejemplo
$sql_public_material = "SELECT m.id, m.titulo, m.descripcion, m.tipo as categoria, m.creado_en as fecha_subida,
                               m.enlace_url, m.archivo_adjunto_url, m.grado, m.publico,
                               u.nombre as docente_nombre, u.apellidos as docente_apellido,
                               d.area_desempeno as docente_especialidad
                        FROM material_didactico m
                        JOIN docentes d ON m.docente_id = d.id
                        JOIN usuarios u ON d.usuario_id = u.id
                        WHERE m.publico = 1
                        ORDER BY m.creado_en DESC";

// Si no hay materiales públicos, obtener algunos materiales para mostrar la funcionalidad
$sql_fallback_material = "SELECT m.id, m.titulo, m.descripcion, m.tipo as categoria, m.creado_en as fecha_subida,
                                 m.enlace_url, m.archivo_adjunto_url, m.grado, m.publico,
                                 u.nombre as docente_nombre, u.apellidos as docente_apellido,
                                 d.area_desempeno as docente_especialidad
                          FROM material_didactico m
                          JOIN docentes d ON m.docente_id = d.id
                          JOIN usuarios u ON d.usuario_id = u.id
                          ORDER BY m.creado_en DESC
                          LIMIT 10";

$stmt_public_material = $conexion->prepare($sql_public_material);
$stmt_public_material->execute();
$all_public_materials = $stmt_public_material->get_result()->fetch_all(MYSQLI_ASSOC);

// Si no hay materiales públicos, usar fallback para mostrar algunos materiales
$materiales_data = [];
if (empty($all_public_materials)) {
    // Usar materiales fallback para mostrar la funcionalidad
    $stmt_fallback = $conexion->prepare($sql_fallback_material);
    $stmt_fallback->execute();
    $materiales_data = $stmt_fallback->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $materiales_data = $all_public_materials;
}

// Filtrar materiales por grado del estudiante
if ($student_grade) {
    $materiales_data = array_filter($materiales_data, function($material) use ($student_grade) {
        return $material['grado'] === 'todos' || $material['grado'] === $student_grade;
    });
}

// Si no hay materiales disponibles, mostrar mensaje informativo
if (empty($materiales_data)) {
    $materiales_data = [];
}

// Adaptar los datos para que coincidan con lo que espera el frontend
$materiales = [];
foreach ($materiales_data as $material) {
    // Inferir el tipo de almacenamiento y la ruta del archivo
    $is_link = !empty($material['enlace_url']);
    $material['storage_type'] = $is_link ? 'google_drive' : 'local';
    $material['ruta_archivo'] = $is_link ? $material['enlace_url'] : ($material['archivo_adjunto_url'] ?? '');
    $material['nombre_archivo'] = basename($material['ruta_archivo']);

    $materiales[] = $material;
}

// Obtener estadísticas del material
$total_materiales = count($materiales);
$videos = array_filter($materiales, fn($m) => $m['categoria'] === 'video');
$documentos = array_filter($materiales, fn($m) => $m['categoria'] === 'documento');
$presentaciones = array_filter($materiales, fn($m) => $m['categoria'] === 'presentacion');
$enlaces = array_filter($materiales, fn($m) => $m['storage_type'] === 'google_drive');
$imagenes = array_filter($materiales, fn($m) => $m['categoria'] === 'imagen');
$audios = array_filter($materiales, fn($m) => $m['categoria'] === 'audio');
$planes_apoyo = array_filter($materiales, fn($m) => $m['categoria'] === 'plan_apoyo');
$otros = array_filter($materiales, fn($m) => $m['categoria'] === 'otro');

// Estadísticas adicionales
$docentes_unicos = [];
foreach ($materiales as $material) {
    $docente_key = $material['docente_nombre'] . ' ' . $material['docente_apellido'];
    if (!in_array($docente_key, $docentes_unicos)) {
        $docentes_unicos[] = $docente_key;
    }
}
$total_docentes = count($docentes_unicos);

$materiales_mi_grado = array_filter($materiales, fn($m) => $m['grado'] === $student_grade || $m['grado'] === 'todos');
$total_para_mi_grado = count($materiales_mi_grado);

$materiales_recientes = array_filter($materiales, fn($m) => strtotime($m['fecha_subida']) > strtotime('-7 days'));
$total_recientes = count($materiales_recientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Didáctico - UDL</title>
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
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color, #C97E28);
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

        .filters-container {
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
            min-width: 150px;
        }

        .filter-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2C3E50;
        }

        .filter-select,
        .search-input {
            padding: 0.75rem;
            border: 1px solid #E9ECEF;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus,
        .search-input:focus {
            outline: none;
            border-color: #C97E28;
        }

        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .view-btn {
            padding: 0.75rem;
            border: 1px solid #E9ECEF;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-btn.active {
            background: #C97E28;
            color: white;
            border-color: #C97E28;
        }

        .materials-container {
            display: grid;
            gap: 1.5rem;
        }

        .materials-container.grid-view {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }

        .materials-container.list-view {
            grid-template-columns: 1fr;
        }

        .material-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .material-card:hover {
            transform: translateY(-2px);
        }

        .material-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E9ECEF;
        }

        .material-title {
            margin: 0 0 0.5rem 0;
            color: #2C3E50;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .material-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .material-type {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .type-video { background: #FFF5F5; color: #E53E3E; }
        .type-documento { background: #F0F9FF; color: #2563EB; }
        .type-pdf { background: #F0F9FF; color: #2563EB; }
        .type-presentacion { background: #FFFBEB; color: #D97706; }
        .type-enlace { background: #F0FDF4; color: #16A34A; }
        .type-audio { background: #FAF5FF; color: #9333EA; }
        .type-imagen { background: #F0FDF4; color: #16A34A; }
        .type-plan_apoyo { background: #FFF7ED; color: #EA580C; }
        .type-otro { background: #F9FAFB; color: #6B7280; }

        .material-grade {
            color: #7F8C8D;
            font-size: 0.9rem;
        }

        .material-teacher {
            color: #7F8C8D;
            font-size: 0.9rem;
        }

        .material-specialty {
            color: #7F8C8D;
            font-size: 0.8rem;
        }

        .material-date {
            color: #7F8C8D;
            font-size: 0.8rem;
        }

        .material-body {
            padding: 1.5rem;
        }

        .material-description {
            color: #7F8C8D;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .material-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #C97E28;
            color: white;
        }

        .btn-primary:hover {
            background: #B8691E;
        }

        .btn-secondary {
            background: #E9ECEF;
            color: #495057;
        }

        .btn-secondary:hover {
            background: #DEE2E6;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #7F8C8D;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
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
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                min-width: auto;
            }

            .view-toggle {
                margin-left: 0;
                justify-content: center;
            }

            .materials-container.grid-view {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .material-meta {
                flex-direction: column;
                align-items: flex-start;
            }

            .material-actions {
                flex-direction: column;
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
                    <i class="fas fa-book"></i>
                    Material Didáctico
                </h1>
                <p>Recursos educativos para tu aprendizaje</p>
            </div>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card" style="--accent-color: #C97E28;">
                    <div class="stat-icon" style="color: #C97E28;">📚</div>
                    <div class="stat-number" style="color: #C97E28;"><?= $total_materiales ?></div>
                    <div class="stat-label">Total Recursos</div>
                </div>

                <div class="stat-card" style="--accent-color: #E53E3E;">
                    <div class="stat-icon" style="color: #E53E3E;">👥</div>
                    <div class="stat-number" style="color: #E53E3E;"><?= $total_docentes ?></div>
                    <div class="stat-label">Docentes</div>
                </div>

                <div class="stat-card" style="--accent-color: #2563EB;">
                    <div class="stat-icon" style="color: #2563EB;">🎓</div>
                    <div class="stat-number" style="color: #2563EB;"><?php echo $student_grade ? $student_grade . '°' : 'N/A'; ?></div>
                    <div class="stat-label">Mi Grado</div>
                </div>

                <div class="stat-card" style="--accent-color: #16A34A;">
                    <div class="stat-icon" style="color: #16A34A;">📖</div>
                    <div class="stat-number" style="color: #16A34A;"><?php echo $total_para_mi_grado; ?></div>
                    <div class="stat-label">Para Grado <?php echo $student_grade ? $student_grade . '°' : 'N/A'; ?></div>
                </div>

                <div class="stat-card" style="--accent-color: #9333EA;">
                    <div class="stat-icon" style="color: #9333EA;">🆕</div>
                    <div class="stat-number" style="color: #9333EA;"><?php echo $total_recientes; ?></div>
                    <div class="stat-label">Esta Semana</div>
                </div>

                <div class="stat-card" style="--accent-color: #F59E0B;">
                    <div class="stat-icon" style="color: #F59E0B;">🎥</div>
                    <div class="stat-number" style="color: #F59E0B;"><?php echo count($videos); ?></div>
                    <div class="stat-label">Videos</div>
                </div>

                <div class="stat-card" style="--accent-color: #10B981;">
                    <div class="stat-icon" style="color: #10B981;">🖼️</div>
                    <div class="stat-number" style="color: #10B981;"><?php echo count($imagenes); ?></div>
                    <div class="stat-label">Imágenes</div>
                </div>

                <div class="stat-card" style="--accent-color: #8B5CF6;">
                    <div class="stat-icon" style="color: #8B5CF6;">🎵</div>
                    <div class="stat-number" style="color: #8B5CF6;"><?php echo count($audios); ?></div>
                    <div class="stat-label">Audios</div>
                </div>

                <div class="stat-card" style="--accent-color: #EF4444;">
                    <div class="stat-icon" style="color: #EF4444;">🆘</div>
                    <div class="stat-number" style="color: #EF4444;"><?php echo count($planes_apoyo); ?></div>
                    <div class="stat-label">Planes de Apoyo</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-container">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="filterType">Tipo de Material</label>
                        <select id="filterType" class="filter-select" onchange="filterMaterials()">
                            <option value="">Todos los tipos</option>
                            <option value="video">Videos</option>
                            <option value="documento">Documentos</option>
                            <option value="pdf">PDFs</option>
                            <option value="presentacion">Presentaciones</option>
                            <option value="enlace">Enlaces</option>
                            <option value="audio">Audios</option>
                            <option value="imagen">Imágenes</option>
                            <option value="plan_apoyo">Planes de Apoyo</option>
                            <option value="otro">Otros</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filterGrade">Grado</label>
                        <select id="filterGrade" class="filter-select" onchange="filterMaterials()">
                            <option value="">Todos los grados</option>
                            <option value="6">6° Grado</option>
                            <option value="7">7° Grado</option>
                            <option value="8">8° Grado</option>
                            <option value="9">9° Grado</option>
                            <option value="10">10° Grado</option>
                            <option value="11">11° Grado</option>
                            <option value="todos">Todos los grados</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filterTeacher">Docente</label>
                        <select id="filterTeacher" class="filter-select" onchange="filterMaterials()">
                            <option value="">Todos los docentes</option>
                            <?php
                            // Obtener lista única de docentes que tienen materiales públicos
                            $teachers = [];
                            foreach ($materiales as $material) {
                                $teacher_key = $material['docente_nombre'] . ' ' . $material['docente_apellido'];
                                if (!in_array($teacher_key, $teachers)) {
                                    $teachers[] = $teacher_key;
                                }
                            }
                            sort($teachers);
                            foreach ($teachers as $teacher) {
                                echo '<option value="' . strtolower($teacher) . '">' . htmlspecialchars($teacher) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="searchMaterial">Buscar Material</label>
                        <input type="text" id="searchMaterial" class="search-input"
                               placeholder="Buscar por título, descripción o docente..."
                               onkeyup="filterMaterials()">
                    </div>

                    <div class="view-toggle">
                        <button class="view-btn active" id="gridBtn" onclick="changeView('grid')">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" id="listBtn" onclick="changeView('list')">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Materiales -->
            <div class="materials-container grid-view" id="materialsContainer">
                <?php if (empty($materiales)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <h4>No hay material disponible</h4>
                        <p>
                            <?php if ($student_grade): ?>
                                No hay material disponible para el grado <?php echo $student_grade; ?>° en este momento.
                                Cuando tus docentes suban material didáctico para tu grado o para todos los grados, aparecerá aquí.
                            <?php else: ?>
                                Cuando tus docentes suban material didáctico, aparecerá aquí.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($materiales as $material): ?>
                        <div class="material-card"
                             data-type="<?= $material['categoria'] ?>"
                             data-grade="<?= strtolower($material['grado']) ?>"
                             data-teacher="<?= strtolower($material['docente_nombre'] . ' ' . $material['docente_apellido']) ?>"
                             data-title="<?= strtolower($material['titulo']) ?>"
                             data-description="<?= strtolower($material['descripcion'] ?? '') ?>">
                            <div class="material-header">
                                <h3 class="material-title"><?= htmlspecialchars($material['titulo']) ?></h3>
                                <div class="material-meta">
                                    <span class="material-type type-<?= $material['categoria'] ?>">
                                        <?php
                                        $icons = [
                                            'video' => '🎥',
                                            'documento' => '📄',
                                            'presentacion' => '📊',
                                            'enlace' => '🔗',
                                            'audio' => '🎵',
                                            'imagen' => '🖼️',
                                            'plan_apoyo' => '🆘',
                                            'otro' => '📎'
                                        ];
                                        echo $icons[$material['categoria']] ?? '📎';
                                        ?>
                                        <?= ucfirst(str_replace('_', ' ', $material['categoria'])) ?>
                                    </span>
                                    <span class="material-grade">
                                        <i class="fas fa-graduation-cap"></i>
                                        Grado: <?= $material['grado'] === 'todos' ? 'Todos' : $material['grado'] . '°' ?>
                                    </span>
                                    <span class="material-teacher">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($material['docente_nombre'] . ' ' . $material['docente_apellido']) ?>
                                    </span>
                                    <?php if ($material['docente_especialidad']): ?>
                                        <span class="material-specialty">
                                            <i class="fas fa-bookmark"></i>
                                            <?= htmlspecialchars($material['docente_especialidad']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="material-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($material['fecha_subida'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="material-body">
                                <?php if ($material['descripcion']): ?>
                                    <p class="material-description"><?= htmlspecialchars($material['descripcion']) ?></p>
                                <?php endif; ?>
                                
                                <div class="material-actions">
                                    <?php if ($material['storage_type'] === 'google_drive'): ?>
                                        <a href="<?= htmlspecialchars($material['ruta_archivo']) ?>" 
                                           target="_blank" class="action-btn btn-primary">
                                            <i class="fab fa-google-drive"></i>
                                            Abrir en Drive
                                        </a>
                                    <?php else: ?>
                                        <a href="../../uploads/material_didactico/<?= htmlspecialchars($material['ruta_archivo']) ?>" 
                                           target="_blank" class="action-btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                            Ver
                                        </a>
                                        <a href="../../uploads/material_didactico/<?= htmlspecialchars($material['ruta_archivo']) ?>" 
                                           download="<?= htmlspecialchars($material['nombre_archivo']) ?>" class="action-btn btn-secondary">
                                            <i class="fas fa-download"></i>
                                            Descargar
                                        </a>
                                    <?php endif; ?>
                                </div>
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

        // Cerrar sidebar al hacer clic en enlaces en móvil
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Cambiar vista
        function changeView(view) {
            const container = document.getElementById('materialsContainer');
            const gridBtn = document.getElementById('gridBtn');
            const listBtn = document.getElementById('listBtn');

            if (view === 'grid') {
                container.className = 'materials-container grid-view';
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
            } else {
                container.className = 'materials-container list-view';
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
            }
        }

        // Filtrar materiales
        function filterMaterials() {
            const typeFilter = document.getElementById('filterType').value.toLowerCase();
            const gradeFilter = document.getElementById('filterGrade').value.toLowerCase();
            const teacherFilter = document.getElementById('filterTeacher').value.toLowerCase();
            const searchTerm = document.getElementById('searchMaterial').value.toLowerCase();
            const materials = document.querySelectorAll('.material-card');

            materials.forEach(material => {
                const type = material.dataset.type;
                const grade = material.dataset.grade;
                const teacher = material.dataset.teacher;
                const title = material.dataset.title;
                const description = material.dataset.description;

                const matchesType = !typeFilter || type === typeFilter;
                const matchesGrade = !gradeFilter || grade === gradeFilter;
                const matchesTeacher = !teacherFilter || teacher === teacherFilter;
                const matchesSearch = !searchTerm ||
                    title.includes(searchTerm) ||
                    description.includes(searchTerm) ||
                    teacher.includes(searchTerm);

                if (matchesType && matchesGrade && matchesTeacher && matchesSearch) {
                    material.style.display = 'block';
                } else {
                    material.style.display = 'none';
                }
            });
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.material-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
