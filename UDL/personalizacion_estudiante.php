<?php
session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'estudiante') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mi Cuenta - Estudiante</title>
  <link rel="stylesheet" href="assets/css/Dashboard.css" />
  <style>
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    label { font-weight: 600; }
    .form-control { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #ddd; }
  </style>
</head>
<body>
  <div class="dashboard-container">
     <?php include 'includes/navigation_estudiante.php'; ?>
    <div class="main-content">
      <div class="header">
        <h1>Mi Cuenta</h1>
        <p>Actualiza tu información personal</p>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-icon bg-secondary">👤</div>
          <h3 class="card-title">Información Personal</h3>
        </div>
                  <div class="card-content">
          <form id="updateForm" method="POST" action="php/actualizar_cuenta.php">
            <div class="form-grid">
              <div>
                <label for="nombre">Nombre/s</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required>
              </div>
              <div>
                <label for="apellido1">Primer Apellido</label>
                <input type="text" id="apellido1" name="apellido1" class="form-control" required>
              </div>
              <div>
                <label for="apellido2">Segundo Apellido</label>
                <input type="text" id="apellido2" name="apellido2" class="form-control">
              </div>
              <div>
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" class="form-control" required>
              </div>
              <div>
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" class="form-control">
              </div>
              <div>
                <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control">
              </div>
              <div>
                <label for="grado">Grado Actual</label>
                <select id="grado" name="grado" class="form-control">
                  <option value="">Seleccionar grado</option>
                  <option value="6">6°</option>
                  <option value="7">7°</option>
                  <option value="8">8°</option>
                  <option value="9">9°</option>
                  <option value="10">10°</option>
                  <option value="11">11°</option>
                </select>
              </div>
              <div>
                <label for="password">Nueva Contraseña (dejar en blanco para mantener la actual)</label>
                <input type="password" id="password" name="password" class="form-control">
              </div>
              <div>
                <label for="confirm_password">Confirmar Nueva Contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
              </div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
              <button type="submit" class="btn btn-primary">Actualizar Información</button>
            </div>
          </form>
          <div id="msg"></div>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const msgElement = document.getElementById('msg');
      
      // Cargar datos actuales del usuario
      fetch('php/obtener_datos_usuario.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
          }
          return response.text().then(text => {
            if (!text) {
              throw new Error('Respuesta vacía del servidor');
            }
            try {
              return JSON.parse(text);
            } catch (e) {
              console.error('Texto recibido:', text);
              throw new Error('Error al parsear JSON: ' + e.message);
            }
          });
        })
        .then(data => {
          console.log('Datos recibidos:', data); // Para depuración
          if (data.error) {
            throw new Error(data.error);
          }
          // Cargar datos básicos
          document.getElementById('nombre').value = data.nombre || '';
          document.getElementById('apellido1').value = data.apellido1 || '';
          document.getElementById('apellido2').value = data.apellido2 || '';
          document.getElementById('email').value = data.email || '';
          document.getElementById('telefono').value = data.telefono || '';
          
          // Cargar fecha de nacimiento si existe
          if (data.fecha_nacimiento) {
            document.getElementById('fecha_nacimiento').value = data.fecha_nacimiento;
          }
          
          // Seleccionar el grado actual si existe
          if (data.grado) {
            document.getElementById('grado').value = data.grado;
          }
        })
        .catch(error => {
          console.error('Error completo:', error);
          msgElement.textContent = 'Error al cargar los datos: ' + error.message;
          msgElement.style.color = 'red';
        });
    });

    document.getElementById('updateForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const msgElement = document.getElementById('msg');
      const password = document.getElementById('password').value;
      const confirm_password = document.getElementById('confirm_password').value;
      
      // Validaciones del lado del cliente
      if (password && password !== confirm_password) {
        msgElement.textContent = 'Las contraseñas no coinciden';
        msgElement.style.color = 'red';
        return;
      }

      const formData = new FormData(this);
      msgElement.textContent = 'Actualizando datos...';
      msgElement.style.color = 'blue';
      
      fetch('php/actualizar_cuenta.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Error en la respuesta del servidor: ' + response.status);
        }
        return response.json();
      })
      .then(data => {
        console.log('Respuesta del servidor:', data); // Para depuración
        msgElement.textContent = data.message;
        msgElement.style.color = data.success ? 'green' : 'red';
        
        if (data.success) {
          // Actualizar el nombre en la interfaz si es necesario
          const nombreUsuario = document.querySelector('.header h1');
          if (nombreUsuario) {
            nombreUsuario.textContent = 'Bienvenido, ' + formData.get('nombre');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        msgElement.textContent = 'Error al actualizar los datos: ' + error.message;
        msgElement.style.color = 'red';
      });
    });
  </script>
</body>
</html>
