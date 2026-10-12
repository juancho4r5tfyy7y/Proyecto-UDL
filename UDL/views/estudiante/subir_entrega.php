<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../../index.php');
    exit();
}

$conexion = conexion();
$userId = $_SESSION['id'];
$actividadId = $_GET['actividad'] ?? 0;

// Obtener información de la actividad
$sql = "SELECT a.*, g.nombre as grupo_nombre, g.docente_id,
        CONCAT(d.nombre, ' ', d.apellido) as docente_nombre
        FROM actividades a
        JOIN grupos g ON a.grupo_id = g.id
        JOIN estudiantes_grupos eg ON g.id = eg.grupo_id
        JOIN usuarios d ON g.docente_id = d.id
        WHERE a.id = ? AND eg.estudiante_id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $actividadId, $userId);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();

if (!$actividad) {
    $_SESSION['error'] = 'Actividad no encontrada o sin acceso';
    header('Location: actividades.php');
    exit();
}

// Verificar si ya existe una entrega
$sql = "SELECT * FROM entregas WHERE estudiante_id = ? AND actividad_id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $userId, $actividadId);
$stmt->execute();
$entregaExistente = $stmt->get_result()->fetch_assoc();

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'subir_entrega') {
        $comentarios = $_POST['comentarios'] ?? '';

        // Verificar que se subió un archivo
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Debes seleccionar un archivo para subir';
        } else {
            $archivo = $_FILES['archivo'];

            // Validaciones básicas
            $maxSize = 10 * 1024 * 1024; // 10MB
            $allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/gif'
            ];

            if ($archivo['size'] > $maxSize) {
                $_SESSION['error'] = 'El archivo es demasiado grande. Máximo 10MB permitido.';
            } elseif (!in_array($archivo['type'], $allowedTypes)) {
                $_SESSION['error'] = 'Tipo de archivo no permitido. Solo se permiten PDF, Word, texto e imágenes.';
            } else {
                // Crear directorio si no existe
                $uploadDir = '../../storage/uploads/actividad_' . $actividadId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Generar nombre único
                $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
                $nombreArchivo = sprintf(
                    "%s_%s_%s_%s.%s",
                    $_SESSION['usuario'],
                    preg_replace('/[^a-zA-Z0-9_-]/', '_', $actividad['titulo']),
                    date('Y-m-d_H-i-s'),
                    uniqid(),
                    $extension
                );

                $rutaArchivo = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($archivo['tmp_name'], $rutaArchivo)) {
                    // Guardar en base de datos
