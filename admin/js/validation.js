/**
 * VALIDATION.JS - Sistema de Validaciones
 * Sistema de Gestión de Biblioteca
 * 
 * Funciones de validación reutilizables para todo el sistema
 * Usado en: Todas las páginas con formularios
 */

// ========================================
// VALIDACIÓN DE CÉDULA PANAMEÑA
// ========================================

/**
 * Valida el formato de cédula panameña
 * Formatos válidos: 1-1234-12345, PE-1234-12345, E-1234-123456, 
 *                   N-1234-1234, 1AV-1234-12345, 1PI-1234-12345
 */
function validarCedulaPanama(cedula) {
    const patron = /^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/;
    return patron.test(cedula.trim());
}

/**
 * Formatea la entrada de cédula mientras el usuario escribe
 * Remueve caracteres no permitidos
 */
function validarCedulaInput(input) {
    if (input && input.value) {
        input.value = input.value.replace(/[^0-9\-A-Z]/gi, '');
    }
}

/**
 * Inicializa validación en tiempo real para campos de cédula
 */
function initCedulaValidation() {
    document.querySelectorAll('input[id*="cedula"]').forEach(input => {
        input.addEventListener('input', function() {
            validarCedulaInput(this);
        });
    });
}

// ========================================
// VALIDACIÓN DE CONTRASEÑA
// ========================================

class PasswordValidator {
    constructor(inputId, rulesContainerId, confirmInputId = null) {
        this.input = document.getElementById(inputId);
        this.rulesContainer = document.getElementById(rulesContainerId);
        this.confirmInput = confirmInputId ? document.getElementById(confirmInputId) : null;
        
        // Expresiones regulares
        this.hasUpper = /[A-Z]/;
        this.hasLower = /[a-z]/;
        this.hasNum = /[0-9]/;
        this.hasSpecial = /[!@#$%^&*(),.?":{}|<>]/;
        
        if (this.input && this.rulesContainer) {
            this.init();
        }
    }

    init() {
        // Validación en tiempo real
        this.input.addEventListener('input', () => this.validate());
        
        // Mostrar reglas cuando se enfoca el campo
        this.input.addEventListener('focus', () => {
            if (this.rulesContainer) {
                this.rulesContainer.style.display = 'block';
            }
        });
        
        // Validación de confirmación
        if (this.confirmInput) {
            this.confirmInput.addEventListener('input', () => this.validateConfirmation());
        }
    }

    validate() {
        const pass = this.input.value;
        let allValid = true;

        // Regla 1: Longitud (al menos 8 caracteres)
        const lengthRule = this.rulesContainer.querySelector('[id*="rule-length"]');
        if (lengthRule) {
            if (pass.length >= 8) {
                this.setRuleValid(lengthRule);
            } else {
                this.setRuleInvalid(lengthRule);
                allValid = false;
            }
        }

        // Regla 2: Mayúscula, Minúscula y Número
        const caseNumRule = this.rulesContainer.querySelector('[id*="rule-case-num"]');
        if (caseNumRule) {
            if (this.hasUpper.test(pass) && this.hasLower.test(pass) && this.hasNum.test(pass)) {
                this.setRuleValid(caseNumRule);
            } else {
                this.setRuleInvalid(caseNumRule);
                allValid = false;
            }
        }

        // Regla 3: Carácter especial (condicional)
        const specialRule = this.rulesContainer.querySelector('[id*="rule-special"]');
        if (specialRule) {
            if (pass.length < 12) {
                if (this.hasSpecial.test(pass)) {
                    this.setRuleValid(specialRule);
                } else {
                    this.setRuleInvalid(specialRule);
                    allValid = false;
                }
            } else {
                this.setRuleValid(specialRule);
            }
        }

        // Actualizar validación del input
        if (allValid) {
            this.input.setCustomValidity('');
        } else {
            this.input.setCustomValidity('La contraseña no cumple con todos los requisitos.');
        }

        return allValid;
    }

    setRuleValid(element) {
        if (!element) return;
        element.classList.add('valid');
        element.classList.remove('invalid');
        const icon = element.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-check-circle';
        }
    }

    setRuleInvalid(element) {
        if (!element) return;
        element.classList.add('invalid');
        element.classList.remove('valid');
        const icon = element.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-times-circle';
        }
    }

    validateConfirmation() {
        if (!this.confirmInput) return true;
        
        const pass = this.input.value;
        const confirm = this.confirmInput.value;
        
        if (pass !== confirm) {
            this.confirmInput.setCustomValidity('Las contraseñas no coinciden');
            return false;
        } else {
            this.confirmInput.setCustomValidity('');
            return true;
        }
    }
}

/**
 * Inicializa validadores de contraseña en la página
 */
