<?php
session_start();
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$mensaje = '';
$error = '';

$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];

// Protección CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- MODIFICACIÓN ---
// Se reemplaza la consulta de estadísticas por una más simple para el filtro.
$queryTipos = "SELECT nombre_tipo_usuario FROM tipousuario ORDER BY nombre_tipo_usuario";
$stmtTipos = $conn->prepare($queryTipos);
$stmtTipos->execute();
$tiposDeUsuario = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

// ---- AGREGAR ESTO ----
$pageTitle = 'Gestión de Usuarios';
$activePage = 'usuarios'; // Para el sidebar
$pageStyles = '

';

// Incluir el header
require_once 'templates/header.php';
// ---- FIN DE LO AGREGADO ----
?>


        <div class="header">
            <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
                <h1>Gestión de Usuarios</h1>
                <p>Administración de usuarios del sistema</p>
            </div>
        </div>

        <div class="container">
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Generar Reporte PDF de Usuarios</h2>
               <form action="../generar_pdf.php" method="GET" target="_blank">
                    <input type="hidden" name="tipo" value="usuarios">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio_usuarios">Fecha de Inicio de Registro:</label>
                            <input type="date" id="fecha_inicio_usuarios" name="fecha_inicio" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="fecha_fin_usuarios">Fecha de Fin de Registro:</label>
                            <input type="date" id="fecha_fin_usuarios" name="fecha_fin" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                         <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generar PDF</button>
                         <small style="display: block; margin-top: 5px;">Deje las fechas en blanco para generar un reporte completo.</small>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Lista de Usuarios Registrados</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="filtroUsuario" placeholder="Buscar por nombre, cédula o afiliación..." 
                               class="form-control" onkeyup="filtrarUsuarios()">
                    </div>
                     <div class="form-group">
                        <select id="filtroTipoUsuario" class="form-control" onchange="filtrarUsuarios()">
                            <option value="">Todos los tipos de usuario</option>
                            <?php foreach ($tiposDeUsuario as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['nombre_tipo_usuario']); ?>">
                                    <?php echo htmlspecialchars($tipo['nombre_tipo_usuario']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="date" id="filtroFechaInicio" class="form-control" onchange="filtrarUsuarios()" placeholder="Fecha Inicio Registro">
                    </div>
                    <div class="form-group">
                        <input type="date" id="filtroFechaFin" class="form-control" onchange="filtrarUsuarios()" placeholder="Fecha Fin Registro">
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table" id="tablaUsuarios">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Cédula</th>
                                <th>Tipo Usuario</th>
								<th>Afiliación</th>
                                <th>Facultad</th>
                                <th>Carrera</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                        <tbody id="usuarios-tbody">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

   <script>
    // Toggle sidebar functionality
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    menuToggle.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            mainWrapper.classList.toggle('expanded');
        }
    });

    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        }
    });

    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            }
        });
    });

    let searchTimeout;
    
    function filtrarUsuarios() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchUsuarios();
        }, 500); // Esperar 500ms después de que el usuario deje de escribir
    }

    function renderTablaUsuarios(usuarios) {
        const tbody = document.getElementById('usuarios-tbody');
        tbody.innerHTML = '';

        if (!usuarios || usuarios.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay usuarios para mostrar.</td></tr>';
            return;
        }

        usuarios.forEach(usuario => {
            const nombreFacultad = usuario.nombre_facultad ? usuario.nombre_facultad : 'N/A';
            const nombreCarrera = usuario.nombre_carrera ? usuario.nombre_carrera : 'N/A';

            let fechaRegistro = 'N/A';
            if (usuario.fecha_registro && !usuario.fecha_registro.startsWith('0000-00-00')) {
                try {
                    // Esta línea ya formatea la fecha como dd/mm/aaaa
                    fechaRegistro = new Date(usuario.fecha_registro).toLocaleDateString('es-ES', {
                        day: '2-digit', month: '2-digit', year: 'numeric'
                    });
                } catch (e) {
                    console.error('Error al procesar la fecha para el usuario:', usuario.cedula);
                }
            }

           const row = `
    <tr data-tipo-usuario="${usuario.nombre_tipo_usuario || ''}" data-fecha-registro="${usuario.fecha_registro || ''}">
        <td>${usuario.nombre_completo || ''}</td>
        <td>${usuario.cedula || ''}</td>
        <td>${usuario.nombre_tipo_usuario || ''}</td>
        <td>${usuario.nombre_afiliacion || 'N/A'}</td>
        <td>${nombreFacultad}</td>
        <td>${nombreCarrera}</td>
        <td>${fechaRegistro}</td>
    </tr>
`;
            tbody.innerHTML += row;
        });
    }

    async function fetchUsuarios() {
        try {
            const search = document.getElementById('filtroUsuario').value;
            const tipoUsuario = document.getElementById('filtroTipoUsuario').value;
            const fechaInicio = document.getElementById('filtroFechaInicio').value;
            const fechaFin = document.getElementById('filtroFechaFin').value;
            
            const hayFiltros = search || tipoUsuario || fechaInicio || fechaFin;
            const limit = hayFiltros ? 0 : 10;
            
            const params = new URLSearchParams({
                action: 'get_usuarios',
                limit: limit
            });
            
            if (search) params.append('search', search);
            if (tipoUsuario) params.append('tipo_usuario', tipoUsuario);
            if (fechaInicio) params.append('fecha_inicio', fechaInicio);
            if (fechaFin) params.append('fecha_fin', fechaFin);
            
            const response = await fetch(`api.php?${params.toString()}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            renderTablaUsuarios(data);
            
            const tbody = document.getElementById('usuarios-tbody');
            if (!hayFiltros && data.length === 10) {
                const limitRow = document.createElement('tr');
                limitRow.innerHTML = '<td colspan="6" style="text-align:center; background-color: #f8f9fa; font-style: italic;">Mostrando las últimas 10 registros. Use los filtros para búsquedas específicas.</td>';
                tbody.appendChild(limitRow);
            }
            
        } catch (error) {
            console.error('Error al cargar usuarios:', error);
            const tbody = document.getElementById('usuarios-tbody');
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: red;">Error al cargar los datos. ${error.message}</td></tr>`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchUsuarios();
        
        document.getElementById('filtroUsuario').addEventListener('input', filtrarUsuarios);
        document.getElementById('filtroTipoUsuario').addEventListener('change', filtrarUsuarios);
        document.getElementById('filtroFechaInicio').addEventListener('change', filtrarUsuarios);
        document.getElementById('filtroFechaFin').addEventListener('change', filtrarUsuarios);
        
        if (window.usuarioInterval) clearInterval(window.usuarioInterval);
        window.usuarioInterval = setInterval(() => {
            const hayFiltros = document.getElementById('filtroUsuario').value || 
                              document.getElementById('filtroTipoUsuario').value || 
                              document.getElementById('filtroFechaInicio').value || 
                              document.getElementById('filtroFechaFin').value;
            if (!hayFiltros) {
                fetchUsuarios();
            }
        }, 30000);
    });
	

	
</script>
<?php
// ---- AGREGAR ESTO ----
require_once 'templates/footer.php';
?>