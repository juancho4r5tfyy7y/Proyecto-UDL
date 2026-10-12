<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/conexion.php';

// Obtener la conexión a la base de datos
$conn = conexion();
if (!$conn) {
    echo json_encode(['ok'=>false,'msg'=>'Error de conexión a la base de datos']);
    exit();
}
$usuario_id = (int)($_POST['usuario_id'] ?? 0);
if ($usuario_id <= 0 || $usuario_id !== (int)($_SESSION['usuario_id'] ?? 0)) {
    echo json_encode(['ok'=>false,'msg'=>'Usuario inválido']);
    exit();
}
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$cargo_actual = trim($_POST['cargo_actual'] ?? '');
$titulos = trim($_POST['titulos'] ?? '');
$area_desempeno = trim($_POST['area_desempeno'] ?? '');
$foto_url = trim($_POST['foto_url'] ?? '');
$fecha_cumpleanos = $_POST['fecha_cumpleanos'] ?? null;
$biografia = trim($_POST['biografia'] ?? '');

// Validación básica
if ($nombre_completo === '') {
    echo json_encode(['ok'=>false,'msg'=>'Nombre completo es requerido']);
    exit();
}

// Insertar o actualizar según exista el registro
$stmt = $conn->prepare("SELECT id FROM docentes WHERE usuario_id = ? LIMIT 1");
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$exists = $res && $res->num_rows > 0;
$stmt->close();

if ($exists) {
    $stmt = $conn->prepare("UPDATE docentes SET nombre_completo=?, cargo_actual=?, titulos=?, area_desempeno=?, foto_url=?, fecha_cumpleanos=?, biografia=? WHERE usuario_id=?");
    $stmt->bind_param('sssssssi', $nombre_completo, $cargo_actual, $titulos, $area_desempeno, $foto_url, $fecha_cumpleanos, $biografia, $usuario_id);
    $ok = $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO docentes (usuario_id, nombre_completo, cargo_actual, titulos, area_desempeno, foto_url, fecha_cumpleanos, biografia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssssss', $usuario_id, $nombre_completo, $cargo_actual, $titulos, $area_desempeno, $foto_url, $fecha_cumpleanos, $biografia);
    $ok = $stmt->execute();
    $stmt->close();
}

echo json_encode(['ok'=>$ok]);