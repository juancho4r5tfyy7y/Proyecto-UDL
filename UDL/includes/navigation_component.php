<?php
/**
 * Componente de Navegación UDL
 * Genera los botones de navegación Atrás y Adelante
 */

class UDLNavigationComponent {
    
    private $config;
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'show_back' => true,
            'show_forward' => true,
            'position' => 'fixed', // fixed, relative, absolute
            'theme' => 'default', // default, minimal, compact
            'show_tooltips' => true,
            'custom_class' => '',
            'back_text' => 'Atrás',
            'forward_text' => 'Adelante'
        ], $config);
    }
    
    /**
     * Renderiza el componente de navegación completo
     */
    public function render() {
        $this->includeAssets();
        echo $this->generateHTML();
    }
    
    /**
     * Incluye los archivos CSS y JS necesarios
     */
    private function includeAssets() {
        // Solo incluir una vez
        static $assets_included = false;
        if ($assets_included) return;
        
        echo $this->getCSSLink();
        echo $this->getJSLink();
        
        $assets_included = true;
    }
    
    /**
     * Genera el enlace al archivo CSS
     */
    private function getCSSLink() {
        $css_path = $this->getAssetPath('css/navigation.css');
        return "<link rel=\"stylesheet\" href=\"{$css_path}\">\n";
    }
    
    /**
     * Genera el enlace al archivo JS
     */
    private function getJSLink() {
        $js_path = $this->getAssetPath('js/navigation.js');
        return "<script src=\"{$js_path}\" defer></script>\n";
    }
    
    /**
     * Obtiene la ruta correcta de los assets
     */
    private function getAssetPath($asset) {
        // Detectar la profundidad del directorio actual
        $current_path = $_SERVER['REQUEST_URI'];
        $depth = substr_count(parse_url($current_path, PHP_URL_PATH), '/') - 2; // -2 para /UDL/
        
        $base_path = str_repeat('../', max(0, $depth)) . 'assets/';
        return $base_path . $asset;
    }
    
    /**
     * Genera el HTML del componente
     */
    private function generateHTML() {
        $container_class = $this->getContainerClass();
        $html = "<div class=\"{$container_class}\">\n";
        
        if ($this->config['show_back']) {
            $html .= $this->generateBackButton();
        }
        
        if ($this->config['show_forward']) {
            $html .= $this->generateForwardButton();
        }
        
        $html .= "</div>\n";
        
        return $html;
    }
    
    /**
     * Obtiene las clases CSS del contenedor
     */
    private function getContainerClass() {
        $classes = ['udl-navigation-container'];
        
        if ($this->config['position'] !== 'fixed') {
            $classes[] = 'udl-nav-' . $this->config['position'];
        }
        
        if ($this->config['theme'] !== 'default') {
            $classes[] = 'udl-nav-theme-' . $this->config['theme'];
        }
        
        if (!empty($this->config['custom_class'])) {
            $classes[] = $this->config['custom_class'];
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Genera el botón de atrás
     */
    private function generateBackButton() {
        $tooltip = $this->config['show_tooltips'] ? 'data-tooltip="' . htmlspecialchars($this->config['back_text']) . '"' : '';
        
        return "    <button id=\"udl-nav-back\" class=\"udl-nav-button udl-nav-back\" {$tooltip} type=\"button\">\n" .
               "        <span class=\"nav-icon\">←</span>\n" .
               "    </button>\n";
    }
    
    /**
     * Genera el botón de adelante
     */
    private function generateForwardButton() {
        $tooltip = $this->config['show_tooltips'] ? 'data-tooltip="' . htmlspecialchars($this->config['forward_text']) . '"' : '';
        
        return "    <button id=\"udl-nav-forward\" class=\"udl-nav-button udl-nav-forward\" {$tooltip} type=\"button\">\n" .
               "        <span class=\"nav-icon\">→</span>\n" .
               "    </button>\n";
    }
    
    /**
     * Renderiza solo el botón de atrás
     */
    public function renderBackButton() {
        $this->includeAssets();
        $config = $this->config;
        $config['show_forward'] = false;
        $temp_component = new self($config);
        echo $temp_component->generateHTML();
    }
    
    /**
     * Renderiza solo el botón de adelante
     */
    public function renderForwardButton() {
        $this->includeAssets();
        $config = $this->config;
        $config['show_back'] = false;
        $temp_component = new self($config);
        echo $temp_component->generateHTML();
    }
    
    /**
     * Renderiza una versión compacta (solo iconos)
     */
    public function renderCompact() {
        $this->includeAssets();
        $config = $this->config;
        $config['theme'] = 'compact';
        $config['show_tooltips'] = true;
        $temp_component = new self($config);
        echo $temp_component->generateHTML();
    }
    
    /**
     * Renderiza una versión minimal
     */
    public function renderMinimal() {
        $this->includeAssets();
        $config = $this->config;
        $config['theme'] = 'minimal';
        $temp_component = new self($config);
        echo $temp_component->generateHTML();
    }
}

/**
 * Funciones de conveniencia para uso rápido
 */

/**
 * Renderiza el componente de navegación completo
 */
function udl_render_navigation($config = []) {
    $navigation = new UDLNavigationComponent($config);
    $navigation->render();
}

/**
 * Renderiza solo el botón de atrás
 */
function udl_render_back_button($config = []) {
    $navigation = new UDLNavigationComponent($config);
    $navigation->renderBackButton();
}

/**
 * Renderiza solo el botón de adelante
 */
function udl_render_forward_button($config = []) {
    $navigation = new UDLNavigationComponent($config);
    $navigation->renderForwardButton();
}

/**
 * Renderiza versión compacta
 */
function udl_render_compact_navigation($config = []) {
    $navigation = new UDLNavigationComponent($config);
    $navigation->renderCompact();
}

/**
 * Renderiza versión minimal
 */
function udl_render_minimal_navigation($config = []) {
    $navigation = new UDLNavigationComponent($config);
    $navigation->renderMinimal();
}

/**
 * Función para incluir el sistema de navegación en cualquier página
 */
function udl_include_navigation_system($config = []) {
    // Configuración por defecto
    $default_config = [
        'auto_render' => true,
        'position' => 'fixed',
        'theme' => 'default'
    ];
    
    $config = array_merge($default_config, $config);
    
    if ($config['auto_render']) {
        udl_render_navigation($config);
    }
    
    // Agregar script de inicialización adicional si es necesario
    echo "<script>\n";
    echo "// Configuración adicional del sistema de navegación\n";
    echo "document.addEventListener('DOMContentLoaded', function() {\n";
    echo "    console.log('UDL Navigation System loaded');\n";
    
    // Configurar eventos personalizados si se especifican
    if (isset($config['custom_events']) && is_array($config['custom_events'])) {
        foreach ($config['custom_events'] as $event => $handler) {
            echo "    document.addEventListener('{$event}', {$handler});\n";
        }
    }
    
    echo "});\n";
    echo "</script>\n";
}

/**
 * Función para verificar si el usuario tiene permisos de navegación
 */
function udl_check_navigation_permissions() {
    // Verificar si hay una sesión activa
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si el usuario está logueado
    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario'])) {
        return false;
    }
    
    // Verificar tipos de usuario válidos
    $valid_user_types = ['estudiante', 'docente', 'administrador'];
    if (!in_array($_SESSION['tipo_usuario'], $valid_user_types)) {
        return false;
    }
    
    return true;
}

/**
 * Función para obtener la configuración de navegación según el tipo de usuario
 */
function udl_get_user_navigation_config() {
    if (!udl_check_navigation_permissions()) {
        return ['show_back' => false, 'show_forward' => false];
    }
    
    $user_type = $_SESSION['tipo_usuario'];
    
    switch ($user_type) {
        case 'estudiante':
            return [
                'theme' => 'default',
                'back_text' => 'Página Anterior',
                'forward_text' => 'Página Siguiente',
                'custom_class' => 'udl-nav-student'
            ];
            
        case 'docente':
            return [
                'theme' => 'default',
                'back_text' => 'Atrás',
                'forward_text' => 'Adelante',
                'custom_class' => 'udl-nav-teacher'
            ];
            
        case 'administrador':
            return [
                'theme' => 'minimal',
                'back_text' => 'Volver',
                'forward_text' => 'Continuar',
                'custom_class' => 'udl-nav-admin'
            ];
            
        default:
            return [];
    }
}
?>