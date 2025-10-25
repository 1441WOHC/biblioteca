<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Genera un token CSRF y lo almacena en la sesión.
 * Solo se genera si no existe uno.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Almacena un mensaje flash en la sesión.
 * @param string $message El mensaje a mostrar.
 * @param string $type 'success', 'danger', 'warning' (clases de Bootstrap/CSS)
 */
function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Muestra y luego elimina el mensaje flash de la sesión.
 * Esta función debe ser llamada en el HTML (ej. en header.php).
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        $type = $flash['type']; // success, danger, warning
        $message = htmlspecialchars($flash['message']);
        
        // Determina el ícono basado en el tipo
        $icon = 'fa-check-circle'; // success
        if ($type === 'danger') {
            $icon = 'fa-exclamation-triangle';
        } elseif ($type === 'warning') {
            $icon = 'fa-exclamation-circle';
        }

        echo "<div class='alert alert-{$type}' role='alert'>";
        echo "<i class='fas {$icon}'></i> " . $message;
        echo "</div>";
        
        // Limpiar el mensaje de la sesión
        unset($_SESSION['flash_message']);
    }
}
?>