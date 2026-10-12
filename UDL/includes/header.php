<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>UDL - Unidad Didáctica de Lenguaje</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo getBaseUrl(); ?>/assets/images/udl.png">
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/main.css">
    
    <!-- CSS Específico de página -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Sistema de gestión educativa para la Unidad Didáctica de Lenguaje">
    <meta name="keywords" content="educación, lenguaje, literatura, estudiantes, docentes">
    <meta name="author" content="UDL Sistema">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Estilos globales unificados -->
    <style>
        :root {
            /* Paleta de colores principal */
            --primary-color: #C97E28;
            --primary-dark: #996515;
            --primary-light: #E6A855;
            --secondary-color: #2C3E50;
            --secondary-light: #34495E;
            
            /* Colores de estado */
            --success-color: #27AE60;
            --warning-color: #F39C12;
            --error-color: #E74C3C;
            --info-color: #3498DB;
            
            /* Colores neutros */
            --white: #FFFFFF;
            --light-gray: #F8F9FA;
            --gray: #6C757D;
            --dark-gray: #495057;
            --black: #212529;
            
            /* Colores de fondo */
            --bg-primary: linear-gradient(135deg, #C97E28 0%, #996515 100%);
            --bg-secondary: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            --bg-light: #F8F9FA;
            --bg-white: #FFFFFF;
            
            /* Sombras */
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 12px 24px rgba(0, 0, 0, 0.18);
            
            /* Bordes */
            --border-radius-sm: 4px;
            --border-radius-md: 8px;
            --border-radius-lg: 12px;
            --border-radius-xl: 16px;
            
            /* Espaciado */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            
            /* Tipografía */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;
            --font-size-4xl: 2.25rem;
            
            /* Transiciones */
            --transition-fast: 0.15s ease-in-out;
            --transition-normal: 0.3s ease-in-out;
            --transition-slow: 0.5s ease-in-out;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            font-size: var(--font-size-base);
            line-height: 1.6;
            color: var(--dark-gray);
            background: var(--bg-light);
            overflow-x: hidden;
        }
        
        /* Utilidades globales */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-md);
        }
        
        .container-fluid {
            width: 100%;
            padding: 0 var(--spacing-md);
        }
        
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        .text-primary { color: var(--primary-color); }
        .text-secondary { color: var(--secondary-color); }
        .text-success { color: var(--success-color); }
        .text-warning { color: var(--warning-color); }
        .text-error { color: var(--error-color); }
        .text-info { color: var(--info-color); }
        .text-muted { color: var(--gray); }
        
        .bg-primary { background: var(--bg-primary); }
        .bg-secondary { background: var(--bg-secondary); }
        .bg-light { background: var(--bg-light); }
        .bg-white { background: var(--bg-white); }
        
        .shadow-sm { box-shadow: var(--shadow-sm); }
        .shadow-md { box-shadow: var(--shadow-md); }
        .shadow-lg { box-shadow: var(--shadow-lg); }
        .shadow-xl { box-shadow: var(--shadow-xl); }
        
        .rounded-sm { border-radius: var(--border-radius-sm); }
        .rounded-md { border-radius: var(--border-radius-md); }
        .rounded-lg { border-radius: var(--border-radius-lg); }
        .rounded-xl { border-radius: var(--border-radius-xl); }
        .rounded-full { border-radius: 50%; }
        
        .d-none { display: none; }
        .d-block { display: block; }
        .d-inline { display: inline; }
        .d-inline-block { display: inline-block; }
        .d-flex { display: flex; }
        .d-grid { display: grid; }
        
        .flex-column { flex-direction: column; }
        .flex-row { flex-direction: row; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .justify-around { justify-content: space-around; }
        .align-center { align-items: center; }
        .align-start { align-items: flex-start; }
        .align-end { align-items: flex-end; }
        
        .gap-xs { gap: var(--spacing-xs); }
        .gap-sm { gap: var(--spacing-sm); }
        .gap-md { gap: var(--spacing-md); }
        .gap-lg { gap: var(--spacing-lg); }
        .gap-xl { gap: var(--spacing-xl); }
        
        .p-xs { padding: var(--spacing-xs); }
        .p-sm { padding: var(--spacing-sm); }
        .p-md { padding: var(--spacing-md); }
        .p-lg { padding: var(--spacing-lg); }
        .p-xl { padding: var(--spacing-xl); }
        
        .m-xs { margin: var(--spacing-xs); }
        .m-sm { margin: var(--spacing-sm); }
        .m-md { margin: var(--spacing-md); }
        .m-lg { margin: var(--spacing-lg); }
        .m-xl { margin: var(--spacing-xl); }
        
        .mb-xs { margin-bottom: var(--spacing-xs); }
        .mb-sm { margin-bottom: var(--spacing-sm); }
        .mb-md { margin-bottom: var(--spacing-md); }
        .mb-lg { margin-bottom: var(--spacing-lg); }
        .mb-xl { margin-bottom: var(--spacing-xl); }
        
        .mt-xs { margin-top: var(--spacing-xs); }
        .mt-sm { margin-top: var(--spacing-sm); }
        .mt-md { margin-top: var(--spacing-md); }
        .mt-lg { margin-top: var(--spacing-lg); }
        .mt-xl { margin-top: var(--spacing-xl); }
        
        /* Botones unificados */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-decoration: none;
            border: none;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            transition: all var(--transition-normal);
            white-space: nowrap;
            user-select: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-primary {
            background: var(--bg-primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #E6A855 0%, #C97E28 100%);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--white);
        }
        
        .btn-success {
            background: var(--success-color);
            color: var(--white);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: var(--white);
        }
        
        .btn-error {
            background: var(--error-color);
            color: var(--white);
        }
        
        .btn-info {
            background: var(--info-color);
            color: var(--white);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .btn-sm {
            padding: var(--spacing-xs) var(--spacing-sm);
            font-size: var(--font-size-xs);
        }
        
        .btn-lg {
            padding: var(--spacing-md) var(--spacing-xl);
            font-size: var(--font-size-lg);
        }
        
        /* Cards unificadas */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all var(--transition-normal);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .card-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid #E9ECEF;
            background: var(--light-gray);
        }
        
        .card-body {
            padding: var(--spacing-lg);
        }
        
        .card-footer {
            padding: var(--spacing-lg);
            border-top: 1px solid #E9ECEF;
            background: var(--light-gray);
        }
        
        /* Formularios unificados */
        .form-group {
            margin-bottom: var(--spacing-md);
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--spacing-xs);
            font-weight: 500;
            color: var(--dark-gray);
        }
        
        .form-control {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            font-size: var(--font-size-base);
            border: 2px solid #E9ECEF;
            border-radius: var(--border-radius-md);
            transition: all var(--transition-normal);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(201, 126, 40, 0.1);
        }
        
        .form-control:invalid {
            border-color: var(--error-color);
        }
        
        /* Alertas unificadas */
        .alert {
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-md);
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #D4F6D4;
            border-color: var(--success-color);
            color: #155724;
        }
        
        .alert-warning {
            background: #FFF3CD;
            border-color: var(--warning-color);
            color: #856404;
        }
        
        .alert-error {
            background: #F8D7DA;
            border-color: var(--error-color);
            color: #721C24;
        }
        
        .alert-info {
            background: #CCE7FF;
            border-color: var(--info-color);
            color: #0C5460;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 var(--spacing-sm);
            }
            
            .btn {
                padding: var(--spacing-sm);
                font-size: var(--font-size-sm);
            }
            
            .card-header,
            .card-body,
            .card-footer {
                padding: var(--spacing-md);
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        .animate-pulse {
            animation: pulse 2s infinite;
        }
        
        /* Loading spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Scrollbar personalizada */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
    
    <!-- JavaScript global -->
    <script>
        // Configuración global
        window.UDL = {
            baseUrl: '<?php echo getBaseUrl(); ?>',
            csrfToken: '<?php echo generateCSRFToken(); ?>',
            currentUser: <?php echo isset($_SESSION['id_usuario']) ? json_encode([
                'id' => $_SESSION['id_usuario'],
                'nombre' => $_SESSION['nombre'] ?? '',
                'rol' => $_SESSION['rol'] ?? ''
            ]) : 'null'; ?>
        };
        
        // Función para mostrar notificaciones
        function showNotification(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} animate-fade-in`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
                box-shadow: var(--shadow-lg);
            `;
            notification.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            if (duration > 0) {
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, duration);
            }
        }
        
        // Función para confirmar acciones
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Función para formatear fechas
        function formatDate(dateString, format = 'dd/mm/yyyy') {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            
            switch (format) {
                case 'dd/mm/yyyy':
                    return `${day}/${month}/${year}`;
                case 'yyyy-mm-dd':
                    return `${year}-${month}-${day}`;
                default:
                    return date.toLocaleDateString('es-CO');
            }
        }
        
        // Función para validar formularios
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;
            
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Event listeners globales
        document.addEventListener('DOMContentLoaded', function() {
            // Ocultar el loader global
            const loader = document.getElementById('global-loader');
            if (loader) {
                loader.style.display = 'none';
            }

            // Auto-hide alerts
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    if (!alert.classList.contains('alert-error')) {
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    }
                });
            }, 5000);
            
            // Smooth scroll para enlaces internos
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">
    <?php 
    // Incluir sistema de navegación si está disponible
    if (function_exists('udl_include_navigation_system') && function_exists('udl_check_navigation_permissions') && udl_check_navigation_permissions()) {
        udl_include_navigation_system(udl_get_user_navigation_config());
    }
    ?>
    
    <!-- CSRF Token para formularios -->
    <input type="hidden" id="csrf-token" value="<?php echo generateCSRFToken(); ?>">
    
    <!-- Loader global -->
    <div id="global-loader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center;">
        <div class="spinner"></div>
    </div>