function initPasswordValidation() {
    // Para formulario de crear (administracion.php)
    if (document.getElementById('contrasena')) {
        new PasswordValidator(
            'contrasena',
            'password-rules-create',
            'confirmar_contrasena'
        );
    }

    // Para formulario de editar (administracion.php)
    if (document.getElementById('nueva_contrasena')) {
        const editValidator = new PasswordValidator(
            'nueva_contrasena',
            'password-rules-edit',
            'edit_confirmar_contrasena'
        );
        
        // Lógica especial para edición (contraseña opcional)
        const editPassInput = document.getElementById('nueva_contrasena');
        const editConfirmInput = document.getElementById('edit_confirmar_contrasena');
        const editRulesContainer = document.getElementById('password-rules-edit');
        
        if (editPassInput && editConfirmInput && editRulesContainer) {
            editPassInput.addEventListener('input', function() {
                if (this.value === '') {
                    editRulesContainer.style.display = 'none';
                    editConfirmInput.value = '';
                    editConfirmInput.removeAttribute('required');
                    editPassInput.setCustomValidity('');
                    editConfirmInput.setCustomValidity('');
                } else {
                    editRulesContainer.style.display = 'block';
                    editConfirmInput.setAttribute('required', 'required');
                    editValidator.validate();
                }
            });
        }
    }

    // Validación de confirmación para formulario de crear
    const confirmCreate = document.getElementById('confirmar_contrasena');
    if (confirmCreate) {
        confirmCreate.addEventListener('input', function() {
            const pass = document.getElementById('contrasena')?.value;
            if (pass !== this.value) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Validación de confirmación para formulario de editar
    const confirmEdit = document.getElementById('edit_confirmar_contrasena');
    if (confirmEdit) {
        confirmEdit.addEventListener('input', function() {
            const pass = document.getElementById('nueva_contrasena')?.value;
            if (pass !== this.value) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    }
}

// ========================================
// VALIDACIÓN DE FORMULARIOS
// ========================================

/**
 * Valida todos los campos requeridos de un formulario
 */
function validateForm(formElement) {
    if (!formElement) return false;
    
    let isValid = true;
    const fields = formElement.querySelectorAll('[required]');
    
    fields.forEach(field => {
        // Solo validar campos visibles y habilitados
        if (field.offsetParent !== null && !field.disabled && !field.checkValidity()) {
            isValid = false;
            field.reportValidity();
        }
    });
    
    return isValid;
}

/**
 * Valida campos específicos dentro de un contenedor
 */
function validateFields(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return false;
    
    let isValid = true;
    const fields = container.querySelectorAll('[required]');
    
    fields.forEach(field => {
        if (field.offsetParent !== null && !field.disabled && !field.checkValidity()) {
            isValid = false;
            field.reportValidity();
        }
    });
    
    return isValid;
}

/**
 * Limpia errores de validación de un formulario
 */
function clearFormValidation(formElement) {
    if (!formElement) return;
    
    formElement.querySelectorAll('input, select, textarea').forEach(field => {
        field.setCustomValidity('');
    });
}

// ========================================
// ALERTAS Y MENSAJES
// ========================================

/**
 * Muestra una alerta en el modal
 */
function showAlert(tipo, message, isSuccess = false) {
    const alertDiv = document.getElementById(`alert${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
    
    if (alertDiv) {
        const alertClass = isSuccess ? 'alert-success' : 'alert-danger';
        alertDiv.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
        
        // Scroll hacia la alerta
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

/**
 * Limpia las alertas del modal
 */
function clearAlert(tipo) {
    const alertDiv = document.getElementById(`alert${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
    if (alertDiv) {
        alertDiv.innerHTML = '';
    }
}

// ========================================
// ESTADOS DE CARGA
// ========================================

/**
 * Establece el estado de carga de un botón
 */
function setButtonLoading(btn, isLoading) {
    if (!btn) return;
    
    const btnText = btn.querySelector('.btn-text');
    
    if (isLoading) {
        btn.disabled = true;
        if (btnText) {
            btnText.innerHTML = '<span class="spinner"></span>Procesando...';
        }
        btn.style.pointerEvents = 'none';
    } else {
        btn.disabled = false;
        if (btnText) {
            btnText.innerHTML = 'Realizar Reserva';
        }
        btn.style.pointerEvents = 'auto';
    }
}

// ========================================
// FILTROS DE LIBROS (para reservar_libro.php)
// ========================================

/**
 * Inicializa filtros de búsqueda de libros
 */
function initLibroFilters() {
    const filtroCategoriaSelect = document.getElementById('filtro-categoria-libro');
    const buscarLibroInput = document.getElementById('buscar-libro');
    
    if (filtroCategoriaSelect) {
        filtroCategoriaSelect.addEventListener('change', filtrarLibros);
    }
    
    if (buscarLibroInput) {
        buscarLibroInput.addEventListener('input', filtrarLibros);
    }
}

/**
 * Filtra libros por categoría y término de búsqueda
 */
function filtrarLibros() {
    const filtroCategoriaSelect = document.getElementById('filtro-categoria-libro');
    const buscarLibroInput = document.getElementById('buscar-libro');
    const libroSelect = document.getElementById('libro');
    
    if (!libroSelect) return;
    
    const categoriaId = filtroCategoriaSelect ? filtroCategoriaSelect.value : '';
    const terminoBusqueda = buscarLibroInput ? buscarLibroInput.value.toLowerCase() : '';
    
    const opciones = libroSelect.options;
    
    for (let i = 1; i < opciones.length; i++) {
        const opcion = opciones[i];
        const categoriaCoincide = (categoriaId === '' || opcion.dataset.categoriaId === categoriaId);
        const textoCoincide = (terminoBusqueda.length < 3 || 
                              (opcion.dataset.searchtext && opcion.dataset.searchtext.includes(terminoBusqueda)));
        
        opcion.style.display = (categoriaCoincide && textoCoincide) ? 'block' : 'none';
    }
}

// ========================================
// INICIALIZACIÓN AUTOMÁTICA
// ========================================

/**
 * Inicializa todas las validaciones cuando el DOM está listo
 */
function initValidations() {
    initCedulaValidation();
    initPasswordValidation();
    initLibroFilters();
}

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initValidations);
} else {
    initValidations();
}

// Exportar funciones para uso global
if (typeof window !== 'undefined') {
    window.validarCedulaPanama = validarCedulaPanama;
    window.validarCedulaInput = validarCedulaInput;
    window.PasswordValidator = PasswordValidator;
    window.validateForm = validateForm;
    window.validateFields = validateFields;
    window.clearFormValidation = clearFormValidation;
    window.showAlert = showAlert;
    window.clearAlert = clearAlert;
    window.setButtonLoading = setButtonLoading;
    window.filtrarLibros = filtrarLibros;
}