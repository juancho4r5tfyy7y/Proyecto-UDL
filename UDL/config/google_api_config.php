<?php
// config/google_api_config.php

/*
 * INSTRUCCIONES PARA OBTENER LAS CREDENCIALES DE GOOGLE API:
 *
 * 1. Ve a la Google Cloud Console: https://console.cloud.google.com/
 * 2. Crea un nuevo proyecto o selecciona uno que ya tengas.
 * 3. En el menú de navegación, ve a "APIs y servicios" > "Biblioteca".
 * 4. Busca y HABILITA las siguientes dos APIs:
 *    - Google Drive API
 *    - Google Picker API
 * 5. Una vez habilitadas, ve a "APIs y servicios" > "Pantalla de consentimiento de OAuth".
 *    - Tipo de usuario: selecciona "Externo" y haz clic en "Crear".
 *    - Rellena los detalles de la aplicación (nombre de la app, tu correo, etc.).
 *    - En la sección de "Permisos", no es necesario añadir ninguno por ahora. Guarda y continúa.
 *    - En "Usuarios de prueba", añade las direcciones de correo de Google de los profesores que usarán esta función.
 * 6. Finalmente, ve a "APIs y servicios" > "Credenciales".
 *    - Haz clic en "+ CREAR CREDENCIALES" y selecciona "ID de cliente de OAuth".
 *    - Tipo de aplicación: selecciona "Aplicación web".
 *    - En "URI de redireccionamiento autorizados", haz clic en "+ AÑADIR URI" y pega la siguiente URL:
 *      http://localhost/UDL/views/docente/material.php
 *    - Haz clic en "Crear".
 * 7. Se mostrará tu "ID de cliente" y tu "Secreto de cliente". Cópialos y pégalos abajo.
 * 8. Vuelve a "Credenciales", haz clic en "+ CREAR CREDENCIALES" y selecciona "Clave de API".
 * 9. Copia la clave de API que se muestra y pégala en la constante GOOGLE_API_KEY.
 */

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_API_KEY', 'AIzaSyB4OhyzSQmgymJJp6UvuWd7_nZhpVIB9nk');
define('GOOGLE_REDIRECT_URI', 'http://localhost/UDL/views/docente/material.php'); // No cambies esta línea
?>
