<?php
session_start();
require_once '../../config/database.php';

// Verificar autenticación y rol de estudiante
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$estudiante_id = $_SESSION['id'];
$estudiante_info = null;
$companeros = [];
$stats = [
    'total_estudiantes' => 0,
    'actividades_grado' => 0,
    'materiales_grado' => 0
];

// Obtener información del estudiante, su grado y su docente
$sql_main = "SELECT
                u.*,
                e.grado,
                doc.nombre as docente_nombre,
                doc.apellidos as docente_apellidos,
                doc.email as docente_email
            FROM
                usuarios u
            JOIN
                estudiantes e ON u.id = e.usuario_id
            LEFT JOIN
                docente_grados dg ON e.grado = dg.grado
            LEFT JOIN
                usuarios doc ON dg.docente_id = doc.id
            WHERE
                u.id = ?";

$stmt_main = $conexion->prepare($sql_main);
$stmt_main->bind_param("i", $estudiante_id);
$stmt_main->execute();
$result_main = $stmt_main->get_result();
$estudiante_info = $result_main->fetch_assoc();

if (!$estudiante_info || !$estudiante_info['grado']) {
    $error_message = "No estás asignado a ningún grado actualmente.";
} else {
    $grado_actual = $estudiante_info['grado'];

    // Obtener compañeros de grado (grupo)
    $sql_companeros = "SELECT
                        u.id,
                        u.nombre,
                        u.apellidos,
                        u.email,
                        e.creado_en as fecha_inscripcion
                    FROM
                        usuarios u
                    JOIN
                        estudiantes e ON u.id = e.usuario_id
                    WHERE
                        e.grado = ? AND u.id != ?
                    ORDER BY
                        u.nombre, u.apellidos";
    
    $stmt_companeros = $conexion->prepare($sql_companeros);
    $stmt_companeros->bind_param("si", $grado_actual, $estudiante_id);
    $stmt_companeros->execute();
    $companeros = $stmt_companeros->get_result()->fetch_all(MYSQLI_ASSOC);

    // Obtener estadísticas del grado (grupo)
    $stats['total_estudiantes'] = count($companeros) + 1;

    // Aquí necesitaríamos una forma de vincular actividades y materiales a un grado en lugar de a un grupo_id.
    // Esta parte de la lógica podría necesitar una revisión más profunda del esquema.
    // Por ahora, dejaremos los contadores en 0 para evitar errores.
    $stats['actividades_grado'] = 0; // Lógica pendiente de adaptar
    $stats['materiales_grado'] = 0; // Lógica pendiente de adaptar
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Grado - UDL</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/Dashboard.css" rel="stylesheet">
    <style>
        .grade-container {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .user-details h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .user-details p {
            margin: 0;
            color: #7f8c8d;
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
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu .active a {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .content-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .content-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 2rem;
        }

        .content-header p {
            margin: 0.5rem 0 0 0;
            color: #7f8c8d;
        }

        .mobile-toggle {
            display: none;
            background: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.students { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-icon.activities { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-icon.materials { background: linear-gradient(135deg, #f39c12, #e67e22); }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .grade-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .teacher-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .teacher-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .teacher-details h4 {
            margin: 0;
            color: #2c3e50;
        }

        .teacher-details p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .student-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .student-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .student-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .student-name {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .student-email {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0;
        }

        .join-date {
            color: #95a5a6;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #bdc3c7;
        }

        .error-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-toggle {
                display: block;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .students-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="grade-container">
        <?php include '../../includes/navigation_estudiante_component.php'; ?>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <div class="content-header">
                <div>
                    <h1><i class="fas fa-users"></i> Mi Grado</h1>
                    <p>Conoce a tus compañeros de clase y docente</p>
                </div>
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php else: ?>
                <!-- Estadísticas del Grado -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon students">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-number"><?= $stats['total_estudiantes'] ?></div>
                        <div class="stat-label">Estudiantes en el Grado</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon activities">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?= $stats['actividades_grado'] ?></div>
                        <div class="stat-label">Actividades Asignadas</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon materials">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-number"><?= $stats['materiales_grado'] ?></div>
                        <div class="stat-label">Materiales Compartidos</div>
                    </div>
                </div>

                <!-- Información del Grado -->
                <div class="grade-info-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Información del Grado
                        </h3>
                    </div>
                    <div class="card-body">
                        <h4>Grado <?= htmlspecialchars($estudiante_info['grado']) ?>°</h4>
                        
                        <?php if (!empty($estudiante_info['docente_nombre'])): ?>
                        <div class="teacher-info">
                            <div class="teacher-avatar">
                                <?= strtoupper(substr($estudiante_info['docente_nombre'], 0, 1)) ?>
                            </div>
                            <div class="teacher-details">
                                <h4>Docente: <?= htmlspecialchars($estudiante_info['docente_nombre'] . ' ' . $estudiante_info['docente_apellidos']) ?></h4>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($estudiante_info['docente_email']) ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                            <p>No hay un docente asignado a este grado todavía.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de Compañeros -->
                <div class="grade-info-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-user-friends"></i>
                            Mis Compañeros de Clase (<?= count($companeros) ?>)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($companeros)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends"></i>
                                <h4>No hay otros compañeros</h4>
                                <p>Eres el único estudiante en este grado por ahora</p>
                            </div>
                        <?php else: ?>
                            <div class="students-grid">
                                <?php foreach ($companeros as $companero): ?>
                                    <div class="student-card">
                                        <div class="student-header">
                                            <div class="student-avatar">
                                                <?= strtoupper(substr($companero['nombre'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <h4 class="student-name"><?= htmlspecialchars($companero['nombre'] . ' ' . $companero['apellidos']) ?></h4>
                                                <p class="student-email"><?= htmlspecialchars($companero['email']) ?></p>
                                            </div>
                                        </div>
                                        <div class="join-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            Se unió el <?= date('d/m/Y', strtotime($companero['fecha_inscripcion'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Toggle sidebar en móvil
        document.getElementById('mobileToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        });

        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.getElementById('mobileToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileToggle.contains(event.target)) {
                sidebar.classList.remove('show');
                document.getElementById('mainContent').classList.remove('expanded');
            }
        });

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .student-card, .grade-info-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
