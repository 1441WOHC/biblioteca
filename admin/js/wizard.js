/**
 * WIZARD.JS - Sistema de Pasos para Formularios de Reserva
 * Sistema de Gestión de Biblioteca
 * 
 * Maneja la navegación entre pasos en formularios multi-paso
 * Usado en: nuevareserva.php, reservas_libros.php, reservas_computadoras.php,
 *           reservar_libro.php, reservar_computadora.php
 */

class ReservaWizard {
    constructor(tipo, carrerasPorFacultad = {}) {
        this.tipo = tipo; // 'libro' o 'computadora'
        this.currentStep = 1;
        this.carrerasPorFacultad = carrerasPorFacultad;
        this.usuarioEncontrado = null;
        this.requestInProgress = false;
        
        this.init();
    }

    init() {
        this.mostrarPaso(1);
        this.bindEvents();
    }

    bindEvents() {
        // Eventos de navegación
        const btnNext = document.getElementById(`btn-next-${this.tipo}`);
        const btnPrev = document.getElementById(`btn-prev-${this.tipo}`);
        
        if (btnNext) {
            btnNext.addEventListener('click', () => this.siguientePaso());
        }
        
        if (btnPrev) {
            btnPrev.addEventListener('click', () => this.pasoAnterior());
        }

        // Eventos de afiliación
        const afiSelect = document.getElementById(`afiliacion_${this.tipo}`);
        if (afiSelect) {
            afiSelect.addEventListener('change', () => this.actualizarFormularioPorAfiliacion());
        }

        // Eventos de tipo usuario (UP)
        const tipoUsuarioSelect = document.getElementById(`tipo_usuario_${this.tipo}`);
        if (tipoUsuarioSelect) {
            tipoUsuarioSelect.addEventListener('change', () => this.actualizarCamposUP());
        }

        // Eventos de facultad
        const facultadSelect = document.getElementById(`facultad_${this.tipo}`);
        if (facultadSelect) {
            facultadSelect.addEventListener('change', () => this.cargarCarreras());
        }
    }

    mostrarPaso(paso) {
        this.currentStep = paso;
        
        // Ocultar todos los pasos
        document.querySelectorAll(`#step-1-${this.tipo}, #step-2-${this.tipo}, #step-3-${this.tipo}`)
            .forEach(el => el.classList.remove('active'));
        
        // Mostrar paso actual
        const stepElement = document.getElementById(`step-${paso}-${this.tipo}`);
        if (stepElement) {
            stepElement.classList.add('active');
        }

        // Actualizar indicadores
        const indicators = document.querySelectorAll(`#step-indicators-${this.tipo} .step-indicator`);
        indicators.forEach((ind, index) => {
            ind.classList.remove('active', 'completed');
            if (index < paso - 1) ind.classList.add('completed');
            if (index === paso - 1) ind.classList.add('active');
        });

        // Actualizar botones
        this.actualizarBotonesNavegacion(paso);
    }

    actualizarBotonesNavegacion(paso) {
        const btnPrev = document.getElementById(`btn-prev-${this.tipo}`);
        const btnNext = document.getElementById(`btn-next-${this.tipo}`);
        const btnSubmit = document.getElementById(`btnSubmit${this.tipo.charAt(0).toUpperCase() + this.tipo.slice(1)}`);
        const btnVerify = document.querySelector(`#step-1-${this.tipo} button`);

        if (btnVerify) btnVerify.style.display = (paso === 1) ? 'block' : 'none';
        if (btnPrev) btnPrev.style.display = (paso > 1) ? 'inline-block' : 'none';
        if (btnNext) btnNext.style.display = (paso === 2) ? 'inline-block' : 'none';
        if (btnSubmit) btnSubmit.style.display = (paso === 3) ? 'inline-block' : 'none';
    }

    siguientePaso() {
        if (this.currentStep < 3) {
            // Validar paso 2 si es formulario de nuevo usuario
            if (this.currentStep === 2) {
                const newUserForm = document.getElementById(`new-user-form-${this.tipo}`);
                if (newUserForm && newUserForm.style.display !== 'none') {
                    if (!this.validarCamposRequeridos(`#new-user-form-${this.tipo}`)) {
                        return;
                    }
                }
            }
            this.mostrarPaso(this.currentStep + 1);
        }
    }

    pasoAnterior() {
        if (this.currentStep > 1) {
            // Si regresamos al paso 1, resetear verificación
            if (this.currentStep === 2) {
                this.resetearVerificacion();
            }
            
            // Si regresamos del paso 3 al 2 y hay usuario encontrado
            if (this.currentStep === 3 && this.usuarioEncontrado) {
                this.mostrarInfoUsuarioEncontrado();
            }
            
            this.mostrarPaso(this.currentStep - 1);
        }
    }

    resetearVerificacion() {
        const cedulaInput = document.getElementById(`cedula_${this.tipo}`);
        if (cedulaInput) {
            cedulaInput.readOnly = false;
            cedulaInput.disabled = false;
        }
        
        const userInfo = document.getElementById(`user-info-${this.tipo}`);
        if (userInfo) userInfo.innerHTML = '';
        
        const newUserForm = document.getElementById(`new-user-form-${this.tipo}`);
        if (newUserForm) newUserForm.style.display = 'block';
    }

