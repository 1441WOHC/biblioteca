<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$mensaje = '';

$database = new Database();
$conn = $database->getConnection();

function validarCedulaPanama($cedula) {
    $cedula = trim($cedula);
    $patron = '/^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/';
    return preg_match($patron, $cedula);
}

// ===== INICIO DE CÓDIGO A AGREGAR =====
function normalizarNombre($texto) {
    // Convierte todo a minúsculas primero
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    
    // Divide el texto en palabras
    $palabras = explode(' ', $texto);
    
    // Capitaliza la primera letra de cada palabra
    $palabras = array_map(function($palabra) {
        return mb_convert_case($palabra, MB_CASE_TITLE, 'UTF-8');
    }, $palabras);
    
    // Une las palabras nuevamente
    return implode(' ', $palabras);
}
// ===== FIN DE CÓDIGO A AGREGAR =====

// CSRF token simple
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    header('Content-Type: application/json');

    // ===== INICIO DE CÓDIGO A AGREGAR =====
    // Validar Token CSRF para todas las acciones POST
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Si la acción es AJAX (como verificar_usuario), responde con JSON
        if (isset($_POST['accion'])) {
            $response['message'] = 'Error de validación de seguridad (CSRF). Recargue la página.';
            echo json_encode($response);
            exit;
        }
        // Si es el envío del formulario principal
        $response['message'] = 'Error de validación de seguridad. Por favor, recargue la página e intente de nuevo.';
        echo json_encode($response);
        exit;
    }
    // ===== FIN DE CÓDIGO A AGREGAR =====

    // Verificar usuario por cédula vía AJAX
    if (isset($_POST['accion']) && $_POST['accion'] === 'verificar_usuario') {
        $cedula = trim($_POST['cedula']);
        if (!validarCedulaPanama($cedula)) {
            echo json_encode(['encontrado' => false, 'message' => 'Formato de cédula inválido.']);
            exit;
        }
        $usuario = verificarUsuario($conn, $cedula);
        if ($usuario) {
            echo json_encode(['encontrado' => true, 'usuario' => $usuario]);
        } else {
            echo json_encode(['encontrado' => false, 'message' => 'Usuario no encontrado. Por favor, complete el registro.']);
        }
        exit;
    }
    
    // Lógica de reserva
    if (isset($_POST['tipo_reserva'])) {
        $cedula = trim($_POST['cedula']);
        
        if (!validarCedulaPanama($cedula)) {
            $response['message'] = "Formato de cédula inválido. Formatos válidos: 1-1234-12345, PE-1234-12345, E-1234-123456, N-1234-1234, 1AV-1234-12345, 1PI-1234-12345";
        } else {
            $usuario = verificarUsuario($conn, $cedula);
            
            if (!$usuario) {
                $idTipoUsuario = null;
                if (!empty($_POST['tipo_usuario'])) {
                    $idTipoUsuario = $_POST['tipo_usuario'];
                } elseif ($_POST['afiliacion'] == 3) {
                    $idTipoUsuario = 4;
                }
                
                $userData = [
                    'nombre'        => normalizarNombre($_POST['nombre']),
                    'apellido'      => normalizarNombre($_POST['apellido']),
                    'cedula'        => $cedula,
                    'id_afiliacion' => $_POST['afiliacion'],
                    'id_tipo_usuario' => $idTipoUsuario,
                    'id_facultad'   => !empty($_POST['facultad']) ? $_POST['facultad'] : null,
                    'id_carrera'    => !empty($_POST['carrera']) ? $_POST['carrera'] : null,
                    'celular'       => !empty($_POST['celular']) ? $_POST['celular'] : null,
                    'universidad_externa' => !empty($_POST['universidad_externa']) ? $_POST['universidad_externa'] : null,
                    'fecha_registro'=> date('Y-m-d H:i:s')
                ];

                if (crearUsuario($conn, $userData)) {
                    $usuario = verificarUsuario($conn, $cedula);
                } else {
                    $response['message'] = "Error al registrar el nuevo usuario.";
                }
            }

            if ($usuario && empty($response['message'])) {
                $queryDisponibilidad = "SELECT disponible FROM computadora WHERE id_computadora = :id_computadora";
                $stmtDisponibilidad = $conn->prepare($queryDisponibilidad);
                $stmtDisponibilidad->execute([':id_computadora' => $_POST['computadora']]);
                $computadoraInfo = $stmtDisponibilidad->fetch(PDO::FETCH_ASSOC);
                
                if (!$computadoraInfo || $computadoraInfo['disponible'] != 1) {
                    $response['message'] = "La computadora seleccionada no está disponible";
                } else {
                    $reservaData = [
                        'usuario' => $usuario['id_usuario'],
                        'computadora' => $_POST['computadora'],
                        'fecha' => date('Y-m-d'),
                        'turno' => determinarTurno(),
                        'tipo_uso' => $_POST['tipo_uso'],
                        'hora_entrada' => date('H:i')
                    ];
                    $resultado = reservarComputadora($conn, $reservaData);

if ($resultado['success']) {
    $response['success'] = true;
    $response['message'] = "Reserva de computadora realizada exitosamente";
} else {
    $mensajes = [
        'PC_NO_EXISTE' => 'La computadora seleccionada no existe en el sistema.',
        'PC_NO_DISPONIBLE' => 'Lo sentimos, esta computadora acaba de ser reservada por otro usuario. Por favor, seleccione otra computadora.',
        'TIMEOUT' => 'El sistema está ocupado procesando otra reserva de la misma computadora. Por favor, intente nuevamente en unos segundos.',
        'DEADLOCK' => 'Hubo un conflicto al procesar su reserva. Por favor, intente nuevamente.',
        'ERROR_SISTEMA' => 'Error técnico al procesar la reserva. Si el problema persiste, contacte al administrador.',
        'ERROR_DESCONOCIDO' => 'Error inesperado. Por favor, intente nuevamente.'
    ];
    
    $response['message'] = $mensajes[$resultado['code']] ?? 'Error al realizar la reserva de computadora.';
}
                }
            }
        }
    }
    
    echo json_encode($response);
    exit;
}

