<?php
session_start();
require_once __DIR__ . '/php/conexion.php';
$rol = $_SESSION['rol'] ?? '';
// Si el rol quedó como 'admin' por registros antiguos, normalizar a 'docente'
if ($rol === 'admin') {
    $_SESSION['rol'] = 'docente';
    $rol = 'docente';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información de Docentes - Lengua Castellana</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b3009ff;
            --primary-dark: #d8a15aff;
            --secondary-color: #aa7941ff;
            --text-primary: #2c3e50;
            --text-secondary: #34495e;
            --text-muted: #7f8c8d;
            --background-light: #f5f7fa;
            --background-card: #ffffff;
            --border-color: #e1e8ed;
            --shadow-color: rgba(0,0,0,0.1);
            --shadow-hover: rgba(0,0,0,0.15);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, var(--background-light) 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .header-simple {
            background: var(--background-card);
            padding: 20px 0;
            box-shadow: 0 2px 10px var(--shadow-color);
            text-align: center;
        }

        .header-simple h1 {
            color: var(--text-primary);
            margin: 0;
            font-size: 2rem;
        }

        .docentes-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .docentes-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .docentes-header h2 {
            color: var(--text-primary);
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .docentes-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .back-button {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            margin-bottom: 30px;
            transition: background 0.3s ease;
            font-size: 1rem;
        }

        .back-button:hover {
            background: var(--primary-dark);
        }

        .docentes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .docente-card {
            background: var(--background-card);
            border-radius: 15px;
            box-shadow: 0 10px 30px var(--shadow-color);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .docente-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px var(--shadow-hover);
        }

        .docente-photo {
            height: 200px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            position: relative;
        }

        .docente-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .docente-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .docente-info {
            padding: 25px;
        }

        .docente-nombre {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .docente-especialidad {
            color: var(--primary-color);
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .info-item {
            margin-bottom: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-label {
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .info-value {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.5;
        }

        .no-docentes {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
            background: var(--background-card);
            border-radius: 15px;
            box-shadow: 0 10px 30px var(--shadow-color);
        }

        .no-docentes i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #bdc3c7;
        }
    </style>
</head>

<body>
    <header class="header-simple">
        <h1>Lengua Castellana</h1>
    </header>

    <main class="docentes-container">
        <div class="docentes-header">
            <h2><i class="fas fa-users"></i> Información de Docentes</h2>
            <p>Conoce al equipo de profesionales que guían tu aprendizaje</p>
        </div>

        <a href="index.php#programas" class="back-button">
            <i class="fas fa-arrow-left"></i> Volver a programas
        </a>

        <?php
        // Consulta para obtener información de los docentes
        $conexion = conexion();

        if ($conexion === null) {
            echo '<div class="no-docentes">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Error de conexión</h2>
                    <p>No se pudo conectar a la base de datos.</p>
                  </div>';
        } else {
            $sql = "SELECT u.id, u.nombre, u.apellidos, u.email, u.contacto,
                           d.nombre_completo, d.cargo_actual, d.titulos, d.area_desempeno,
                           d.foto_url, d.fecha_cumpleanos, d.biografia
                    FROM usuarios u
                    LEFT JOIN docentes d ON u.id = d.usuario_id
                    WHERE u.rol IN ('docente', 'admin')
                    ORDER BY u.nombre, u.apellidos";

            $result = $conexion->query($sql);

            if ($result && $result->num_rows > 0):
        ?>
        <div class="docentes-grid">
            <?php while ($docente = $result->fetch_assoc()): ?>
            <div class="docente-card">
                <div class="docente-photo">
                    <?php if (!empty($docente['foto_url'])): ?>
                        <img src="<?php echo htmlspecialchars($docente['foto_url']); ?>" alt="Foto de <?php echo htmlspecialchars($docente['nombre_completo'] ?? $docente['nombre'] . ' ' . $docente['apellidos']); ?>">
                    <?php else: ?>
                        <div class="docente-avatar">
                            <?php
                            $nombre_completo = trim(($docente['nombre_completo'] ?? $docente['nombre'] . ' ' . $docente['apellidos']));
                            $iniciales = '';
                            if (!empty($nombre_completo)) {
                                $partes = preg_split('/\s+/', $nombre_completo);
                                foreach ($partes as $parte) {
                                    if (!empty($parte)) {
                                        $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
                                    }
                                    if (strlen($iniciales) >= 2) break;
                                }
                            }
                            echo htmlspecialchars($iniciales ?: 'D');
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="docente-info">
                    <div class="docente-nombre">
                        <?php echo htmlspecialchars($docente['nombre_completo'] ?? $docente['nombre'] . ' ' . $docente['apellidos']); ?>
                    </div>
                    <div class="docente-especialidad">
                        <i class="fas fa-graduation-cap"></i>
                        <?php echo htmlspecialchars($docente['area_desempeno'] ?? $docente['cargo_actual'] ?? 'Docente de Lengua Castellana'); ?>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Correo electrónico:</div>
                        <div class="info-value">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($docente['email']); ?>
                        </div>
                    </div>

                    <?php if (!empty($docente['contacto'])): ?>
                    <div class="info-item">
                        <div class="info-label">Teléfono:</div>
                        <div class="info-value">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($docente['contacto']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($docente['titulos'])): ?>
                    <div class="info-item">
                        <div class="info-label">Títulos académicos:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($docente['titulos']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($docente['cargo_actual'])): ?>
                    <div class="info-item">
                        <div class="info-label">Cargo actual:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($docente['cargo_actual']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($docente['fecha_cumpleanos'])): ?>
                    <div class="info-item">
                        <div class="info-label">Fecha de cumpleaños:</div>
                        <div class="info-value">
                            <i class="fas fa-birthday-cake"></i>
                            <?php echo date('d \d\e F', strtotime($docente['fecha_cumpleanos'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($docente['biografia'])): ?>
                    <div class="info-item">
                        <div class="info-label">Biografía:</div>
                        <div class="info-value">
                            <?php echo nl2br(htmlspecialchars($docente['biografia'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="no-docentes">
            <i class="fas fa-users"></i>
            <h2>No hay docentes registrados</h2>
            <p>En este momento no hay información de docentes disponible.</p>
        </div>
        <?php endif;
        } ?>
    </main>
</body>

</html>