    mostrarInfoUsuarioEncontrado() {
        if (!this.usuarioEncontrado) return;
        
        const user = this.usuarioEncontrado;
        const userInfoDiv = document.getElementById(`user-info-${this.tipo}`);
        
        if (userInfoDiv) {
            userInfoDiv.innerHTML = `
                <div class="user-found-info">
                    <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                    <p><strong>Cédula:</strong> ${user.cedula}</p>
                    <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                    ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                    ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                </div>`;
            
            const newUserForm = document.getElementById(`new-user-form-${this.tipo}`);
            if (newUserForm) newUserForm.style.display = 'none';
        }
    }

    validarCamposRequeridos(selector) {
        const form = document.querySelector(`form#form${this.tipo.charAt(0).toUpperCase() + this.tipo.slice(1)}`);
        if (!form) return true;

        let isValid = true;
        const fields = form.querySelectorAll(`${selector} [required]`);
        
        fields.forEach(field => {
            // Solo validar si el campo está visible y habilitado
            if (field.offsetParent !== null && !field.disabled && !field.checkValidity()) {
                isValid = false;
                field.reportValidity();
            }
        });
        
        return isValid;
    }

    // ========================================
    // GESTIÓN DE AFILIACIÓN
    // ========================================

    actualizarFormularioPorAfiliacion() {
        const afiSelect = document.getElementById(`afiliacion_${this.tipo}`);
        if (!afiSelect) return;
        
        const afiId = afiSelect.value;

        const secciones = {
            up: document.getElementById(`campos_up_${this.tipo}`),
            otra: document.getElementById(`campos_otra_universidad_${this.tipo}`),
            particular: document.getElementById(`campos_particular_${this.tipo}`)
        };

        // Guardar estado original de required si no se ha hecho
        if (secciones.up && !secciones.up.hasAttribute('data-required-saved')) {
            Object.values(secciones).forEach(seccion => {
                if (seccion) {
                    seccion.querySelectorAll('input, select').forEach(el => {
                        if (el.required) el.setAttribute('data-required', 'true');
                    });
                }
            });
            secciones.up.setAttribute('data-required-saved', 'true');
        }

        // Gestionar campos según afiliación
        this.gestionarCampos(secciones.up, afiId === '1');
        this.gestionarCampos(secciones.otra, afiId === '2');
        this.gestionarCampos(secciones.particular, afiId === '3');

        // Si es UP, actualizar campos según tipo de usuario
        if (afiId === '1') {
            this.actualizarCamposUP();
        }
    }

    gestionarCampos(seccion, habilitar) {
        if (!seccion) return;
        
        seccion.style.display = habilitar ? 'block' : 'none';
        
        seccion.querySelectorAll('input, select').forEach(el => {
            el.disabled = !habilitar;
            
            if (el.hasAttribute('data-required')) {
                el.required = habilitar;
            } else if (!habilitar) {
                el.required = false;
            }
        });
    }

    actualizarCamposUP() {
        const rolSelect = document.getElementById(`tipo_usuario_${this.tipo}`);
        if (!rolSelect) return;
        
        const rol = rolSelect.value;
        const facultadField = document.getElementById(`campo-facultad-${this.tipo}`);
        const carreraField = document.getElementById(`campo-carrera-${this.tipo}`);
        const facultadSelect = document.getElementById(`facultad_${this.tipo}`);
        const carreraSelect = document.getElementById(`carrera_${this.tipo}`);

        const esEstudianteOProfesor = (rol === '1' || rol === '2');
        const esEstudiante = (rol === '1');

        // Gestionar facultad
        if (facultadField) {
            facultadField.style.display = esEstudianteOProfesor ? 'block' : 'none';
        }
        if (facultadSelect) {
            facultadSelect.required = esEstudianteOProfesor;
            if (!esEstudianteOProfesor) {
                facultadSelect.value = '';
                facultadSelect.removeAttribute('required');
            }
        }

        // Gestionar carrera
        if (carreraField) {
            carreraField.style.display = esEstudiante ? 'block' : 'none';
        }
        if (carreraSelect) {
            carreraSelect.required = esEstudiante;
            if (!esEstudiante) {
                carreraSelect.value = '';
                carreraSelect.removeAttribute('required');
            }
        }

        this.cargarCarreras();
    }

    cargarCarreras() {
        const facultadSelect = document.getElementById(`facultad_${this.tipo}`);
        const carreraSelect = document.getElementById(`carrera_${this.tipo}`);
        
        if (!facultadSelect || !carreraSelect) return;
        
        const facultadId = facultadSelect.value;
        carreraSelect.innerHTML = '<option value="">Seleccione una carrera</option>';
        
        if (facultadId && this.carrerasPorFacultad[facultadId]) {
            this.carrerasPorFacultad[facultadId].forEach(carrera => {
                const option = new Option(carrera.nombre_carrera, carrera.id_carrera);
                carreraSelect.add(option);
            });
        } else {
            carreraSelect.innerHTML = '<option value="">Seleccione una facultad primero</option>';
        }
    }