// Obtener datos para los formularios
$stmtFacultades = $conn->prepare("SELECT id_facultad, nombre_facultad FROM facultad WHERE activa = 1 ORDER BY nombre_facultad ASC");
$stmtFacultades->execute();
$facultades = $stmtFacultades->fetchAll(PDO::FETCH_ASSOC);
$tiposUso = getTiposUso($conn);

$stmtAfiliaciones = $conn->prepare("SELECT * FROM afiliacion ORDER BY id_afiliacion");
$stmtAfiliaciones->execute();
$afiliaciones = $stmtAfiliaciones->fetchAll(PDO::FETCH_ASSOC);

$stmtComputadoras = $conn->prepare("SELECT id_computadora, numero FROM computadora WHERE disponible = 1 ORDER BY numero ASC");
$stmtComputadoras->execute();
$computadoras = $stmtComputadoras->fetchAll(PDO::FETCH_ASSOC);

$stmtCarreras = $conn->prepare("SELECT c.id_carrera, c.nombre_carrera, fc.id_facultad FROM carrera c INNER JOIN facultadcarrera fc ON c.id_carrera = fc.id_carrera WHERE c.activa = 1 ORDER BY fc.id_facultad, c.nombre_carrera");
$stmtCarreras->execute();
$todasCarreras = $stmtCarreras->fetchAll(PDO::FETCH_ASSOC);

