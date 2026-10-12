<?php
session_start();
require_once '../../config/database.php';

// Verificar autenticación y rol de docente
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: ../../auth/login.php');
    exit();
}

$docente_id = $_SESSION['usuario_id'];

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener actividades del docente con entregas pendientes
$sql_actividades = "SELECT DISTINCT a.id, a.titulo, a.descripcion, a.fecha_limite,
                           g.nombre as grupo_nombre, g.id as grupo_id,
                           COUNT(e.id) as total_entregas,
                           COUNT(CASE WHEN e.calificacion IS NULL THEN 1 END) as pendientes
                    FROM actividades a
                    INNER JOIN grupos g ON a.grupo_id = g.id
                    LEFT JOIN entregas e ON a.id = e.actividad_id
                    WHERE g.docente_id = ?
                    GROUP BY a.id, a.titulo, a.descripcion, a.fecha_limite, g.nombre, g.id
                    HAVING total_entregas > 0
                    ORDER BY a.fecha_limite DESC";

$stmt_actividades = $conexion->prepare($sql_actividades);
$stmt_actividades->bind_param("i", $docente_id);
$stmt_actividades->execute();
$actividades = $stmt_actividades->get_result()->fetch_all(MYSQLI_ASSOC);

// Si se selecciona una actividad específica
$actividad_seleccionada = null;
$entregas = [];

