<?php
session_start();
require_once '../../config/database.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado');
}

$grupo_id = $_GET['grupo_id'] ?? 0;

if (!$grupo_id) {
    exit('ID de grupo no válido');
}

$conexion = conexion();

// Obtener estudiantes del grupo
$sql = "SELECT u.id, u.nombre, u.email, eg.fecha_asignacion
        FROM estudiantes_grupos eg
        JOIN usuarios u ON eg.estudiante_id = u.id
        WHERE eg.grupo_id = ?
        ORDER BY u.nombre";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $grupo_id);
$stmt->execute();
$estudiantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener estudiantes disponibles para asignar
$sql_disponibles = "SELECT u.id, u.nombre 
                    FROM usuarios u 
                    WHERE u.rol = 'estudiante' 
                    AND u.id NOT IN (SELECT estudiante_id FROM estudiantes_grupos WHERE grupo_id = ?)
                    ORDER BY u.nombre";

$stmt = $conexion->prepare($sql_disponibles);
$stmt->bind_param("i", $grupo_id);
$stmt->execute();
$estudiantes_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div style="margin-bottom: 1.5rem;">
    <h4>Estudiantes Asignados (<?= count($estudiantes) ?>)</h4>
    
    <?php if (!empty($estudiantes)): ?>
        <div class="students-list">
            <?php foreach ($estudiantes as $estudiante): ?>
                <div class="student-item">
                    <div>
                        <strong><?= htmlspecialchars($estudiante['nombre']) ?></strong><br>
                        <small style="color: #7F8C8D;">
                            <?= htmlspecialchars($estudiante['email']) ?> - 
                            Asignado: <?= date('d/m/Y', strtotime($estudiante['fecha_asignacion'])) ?>
                        </small>
                    </div>
                    <button class="btn btn-sm btn-danger" 
                            onclick="removeStudent(<?= $grupo_id ?>, <?= $estudiante['id'] ?>, '<?= htmlspecialchars($estudiante['nombre']) ?>')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #7F8C8D; padding: 2rem;">
            No hay estudiantes asignados a este grupo
        </p>
    <?php endif; ?>
</div>

<?php if (!empty($estudiantes_disponibles)): ?>
    <div>
        <h4>Asignar Nuevo Estudiante</h4>
        <form method="POST" action="grupos.php" style="display: flex; gap: 1rem; align-items: end;">
            <input type="hidden" name="accion" value="asignar_estudiante">
            <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">
            
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label>Seleccionar Estudiante:</label>
                <select name="estudiante_id" class="form-control" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($estudiantes_disponibles as $estudiante): ?>
                        <option value="<?= $estudiante['id'] ?>">
                            <?= htmlspecialchars($estudiante['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Asignar
            </button>
        </form>
    </div>
<?php else: ?>
    <div style="text-align: center; padding: 1rem; background: #F8F9FA; border-radius: 8px;">
        <i class="fas fa-info-circle" style="color: #C97E28;"></i>
        <p style="margin: 0.5rem 0 0 0; color: #7F8C8D;">
            No hay estudiantes disponibles para asignar
        </p>
    </div>
<?php endif; ?>

<script>
function removeStudent(grupoId, estudianteId, estudianteNombre) {
    if (confirm(`¿Remover a ${estudianteNombre} de este grupo?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'grupos.php';
        form.innerHTML = `
            <input type="hidden" name="accion" value="remover_estudiante">
            <input type="hidden" name="grupo_id" value="${grupoId}">
            <input type="hidden" name="estudiante_id" value="${estudianteId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>