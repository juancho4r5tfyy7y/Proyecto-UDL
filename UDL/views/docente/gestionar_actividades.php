<?php
session_start();
require_once '../../config/database.php';
$additionalCSS = ['Dashboard.css'];
require_once '../../includes/header.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id'];

// Obtener información del docente
$sql = "SELECT u.*, d.area_desempeno as especialidad FROM usuarios u LEFT JOIN docentes d ON u.id = d.usuario_id WHERE u.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$docente = $stmt->get_result()->fetch_assoc();

// Obtener grupos del docente
$sql_grupos = "SELECT id, nombre FROM grupos WHERE docente_id = ?";
$stmt_grupos = $conexion->prepare($sql_grupos);
$stmt_grupos->bind_param("i", $userId);
$stmt_grupos->execute();
$grupos = $stmt_grupos->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener actividades del docente
$sql_actividades = "SELECT a.*, g.nombre as grupo_nombre FROM actividades a JOIN grupos g ON a.grupo_id = g.id WHERE g.docente_id = ? ORDER BY a.fecha_creacion DESC";
$stmt_actividades = $conexion->prepare($sql_actividades);
$stmt_actividades->bind_param("i", $userId);
$stmt_actividades->execute();
$actividades = $stmt_actividades->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="dashboard-container">
    <?php include '../../includes/navigation_docente.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-tasks"></i> Gestionar Actividades</h1>
            <p>Crea, edita y asigna actividades a tus grupos.</p>
        </div>

        <div class="actions-bar">
            <a href="crear_actividad.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Actividad
            </a>
        </div>

        <div class="materials-section">
            <h3><i class="fas fa-list"></i> Lista de Actividades</h3>
            
            <?php if (empty($actividades)): ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h4>No has creado actividades</h4>
                    <p>Crea tu primera actividad para empezar a interactuar con tus estudiantes.</p>
                    <a href="crear_actividad.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear Actividad
                    </a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Grupo</th>
                            <th>Fecha de Entrega</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actividades as $actividad): ?>
                            <tr>
                                <td><?= htmlspecialchars($actividad['titulo']) ?></td>
                                <td><?= htmlspecialchars($actividad['grupo_nombre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($actividad['fecha_entrega'])) ?></td>
                                <td>
                                    <a href="editar_actividad.php?id=<?= $actividad['id'] ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="ver_entregas.php?id=<?= $actividad['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Ver Entregas
                                    </a>
                                    <form method="POST" action="eliminar_actividad.php" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta actividad?');">
                                        <input type="hidden" name="id" value="<?= $actividad['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once '../../includes/footer.php'; ?>
