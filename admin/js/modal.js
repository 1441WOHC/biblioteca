/**
 * MODAL.JS - Sistema de Gestión de Modales
 * Sistema de Gestión de Biblioteca
 * 
 * Funciones para abrir, cerrar y gestionar modales
 * Usado en: nuevareserva.php, administracion.php, computadoras.php, libros.php
 */

// ========================================
// FUNCIONES PRINCIPALES DE MODAL
// ========================================

/**
 * Abre un modal por ID
 */
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Resetear wizard si existe
    const tipo = modalId.replace('modal', '').toLowerCase();
    if (window.wizards && window.wizards[tipo]) {
        window.wizards[tipo].reset();
    }
}

/**
 * Cierra un modal por ID
 */
function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.remove('show');
    document.body.style.overflow = '';
    
    // Resetear wizard si existe
    const tipo = modalId.replace('modal', '').toLowerCase();
    if (window.wizards && window.wizards[tipo]) {
        window.wizards[tipo].reset();
    }
}

/**
 * Alias para compatibilidad
 */
function openModal(modalId) {
    abrirModal(modalId);
}

/**
 * Alias para compatibilidad
 */
function closeModal(modalId) {
    cerrarModal(modalId);
}

// ========================================
// EVENTOS GLOBALES
// ========================================

/**
 * Cierra modales con la tecla ESC
 */
function initModalEscapeKey() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                cerrarModal(modal.id);
            });
        }
    });
}

/**
 * Cierra modal al hacer clic en el overlay
 */
function initModalOverlayClick() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                cerrarModal(modal.id);
            }
        }
    });
}

/**
 * Inicializa todos los eventos de modales
 */
function initModalEvents() {
    initModalEscapeKey();
    initModalOverlayClick();
    
    // Bind close buttons
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                cerrarModal(modal.id);
            }
        });
    });
}

// ========================================
// MODALES DE CONFIRMACIÓN
// ========================================

/**
 * Muestra un modal de confirmación genérico
 */
function mostrarModalConfirmacion(config) {
    const {
        titulo = 'Confirmar Acción',
        mensaje = '¿Estás seguro?',
        textoConfirmar = 'Confirmar',
        textoCancelar = 'Cancelar',
        claseBtnConfirmar = 'btn-warning',
        onConfirm = () => {},
        onCancel = () => {}
    } = config;
    
    // Crear modal dinámicamente si no existe
    let modal = document.getElementById('modalConfirmacionGenerico');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modalConfirmacionGenerico';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h3 id="modalConfirmTitulo"></h3>
                <p id="modalConfirmMensaje"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="modalConfirmCancel">
                        <i class="fas fa-times"></i> ${textoCancelar}
                    </button>
                    <button type="button" class="btn ${claseBtnConfirmar}" id="modalConfirmOk">
                        <i class="fas fa-check"></i> ${textoConfirmar}
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Actualizar contenido
    document.getElementById('modalConfirmTitulo').innerHTML = `<i class="fas fa-exclamation-circle"></i> ${titulo}`;
    document.getElementById('modalConfirmMensaje').textContent = mensaje;
    
    const btnConfirm = document.getElementById('modalConfirmOk');
    const btnCancel = document.getElementById('modalConfirmCancel');
    
    // Limpiar eventos anteriores
    const newBtnConfirm = btnConfirm.cloneNode(true);
    const newBtnCancel = btnCancel.cloneNode(true);
    btnConfirm.parentNode.replaceChild(newBtnConfirm, btnConfirm);
    btnCancel.parentNode.replaceChild(newBtnCancel, btnCancel);
    
    // Agregar nuevos eventos
    newBtnConfirm.addEventListener('click', () => {
        cerrarModal('modalConfirmacionGenerico');
        onConfirm();
    });
    
    newBtnCancel.addEventListener('click', () => {
        cerrarModal('modalConfirmacionGenerico');
        onCancel();
    });
    
    // Mostrar modal
    abrirModal('modalConfirmacionGenerico');
}

// ========================================
// FUNCIONES ESPECÍFICAS PARA ADMINISTRACIÓN
// ========================================

/**
 * Confirma eliminación de bibliotecario
 */
function confirmarEliminarBibliotecario(id, nombre) {
    document.getElementById('idEliminarBibliotecario').value = id;
    document.getElementById('mensajeEliminarBibliotecario').textContent = 
        `¿Estás seguro de que deseas eliminar al bibliotecario "${nombre}"? Esta acción no se puede deshacer.`;
    abrirModal('modalEliminarBibliotecario');
}