$carrerasPorFacultad = [];
foreach ($todasCarreras as $carrera) {
    $carrerasPorFacultad[$carrera['id_facultad']][] = $carrera;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar ... - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/components.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Reservar Computadora</h1>
            <p>Complete el formulario para reservar una computadora</p>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="btn btn-secondary">← Volver al inicio</a>
        
        <div class="card">
            <h2>Formulario de Reserva de Computadora</h2>
            <div id="alertComputadora"></div>
            
            <form id="formComputadora" class="modal-form" onsubmit="submitForm(event)">
                <input type="hidden" name="tipo_reserva" value="computadora">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="step-indicators" id="step-indicators-computadora">
                    <div class="step-indicator active" data-step="1">1. Identificación</div>
                    <div class="step-indicator" data-step="2">2. Datos Usuario</div>
                    <div class="step-indicator" data-step="3">3. Reserva</div>
                </div>

                <!-- Paso 1: Identificación -->
                <div id="step-1-computadora" class="step-content active">
                    <div class="form-section">
                        <div class="form-section-title">Identificación del Usuario</div>
                        <div class="form-group">
                            <label for="cedula_computadora">Cédula:</label>
                            <input type="text" id="cedula_computadora" name="cedula" class="form-control" placeholder="Ej: 8-123-4567" autocomplete="off" required>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="verificarUsuario()">Verificar Usuario</button>
                    </div>
                </div>

                <!-- Paso 2: Datos del Usuario -->
                <div id="step-2-computadora" class="step-content">
                    <div id="user-info-computadora"></div>
                    <div id="new-user-form-computadora">
                        <div class="form-section">
                            <div class="form-section-title">Datos del Nuevo Usuario</div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nombre_computadora">Nombre:</label>
                                    <input type="text" id="nombre_computadora" name="nombre" class="form-control" autocomplete="off" required>
                                </div>
                                <div class="form-group">
                                    <label for="apellido_computadora">Apellido:</label>
                                    <input type="text" id="apellido_computadora" name="apellido" class="form-control" autocomplete="off" required>
                                </div>
                            </div>
                        </div>
                        <hr class="form-separator">
                        <div class="form-section">
                            <div class="form-section-title">Afiliación</div>
                            <div class="form-group">
                                <label for="afiliacion_computadora">Seleccione la afiliación:</label>
                                <select id="afiliacion_computadora" name="afiliacion" class="form-control" required onchange="actualizarFormularioPorAfiliacion()">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($afiliaciones as $afiliacion): ?>
                                    <option value="<?php echo $afiliacion['id_afiliacion']; ?>"><?php echo htmlspecialchars($afiliacion['nombre_afiliacion']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="campos_up_computadora" class="hidden-fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tipo_usuario_computadora">Rol en la Universidad:</label>
                                        <select id="tipo_usuario_computadora" name="tipo_usuario" class="form-control" onchange="actualizarCamposUP()">
                                            <option value="1">Estudiante</option>
                                            <option value="2">Profesor</option>
                                            <option value="3">Administrativo</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="campo-facultad-computadora">
                                        <label for="facultad_computadora">Facultad:</label>
                                        <select id="facultad_computadora" name="facultad" class="form-control" onchange="cargarCarreras()">
                                            <option value="">Seleccionar facultad</option>
                                            <?php foreach ($facultades as $facultad): ?>
                                            <option value="<?php echo $facultad['id_facultad']; ?>"><?php echo htmlspecialchars($facultad['nombre_facultad']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" id="campo-carrera-computadora">
                                        <label for="carrera_computadora">Carrera:</label>
                                        <select id="carrera_computadora" name="carrera" class="form-control">
                                            <option value="">Seleccione una facultad</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="campos_otra_universidad_computadora" class="hidden-fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tipo_usuario_externa_computadora">Tipo de Usuario:</label>
                                        <select id="tipo_usuario_externa_computadora" name="tipo_usuario" class="form-control">
                                            <option value="1">Estudiante</option>
                                            <option value="2">Profesor</option>
                                            <option value="3">Administrativo</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="universidad_externa_computadora">Nombre de la Universidad:</label>
                                        <input type="text" id="universidad_externa_computadora" name="universidad_externa" class="form-control" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            
                            <div id="campos_particular_computadora" class="hidden-fields">
                                <div class="form-group">
                                    <label for="celular_computadora">Número de Celular:</label>
                                    <input type="tel" id="celular_computadora" name="celular" class="form-control" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Detalles de Reserva -->
                <div id="step-3-computadora" class="step-content">
                    <div class="form-section">
                        <div class="form-section-title">Detalles de la Reserva</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="computadora">Computadora:</label>
                                <select id="computadora" name="computadora" class="form-control" required>
                                    <option value="">Seleccionar computadora</option>
                                    <?php if (empty($computadoras)): ?>
                                    <option value="" disabled>No hay computadoras disponibles</option>
                                    <?php else: ?>
                                    <?php foreach ($computadoras as $pc): ?>
                                    <option value="<?php echo $pc['id_computadora']; ?>">Computadora <?php echo $pc['numero']; ?></option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tipo_uso">Tipo de Uso:</label>
                                <select id="tipo_uso" name="tipo_uso" class="form-control" required>
                                    <option value="">Seleccionar tipo de uso</option>
                                    <?php foreach ($tiposUso as $tipo): ?>
                                    <option value="<?php echo $tipo['id_tipo_uso']; ?>"><?php echo htmlspecialchars($tipo['nombre_tipo_uso']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-navigation" id="nav-computadora">
                    <button type="button" class="btn btn-secondary" id="btn-prev-computadora" onclick="pasoAnterior()" style="display: none;">Anterior</button>
                    <button type="button" class="btn btn-primary" id="btn-next-computadora" onclick="siguientePaso()" style="display: none;">Siguiente</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitComputadora" style="display: none;"><span class="btn-text">Realizar Reserva</span></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const carrerasPorFacultad = <?php echo json_encode($carrerasPorFacultad); ?>;
        let requestInProgress = false;
        let currentStep = 1;
        window.usuarioEncontrado = null;

        function mostrarPaso(paso) {
            currentStep = paso;
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById(`step-${paso}-computadora`).classList.add('active');

            const indicators = document.querySelectorAll('#step-indicators-computadora .step-indicator');
            indicators.forEach((ind, index) => {
                ind.classList.remove('active', 'completed');
                if (index < paso - 1) ind.classList.add('completed');
                if (index === paso - 1) ind.classList.add('active');
            });

            const btnPrev = document.getElementById('btn-prev-computadora');
            const btnNext = document.getElementById('btn-next-computadora');
            const btnSubmit = document.getElementById('btnSubmitComputadora');
            const btnVerify = document.querySelector('#step-1-computadora button');

            btnVerify.style.display = (paso === 1) ? 'block' : 'none';
            btnPrev.style.display = (paso > 1) ? 'inline-block' : 'none';
            btnNext.style.display = (paso === 2) ? 'inline-block' : 'none';
            btnSubmit.style.display = (paso === 3) ? 'inline-block' : 'none';
        }

        function siguientePaso() {
            if (currentStep < 3) {
                if (currentStep === 2 && document.getElementById('new-user-form-computadora').style.display !== 'none') {
                    const form = document.getElementById('formComputadora');
                    let isValid = true;
                    const fields = form.querySelectorAll('#new-user-form-computadora [required]');
                    fields.forEach(field => {
                        if (!field.checkValidity()) {
                            isValid = false;
                            field.reportValidity();
                        }
                    });
                    if (!isValid) return;
                }
                mostrarPaso(currentStep + 1);
            }
        }

        function pasoAnterior() {
            if (currentStep > 1) {
                if (currentStep === 2) {
                    const cedulaInput = document.getElementById('cedula_computadora');
                    cedulaInput.readOnly = false;
                    cedulaInput.disabled = false;
                    document.getElementById('user-info-computadora').innerHTML = '';
                    document.getElementById('new-user-form-computadora').style.display = 'block';
                }
                
                if (currentStep === 3 && window.usuarioEncontrado) {
                    const user = window.usuarioEncontrado;
                    const userInfoDiv = document.getElementById('user-info-computadora');
                    userInfoDiv.innerHTML = `
                        <div class="user-found-info">
                            <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                            <p><strong>Cédula:</strong> ${user.cedula}</p>
                            <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                            ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                            ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                        </div>`;
                    document.getElementById('new-user-form-computadora').style.display = 'none';
                    mostrarPaso(2);
                } else {
                    mostrarPaso(currentStep - 1);
                }
            }
        }

        async function verificarUsuario() {
    const cedulaInput = document.getElementById('cedula_computadora'); // o cedula_libro según el archivo
    const cedula = cedulaInput.value;
    clearAlert();

    if (!validarCedulaPanama(cedula)) {
        showAlert('Formato de cédula inválido.');
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'verificar_usuario');
    formData.append('cedula', cedula);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    try {
        const response = await fetch('reservar_computadora.php', { method: 'POST', body: formData }); // o reservar_libro.php
        const result = await response.json();
        
        cedulaInput.readOnly = true;

        if (result.encontrado) {
            const user = result.usuario;
            window.usuarioEncontrado = user;
            
            const userInfoDiv = document.getElementById('user-info-computadora'); // o user-info-libro
            userInfoDiv.innerHTML = `
                <div class="user-found-info">
                    <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                    <p><strong>Cédula:</strong> ${user.cedula}</p>
                    <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                    ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                    ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                </div>`;
            
            document.getElementById('new-user-form-computadora').style.display = 'none'; // o new-user-form-libro
            
            // ============ CRÍTICO: LLENAR TODOS LOS CAMPOS ============
            document.getElementById('nombre_computadora').value = user.nombre || '';
            document.getElementById('apellido_computadora').value = user.apellido || '';
            document.getElementById('afiliacion_computadora').value = user.id_afiliacion || '';
            
            // Llenar campos según afiliación
            if (user.id_afiliacion == 1) { // Universidad de Panamá
                if (user.id_tipo_usuario) {
                    document.getElementById('tipo_usuario_computadora').value = user.id_tipo_usuario;
                }
                if (user.id_facultad) {
                    document.getElementById('facultad_computadora').value = user.id_facultad;
                    cargarCarreras();
                    if (user.id_carrera) {
                        setTimeout(() => {
                            document.getElementById('carrera_computadora').value = user.id_carrera;
                        }, 100);
                    }
                }
            } else if (user.id_afiliacion == 2) { // Otra Universidad
                if (user.id_tipo_usuario) {
                    document.getElementById('tipo_usuario_externa_computadora').value = user.id_tipo_usuario;
                }
                if (user.universidad_externa) {
                    document.getElementById('universidad_externa_computadora').value = user.universidad_externa;
                }
            } else if (user.id_afiliacion == 3) { // Particular
                if (user.celular) {
                    document.getElementById('celular_computadora').value = user.celular;
                }
            }
            // ========================================================
            
            mostrarPaso(3);
        } else {
            showAlert('Usuario no encontrado. Por favor, complete el formulario de registro.', true);
            document.getElementById('new-user-form-computadora').style.display = 'block';
            document.getElementById('user-info-computadora').innerHTML = '';
            window.usuarioEncontrado = null;
            mostrarPaso(2);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error al verificar el usuario.');
        cedulaInput.readOnly = false;
    }
}

        function showAlert(message, isSuccess = false) {
            const alertDiv = document.getElementById('alertComputadora');
            alertDiv.innerHTML = `<div class="alert ${isSuccess ? 'alert-success' : 'alert-danger'}">${message}</div>`;
        }

        function clearAlert() {
            document.getElementById('alertComputadora').innerHTML = '';
        }

        function setButtonLoading(btn, isLoading) {
            const btnText = btn.querySelector('.btn-text');
            if (isLoading) {
                btn.disabled = true;
                if(btnText) btnText.innerHTML = '<span class="spinner"></span>Procesando...';
                btn.style.pointerEvents = 'none';
            } else {
                btn.disabled = false;
                if(btnText) btnText.innerHTML = 'Realizar Reserva';
                btn.style.pointerEvents = 'auto';
            }
        }

        function validarCedulaPanama(cedula) {
            const patron = /^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/;
            return patron.test(cedula.trim());
        }

        function actualizarFormularioPorAfiliacion() {
            const afiSelect = document.getElementById('afiliacion_computadora');
            const afiId = afiSelect.value;

            const secciones = {
                up: document.getElementById('campos_up_computadora'),
                otra: document.getElementById('campos_otra_universidad_computadora'),
                particular: document.getElementById('campos_particular_computadora')
            };

            const gestionarCampos = (seccion, habilitar) => {
                seccion.style.display = habilitar ? 'block' : 'none';
                seccion.querySelectorAll('input, select').forEach(el => {
                    el.disabled = !habilitar;
                    if (el.hasAttribute('data-required')) {
                        el.required = habilitar;
                    }
                });
            };
            
            if (!secciones.up.querySelector('[data-required]')) {
                secciones.up.querySelectorAll('[required]').forEach(el => el.setAttribute('data-required', 'true'));
                secciones.otra.querySelectorAll('[required]').forEach(el => el.setAttribute('data-required', 'true'));
                secciones.particular.querySelectorAll('[required]').forEach(el => el.setAttribute('data-required', 'true'));
            }

            gestionarCampos(secciones.up, false);
            gestionarCampos(secciones.otra, false);
            gestionarCampos(secciones.particular, false);

            if (afiId === '1') {
                gestionarCampos(secciones.up, true);
                actualizarCamposUP();
            } else if (afiId === '2') {
                gestionarCampos(secciones.otra, true);
            } else if (afiId === '3') {
                gestionarCampos(secciones.particular, true);
            }
        }

        // Buscar esta función en reservar_computadora.php (alrededor de la línea 280)
// y reemplazarla con esta versión:

function actualizarCamposUP() {
    const rolSelect = document.getElementById('tipo_usuario_computadora');
    const facultadField = document.getElementById('campo-facultad-computadora');
    const carreraField = document.getElementById('campo-carrera-computadora');
    const facultadSelect = document.getElementById('facultad_computadora');
    const carreraSelect = document.getElementById('carrera_computadora');
    const rol = rolSelect.value;
    
    const esEstudianteOProfesor = (rol === '1' || rol === '2');
    const esEstudiante = (rol === '1');

    facultadField.style.display = esEstudianteOProfesor ? 'block' : 'none';
    facultadSelect.required = esEstudianteOProfesor;
    if (!esEstudianteOProfesor) {
        facultadSelect.value = '';
        facultadSelect.removeAttribute('required');
    }

    carreraField.style.display = esEstudiante ? 'block' : 'none';
    carreraSelect.required = esEstudiante;
    if (!esEstudiante) {
        carreraSelect.value = '';
        carreraSelect.removeAttribute('required');
    }
    
    cargarCarreras();
}
        function cargarCarreras() {
            const facultadId = document.getElementById('facultad_computadora').value;
            const carreraSelect = document.getElementById('carrera_computadora');
            carreraSelect.innerHTML = '<option value="">Seleccione una carrera</option>';
            if (facultadId && carrerasPorFacultad[facultadId]) {
                carrerasPorFacultad[facultadId].forEach(carrera => {
                    const option = new Option(carrera.nombre_carrera, carrera.id_carrera);
                    carreraSelect.add(option);
                });
            } else {
                carreraSelect.innerHTML = '<option value="">Seleccione una facultad primero</option>';
            }
        }

        async function submitForm(e) {
            e.preventDefault();
            if (requestInProgress) return;

            const form = e.target;
            let isValid = true;
            const fields = form.querySelectorAll('#step-3-computadora [required]');
            fields.forEach(field => {
                if (!field.checkValidity()) {
                    isValid = false;
                    field.reportValidity();
                }
            });
            if (!isValid) return;

            requestInProgress = true;
            const btn = document.getElementById('btnSubmitComputadora');
            setButtonLoading(btn, true);
            clearAlert();

            try {
                const response = await fetch('reservar_computadora.php', { method: 'POST', body: new FormData(form) });
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, true);
                    setTimeout(() => {
                        window.location.href = 'index.php?reserva_exitosa=1';
                    }, 1500);
                } else {
                    showAlert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error al procesar la reserva');
            } finally {
                requestInProgress = false;
                setButtonLoading(btn, false);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            mostrarPaso(1);
        });
    </script>
</body>
</html>