<?php
session_start();
require_once '../../php/conexion.php';

// Verificar autenticación y rol de superadmin
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'superadmin') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$mensaje = '';
$error = '';

// Procesar la actualización de asignaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['docente_id'])) {
    $docente_id = intval($_POST['docente_id']);
    $grados_asignados = $_POST['grados'] ?? [];
    
    try {
        $conexion->begin_transaction();
        
        $sql_delete = "DELETE FROM docente_grados WHERE docente_id = ?";
        $stmt_delete = $conexion->prepare($sql_delete);
        $stmt_delete->bind_param("i", $docente_id);
        $stmt_delete->execute();
        
        if (!empty($grados_asignados)) {
            $sql_insert = "INSERT INTO docente_grados (docente_id, grado) VALUES (?, ?)";
            $stmt_insert = $conexion->prepare($sql_insert);
            foreach ($grados_asignados as $grado) {
                $stmt_insert->bind_param("is", $docente_id, $grado);
                $stmt_insert->execute();
            }
        }
        
        $conexion->commit();
        $mensaje = "Asignaciones actualizadas correctamente.";
        
    } catch (Exception $e) {
        $conexion->rollback();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Obtener docentes (rol 'admin')
$sql_docentes = "SELECT id, nombre, apellidos FROM usuarios WHERE rol = 'admin' ORDER BY nombre, apellidos";
$docentes = $conexion->query($sql_docentes)->fetch_all(MYSQLI_ASSOC);

// Obtener asignaciones actuales
$sql_asignaciones = "SELECT docente_id, grado FROM docente_grados";
$resultado_asignaciones = $conexion->query($sql_asignaciones);
$asignaciones = [];
while ($row = $resultado_asignaciones->fetch_assoc()) {
    $asignaciones[$row['docente_id']][] = $row['grado'];
}

$grados_disponibles = ['6', '7', '8', '9', '10', '11'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grupos - UDL</title>
    <link rel="stylesheet" href="../../assets/css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .teacher-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .teacher-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden; }
        .teacher-header { padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
        .teacher-header h3 { margin: 0; font-size: 1.2rem; color: #34495e; }
        .teacher-body { padding: 1.5rem; }
        .grades-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .grade-option { display: flex; align-items: center; background: #f8f9fa; padding: 0.75rem; border-radius: 8px; }
        .grade-option input[type="checkbox"] { width: 1.2em; height: 1.2em; margin-right: 0.75rem; }
        .btn-save { background: #C97E28; color: white; width: 100%; padding: 0.75rem; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background 0.3s; }
        .btn-save:hover { background: #996515; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #D1FAE5; color: #059669; }
        .alert-danger { background: #FEE2E2; color: #DC2626; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/navigation_superadmin.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Gestión de Grados</h1>
                <p>Asigna los grados que cada docente tiene a su cargo.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="teacher-grid">
                <?php foreach ($docentes as $docente): ?>
                    <div class="teacher-card">
                        <div class="teacher-header">
                            <h3><i class="fas fa-user-tie"></i> <?= htmlspecialchars($docente['nombre'] . ' ' . $docente['apellidos']) ?></h3>
                        </div>
                        <div class="teacher-body">
                            <form method="POST">
                                <input type="hidden" name="docente_id" value="<?= $docente['id'] ?>">
                                <div style="margin-bottom: 1rem;">
                                    <label style="font-weight: 600; margin-bottom: 1rem; display: block;">Grados Asignados:</label>
                                    <div class="grades-grid">
                                        <?php
                                        $grados_asignados_docente = $asignaciones[$docente['id']] ?? [];
                                        foreach ($grados_disponibles as $grado):
                                            $is_checked = in_array($grado, $grados_asignados_docente);
                                        ?>
                                            <div class="grade-option">
                                                <input type="checkbox" id="grado_<?= $docente['id'] ?>_<?= $grado ?>" name="grados[]" value="<?= $grado ?>" <?= $is_checked ? 'checked' : '' ?>>
                                                <label for="grado_<?= $docente['id'] ?>_<?= $grado ?>">Grado <?= $grado ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
