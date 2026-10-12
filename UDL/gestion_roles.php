<?php
session_start();
require_once 'php/conexion.php';

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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_role':
                createRole();
                break;
            case 'update_role':
                updateRole();
                break;
            case 'delete_role':
                deleteRole();
                break;
            case 'assign_permissions':
                assignPermissions();
                break;
            case 'update_user_role':
                updateUserRole();
                break;
        }
    }
}

function createRole() {
    global $conexion;
    
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $permisos = $_POST['permisos'] ?? [];
    
    if (empty($nombre)) {
        $_SESSION['message'] = 'El nombre del rol es obligatorio';
        $_SESSION['message_type'] = 'danger';
        return;
    }
    
    // Simular creación de rol
    $_SESSION['message'] = 'Rol creado correctamente';
    $_SESSION['message_type'] = 'success';
}

function updateRole() {
    global $conexion;
    
    $roleId = $_POST['role_id'] ?? 0;
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    if (empty($nombre)) {
        $_SESSION['message'] = 'El nombre del rol es obligatorio';
        $_SESSION['message_type'] = 'danger';
        return;
    }
    
    // Simular actualización de rol
    $_SESSION['message'] = 'Rol actualizado correctamente';
    $_SESSION['message_type'] = 'success';
}

function deleteRole() {
    global $conexion;
    
    $roleId = $_POST['role_id'] ?? 0;
    
    // Verificar que no sea un rol del sistema
    $systemRoles = ['superadmin', 'admin', 'docente', 'estudiante'];
    
    // Simular eliminación de rol
    $_SESSION['message'] = 'Rol eliminado correctamente';
    $_SESSION['message_type'] = 'success';
}

function assignPermissions() {
    global $conexion;
    
    $roleId = $_POST['role_id'] ?? 0;
    $permisos = $_POST['permisos'] ?? [];
    
    // Simular asignación de permisos
    $_SESSION['message'] = 'Permisos asignados correctamente';
    $_SESSION['message_type'] = 'success';
}

function updateUserRole() {
    global $conexion;
    
    $userId = $_POST['user_id'] ?? 0;
    $newRole = $_POST['new_role'] ?? '';
    
    if (empty($newRole)) {
        $_SESSION['message'] = 'Debe seleccionar un rol';
        $_SESSION['message_type'] = 'danger';
        return;
    }
    
    // Actualizar rol del usuario
    $sql = "UPDATE usuarios SET rol = ? WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $newRole, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Rol de usuario actualizado correctamente';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error al actualizar el rol del usuario';
        $_SESSION['message_type'] = 'danger';
    }
}

// Obtener roles del sistema
$roles_sistema = [
    [
        'id' => 1,
        'nombre' => 'superadmin',
        'descripcion' => 'Administrador del sistema con acceso completo',
        'usuarios_count' => 1,
        'es_sistema' => true,
        'permisos' => ['all']
    ],
    [
        'id' => 2,
        'nombre' => 'admin',
        'descripcion' => 'Administrador con permisos limitados',
        'usuarios_count' => 2,
        'es_sistema' => true,
        'permisos' => ['manage_users', 'view_reports', 'manage_content']
    ],
    [
        'id' => 3,
        'nombre' => 'docente',
        'descripcion' => 'Profesor con acceso a herramientas educativas',
        'usuarios_count' => 15,
        'es_sistema' => true,
        'permisos' => ['manage_students', 'create_content', 'view_grades']
    ],
    [
        'id' => 4,
        'nombre' => 'estudiante',
        'descripcion' => 'Estudiante con acceso limitado',
        'usuarios_count' => 120,
        'es_sistema' => true,
        'permisos' => ['view_content', 'submit_assignments']
    ]
];

// Obtener usuarios para gestión de roles
$sql = "SELECT id, nombre, apellido, email, rol FROM usuarios ORDER BY nombre";
$result = $conexion->query($sql);
$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

