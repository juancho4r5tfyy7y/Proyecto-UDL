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
    <title>Proyecto</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>

<body>
    <header>
        <div class="container">
            <div style="display: flex; align-items: center; gap: 32px;">
                <p class="logo" style="margin-right: 24px;">Lengua Castellana</p>
                <img id="logos" src="assets/images/iebs.png" alt="Logo IEBS">
                <img id="logos" src="assets/images/udl.png" alt="Logo UDL">
                <img id="logos" src="assets/images/sena.png" alt="Logo SENA">
            </div>
            <nav class="nav-menu">
                <div class="nav-links">
                    <div class="nav-dropdown" style="margin-right:auto;">
                        <button type="button" class="nav-item dropdown-toggle">Más <i class="fa fa-chevron-down" aria-hidden="true"></i></button>
                        <div class="dropdown-menu" role="menu">
                            <a href="#quienes" class="dropdown-item">Quiénes somos</a>
                            <a href="#programas" class="dropdown-item">Nuestros programas</a>
                        </div>
                    </div>
                </div>
            </nav>
            <?php if (isset($_SESSION['usuario']) && !empty($_SESSION['usuario'])): ?>
                <?php
                $nombre = isset($_SESSION['nombre']) ? trim($_SESSION['nombre']) : '';
                $apellidos = isset($_SESSION['apellidos']) ? trim($_SESSION['apellidos']) : '';
                $usuario = isset($_SESSION['usuario']) ? trim($_SESSION['usuario']) : '';
                $iniciales = '';
                if ($nombre !== '' || $apellidos !== '') {
                    $partes = preg_split('/\s+/', trim($nombre . ' ' . $apellidos));
                    foreach ($partes as $p) {
                        if ($p !== '') {
                            $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
                        }
                        if (mb_strlen($iniciales) >= 2) break;
                    }
                }
                if ($iniciales === '' && $usuario !== '') {
                    $iniciales = mb_strtoupper(mb_substr($usuario, 0, 2));
                }
                if ($iniciales === '') {
                    $iniciales = 'U';
                }
                ?>
                <div class="account-menu-container">
                    <button type="button" class="user-avatar" aria-haspopup="true" aria-expanded="false"><?php echo htmlspecialchars($iniciales); ?></button>
                    <div class="account-menu" role="menu">
                        <?php
                        $panel_url = '';
                        switch ($_SESSION['rol']) {
                            case 'superadmin':
                                $panel_url = 'superadmin_dashboard.php';
                                break;
                            case 'docente':
                                $panel_url = 'docente_dashboard.php';
                                break;
                            case 'estudiante':
                                $panel_url = 'estudiante_dashboard.php';
                                break;
                            default:
                                $panel_url = 'Dashboard.html';
                        }
                        ?>
                          <form action="<?php echo htmlspecialchars($panel_url); ?>" method="post" style="display:inline;">
                            <button type="submit" class="account-item">Panel</button>
                        </form>

                        <form action="php/logout.php" method="post">
                            <button type="submit" class="account-item">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
                <div class="user-menu-container">
                    <span class="material-icons user-icon">person</span>
                    <div class="user-menu">
                        <a href="form_reg.html">Registrarse</a>
                        <a href="php/login.php">Iniciar sesión</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <!-- ===== SECCIÓN 1: MISIÓN, VISIÓN Y PROPÓSITO ===== -->
    <section id="quienes" class="seccion-mision-vision">
        <div class="container">
            <h2 class="seccion-titulo">Nuestra Identidad</h2>

            <!-- Misión y Visión -->
            <div class="card-section card-row mision-vision-bg">
                <div class="libro-animado-bg">
                    <div class="pagina izq"></div>
                    <div class="pagina der"></div>
                    <div class="lomo"></div>
                </div>
                <div class="card card-vm">
                    <h2 class="no-underline">Misión</h2>
                    <p>Nuestra misión es potenciar el desarrollo integral de los estudiantes a través del dominio de la lengua castellana. Nos dedicamos a cultivar habilidades de comunicación efectiva, pensamiento crítico y creatividad, proporcionando un entorno de aprendizaje dinámico y estimulante que prepare a los jóvenes para enfrentar los desafíos del futuro con seguridad y elocuencia.</p>
                </div>
                <div class="card card-vm">
                    <h2 class="no-underline">Visión</h2>
                    <p>Aspiramos a ser un pilar en la comunidad educativa, reconocidos por nuestra excelencia en la enseñanza de la lengua castellana. Proyectamos un futuro donde nuestros estudiantes no solo dominen el idioma, sino que también lo utilicen como una herramienta para el entendimiento intercultural, el liderazgo y la innovación, contribuyendo activamente a una sociedad más justa y comunicada.</p>
                </div>
            </div>

            <!-- Propósito -->
            <div class="card-section">
                <div class="card card-proposito">
                    <h2>Nuestro Propósito</h2>
                    <p>Nuestro propósito es avivar la pasión por la lengua española, fomentando una apreciación profunda por su riqueza y diversidad. Buscamos inspirar a los estudiantes a explorar nuevas ideas, a expresarse con autenticidad y a valorar el poder de las palabras para construir puentes, resolver conflictos y enriquecer sus vidas y las de quienes los rodean.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== SECCIÓN 2: NUESTROS PROGRAMAS ===== -->
    <section id="programas" class="seccion-programas">
        <h2 class="seccion-titulo">Nuestros Programas</h2>
        <div class="programas-grid">
            <div class="programa-card">
                <div class="card-header">
                    <h3>Información Sobre los Docentes </h3>
                    <button class="btn-info"  onclick="window.location.href='info_docentes.php'">+ Info</button>
                </div>
                <div class="card-content">
                    <div class="docente-info">
                        <div class="info-item">
                            <strong>Docentes:</strong> Prof. Walther Espinal
                        </div>
                        <div class="info-item">
                            <strong>Especialidad:</strong> Lengua Castellana y Literatura
                        </div>
                        <div class="info-item">
                            <strong>Info...</strong> 
                        </div>
                    </div>
                </div>
                <img src="assets/images/br1.png" alt="Decoración 1" class="card-footer-img">
            </div>

            <div class="programa-card">
                <div class="card-header">
                    <h3>Actividades</h3>
                    <?php if (isset($_SESSION['usuario']) && $_SESSION['rol'] === 'estudiante'): ?>
                        <button class="btn-info" onclick="window.location.href='views/estudiante/actividades.php'">Ver Actividades</button>
                    <?php else: ?>
                        <button class="btn-info" onclick="showLoginModal('actividades')">+ Info</button>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <p>Accede a nuestras actividades diseñadas para reforzar el aprendizaje. Material estructurado para un progreso efectivo.</p>
                    <div class="features">
                        <span>✓ Actividades interactivas</span>
                        <span>✓ Ejercicios prácticos</span>
                        <span>✓ Seguimiento de progreso</span>
                    </div>
                </div>
                <img src="assets/images/br2.png" alt="Decoración 2" class="card-footer-img">
            </div>

            <div class="programa-card">
                <div class="card-header">
                    <h3>Material Didáctico</h3>
                    <?php if (isset($_SESSION['usuario']) && $_SESSION['rol'] === 'estudiante'): ?>
                        <button class="btn-info" onclick="window.location.href='views/estudiante/material.php'">Ver Material</button>
                    <?php else: ?>
                        <button class="btn-info" onclick="showLoginModal('material')">+ Info</button>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <p>Sumérgete en nuestro repositorio de materiales didácticos, cuidadosamente seleccionados para enriquecer tu aprendizaje.</p>
                    <div class="features">
                        <span>✓ Guías de estudio</span>
                        <span>✓ Recursos multimedia</span>
                        <span>✓ Material complementario</span>
                    </div>
                </div>
                <img src="assets/images/br3.png" alt="Decoración 3" class="card-footer-img">
            </div>

            <div class="programa-card">
                <div class="card-header">
                    <h3>Planes de Mejoramiento</h3>
                    <?php if (isset($_SESSION['usuario']) && $_SESSION['rol'] === 'estudiante'): ?>
                        <button class="btn-info" onclick="window.location.href='views/estudiante/progreso.php'">Ver Progreso</button>
                    <?php else: ?>
                        <button class="btn-info" onclick="showLoginModal('progreso')">+ Info</button>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <p>Ofrecemos planes de mejoramiento personalizados, diseñados para atender tus necesidades específicas.</p>
                    <div class="features">
                        <span>✓ Diagnóstico personalizado</span>
                        <span>✓ Planes de acción</span>
                        <span>✓ Seguimiento de progreso</span>
                    </div>
                </div>
                <img src="assets/images/br4.png" alt="Decoración 4" class="card-footer-img">
            </div>
        </div>
    </section>


    <?php include 'includes/footer.php'; ?>

    <!-- Modal de Login -->
    <div id="loginModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeLoginModal()">&times;</span>
            <div class="login-container">
                <h1>Iniciar Sesión</h1>
                <p id="modalMessage">Para acceder a esta sección necesitas iniciar sesión como estudiante.</p>
                <?php if (!empty($error)): ?>
                    <div style="background:#ffd6d6; color:#8a0000; padding:10px 12px; border-radius:6px; margin-bottom:12px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="php/login.php" id="modalLoginForm">
                    <div class="input-box">
                        <span class="details">Usuario o Email</span>
                        <input type="text" name="usuario" required>
                    </div>
                    <div class="input-box">
                        <span class="details">Contraseña</span>
                        <input type="password" name="clave" required>
                    </div>
                    <div class="button">
                        <input type="submit" value="Entrar">
                    </div>
                </form>
                <p>¿No tienes cuenta? <a href="form_reg.html">Regístrate</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        // Función para mostrar el modal de login
        function showLoginModal(section) {
            document.getElementById('loginModal').style.display = 'block';

            // Agregar campo hidden con la sección solicitada
            const form = document.getElementById('modalLoginForm');
            let hiddenInput = form.querySelector('input[name="redirect"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'redirect';
                form.appendChild(hiddenInput);
            }
            hiddenInput.value = section;

            // Actualizar mensaje según la sección
            const messages = {
                'actividades': 'Para acceder a las actividades de estudiante necesitas iniciar sesión.',
                'material': 'Para acceder al material didáctico de estudiante necesitas iniciar sesión.',
                'progreso': 'Para acceder a tu progreso académico necesitas iniciar sesión como estudiante.'
            };
            document.getElementById('modalMessage').textContent = messages[section] || 'Para acceder a esta sección necesitas iniciar sesión.';
        }

        // Función para cerrar el modal
        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Cerrar modal cuando se hace clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <style>
        /* Estilos del Modal - Coincide con login.css */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, rgba(249,246,241,0.8) 0%, rgba(243,231,217,0.8) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #fffaf6;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(120,72,36,0.10), 0 1.5px 4px rgba(120,72,36,0.08);
            width: 90%;
            max-width: 400px;
            border: 1.5px solid #a67c52;
            position: relative;
            backdrop-filter: none;
        }

        .close {
            color: #a67c52;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 15px;
            right: 20px;
            cursor: pointer;
            z-index: 1001;
            transition: color 0.2s;
        }

        .close:hover {
            color: #7c3f00;
        }

        .login-container {
            padding: 40px 32px 32px 32px;
        }

        .login-container h1 {
            color: #7c3f00;
            font-size: 2.2em;
            margin-bottom: 24px;
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-weight: 700;
            letter-spacing: 1px;
            text-shadow: 0 2px 8px #f3e7d9;
            text-align: center;
        }

        .login-container p {
            text-align: center;
            margin-bottom: 20px;
            color: #5e4630;
            font-size: 1em;
            font-family: 'Montserrat', Arial, sans-serif;
        }

        .input-box {
            margin-bottom: 18px;
        }

        .input-box span.details {
            font-weight: 600;
            color: #a67c52;
            margin-bottom: 6px;
            font-family: 'Montserrat', Arial, sans-serif;
            letter-spacing: 0.5px;
            display: block;
        }

        .input-box input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1.5px solid #d8c3a5;
            font-size: 1em;
            font-family: 'Montserrat', Arial, sans-serif;
            background: #f9f6f1;
            color: #7c3f00;
            transition: border 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .input-box input:focus {
            border: 1.5px solid #a67c52;
            outline: none;
            box-shadow: 0 0 0 2px #a67c5222;
        }

        .button {
            text-align: center;
            margin-top: 18px;
        }

        .button input[type="submit"] {
            background: linear-gradient(90deg, #a67c52 60%, #7c3f00 100%);
            color: #fffbe6;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 1.1em;
            font-family: 'Montserrat', Arial, sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(120,72,36,0.08);
            transition: background 0.2s, transform 0.2s;
            letter-spacing: 1px;
            width: 100%;
        }

        .button input[type="submit"]:hover {
            background: linear-gradient(90deg, #7c3f00 60%, #a67c52 100%);
            transform: translateY(-2px) scale(1.03);
        }

        .login-container a {
            color: #a67c52;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
            font-family: 'Montserrat', Arial, sans-serif;
        }

        .login-container a:hover {
            color: #7c3f00;
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 500px) {
            .login-container {
                padding: 24px 8px 18px 8px;
                max-width: 98vw;
            }
        }
    </style>
</body>

</html>
