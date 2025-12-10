<?php
session_start();
require_once '../config/database.php';

// Si el bibliotecario ya tiene una sesión activa, lo redirigimos al dashboard.
if (isset($_SESSION['bibliotecario'])) {
    header('Location: nuevareserva.php');
    exit;
}

$error = '';
const MAX_ATTEMPTS = 3;
const LOCKOUT_TIME = 1800; // 30 minutos en segundos

$database = new Database();
$conn = $database->getConnection();

$is_locked = false;
$cedula = $_POST['cedula'] ?? '';

// --- NUEVA LÓGICA DE BLOQUEO BASADA EN LA BASE DE DATOS ---

// Solo procedemos si se envió una cédula.
if (!empty($cedula)) {
    // 1. Buscamos al usuario en la base de datos PRIMERO.
    $query = "SELECT * FROM bibliotecario WHERE cedula = :cedula";
    $stmt = $conn->prepare($query);
    $stmt->execute([':cedula' => $cedula]);
    $bibliotecario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bibliotecario) {
        // 2. Verificamos si la cuenta está bloqueada según los datos de la BD.
        if ($bibliotecario['intentos_fallidos'] >= MAX_ATTEMPTS) {
            $last_attempt_time = strtotime($bibliotecario['ultimo_intento']);
            $time_since_last_attempt = time() - $last_attempt_time;

            if ($time_since_last_attempt < LOCKOUT_TIME) {
                $remaining_time = LOCKOUT_TIME - $time_since_last_attempt;
                $minutes = ceil($remaining_time / 60);
                $error = "Demasiados intentos fallidos. Esta cuenta está bloqueada. Por favor, intente de nuevo en $minutes minutos.";
                $is_locked = true;
            } else {
                // El tiempo de bloqueo ha pasado. Reseteamos los intentos en la BD para permitir un nuevo intento.
                $resetQuery = "UPDATE bibliotecario SET intentos_fallidos = 0, ultimo_intento = NULL WHERE cedula = :cedula";
                $resetStmt = $conn->prepare($resetQuery);
                $resetStmt->execute([':cedula' => $cedula]);
                $bibliotecario['intentos_fallidos'] = 0; // Actualizamos el array local también
            }
        }

        // 3. Si la cuenta NO está bloqueada y se envió el formulario, intentamos el login.
        if ($_POST && !$is_locked) {
            if (password_verify($_POST['contrasena'], $bibliotecario['contrasena'])) {
                // LOGIN EXITOSO
                // Reseteamos el contador de intentos en la base de datos.
                $updateQuery = "UPDATE bibliotecario SET intentos_fallidos = 0, ultimo_intento = NULL WHERE cedula = :cedula";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->execute([':cedula' => $cedula]);

                // Limpiamos cualquier variable de sesión de intentos anteriores (buena práctica)
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt_time']);

                // Creamos la sesión del bibliotecario y redirigimos.
                $_SESSION['bibliotecario'] = $bibliotecario;
                header('Location: nuevareserva.php');
                exit;
            } else {
                // LOGIN FALLIDO
                // Incrementamos el contador de intentos en la base de datos.
                $new_attempts = $bibliotecario['intentos_fallidos'] + 1;
                $updateQuery = "UPDATE bibliotecario SET intentos_fallidos = :intentos, ultimo_intento = NOW() WHERE cedula = :cedula";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->execute([':intentos' => $new_attempts, ':cedula' => $cedula]);

                $remaining_attempts = MAX_ATTEMPTS - $new_attempts;

                if ($remaining_attempts > 0) {
                    $error = "Credenciales incorrectas. Le quedan $remaining_attempts intento(s).";
                } else {
                    $error = "Demasiados intentos fallidos. Su cuenta ha sido bloqueada por 30 minutos.";
                    $is_locked = true;
                }
            }
        }
    } elseif ($_POST) {
        // El usuario no existe, pero damos un mensaje genérico para no revelar información.
        $error = "Credenciales incorrectas.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="card">
            <h2>Acceso Administrativo</h2>
            <p>Ingrese sus credenciales para acceder al panel de administración</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="cedula">Cédula:</label>
                    <input type="text" id="cedula" name="cedula" class="form-control" autocomplete="off" required value="<?php echo htmlspecialchars($cedula); ?>">
                </div>
                
                <div class="form-group">
                    <label for="contrasena">Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" class="form-control" required>
                </div>
                
                <?php
                // La variable $is_locked ya se calculó en la lógica PHP de arriba.
                // Si el usuario no existe, el botón no se deshabilita, lo cual es correcto
                // para no dar pistas a un atacante. El bloqueo solo se activa visualmente
                // si la cédula es correcta y la cuenta está bloqueada.
                ?>
                <button type="submit" class="btn btn-primary" style="width: 100%;" <?php if ($is_locked) echo 'disabled'; ?>>
                    <?php echo $is_locked ? 'Cuenta Bloqueada' : 'Iniciar Sesión'; ?>
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="../cliente/index.php" class="btn btn-secondary">Portal de Reserva</a>
            </div>
        </div>
    </div>
</body>
</html>