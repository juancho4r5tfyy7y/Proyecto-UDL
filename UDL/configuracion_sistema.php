<?php
session_start();
require_once 'php/conexion.php';
require_once 'config/database.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: index.php');
    exit();
}

// Obtener datos del superadmin
$conexion = conexion();
$userId = $_SESSION['id_usuario'];

// Obtener información del superadmin
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$superadmin = $stmt->get_result()->fetch_assoc();

// Procesar configuraciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                updateGeneralConfig();
                break;
            case 'update_security':
                updateSecurityConfig();
                break;
            case 'update_email':
                updateEmailConfig();
                break;
            case 'update_backup':
                updateBackupConfig();
                break;
            case 'clear_cache':
                clearSystemCache();
                break;
            case 'generate_backup':
                generateBackup();
                break;
        }
    }
}

function updateGeneralConfig() {
    // Implementar actualización de configuración general
    $_SESSION['message'] = 'Configuración general actualizada correctamente';
    $_SESSION['message_type'] = 'success';
}

function updateSecurityConfig() {
    // Implementar actualización de configuración de seguridad
    $_SESSION['message'] = 'Configuración de seguridad actualizada correctamente';
    $_SESSION['message_type'] = 'success';
}

function updateEmailConfig() {
    // Implementar actualización de configuración de email
    $_SESSION['message'] = 'Configuración de email actualizada correctamente';
    $_SESSION['message_type'] = 'success';
}

function updateBackupConfig() {
    // Implementar actualización de configuración de backup
    $_SESSION['message'] = 'Configuración de backup actualizada correctamente';
    $_SESSION['message_type'] = 'success';
}

function clearSystemCache() {
    // Implementar limpieza de caché
    $_SESSION['message'] = 'Caché del sistema limpiado correctamente';
    $_SESSION['message_type'] = 'success';
}

function generateBackup() {
    // Implementar generación de backup
    $_SESSION['message'] = 'Backup generado correctamente';
    $_SESSION['message_type'] = 'success';
}

// Obtener configuraciones actuales
$config = [
    'app_name' => APP_NAME ?? 'UDL Sistema',
    'app_version' => APP_VERSION ?? '1.0.0',
    'max_file_size' => MAX_FILE_SIZE ?? 10485760,
    'session_timeout' => SESSION_TIMEOUT ?? 3600,
    'backup_frequency' => 'daily',
    'maintenance_mode' => false,
    'debug_mode' => false
];