/**
 * Confirma cambio de estado de facultad
 */
function confirmarCambiarEstadoFacultad(id, nombre, nuevoEstado) {
    document.getElementById('idCambiarEstadoFacultad').value = id;
    document.getElementById('nuevoEstadoFacultad').value = nuevoEstado;
    
    const accion = nuevoEstado === 0 ? 'desactivar' : 'activar';
    const btnConfirmar = document.getElementById('btnConfirmarEstadoFacultad');
    
    document.getElementById('mensajeCambiarEstadoFacultad').textContent = 
        `¿Estás seguro de que deseas ${accion} la facultad "${nombre}"?`;
    
    if (nuevoEstado === 0) {
        btnConfirmar.className = 'btn btn-warning';
        btnConfirmar.innerHTML = '<i class="fas fa-toggle-off"></i> Desactivar';
    } else {
        btnConfirmar.className = 'btn btn-success';
        btnConfirmar.innerHTML = '<i class="fas fa-toggle-on"></i> Activar';
    }
    
    abrirModal('modalCambiarEstadoFacultad');
}

/**
 * Confirma cambio de estado de carrera
 */
function confirmarCambiarEstadoCarrera(id, nombre, nuevoEstado) {
    document.getElementById('idCambiarEstadoCarrera').value = id;
    document.getElementById('nuevoEstadoCarrera').value = nuevoEstado;
    
    const accion = nuevoEstado === 0 ? 'desactivar' : 'activar';
    const btnConfirmar = document.getElementById('btnConfirmarEstadoCarrera');
    
    document.getElementById('mensajeCambiarEstadoCarrera').textContent = 
        `¿Estás seguro de que deseas ${accion} la carrera "${nombre}"?`;
    
    if (nuevoEstado === 0) {
        btnConfirmar.className = 'btn btn-warning';
        btnConfirmar.innerHTML = '<i class="fas fa-toggle-off"></i> Desactivar';
    } else {
        btnConfirmar.className = 'btn btn-success';
        btnConfirmar.innerHTML = '<i class="fas fa-toggle-on"></i> Activar';
    }
    
    abrirModal('modalCambiarEstadoCarrera');
}

// ========================================
// FUNCIONES PARA LIBROS Y COMPUTADORAS
// ========================================

/**
 * Confirma desactivación de libro/computadora
 */
function confirmarDesactivar(id, nombre) {
    document.getElementById('idDesactivar').value = id;
    document.getElementById('mensajeDesactivar').textContent = 
        `¿Estás seguro de que deseas desactivar "${nombre}"?`;
    abrirModal('modalDesactivar');
}

/**
 * Confirma eliminación de libro/computadora
 */
function confirmarEliminar(id, nombre) {
    document.getElementById('idEliminar').value = id;
    document.getElementById('mensajeEliminar').textContent = 
        `¿Seguro que quieres ELIMINAR PERMANENTEMENTE "${nombre}"? Esta acción no se puede deshacer.`;
    abrirModal('modalEliminar');
}

// ========================================
// FORMULARIOS EN MODALES (para admin)
// ========================================

/**
 * Muestra/oculta formulario de bibliotecario
 */
function toggleFormularioBibliotecario() {
    const formulario = document.getElementById('formularioBibliotecario');
    const formularioEditar = document.getElementById('formularioEditarBibliotecario');
    
    if (formulario.style.display === 'none' || formulario.style.display === '') {
        formulario.style.display = 'block';
        if (formularioEditar) formularioEditar.style.display = 'none';
    } else {
        formulario.style.display = 'none';
    }
}

/**
 * Cancela edición de bibliotecario
 */
function cancelarEdicion() {
    const formularioEditar = document.getElementById('formularioEditarBibliotecario');
    if (formularioEditar) formularioEditar.style.display = 'none';
}

/**
 * Muestra formulario de edición de bibliotecario
 */