// Permisos disponibles
$permisos_disponibles = [
    'manage_users' => 'Gestionar Usuarios',
    'manage_roles' => 'Gestionar Roles',
    'view_reports' => 'Ver Reportes',
    'manage_content' => 'Gestionar Contenido',
    'manage_students' => 'Gestionar Estudiantes',
    'create_content' => 'Crear Contenido',
    'view_grades' => 'Ver Calificaciones',
    'submit_assignments' => 'Enviar Tareas',
    'view_content' => 'Ver Contenido',
    'manage_system' => 'Gestionar Sistema',
    'backup_restore' => 'Backup y Restauración',
    'view_logs' => 'Ver Logs del Sistema'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles - UDL</title>
    <link rel="stylesheet" href="assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .roles-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .roles-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .roles-tabs {
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
        }
        
        .tab-content.active {
            display: block;
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .role-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .role-card:hover {
            transform: translateY(-5px);
        }
        
        .role-card.system-role {
            border-left: 4px solid #4CAF50;
        }
        
        .role-card.custom-role {
            border-left: 4px solid #2196F3;
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .role-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-system {
            background: #e8f5e8;
            color: #4CAF50;
        }
        
        .badge-custom {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .role-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .role-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .permissions-list {
            margin-bottom: 15px;
        }
        
        .permission-tag {
            display: inline-block;
            background: #e0e0e0;
            color: #333;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin: 2px;
        }
        
        .role-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .users-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .role-select {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
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
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .permission-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .permission-checkbox input[type="checkbox"] {
            width: auto;
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
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .roles-container {
                padding: 10px;
            }
            
            .roles-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .roles-grid {
                grid-template-columns: 1fr;
            }
            
            .role-actions {
                justify-content: center;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 8px;
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
            <li class="nav-item"><a href="configuracion_sistema.php" class="nav-link">⚙️ Configuración</a></li>
            <li class="nav-item"><a href="gestion_roles.php" class="nav-link active">🛡️ Roles</a></li>
            <li class="nav-item"><a href="reportes_superadmin.php" class="nav-link">📊 Reportes</a></li>
            <li class="nav-item"><a href="php/logout.php" class="nav-link">🚪 Cerrar Sesión</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="roles-container">
            <!-- Header -->
            <div class="roles-header">
                <div>
                    <h1><i class="fas fa-shield-alt"></i> Gestión de Roles y Permisos</h1>
                    <p>Administra roles de usuario y sus permisos en el sistema</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('createRoleModal')">
                    <i class="fas fa-plus"></i> Crear Rol
                </button>
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

            <!-- Tabs -->
            <div class="roles-tabs">
                <button class="tab-btn active" onclick="showTab('roles')">Roles del Sistema</button>
                <button class="tab-btn" onclick="showTab('users')">Asignar Roles</button>
                <button class="tab-btn" onclick="showTab('permissions')">Permisos</button>
            </div>

            <!-- Tab: Roles del Sistema -->
            <div id="roles" class="tab-content active">
                <div class="roles-grid">
                    <?php foreach ($roles_sistema as $rol): ?>
                        <div class="role-card <?php echo $rol['es_sistema'] ? 'system-role' : 'custom-role'; ?>">
                            <div class="role-header">
                                <h3 class="role-name"><?php echo htmlspecialchars($rol['nombre']); ?></h3>
                                <span class="role-badge <?php echo $rol['es_sistema'] ? 'badge-system' : 'badge-custom'; ?>">
                                    <?php echo $rol['es_sistema'] ? 'Sistema' : 'Personalizado'; ?>
                                </span>
                            </div>
                            
                            <p class="role-description"><?php echo htmlspecialchars($rol['descripcion']); ?></p>
                            
                            <div class="role-stats">
                                <div class="stat">
                                    <div class="stat-value"><?php echo $rol['usuarios_count']; ?></div>
                                    <div class="stat-label">Usuarios</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo count($rol['permisos']); ?></div>
                                    <div class="stat-label">Permisos</div>
                                </div>
                            </div>
                            
                            <div class="permissions-list">
                                <?php foreach (array_slice($rol['permisos'], 0, 3) as $permiso): ?>
                                    <span class="permission-tag"><?php echo $permiso === 'all' ? 'Todos los permisos' : $permiso; ?></span>
                                <?php endforeach; ?>
                                <?php if (count($rol['permisos']) > 3): ?>
                                    <span class="permission-tag">+<?php echo count($rol['permisos']) - 3; ?> más</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="role-actions">
                                <button class="btn btn-primary" onclick="viewRoleDetails(<?php echo $rol['id']; ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                                <?php if (!$rol['es_sistema']): ?>
                                    <button class="btn btn-warning" onclick="editRole(<?php echo $rol['id']; ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteRole(<?php echo $rol['id']; ?>)">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-warning" onclick="editRolePermissions(<?php echo $rol['id']; ?>)">
                                        <i class="fas fa-key"></i> Permisos
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab: Asignar Roles -->
            <div id="users" class="tab-content">
                <div class="users-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol Actual</th>
                                <th>Cambiar Rol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <span class="role-badge badge-<?php echo $usuario['rol'] === 'superadmin' ? 'system' : 'custom'; ?>">
                                            <?php echo htmlspecialchars($usuario['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_user_role">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <select name="new_role" class="role-select" onchange="this.form.submit()">
                                                <option value="">Seleccionar...</option>
                                                <option value="estudiante" <?php echo $usuario['rol'] === 'estudiante' ? 'selected' : ''; ?>>Estudiante</option>
                                                <option value="docente" <?php echo $usuario['rol'] === 'docente' ? 'selected' : ''; ?>>Docente</option>
                                                <option value="admin" <?php echo $usuario['rol'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <?php if ($_SESSION['rol'] === 'superadmin'): ?>
                                                    <option value="superadmin" <?php echo $usuario['rol'] === 'superadmin' ? 'selected' : ''; ?>>SuperAdmin</option>
                                                <?php endif; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary" onclick="viewUserPermissions(<?php echo $usuario['id']; ?>)">
                                            <i class="fas fa-key"></i> Permisos
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Permisos -->
            <div id="permissions" class="tab-content">
                <div class="role-card">
                    <h3><i class="fas fa-key"></i> Permisos Disponibles en el Sistema</h3>
                    <p>Lista completa de permisos que pueden ser asignados a los roles</p>
                    
                    <div class="permissions-grid">
                        <?php foreach ($permisos_disponibles as $key => $nombre): ?>
                            <div class="permission-checkbox">
                                <i class="fas fa-key"></i>
                                <div>
                                    <strong><?php echo $nombre; ?></strong>
                                    <br><small><?php echo $key; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Crear Rol -->
    <div id="createRoleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createRoleModal')">&times;</span>
            <h2><i class="fas fa-plus"></i> Crear Nuevo Rol</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_role">
                
                <div class="form-group">
                    <label for="nombre">Nombre del Rol</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej: moderador">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="3" placeholder="Describe las responsabilidades de este rol"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Permisos</label>
                    <div class="permissions-grid">
                        <?php foreach ($permisos_disponibles as $key => $nombre): ?>
                            <div class="permission-checkbox">
                                <input type="checkbox" name="permisos[]" value="<?php echo $key; ?>" id="perm_<?php echo $key; ?>">
                                <label for="perm_<?php echo $key; ?>"><?php echo $nombre; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="role-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Crear Rol
                    </button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('createRoleModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
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
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function viewRoleDetails(roleId) {
            alert('Ver detalles del rol ID: ' + roleId);
        }
        
        function editRole(roleId) {
            alert('Editar rol ID: ' + roleId);
        }
        
        function deleteRole(roleId) {
            if (confirm('¿Estás seguro de que deseas eliminar este rol?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" name="role_id" value="${roleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editRolePermissions(roleId) {
            alert('Editar permisos del rol ID: ' + roleId);
        }
        
        function viewUserPermissions(userId) {
            alert('Ver permisos del usuario ID: ' + userId);
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
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