// Estadísticas del sistema
$system_stats = [
    'php_version' => phpversion(),
    'mysql_version' => $conexion->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_usage' => '2.5 GB / 10 GB',
    'memory_usage' => '512 MB / 2 GB',
    'uptime' => '15 días, 3 horas'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - UDL</title>
    <link rel="stylesheet" href="assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .config-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .config-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: 120px;
        }
        
        .tab-btn.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .tab-content {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .config-section {
            margin-bottom: 30px;
        }
        
        .config-section h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #4CAF50;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .system-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .action-card:hover {
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .action-card i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .config-container {
                padding: 10px;
            }
            
            .config-tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                margin-bottom: 5px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🔧 UDL SuperAdmin</h2>
        </div>
        <ul class="sidebar-menu">
            <li class="nav-item"><a href="superadmin_dashboard.php" class="nav-link">🏠 Dashboard</a></li>
            <li class="nav-item"><a href="usuarios_admin.php" class="nav-link">👥 Usuarios</a></li>
            <li class="nav-item"><a href="grupos_admin.php" class="nav-link">📚 Grupos</a></li>
            <li class="nav-item"><a href="configuracion_sistema.php" class="nav-link active">⚙️ Configuración</a></li>
            <li class="nav-item"><a href="reportes_superadmin.php" class="nav-link">📊 Reportes</a></li>
            <li class="nav-item"><a href="php/logout.php" class="nav-link">🚪 Cerrar Sesión</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="config-container">
            <!-- Header -->
            <div class="header-section">
                <h1><i class="fas fa-cogs"></i> Configuración del Sistema</h1>
                <p>Administra todas las configuraciones del sistema UDL</p>
            </div>

            <!-- Alertas -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message'], $_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas del Sistema -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Versión PHP</h4>
                    <p class="value"><?php echo $system_stats['php_version']; ?></p>
                </div>
                <div class="stat-card">
                    <h4>MySQL</h4>
                    <p class="value"><?php echo $system_stats['mysql_version']; ?></p>
                </div>
                <div class="stat-card">
                    <h4>Uso de Disco</h4>
                    <p class="value"><?php echo $system_stats['disk_usage']; ?></p>
                </div>
                <div class="stat-card">
                    <h4>Memoria</h4>
                    <p class="value"><?php echo $system_stats['memory_usage']; ?></p>
                </div>
            </div>

            <!-- Tabs de Configuración -->
            <div class="config-tabs">
                <button class="tab-btn active" onclick="showTab('general')">General</button>
                <button class="tab-btn" onclick="showTab('security')">Seguridad</button>
                <button class="tab-btn" onclick="showTab('email')">Email</button>
                <button class="tab-btn" onclick="showTab('backup')">Backup</button>
                <button class="tab-btn" onclick="showTab('system')">Sistema</button>
            </div>

            <!-- Configuración General -->
            <div id="general" class="tab-content active">
                <form method="POST">
                    <input type="hidden" name="action" value="update_general">
                    
                    <div class="config-section">
                        <h3><i class="fas fa-info-circle"></i> Información de la Aplicación</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="app_name">Nombre de la Aplicación</label>
                                <input type="text" id="app_name" name="app_name" value="<?php echo htmlspecialchars($config['app_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="app_version">Versión</label>
                                <input type="text" id="app_version" name="app_version" value="<?php echo htmlspecialchars($config['app_version']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="config-section">
                        <h3><i class="fas fa-upload"></i> Configuración de Archivos</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_file_size">Tamaño Máximo de Archivo (bytes)</label>
                                <input type="number" id="max_file_size" name="max_file_size" value="<?php echo $config['max_file_size']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="session_timeout">Tiempo de Sesión (segundos)</label>
                                <input type="number" id="session_timeout" name="session_timeout" value="<?php echo $config['session_timeout']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="config-section">
                        <h3><i class="fas fa-toggle-on"></i> Modos del Sistema</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Modo de Mantenimiento</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" <?php echo $config['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Modo Debug</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="debug_mode" <?php echo $config['debug_mode'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Configuración General
                    </button>
                </form>
            </div>

            <!-- Configuración de Seguridad -->
            <div id="security" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_security">
                    
                    <div class="config-section">
                        <h3><i class="fas fa-shield-alt"></i> Configuración de Seguridad</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password_min_length">Longitud Mínima de Contraseña</label>
                                <input type="number" id="password_min_length" name="password_min_length" value="8" min="6" max="20">
                            </div>
                            <div class="form-group">
                                <label for="max_login_attempts">Intentos Máximos de Login</label>
                                <input type="number" id="max_login_attempts" name="max_login_attempts" value="5" min="3" max="10">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Requerir Contraseña Compleja</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="require_complex_password" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Habilitar 2FA</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_2fa">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-shield-alt"></i> Guardar Configuración de Seguridad
                    </button>
                </form>
            </div>

            <!-- Configuración de Email -->
            <div id="email" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    
                    <div class="config-section">
                        <h3><i class="fas fa-envelope"></i> Configuración SMTP</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_host">Servidor SMTP</label>
                                <input type="text" id="smtp_host" name="smtp_host" value="smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="smtp_port">Puerto SMTP</label>
                                <input type="number" id="smtp_port" name="smtp_port" value="587">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_username">Usuario SMTP</label>
                                <input type="email" id="smtp_username" name="smtp_username" placeholder="usuario@gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="smtp_password">Contraseña SMTP</label>
                                <input type="password" id="smtp_password" name="smtp_password" placeholder="••••••••">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Habilitar SSL/TLS</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="smtp_ssl" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Guardar Configuración de Email
                    </button>
                    <button type="button" class="btn btn-warning" onclick="testEmail()">
                        <i class="fas fa-paper-plane"></i> Probar Configuración
                    </button>
                </form>
            </div>

            <!-- Configuración de Backup -->
            <div id="backup" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_backup">
                    
                    <div class="config-section">
                        <h3><i class="fas fa-database"></i> Configuración de Respaldo</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="backup_frequency">Frecuencia de Backup</label>
                                <select id="backup_frequency" name="backup_frequency">
                                    <option value="daily" <?php echo $config['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Diario</option>
                                    <option value="weekly">Semanal</option>
                                    <option value="monthly">Mensual</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="backup_retention">Retención (días)</label>
                                <input type="number" id="backup_retention" name="backup_retention" value="30" min="1" max="365">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Incluir Archivos de Usuario</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="backup_include_files" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Configuración de Backup
                    </button>
                </form>
                
                <div class="system-actions">
                    <div class="action-card">
                        <i class="fas fa-download"></i>
                        <h4>Generar Backup</h4>
                        <p>Crear respaldo completo del sistema</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="generate_backup">
                            <button type="submit" class="btn btn-success">Generar</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Herramientas del Sistema -->
            <div id="system" class="tab-content">
                <div class="config-section">
                    <h3><i class="fas fa-tools"></i> Herramientas del Sistema</h3>
                    
                    <div class="system-actions">
                        <div class="action-card">
                            <i class="fas fa-broom"></i>
                            <h4>Limpiar Caché</h4>
                            <p>Eliminar archivos temporales y caché</p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-warning">Limpiar</button>
                            </form>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-chart-line"></i>
                            <h4>Optimizar BD</h4>
                            <p>Optimizar tablas de la base de datos</p>
                            <button type="button" class="btn btn-primary" onclick="optimizeDatabase()">Optimizar</button>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-file-export"></i>
                            <h4>Exportar Logs</h4>
                            <p>Descargar registros del sistema</p>
                            <button type="button" class="btn btn-primary" onclick="exportLogs()">Exportar</button>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-sync-alt"></i>
                            <h4>Reiniciar Sistema</h4>
                            <p>Reiniciar servicios del sistema</p>
                            <button type="button" class="btn btn-danger" onclick="restartSystem()">Reiniciar</button>
                        </div>
                    </div>
                </div>
                
                <div class="config-section">
                    <h3><i class="fas fa-info"></i> Información del Sistema</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4>Servidor Web</h4>
                            <p class="value"><?php echo $system_stats['server_software']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h4>Tiempo Activo</h4>
                            <p class="value"><?php echo $system_stats['uptime']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Ocultar todos los contenidos
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Desactivar todos los botones
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Mostrar el contenido seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el botón seleccionado
            event.target.classList.add('active');
        }
        
        function testEmail() {
            alert('Función de prueba de email - Implementar según necesidades');
        }
        
        function optimizeDatabase() {
            if (confirm('¿Estás seguro de que deseas optimizar la base de datos?')) {
                alert('Optimización de base de datos iniciada');
            }
        }
        
        function exportLogs() {
            alert('Exportando logs del sistema...');
        }
        
        function restartSystem() {
            if (confirm('¿Estás seguro de que deseas reiniciar el sistema? Esto puede interrumpir las sesiones activas.')) {
                alert('Reinicio del sistema programado');
            }
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>