function editarBibliotecario(bibliotecario) {
    const formulario = document.getElementById('formularioBibliotecario');
    const formularioEditar = document.getElementById('formularioEditarBibliotecario');
    
    if (formularioEditar) {
        formularioEditar.style.display = 'block';
    }
    if (formulario) {
        formulario.style.display = 'none';
    }
    
    document.getElementById('edit_id_bibliotecario').value = bibliotecario.id_bibliotecario;
    document.getElementById('edit_nombre_completo').value = bibliotecario.nombre_completo;
    document.getElementById('edit_cedula').value = bibliotecario.cedula;
    document.getElementById('edit_es_administrador').checked = bibliotecario.es_administrador == 1;
    
    // Limpiar campos de contraseña
    const nuevaPassword = document.getElementById('nueva_contrasena');
    const confirmPassword = document.getElementById('edit_confirmar_contrasena');
    const rulesEdit = document.getElementById('password-rules-edit');
    
    if (nuevaPassword) nuevaPassword.value = '';
    if (confirmPassword) {
        confirmPassword.value = '';
        confirmPassword.removeAttribute('required');
    }
    if (rulesEdit) rulesEdit.style.display = 'none';
    
    // Scroll al formulario
    formularioEditar.scrollIntoView({ behavior: 'smooth' });
}

// Funciones similares para facultades y carreras
function toggleFormularioFacultad() {
    const formulario = document.getElementById('formularioFacultad');
    const formularioEditar = document.getElementById('formularioEditarFacultad');
    
    if (formulario.style.display === 'none' || formulario.style.display === '') {
        formulario.style.display = 'block';
        if (formularioEditar) formularioEditar.style.display = 'none';
    } else {
        formulario.style.display = 'none';
    }
}

function editarFacultad(facultad) {
    const formulario = document.getElementById('formularioFacultad');
    const formularioEditar = document.getElementById('formularioEditarFacultad');
    
    if (formularioEditar) formularioEditar.style.display = 'block';
    if (formulario) formulario.style.display = 'none';
    
    document.getElementById('edit_id_facultad').value = facultad.id_facultad;
    document.getElementById('edit_nombre_facultad').value = facultad.nombre_facultad;
    
    formularioEditar.scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicionFacultad() {
    const formularioEditar = document.getElementById('formularioEditarFacultad');
    if (formularioEditar) formularioEditar.style.display = 'none';
}

function toggleFormularioCarrera() {
    const formulario = document.getElementById('formularioCarrera');
    const formularioEditar = document.getElementById('formularioEditarCarrera');
    
    if (formulario.style.display === 'none' || formulario.style.display === '') {
        formulario.style.display = 'block';
        if (formularioEditar) formularioEditar.style.display = 'none';
    } else {
        formulario.style.display = 'none';
    }
}

function editarCarrera(carrera) {
    const formulario = document.getElementById('formularioCarrera');
    const formularioEditar = document.getElementById('formularioEditarCarrera');
    
    if (formularioEditar) formularioEditar.style.display = 'block';
    if (formulario) formulario.style.display = 'none';
    
    document.getElementById('edit_id_carrera').value = carrera.id_carrera;
    document.getElementById('edit_nombre_carrera').value = carrera.nombre_carrera;
    
    // Seleccionar facultad
    const facultadId = carrera.facultades_ids ? carrera.facultades_ids.split(',')[0] : '';
    const selectFacultad = document.getElementById('edit_facultad_carrera');
    if (selectFacultad) {
        selectFacultad.value = facultadId;
    }
    
    formularioEditar.scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicionCarrera() {
    const formularioEditar = document.getElementById('formularioEditarCarrera');
    if (formularioEditar) formularioEditar.style.display = 'none';
}

// ========================================
// INICIALIZACIÓN
// ========================================

// Auto-inicializar eventos cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModalEvents);
} else {
    initModalEvents();
}

// Exportar funciones para uso global
if (typeof window !== 'undefined') {
    window.abrirModal = abrirModal;
    window.cerrarModal = cerrarModal;
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.mostrarModalConfirmacion = mostrarModalConfirmacion;
    window.confirmarEliminarBibliotecario = confirmarEliminarBibliotecario;
    window.confirmarCambiarEstadoFacultad = confirmarCambiarEstadoFacultad;
    window.confirmarCambiarEstadoCarrera = confirmarCambiarEstadoCarrera;
    window.confirmarDesactivar = confirmarDesactivar;
    window.confirmarEliminar = confirmarEliminar;
    window.toggleFormularioBibliotecario = toggleFormularioBibliotecario;
    window.cancelarEdicion = cancelarEdicion;
    window.editarBibliotecario = editarBibliotecario;
    window.toggleFormularioFacultad = toggleFormularioFacultad;
    window.editarFacultad = editarFacultad;
    window.cancelarEdicionFacultad = cancelarEdicionFacultad;
    window.toggleFormularioCarrera = toggleFormularioCarrera;
    window.editarCarrera = editarCarrera;
    window.cancelarEdicionCarrera = cancelarEdicionCarrera;
}