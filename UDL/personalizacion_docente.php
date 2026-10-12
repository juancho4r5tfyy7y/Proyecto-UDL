<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'docente') {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/php/conexion.php';
$conn = conexion();
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
// Cargar datos del docente (si existen)
$docente = null;
if ($usuario_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM docentes WHERE usuario_id = ? LIMIT 1");
    $stmt->bind_param('i', $usuario_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $docente = $res->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Configuración del Docente</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
  <style>
    form .row { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    label { font-weight:600; }
    input, textarea { width:100%; padding:8px; border-radius:8px; border:1px solid #ddd; }
    textarea { min-height:100px; }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <?php include 'includes/navigation_docente.php'; ?>
    <div class="main-content">
      <div class="header">
        <h1>Personalización del Docente</h1>
        <p>Gestiona tus datos públicos y de perfil.</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-secondary">🧑‍🏫</div>
          <h3 class="card-title">Perfil</h3>
        </div>
        <div class="card-content">
          <form action="php/guardar_docente.php" method="post">
            <input type="hidden" name="usuario_id" value="<?php echo htmlspecialchars($usuario_id); ?>">
            <div class="row">
              <div>
                <label>Nombre completo</label>
                <input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($docente['nombre_completo'] ?? ''); ?>" required>
              </div>
              <div>
                <label>Cargo actual</label>
                <input type="text" name="cargo_actual" value="<?php echo htmlspecialchars($docente['cargo_actual'] ?? ''); ?>">
              </div>
              <div>
                <label>Títulos</label>
                <input type="text" name="titulos" value="<?php echo htmlspecialchars($docente['titulos'] ?? ''); ?>">
              </div>
              <div>
                <label>Área de desempeño</label>
                <input type="text" name="area_desempeno" value="<?php echo htmlspecialchars($docente['area_desempeno'] ?? ''); ?>">
              </div>
              <div>
                <label>Foto (URL)</label>
                <input type="url" name="foto_url" value="<?php echo htmlspecialchars($docente['foto_url'] ?? ''); ?>">
              </div>
              <div>
                <label>Fecha de cumpleaños</label>
                <input type="date" name="fecha_cumpleanos" value="<?php echo htmlspecialchars($docente['fecha_cumpleanos'] ?? ''); ?>">
              </div>
            </div>
            <div style="margin-top:12px;">
              <label>Biografía</label>
              <textarea name="biografia" placeholder="Escribe una breve biografía..."><?php echo htmlspecialchars($docente['biografia'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:12px;">Guardar</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de notificación -->
  <div id="notificationModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-icon" id="modalIcon">✅</span>
        <h3 id="modalTitle">Información guardada</h3>
        <span class="close" onclick="closeModal()">&times;</span>
      </div>
      <div class="modal-body">
        <p id="modalMessage">Los datos del docente se han guardado correctamente.</p>
      </div>
      <div class="modal-footer">
        <button onclick="closeModal()" class="btn-primary">Aceptar</button>
      </div>
    </div>
  </div>

  <style>
    .modal {
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .modal-content {
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
      max-width: 400px;
      width: 90%;
      animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .modal-icon {
      font-size: 24px;
    }
    
    .modal-header h3 {
      margin: 0;
      flex: 1;
      color: #333;
    }
    
    .close {
      font-size: 24px;
      cursor: pointer;
      color: #999;
      transition: color 0.2s;
    }
    
    .close:hover {
      color: #333;
    }
    
    .modal-body {
      padding: 20px;
    }
    
    .modal-body p {
      margin: 0;
      color: #666;
      line-height: 1.5;
    }
    
    .modal-footer {
      padding: 20px;
      border-top: 1px solid #eee;
      text-align: right;
    }
    
    .success {
      border-left: 4px solid #4CAF50;
    }
    
    .error {
      border-left: 4px solid #f44336;
    }
  </style>

  <script>
    function closeModal() {
      document.getElementById('notificationModal').style.display = 'none';
    }
    
    function showNotification(type, title, message) {
      const modal = document.getElementById('notificationModal');
      const icon = document.getElementById('modalIcon');
      const titleEl = document.getElementById('modalTitle');
      const messageEl = document.getElementById('modalMessage');
      const content = modal.querySelector('.modal-content');
      
      // Configurar contenido
      titleEl.textContent = title;
      messageEl.textContent = message;
      
      // Configurar estilo según tipo
      if (type === 'success') {
        icon.textContent = '✅';
        content.className = 'modal-content success';
      } else if (type === 'error') {
        icon.textContent = '❌';
        content.className = 'modal-content error';
      }
      
      // Mostrar modal
      modal.style.display = 'flex';
      
      // Auto-cerrar después de 3 segundos
      setTimeout(() => {
        closeModal();
      }, 3000);
    }
    
    // Interceptar envío del formulario
    document.querySelector('form').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      
      fetch('php/guardar_docente.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.ok) {
          showNotification('success', 'Información guardada', 'Los datos del docente se han actualizado correctamente.');
        } else {
          showNotification('error', 'Error al guardar', data.msg || 'Ocurrió un error al guardar la información.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Error de conexión', 'No se pudo conectar con el servidor.');
      });
    });
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModal();
      }
    });
  </script>
</body>
</html>
        console.error('Error:', error);
        showNotification('error', 'Error de conexión', 'No se pudo conectar con el servidor.');
      });
    });
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModal();
      }
    });
  </script>
</body>
</html>
