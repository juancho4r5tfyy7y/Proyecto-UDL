<?php
// Asumimos que la sesión ya está iniciada y las variables de sesión están disponibles.
$nombre_docente = htmlspecialchars($_SESSION['nombre'] ?? 'Docente');
?>
<div class="header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <h1>Panel de Docente</h1>
            <p>Bienvenido, Profesor <?php echo $nombre_docente; ?></p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="/UDL/index.php" class="btn-primary" style="text-decoration: none;">🏠 Inicio</a>
            <form action="/UDL/php/logout.php" method="post" style="display: inline; margin: 0;">
                <button type="submit" class="btn-primary" style="border: none; cursor: pointer;">🚪 Salir</button>
            </form>
        </div>
    </div>
</div>
