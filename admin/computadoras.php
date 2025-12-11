<?php
session_start();
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Verificar si el bibliotecario actual es administrador
$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];

// Procesar acciones
$mensaje = '';
$tipoMensaje = '';

// Obtener computadoras
$query = "SELECT * FROM computadora ORDER BY numero";
$stmt = $conn->prepare($query);
$stmt->execute();
$computadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas de uso
$statsQuery = "SELECT 
    c.id_computadora,
    c.numero,
    COUNT(rc.id_reserva_pc) as total_reservas,
    COUNT(CASE WHEN rc.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as reservas_mes
FROM computadora c
LEFT JOIN reservacomputadora rc ON c.id_computadora = rc.id_computadora
GROUP BY c.id_computadora, c.numero
ORDER BY c.numero";
$stmt = $conn->prepare($statsQuery);
$stmt->execute();
$estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$statsMap = [];
foreach ($estadisticas as $stat) {
    $statsMap[$stat['id_computadora']] = $stat;
    }
// ---- AGREGAR ESTO ----
$pageTitle = 'Gestión de Computadoras';
$activePage = 'computadoras'; // Para que el sidebar se marque como activo
$pageStyles = '';

// Incluir el header
require_once 'templates/header.php';
// ---- FIN DE LO AGREGADO ----
?>


        <div class="header">
            <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
                <h1>Gestión de Computadoras</h1>
                <p>Administrar las computadoras disponibles en la biblioteca</p>
            </div>
        </div>
        
        <div class="container">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipoMensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <div class="card form-card" id="formularioComputadora">
                <h2 id="tituloFormulario">Agregar Nueva Computadora</h2>
                <form method="POST" id="computadoraForm" action="form_handler.php">
                    <input type="hidden" name="action" value="crear_computadora" id="formAction">
                    <input type="hidden" name="id_computadora" id="idComputadora">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="numero">Número de Computadora</label>
                            <input type="number" class="form-control" id="numero" name="numero" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="disponible">Estado</label>
                            <select class="form-control" id="disponible" name="disponible">
                                <option value="1">Disponible</option>
                                <option value="0">Reservada</option>
                                <option value="2">En Mantenimiento</option>
                                <option value="3">Desactivada</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="btnSubmit"><i class="fas fa-save"></i> Guardar Computadora</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-times"></i> Cancelar</button>
                </form>
            </div>

            <div class="card">
                <h2>Inventario de Computadoras</h2>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Estado</th>
                                <th>Total Reservas</th>
                                <th>Reservas Este Mes</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($computadoras as $computadora): 
                                $stats = $statsMap[$computadora['id_computadora']] ?? ['total_reservas' => 0, 'reservas_mes' => 0];
                                
                                $estadoTexto = '';
                                $estadoColor = '';
                                $estadoIcono = '';

                                switch ($computadora['disponible']) {
                                    case 1:
                                        $estadoTexto = 'Disponible';
                                        $estadoColor = 'green';
                                        $estadoIcono = 'fa-check-circle';
                                        break;
                                    case 0:
                                        $estadoTexto = 'Reservada';
                                        $estadoColor = '#e67e22';
                                        $estadoIcono = 'fa-bookmark';
                                        break;
                                    case 2:
                                        $estadoTexto = 'En Mantenimiento';
                                        $estadoColor = '#3498db';
                                        $estadoIcono = 'fa-wrench';
                                        break;
                                    case 3:
                                        $estadoTexto = 'Desactivada';
                                        $estadoColor = 'red';
                                        $estadoIcono = 'fa-power-off';
                                        break;
                                    default:
                                        $estadoTexto = 'Desconocido';
                                        $estadoColor = 'grey';
                                        $estadoIcono = 'fa-question-circle';
                                }
                            ?>
                                <tr>
                                    <td><strong>PC-<?php echo str_pad($computadora['numero'], 2, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <span style="color: <?php echo $estadoColor; ?>;">
                                            <i class="fas <?php echo $estadoIcono; ?>" style="font-size: 0.9em;"></i>
                                            <?php echo $estadoTexto; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $stats['total_reservas']; ?></td>
                                    <td><?php echo $stats['reservas_mes']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary" onclick="editarComputadora(<?php echo htmlspecialchars(json_encode($computadora)); ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($computadora['disponible'] == 1): ?>
                                                <button onclick="confirmarDesactivar(<?php echo $computadora['id_computadora']; ?>, 'PC-<?php echo str_pad($computadora['numero'], 2, '0', STR_PAD_LEFT); ?>')" 
                                                        class="btn btn-warning" title="Desactivar">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="confirmarEliminar(<?php echo $computadora['id_computadora']; ?>, 'PC-<?php echo str_pad($computadora['numero'], 2, '0', STR_PAD_LEFT); ?>')" 
                                                    class="btn btn-danger" title="Eliminar permanentemente">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DESACTIVAR -->
    <div id="modalDesactivar" class="modal">
        <div class="modal-overlay" onclick="cerrarModal('modalDesactivar')"></div>
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-circle"></i> Confirmar Desactivación</h3>
            <p id="mensajeDesactivar"></p>
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal('modalDesactivar')" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </button>
               <form method="POST" style="display: inline;" action="form_handler.php">
                <input type="hidden" name="action" value="desactivar_computadora">
                <input type="hidden" id="idDesactivar" name="id_computadora">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-power-off"></i> Desactivar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ELIMINAR -->
    <div id="modalEliminar" class="modal">
        <div class="modal-overlay" onclick="cerrarModal('modalEliminar')"></div>
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
            <p id="mensajeEliminar"></p>
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal('modalEliminar')" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form method="POST" style="display: inline;" action="form_handler.php">
                <input type="hidden" name="action" value="eliminar_computadora">
                <input type="hidden" id="idEliminar" name="id_computadora">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>

        // Funciones para modales
        function abrirModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    cerrarModal(modal.id);
                });
            }
        });

        function confirmarDesactivar(id, nombre) {
            document.getElementById('idDesactivar').value = id;
            document.getElementById('mensajeDesactivar').textContent = `¿Estás seguro de que deseas desactivar la computadora ${nombre}?`;
            abrirModal('modalDesactivar');
        }

        function confirmarEliminar(id, nombre) {
            document.getElementById('idEliminar').value = id;
            document.getElementById('mensajeEliminar').textContent = `¿Seguro que quieres ELIMINAR PERMANENTEMENTE la computadora ${nombre}? Esta acción no se puede deshacer.`;
            abrirModal('modalEliminar');
        }

        function scrollToForm() {
            const formulario = document.getElementById('formularioComputadora');
            formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function editarComputadora(computadora) {
            document.getElementById('formAction').value = 'editar_computadora';
            document.getElementById('idComputadora').value = computadora.id_computadora;
            document.getElementById('numero').value = computadora.numero;
            document.getElementById('disponible').value = computadora.disponible;
            
            document.getElementById('tituloFormulario').textContent = 'Editar Computadora';
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Actualizar Computadora';
            
            scrollToForm();
        }

        function resetForm() {
            document.getElementById('computadoraForm').reset();
            document.getElementById('formAction').value = 'crear_computadora';
            document.getElementById('idComputadora').value = '';
            document.getElementById('tituloFormulario').textContent = 'Agregar Nueva Computadora';
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Guardar Computadora';
        }
        
       
    </script>
<?php
// ---- AGREGAR ESTO ----
require_once 'templates/footer.php';
?>