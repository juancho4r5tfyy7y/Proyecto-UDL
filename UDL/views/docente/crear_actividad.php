<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'docente') {
    header('Location: ../../index.php');
    exit();
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conexion = conexion();
$docente_id = $_SESSION['id'];

// Obtener grados asignados al docente
$sql = "SELECT DISTINCT grado FROM docente_grados WHERE docente_id = ? ORDER BY grado";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $docente_id);
$stmt->execute();
$grados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener unidades temáticas
$sql = "SELECT id, nombre, descripcion FROM unidades_tematicas ORDER BY nombre";
$stmt = $conexion->prepare($sql);
$stmt->execute();
$unidades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Actividad - UDL</title>
    <link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/all.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/Dashboard.css" rel="stylesheet">
    <!-- Scripts de Google API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://apis.google.com/js/api.js" async defer></script>
    <style>
        /* Estilos específicos para esta página pueden ir aquí si es necesario */
        body {
            background-color: #f4f7f6; /* Un fondo más neutro para el área de contenido */
        }
        .main-container {
            padding-top: 2rem;
        }
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            padding: 2rem 0;
        }

        .create-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 0.5rem;
            color: var(--secondary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
        }

        .required {
            color: var(--danger-color);
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .file-upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
        }

        .file-upload-area:hover {
            border-color: var(--secondary-color);
            background: #e3f2fd;
        }

        .file-upload-area.dragover {
            border-color: var(--success-color);
            background: #e8f5e8;
        }

        .upload-icon {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .file-list {
            margin-top: 1rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border: 1px solid #e9ecef;
        }

        .file-info {
            display: flex;
            align-items: center;
        }

        .file-icon {
            margin-right: 0.75rem;
            color: var(--secondary-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .btn-danger {
            background: var(--danger-color);
            border: none;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
        }

        .input-group-text {
            background: var(--light-bg);
            border: 2px solid #e9ecef;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
        }

        .help-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .preview-card {
            background: #e3f2fd;
            border: 1px solid var(--secondary-color);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .activity-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .type-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .type-option:hover {
            border-color: var(--secondary-color);
            background: #e3f2fd;
        }

        .type-option.selected {
            border-color: var(--secondary-color);
            background: #e3f2fd;
        }

        .type-option input[type="radio"] {
            display: none;
        }

        .type-icon {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .form-section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php require_once __DIR__ . '/../../includes/header_docente.php'; ?>
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="create-card">
                    <div class="card-header">
                            <h4><i class="fas fa-plus-circle me-2"></i>Crear Nueva Actividad</h4>
                        </div>
                        <div class="card-body">
                            <form id="createActivityForm" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <!-- Información Básica -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-info-circle"></i>
                                    Información Básica
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="form-label">Título de la Actividad <span class="required">*</span></label>
                                            <input type="text" name="titulo" class="form-control" required
                                                   placeholder="Ej: Ensayo sobre metodologías ágiles">
                                            <div class="help-text">Título descriptivo y claro de la actividad</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Puntos Máximos <span class="required">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-star"></i></span>
                                                <input type="number" name="puntos_maximos" class="form-control" 
                                                       value="100" min="1" max="1000" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Descripción <span class="required">*</span></label>
                                    <textarea name="descripcion" class="form-control" rows="3" required
                                              placeholder="Describe brevemente en qué consiste la actividad..."></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Grado <span class="required">*</span></label>
                                            <select name="grado" class="form-select" required>
                                                <option value="">Seleccionar grado...</option>
                                                <?php foreach ($grados as $grado): ?>
                                                    <option value="<?= $grado['grado'] ?>">
                                                        Grado <?= htmlspecialchars($grado['grado']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Unidad Temática</label>
                                            <select name="unidad_tematica_id" class="form-select">
                                                <option value="">Sin unidad específica</option>
                                                <?php foreach ($unidades as $unidad): ?>
                                                    <option value="<?= $unidad['id'] ?>">
                                                        <?= htmlspecialchars($unidad['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tipo de Actividad -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-tasks"></i>
                                    Tipo de Actividad
                                </h5>
                                
                                <div class="activity-types">
                                    <div class="type-option" onclick="selectActivityType('tarea')">
                                        <input type="radio" name="tipo_actividad" value="tarea" checked>
                                        <div class="type-icon"><i class="fas fa-clipboard-list"></i></div>
                                        <h6>Tarea</h6>
                                        <small>Actividad individual o grupal</small>
                                    </div>
                                    <div class="type-option" onclick="selectActivityType('proyecto')">
                                        <input type="radio" name="tipo_actividad" value="proyecto">
                                        <div class="type-icon"><i class="fas fa-project-diagram"></i></div>
                                        <h6>Proyecto</h6>
                                        <small>Trabajo extenso y complejo</small>
                                    </div>
                                    <div class="type-option" onclick="selectActivityType('ensayo')">
                                        <input type="radio" name="tipo_actividad" value="ensayo">
                                        <div class="type-icon"><i class="fas fa-pen-fancy"></i></div>
                                        <h6>Ensayo</h6>
                                        <small>Texto argumentativo</small>
                                    </div>
                                    <div class="type-option" onclick="selectActivityType('investigacion')">
                                        <input type="radio" name="tipo_actividad" value="investigacion">
                                        <div class="type-icon"><i class="fas fa-search"></i></div>
                                        <h6>Investigación</h6>
                                        <small>Trabajo de investigación</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Fechas y Plazos -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    Fechas y Plazos
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Fecha Límite <span class="required">*</span></label>
                                            <input type="datetime-local" name="fecha_limite" class="form-control" required>
                                            <div class="help-text">Fecha y hora límite para entregar</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Recordatorio</label>
                                            <select name="recordatorio" class="form-select">
                                                <option value="">Sin recordatorio</option>
                                                <option value="1">1 día antes</option>
                                                <option value="2">2 días antes</option>
                                                <option value="7">1 semana antes</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Instrucciones Detalladas -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-list-ol"></i>
                                    Instrucciones y Criterios
                                </h5>
                                
                                <div class="form-group">
                                    <label class="form-label">Instrucciones Detalladas</label>
                                    <textarea name="instrucciones" class="form-control" rows="4"
                                              placeholder="Proporciona instrucciones paso a paso para completar la actividad..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Recursos Necesarios</label>
                                    <textarea name="recursos_necesarios" class="form-control" rows="3"
                                              placeholder="Lista los recursos, materiales o herramientas necesarios..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Criterios de Evaluación</label>
                                    <textarea name="criterios_evaluacion" class="form-control" rows="4"
                                              placeholder="Define cómo se evaluará la actividad (rúbrica, criterios específicos, etc.)..."></textarea>
                                </div>
                            </div>

                            <!-- Archivo de Google Drive -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fab fa-google-drive"></i>
                                    Archivo Adjunto de Google Drive
                                </h5>
                                <div class="form-group">
                                    <label class="form-label">Material de Apoyo</label>
                                    <input type="hidden" name="google_drive_link" id="google_drive_link">
                                    <button type="button" class="btn btn-outline-secondary" id="authorize_button" onclick="handleAuthClick()">
                                        <i class="fab fa-google-drive me-2"></i>Seleccionar de Google Drive
                                    </button>
                                    <div id="file-display" class="mt-2" style="display: none;">
                                        <strong>Archivo seleccionado:</strong>
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-alt file-icon"></i>
                                                <span id="file-name"></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="resetFileSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="help-text mt-2">
                                        <strong>Importante:</strong> Después de seleccionar, asegúrese de que el archivo en Google Drive tenga los permisos de <strong>"Cualquier persona con el enlace puede ver"</strong>.
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary me-md-2" onclick="window.location.href='dashboard.php'">
                                    <i class="fas fa-arrow-left me-1"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-outline-primary me-md-2" onclick="previewActivity()">
                                    <i class="fas fa-eye me-1"></i>Vista Previa
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-save me-1"></i>Crear Actividad
                                </button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Vista Previa -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vista Previa de la Actividad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewContent">
                    <!-- Contenido de vista previa -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="$('#previewModal').modal('hide'); $('#createActivityForm').submit();">
                        Crear Actividad
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= APP_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuración de la API de Google
        const CLIENT_ID = '<?= GOOGLE_CLIENT_ID ?>';
        const API_KEY = '<?= GOOGLE_API_KEY ?>';
        const SCOPES = 'https://www.googleapis.com/auth/drive.readonly';

        let tokenClient;

        // La carga de los scripts es asíncrona, por lo que usamos callbacks
        window.onGoogleLibraryLoad = () => {
            // Inicializar el cliente de tokens para OAuth
            tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: CLIENT_ID,
                scope: SCOPES,
                callback: (tokenResponse) => {
                    if (tokenResponse && tokenResponse.access_token) {
                        gapi.client.setToken(tokenResponse);
                        createPicker();
                    }
                },
            });

            // Inicializar el cliente de GAPI para el Picker y la API de Drive
            gapi.load('client:picker', () => {
                gapi.client.init({
                    apiKey: API_KEY,
                    discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/drive/v3/rest'],
                });
            });
        };

        // Asignamos la función de callback al script de GSI
        const gsiScript = document.querySelector('script[src="https://accounts.google.com/gsi/client"]');
        gsiScript.onload = () => onGoogleLibraryLoad();

        function handleAuthClick() {
            if (gapi.client.getToken() === null) {
                // Si no hay token, solicitar uno. El callback se encargará de abrir el picker.
                tokenClient.requestAccessToken({ prompt: 'consent' });
            } else {
                // Si ya hay un token, abrir el picker directamente.
                createPicker();
            }
        }

        function createPicker() {
            const view = new google.picker.View(google.picker.ViewId.DOCS);
            const picker = new google.picker.PickerBuilder()
                .setAppId('<?= substr(GOOGLE_CLIENT_ID, 0, strpos(GOOGLE_CLIENT_ID, "-")) ?>')
                .setOAuthToken(gapi.client.getToken().access_token)
                .addView(view)
                .setDeveloperKey(API_KEY)
                .setCallback(pickerCallback)
                .build();
            picker.setVisible(true);
        }

        function pickerCallback(data) {
            if (data.action === google.picker.Action.PICKED) {
                const file = data.docs[0];
                const fileId = file.id;
                const fileName = file.name;
                
                // Usamos la API de Drive para obtener el enlace web
                gapi.client.drive.files.get({
                    fileId: fileId,
                    fields: 'webViewLink'
                }).then((response) => {
                    const fileLink = response.result.webViewLink;
                    document.getElementById('google_drive_link').value = fileLink;
                    document.getElementById('file-name').textContent = fileName;
                    document.getElementById('file-display').style.display = 'block';
                    document.getElementById('authorize_button').style.display = 'none';
                });
            }
        }

        function resetFileSelection() {
            document.getElementById('google_drive_link').value = '';
            document.getElementById('file-display').style.display = 'none';
            document.getElementById('authorize_button').style.display = 'block';
        }


        // Elementos del DOM
        const createActivityForm = document.getElementById('createActivityForm');
        const submitBtn = document.getElementById('submitBtn');

        // Event listeners
        createActivityForm.addEventListener('submit', handleSubmit);

        // Establecer fecha mínima (hoy)
        const fechaLimiteInput = document.querySelector('input[name="fecha_limite"]');
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        fechaLimiteInput.min = now.toISOString().slice(0, 16);

        function selectActivityType(type) {
            // Remover selección anterior
            document.querySelectorAll('.type-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Seleccionar nuevo tipo
            event.currentTarget.classList.add('selected');
            document.querySelector(`input[value="${type}"]`).checked = true;
        }

        // Seleccionar tipo por defecto
        document.querySelector('.type-option').classList.add('selected');


        function previewActivity() {
            const formData = new FormData(createActivityForm);
            const titulo = formData.get('titulo') || 'Sin título';
            const descripcion = formData.get('descripcion') || 'Sin descripción';
            const puntos = formData.get('puntos_maximos') || '0';
            const fechaLimite = formData.get('fecha_limite');
            const tipo = formData.get('tipo_actividad');
            const instrucciones = formData.get('instrucciones') || 'Sin instrucciones específicas';
            
            const fechaFormateada = fechaLimite ? 
                new Date(fechaLimite).toLocaleString('es-ES') : 'No especificada';
            
            const tipoTexto = {
                'tarea': 'Tarea',
                'proyecto': 'Proyecto',
                'ensayo': 'Ensayo',
                'investigacion': 'Investigación'
            }[tipo] || tipo;

            const previewContent = `
                <div class="preview-card">
                    <h4>${titulo}</h4>
                    <p><strong>Tipo:</strong> ${tipoTexto}</p>
                    <p><strong>Descripción:</strong> ${descripcion}</p>
                    <p><strong>Puntos máximos:</strong> ${puntos}</p>
                    <p><strong>Fecha límite:</strong> ${fechaFormateada}</p>
                    <p><strong>Instrucciones:</strong></p>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                        ${instrucciones.replace(/\n/g, '<br>')}
                    </div>
                    ${formData.get('google_drive_link') ? `
                        <p><strong>Enlace de Google Drive:</strong></p>
                        <a href="${formData.get('google_drive_link')}" target="_blank">${formData.get('google_drive_link')}</a>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('previewContent').innerHTML = previewContent;
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        async function handleSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(createActivityForm);

            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creando...';
                
                const response = await fetch('<?= APP_URL ?>/controllers/crear_actividad.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Actividad creada exitosamente', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    throw new Error(result.message || 'Error al crear la actividad');
                }

            } catch (error) {
                console.error('Error:', error);
                showAlert('Error al crear la actividad: ' + error.message, 'danger');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Crear Actividad';
            }
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.card-body');
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>
