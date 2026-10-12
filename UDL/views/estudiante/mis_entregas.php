<?php
session_start();

// Verificar si el usuario está logueado y es estudiante
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'estudiante') {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';

$estudiante_id = $_SESSION['user_id'];

try {
    // Obtener información del estudiante
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$estudiante_id]);
    $estudiante = $stmt->fetch();

    // Obtener entregas del estudiante con información de actividades y calificaciones
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            a.titulo as actividad_titulo,
            a.descripcion as actividad_descripcion,
            a.fecha_limite,
            g.nombre as grupo_nombre,
            CONCAT(d.nombre, ' ', d.apellido) as docente_nombre
        FROM entregas e
        INNER JOIN actividades a ON e.actividad_id = a.id
        INNER JOIN grupos g ON a.grupo_id = g.id
        INNER JOIN usuarios d ON g.docente_id = d.id
        WHERE e.estudiante_id = ?
        ORDER BY e.fecha_entrega DESC
    ");
    $stmt->execute([$estudiante_id]);
    $entregas = $stmt->fetchAll();

    // Calcular estadísticas
    $total_entregas = count($entregas);
    $entregas_calificadas = 0;
    $suma_calificaciones = 0;

    foreach ($entregas as $entrega) {
        if ($entrega['calificacion'] !== null) {
            $entregas_calificadas++;
            $suma_calificaciones += $entrega['calificacion'];
        }
    }

    $promedio_calificaciones = $entregas_calificadas > 0 ? $suma_calificaciones / $entregas_calificadas : 0;

} catch (PDOException $e) {
    error_log("Error en mis_entregas.php: " . $e->getMessage());
    $entregas = [];
    $total_entregas = 0;
    $entregas_calificadas = 0;
    $promedio_calificaciones = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Entregas - UDL</title>
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
            padding: 30px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .entregas-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .entregas-grid {
            display: grid;
            gap: 20px;
        }

        .entrega-card {
            border: 2px solid #f0f0f0;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
        }

        .entrega-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .entrega-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .entrega-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .entrega-grupo {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .entrega-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .info-item i {
            color: #667eea;
            width: 16px;
        }

        .calificacion-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .calificacion-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 16px;
        }

        .calificacion-excelente {
            background: #d4edda;
            color: #155724;
        }

        .calificacion-buena {
            background: #cce5ff;
            color: #004085;
        }

        .calificacion-regular {
            background: #fff3cd;
            color: #856404;
        }

        .calificacion-mala {
            background: #f8d7da;
            color: #721c24;
        }

        .sin-calificar {
            background: #e2e3e5;
            color: #6c757d;
        }

        .comentarios {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .comentarios h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .comentarios p {
            color: #666;
            line-height: 1.5;
        }

        .archivo-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #667eea;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .archivo-link:hover {
            background: #5a6fd8;
            color: white;
        }

        .btn {
            padding: 12px 24px;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .entrega-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .entrega-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-upload"></i> Mis Entregas</h1>
            <p>Revisa tus entregas, calificaciones y comentarios de los docentes</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #667eea;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-number" style="color: #667eea;"><?php echo $total_entregas; ?></div>
                <div class="stat-label">Total Entregas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number" style="color: #28a745;"><?php echo $entregas_calificadas; ?></div>
                <div class="stat-label">Calificadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #ffc107;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number" style="color: #ffc107;">
                    <?php echo $entregas_calificadas > 0 ? number_format($promedio_calificaciones, 1) : '0.0'; ?>
                </div>
                <div class="stat-label">Promedio</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #dc3545;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number" style="color: #dc3545;"><?php echo $total_entregas - $entregas_calificadas; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>

        <!-- Lista de entregas -->
        <div class="entregas-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Historial de Entregas</h2>
                <a href="subir_entrega.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Entrega
                </a>
            </div>

            <?php if (empty($entregas)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No tienes entregas aún</h3>
                    <p>Cuando realices tu primera entrega, aparecerá aquí.</p>
                    <a href="subir_entrega.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-upload"></i> Subir Primera Entrega
                    </a>
                </div>
            <?php else: ?>
                <div class="entregas-grid">
                    <?php foreach ($entregas as $entrega): ?>
                        <div class="entrega-card">
                            <div class="entrega-header">
                                <div>
                                    <div class="entrega-title"><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></div>
                                    <div class="entrega-grupo"><?php echo htmlspecialchars($entrega['grupo_nombre']); ?></div>
                                </div>
                                
                                <?php if ($entrega['calificacion'] !== null): ?>
                                    <?php
                                    $cal = (float)$entrega['calificacion'];
                                    $clase_cal = '';
                                    if ($cal >= 9) $clase_cal = 'calificacion-excelente';
                                    elseif ($cal >= 7) $clase_cal = 'calificacion-buena';
                                    elseif ($cal >= 5) $clase_cal = 'calificacion-regular';
                                    else $clase_cal = 'calificacion-mala';
                                    ?>
                                    <span class="calificacion-badge <?php echo $clase_cal; ?>">
                                        <?php echo number_format($cal, 1); ?>/10
                                    </span>
                                <?php else: ?>
                                    <span class="calificacion-badge sin-calificar">
                                        Sin calificar
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="entrega-info">
                                <div class="info-item">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($entrega['docente_nombre'] . ' ' . $entrega['docente_apellido']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Entregado: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Límite: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_limite'])); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-file"></i>
                                    <?php if (!empty($entrega['ruta_archivo'])): ?>
                                        <a href="../../<?php echo htmlspecialchars($entrega['ruta_archivo']); ?>" 
                                           target="_blank" class="archivo-link">
                                            <i class="fas fa-download"></i>
                                            <?php echo htmlspecialchars($entrega['nombre_archivo']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="archivo-link" style="background: #6c757d;">
                                            <i class="fas fa-file"></i>
                                            <?php echo htmlspecialchars($entrega['nombre_archivo'] ?? 'Archivo no disponible'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($entrega['calificacion'] !== null): ?>
                                <div class="calificacion-section">
                                    <h4><i class="fas fa-star"></i> Calificación: <?php echo number_format($entrega['calificacion'], 1); ?>/10</h4>
                                    
                                    <?php if (!empty($entrega['comentarios_calificacion'])): ?>
                                        <div class="comentarios">
                                            <h4><i class="fas fa-comment"></i> Comentarios del docente:</h4>
                                            <p><?php echo nl2br(htmlspecialchars($entrega['comentarios_calificacion'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .entrega-card');
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