if (isset($_GET['actividad_id'])) {
    $actividad_id = (int)$_GET['actividad_id'];
    
    // Verificar que la actividad pertenece al docente
    $sql_verificar = "SELECT a.*, g.nombre as grupo_nombre 
                      FROM actividades a 
                      INNER JOIN grupos g ON a.grupo_id = g.id 
                      WHERE a.id = ? AND g.docente_id = ?";
    
    $stmt_verificar = $conexion->prepare($sql_verificar);
    $stmt_verificar->bind_param("ii", $actividad_id, $docente_id);
    $stmt_verificar->execute();
    $actividad_seleccionada = $stmt_verificar->get_result()->fetch_assoc();
    
    if ($actividad_seleccionada) {
        // Obtener entregas de la actividad
        $sql_entregas = "SELECT e.*, u.nombre, u.apellido, u.email
                         FROM entregas e
                         INNER JOIN usuarios u ON e.estudiante_id = u.id
                         WHERE e.actividad_id = ?
                         ORDER BY e.fecha_entrega DESC";
        
        $stmt_entregas = $conexion->prepare($sql_entregas);
        $stmt_entregas->bind_param("i", $actividad_id);
        $stmt_entregas->execute();
        $entregas = $stmt_entregas->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar Entregas - UDL</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .actividades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .actividad-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .actividad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .actividad-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .actividad-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .grupo-badge {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .fecha-limite {
            color: #666;
            font-size: 14px;
        }

        .entregas-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .stat {
            text-align: center;
            flex: 1;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .pendientes {
            color: #e74c3c !important;
        }

        .entregas-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .entregas-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .entregas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .entregas-table th,
        .entregas-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .entregas-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .entregas-table tr:hover {
            background: #f8f9fa;
        }

        .estado-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .estado-entregada {
            background: #d4edda;
            color: #155724;
        }

        .estado-calificada {
            background: #cce5ff;
            color: #004085;
        }

        .calificacion-input {
            width: 80px;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-link {
            background: #17a2b8;
            color: white;
            font-size: 12px;
            padding: 5px 10px;
        }

        .btn-link:hover {
            background: #138496;
        }

        .back-btn {
            margin-bottom: 20px;
        }

        .comentarios-textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            resize: vertical;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .actividades-grid {
                grid-template-columns: 1fr;
            }
            
            .entregas-table {
                font-size: 14px;
            }
            
            .entregas-table th,
            .entregas-table td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-clipboard-check"></i> Calificar Entregas</h1>
            <p>Gestiona y califica las entregas de tus estudiantes</p>
        </div>

        <?php if (!$actividad_seleccionada): ?>
            <!-- Lista de actividades -->
            <?php if (empty($actividades)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No hay actividades con entregas para calificar.
                </div>
            <?php else: ?>
                <div class="actividades-grid">
                    <?php foreach ($actividades as $actividad): ?>
                        <div class="actividad-card" onclick="location.href='?actividad_id=<?php echo $actividad['id']; ?>'">
                            <h3><?php echo htmlspecialchars($actividad['titulo']); ?></h3>
                            
                            <div class="actividad-info">
                                <span class="grupo-badge"><?php echo htmlspecialchars($actividad['grupo_nombre']); ?></span>
                                <span class="fecha-limite">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?>
                                </span>
                            </div>
                            
                            <p style="color: #666; margin-bottom: 15px;">
                                <?php echo htmlspecialchars(substr($actividad['descripcion'], 0, 100)) . '...'; ?>
                            </p>
                            
                            <div class="entregas-stats">
                                <div class="stat">
                                    <div class="stat-number"><?php echo $actividad['total_entregas']; ?></div>
                                    <div class="stat-label">Total Entregas</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-number pendientes"><?php echo $actividad['pendientes']; ?></div>
                                    <div class="stat-label">Pendientes</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Detalles de entregas de una actividad -->
            <div class="back-btn">
                <a href="calificar_entregas.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver a Actividades
                </a>
            </div>

            <div class="entregas-section">
                <div class="entregas-header">
                    <div>
                        <h2><?php echo htmlspecialchars($actividad_seleccionada['titulo']); ?></h2>
                        <p style="color: #666; margin-top: 5px;">
                            Grupo: <?php echo htmlspecialchars($actividad_seleccionada['grupo_nombre']); ?> | 
                            Fecha límite: <?php echo date('d/m/Y H:i', strtotime($actividad_seleccionada['fecha_limite'])); ?>
                        </p>
                    </div>
                </div>

                <?php if (empty($entregas)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No hay entregas para esta actividad.
                    </div>
                <?php else: ?>
                    <table class="entregas-table">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Archivo</th>
                                <th>Fecha Entrega</th>
                                <th>Estado</th>
                                <th>Calificación</th>
                                <th>Comentarios</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($entrega['nombre'] . ' ' . $entrega['apellido']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($entrega['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($entrega['ruta_archivo'])): ?>
                                            <a href="../../<?php echo htmlspecialchars($entrega['ruta_archivo']); ?>" 
                                               target="_blank" class="btn btn-link">
                                                <i class="fas fa-download"></i> <?php echo htmlspecialchars($entrega['nombre_archivo']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="btn btn-link" style="color: #6c757d; cursor: default;">
                                                <i class="fas fa-file"></i> <?php echo htmlspecialchars($entrega['nombre_archivo'] ?? 'Archivo no disponible'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?></td>
                                    <td>
                                        <span class="estado-badge <?php echo $entrega['calificacion'] ? 'estado-calificada' : 'estado-entregada'; ?>">
                                            <?php echo $entrega['calificacion'] ? 'Calificada' : 'Entregada'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="../../controllers/calificar_entrega.php" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="entrega_id" value="<?php echo $entrega['id']; ?>">
                                            <input type="number" name="calificacion" 
                                                   class="calificacion-input" 
                                                   min="0" max="10" step="0.1"
                                                   value="<?php echo $entrega['calificacion']; ?>"
                                                   placeholder="0-10">
                                    </td>
                                    <td>
                                        <textarea name="comentarios_calificacion" 
                                                  class="comentarios-textarea"
                                                  placeholder="Comentarios sobre la calificación..."><?php echo htmlspecialchars($entrega['comentarios_calificacion'] ?? ''); ?></textarea>
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Guardar
                                        </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Mostrar alertas si existen
        <?php if (isset($_SESSION['mensaje'])): ?>
            alert('<?php echo addslashes($_SESSION['mensaje']); ?>');
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        // Validación de calificaciones
        document.querySelectorAll('.calificacion-input').forEach(input => {
            input.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value < 0) this.value = 0;
                if (value > 10) this.value = 10;
            });
        });
    </script>
</body>
</html>