    // ========================================
    // VERIFICACIÓN DE USUARIO
    // ========================================

    async verificarUsuario(endpoint = null) {
        const cedulaInput = document.getElementById(`cedula_${this.tipo}`);
        if (!cedulaInput) return;
        
        const cedula = cedulaInput.value;
        
        // Limpiar alertas previas
        if (window.clearAlert) {
            window.clearAlert(this.tipo);
        }

        // Validar formato de cédula
        if (window.validarCedulaPanama && !window.validarCedulaPanama(cedula)) {
            if (window.showAlert) {
                window.showAlert(this.tipo, 'Formato de cédula inválido.');
            }
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'verificar_usuario');
        formData.append('cedula', cedula);
        
        // Añadir CSRF token si existe
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            formData.append('csrf_token', csrfInput.value);
        }

        try {
            // Determinar endpoint automáticamente si no se proporciona
            if (!endpoint) {
                endpoint = window.location.pathname;
            }

            const response = await fetch(endpoint, { 
                method: 'POST', 
                body: formData 
            });
            
            const result = await response.json();
            
            cedulaInput.readOnly = true;
            cedulaInput.disabled = false;

            if (result.encontrado) {
                this.usuarioEncontrado = result.usuario;
                this.llenarDatosUsuario(result.usuario);
                this.mostrarPaso(3);
            } else {
                if (window.showAlert) {
                    window.showAlert(this.tipo, 'Usuario no encontrado. Por favor, complete el formulario de registro.', true);
                }
                
                const newUserForm = document.getElementById(`new-user-form-${this.tipo}`);
                if (newUserForm) newUserForm.style.display = 'block';
                
                const userInfo = document.getElementById(`user-info-${this.tipo}`);
                if (userInfo) userInfo.innerHTML = '';
                
                this.usuarioEncontrado = null;
                this.mostrarPaso(2);
            }
        } catch (error) {
            console.error('Error:', error);
            if (window.showAlert) {
                window.showAlert(this.tipo, 'Error al verificar el usuario.');
            }
            cedulaInput.readOnly = false;
        }
    }

    llenarDatosUsuario(user) {
        const userInfoDiv = document.getElementById(`user-info-${this.tipo}`);
        
        if (userInfoDiv) {
            userInfoDiv.innerHTML = `
                <div class="user-found-info">
                    <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                    <p><strong>Cédula:</strong> ${user.cedula}</p>
                    <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                    ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                    ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                </div>`;
        }
        
        const newUserForm = document.getElementById(`new-user-form-${this.tipo}`);
        if (newUserForm) newUserForm.style.display = 'none';
        
        // Llenar campos ocultos
        this.setInputValue(`nombre_${this.tipo}`, user.nombre);
        this.setInputValue(`apellido_${this.tipo}`, user.apellido);
        this.setInputValue(`afiliacion_${this.tipo}`, user.id_afiliacion);
        
        // Llenar campos según afiliación
        if (user.id_afiliacion == 1) { // Universidad de Panamá
            this.setInputValue(`tipo_usuario_${this.tipo}`, user.id_tipo_usuario);
            
            if (user.id_facultad) {
                this.setInputValue(`facultad_${this.tipo}`, user.id_facultad);
                this.cargarCarreras();
                
                if (user.id_carrera) {
                    setTimeout(() => {
                        this.setInputValue(`carrera_${this.tipo}`, user.id_carrera);
                    }, 100);
                }
            }
        } else if (user.id_afiliacion == 2) { // Otra Universidad
            this.setInputValue(`tipo_usuario_externa_${this.tipo}`, user.id_tipo_usuario);
            this.setInputValue(`universidad_externa_${this.tipo}`, user.universidad_externa);
        } else if (user.id_afiliacion == 3) { // Particular
            this.setInputValue(`celular_${this.tipo}`, user.celular);
        }
    }

    setInputValue(id, value) {
        const input = document.getElementById(id);
        if (input && value) {
            input.value = value;
        }
    }

    // ========================================
    // RESET
    // ========================================

    reset() {
        this.currentStep = 1;
        this.usuarioEncontrado = null;
        this.requestInProgress = false;
        
        const form = document.getElementById(`form${this.tipo.charAt(0).toUpperCase() + this.tipo.slice(1)}`);
        if (form) form.reset();
        
        this.mostrarPaso(1);
        
        if (window.clearAlert) {
            window.clearAlert(this.tipo);
        }
        
        this.resetearVerificacion();
        
        // Ocultar campos condicionales
        this.gestionarCampos(document.getElementById(`campos_up_${this.tipo}`), false);
        this.gestionarCampos(document.getElementById(`campos_otra_universidad_${this.tipo}`), false);
        this.gestionarCampos(document.getElementById(`campos_particular_${this.tipo}`), false);
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ReservaWizard = ReservaWizard;
}