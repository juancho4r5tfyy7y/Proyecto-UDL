<?php
/**
 * Configuraciรณn de Base de Datos - UDL
 * Archivo centralizado para la conexión a la base de datos
 */

// Configuraciรณn de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'unidad_didactica');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuraciรณn de la aplicaciรณn
define('APP_NAME', 'UDL - Unidad Didรกctica de Lenguaje');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/UDL');

// Configuraciรณn de Google Drive - REMOVIDO

// Configuraciรณn de archivos
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt']);
define('UPLOAD_PATH', __DIR__ . '/../storage/uploads/');

// Configuraciรณn de sesiรณn
define('SESSION_TIMEOUT', 3600); // 1 hora
define('SESSION_NAME', 'UDL_SESSION');

// Configuraciรณn de seguridad
define('BCRYPT_COST', 12);
define('CSRF_TOKEN_LENGTH', 32);

// Configuraciรณn de logs
define('LOG_PATH', __DIR__ . '/../storage/logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Configuraciรณn de email (para futuras implementaciones)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@udl.edu.co');
define('SMTP_FROM_NAME', 'UDL Sistema');

// Configuraciรณn acadรฉmica
define('CURRENT_ACADEMIC_YEAR', date('Y'));
define('ACADEMIC_PERIODS', 4);
define('MAX_GRADE', 5.0);
define('MIN_PASSING_GRADE', 3.0);

// Configuraciรณn de paginaciรณn
define('RECORDS_PER_PAGE', 20);
define('MAX_RECORDS_PER_PAGE', 100);

// Configuraciรณn de cache
define('CACHE_ENABLED', false);
define('CACHE_DURATION', 3600); // 1 hora

// Configuraciรณn de timezone
date_default_timezone_set('America/Bogota');

/**
 * Funciรณn para obtener conexiรณn a la base de datos
 * @return mysqli
 */
function getDatabaseConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($connection->connect_error) {
                throw new Exception("Error de conexiรณn: " . $connection->connect_error);
            }
            
            // Configurar charset
            $connection->set_charset(DB_CHARSET);
            
            // Configurar timezone
            $connection->query("SET time_zone = '-05:00'");
            
        } catch (Throwable $e) { // Catch Throwable to handle Errors as well as Exceptions
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            // Provide a more informative error message for common issues
            if (strpos($e->getMessage(), 'mysqli') !== false) {
                die("Error de configuración del servidor: La extensión 'mysqli' de PHP no está habilitada. Por favor, active la extensión en su archivo php.ini y reinicie el servidor web.");
            }
            die("Error de conexión a la base de datos. Por favor, contacte al administrador. Detalles: " . $e->getMessage());
        }
    }
    
    return $connection;
}

/**
 * Alias para compatibilidad con cรณdigo antiguo.
 * @return mysqli
 */
function conexion() {
    return getDatabaseConnection();
}

/**
 * Funciรณn para cerrar conexiรณn a la base de datos
 */
function closeDatabaseConnection() {
    global $connection;
    if ($connection && !$connection->connect_error) {
        $connection->close();
    }
}

/**
 * Funciรณn para obtener configuraciรณn del sistema
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getSystemConfig($key, $default = null) {
    static $config = null;
    
    if ($config === null) {
        $connection = getDatabaseConnection();
        $result = $connection->query("SELECT clave, valor FROM configuracion_sistema WHERE categoria = 'general'");
        
        $config = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $config[$row['clave']] = $row['valor'];
            }
        }
    }
    
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Funciรณn para logging
 * @param string $level
 * @param string $message
 * @param array $context
 */
function writeLog($level, $message, $context = []) {
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    $logFile = LOG_PATH . 'udl_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    
    $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Funciรณn para generar token CSRF
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Funciรณn para validar token CSRF
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Funciรณn para sanitizar entrada
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Funciรณn para validar email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Funciรณn para generar hash de contraseรฑa
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Funciรณn para verificar contraseรฑa
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Funciรณn para redireccionar
 * @param string $url
 * @param int $statusCode
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit();
}

/**
 * Funciรณn para obtener URL base
 * @return string
 */
function getBaseUrl() {
    return APP_URL;
}

/**
 * Funciรณn para formatear fecha
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Funciรณn para calcular edad
 * @param string $birthDate
 * @return int
 */
function calculateAge($birthDate) {
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

/**
 * Funciรณn para generar cรณdigo รบnico
 * @param string $prefix
 * @param int $length
 * @return string
 */
function generateUniqueCode($prefix = '', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = $prefix;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

// Inicializar sesiรณn si no estรก iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
    
    // Configurar timeout de sesiรณn
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// Registrar funciรณn de cierre de conexiรณn al finalizar el script
register_shutdown_function('closeDatabaseConnection');
?>
