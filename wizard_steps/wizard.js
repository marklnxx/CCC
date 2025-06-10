// wizard.js - Funcionalidades comunes del wizard

document.addEventListener('DOMContentLoaded', function() {
    // Animaciones de entrada
    initializeAnimations();
    
    // Validaciones generales
    initializeValidations();
    
    // Navegación del wizard
    initializeNavigation();
    
    // Auto-save de progreso
    initializeAutoSave();
});

// Animaciones de entrada
function initializeAnimations() {
    // Fade in para elementos principales
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, 100 * index);
    });
    
    // Animación de la barra de progreso
    const progressBar = document.querySelector('.progress');
    if (progressBar) {
        const targetWidth = progressBar.style.width;
        progressBar.style.width = '0%';
        
        setTimeout(() => {
            progressBar.style.width = targetWidth;
        }, 500);
    }
}

// Validaciones generales
function initializeValidations() {
    // Validación de campos numéricos
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', function() {
            const value = parseInt(this.value);
            const min = parseInt(this.getAttribute('min')) || 0;
            const max = parseInt(this.getAttribute('max')) || Infinity;
            
            if (value < min || value > max) {
                this.style.borderColor = '#e74c3c';
                this.setCustomValidity(`El valor debe estar entre ${min} y ${max}`);
            } else {
                this.style.borderColor = '';
                this.setCustomValidity('');
            }
        });
    });
    
    // Validación de campos de texto requeridos
    const textInputs = document.querySelectorAll('input[type="text"][required]');
    textInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '';
            }
        });
    });
    
    // Prevenir envío de formulario con Enter en campos de texto
    const formInputs = document.querySelectorAll('.wizard-form input[type="text"], .wizard-form input[type="number"]');
    formInputs.forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    });
}

// Navegación del wizard
function initializeNavigation() {
    // Confirmación antes de retroceder si hay datos
    const backButtons = document.querySelectorAll('a[href*="paso="]');
    backButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (hasUnsavedData()) {
                if (!confirm('¿Estás seguro de retroceder? Los datos no guardados se perderán.')) {
                    e.preventDefault();
                }
            }
        });
    });
    
    // Prevenir salida accidental del wizard
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedData()) {
            e.preventDefault();
            e.returnValue = '¿Estás seguro de salir? Los datos no guardados se perderán.';
        }
    });
}

// Auto-save de progreso
function initializeAutoSave() {
    const inputs = document.querySelectorAll('.wizard-form input, .wizard-form select, .wizard-form textarea');
    
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            saveProgressToStorage();
        });
        
        // Para inputs de texto, guardar con delay
        if (input.type === 'text' || input.tagName === 'TEXTAREA') {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    saveProgressToStorage();
                }, 1000);
            });
        }
    });
    
    // Restaurar progreso al cargar
    restoreProgressFromStorage();
}

// Verificar si hay datos no guardados
function hasUnsavedData() {
    const inputs = document.querySelectorAll('.wizard-form input:not([type="hidden"]), .wizard-form select, .wizard-form textarea');
    
    for (let input of inputs) {
        if (input.type === 'radio' || input.type === 'checkbox') {
            if (input.checked) return true;
        } else if (input.value && input.value.trim() !== '') {
            return true;
        }
    }
    
    return false;
}

// Guardar progreso en localStorage
function saveProgressToStorage() {
    const currentStep = getCurrentStep();
    const formData = {};
    
    const inputs = document.querySelectorAll('.wizard-form input, .wizard-form select, .wizard-form textarea');
    inputs.forEach(input => {
        if (input.type === 'radio' || input.type === 'checkbox') {
            if (input.checked) {
                formData[input.name] = input.value;
            }
        } else if (input.value) {
            formData[input.name] = input.value;
        }
    });
    
    if (Object.keys(formData).length > 0) {
        localStorage.setItem(`wizard_step_${currentStep}`, JSON.stringify(formData));
        console.log(`Progreso guardado para paso ${currentStep}`);
    }
}

// Restaurar progreso desde localStorage
function restoreProgressFromStorage() {
    const currentStep = getCurrentStep();
    const savedData = localStorage.getItem(`wizard_step_${currentStep}`);
    
    if (savedData) {
        try {
            const formData = JSON.parse(savedData);
            
            Object.keys(formData).forEach(name => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input) {
                    if (input.type === 'radio' || input.type === 'checkbox') {
                        if (input.value === formData[name]) {
                            input.checked = true;
                            // Trigger change event
                            input.dispatchEvent(new Event('change'));
                        }
                    } else {
                        input.value = formData[name];
                        // Trigger change event
                        input.dispatchEvent(new Event('change'));
                    }
                }
            });
            
            console.log(`Progreso restaurado para paso ${currentStep}`);
        } catch (e) {
            console.error('Error restaurando progreso:', e);
        }
    }
}

// Obtener paso actual
function getCurrentStep() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('paso') || '1';
}

// Limpiar localStorage cuando se completa el wizard
function clearWizardProgress() {
    for (let i = 1; i <= 5; i++) {
        localStorage.removeItem(`wizard_step_${i}`);
    }
    console.log('Progreso del wizard limpiado');
}

// Utilidades adicionales
const WizardUtils = {
    // Mostrar loading spinner
    showLoading: function(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        button.disabled = true;
        
        return function() {
            button.innerHTML = originalText;
            button.disabled = false;
        };
    },
    
    // Validar formato de nombres de cubiertas
    validateCubiertaName: function(name) {
        return /^[A-Za-z0-9\-_]+$/.test(name.trim());
    },
    
    // Formatear números con separadores de miles
    formatNumber: function(num) {
        return parseInt(num).toLocaleString();
    },
    
    // Generar ID único para elementos dinámicos
    generateId: function(prefix = 'wizard') {
        return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    },
    
    // Scroll suave al elemento
    scrollToElement: function(element) {
        if (element) {
            element.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }
    },
    
    // Validar rango de números
    validateRange: function(start, end, maxRange = 1000) {
        if (!start || !end) return { valid: false, message: 'Ambos valores son requeridos' };
        if (start > end) return { valid: false, message: 'El valor inicial debe ser menor o igual al final' };
        if (end - start + 1 > maxRange) return { valid: false, message: `El rango no puede exceder ${maxRange} elementos` };
        return { valid: true };
    }
};

// Exportar utilidades para uso global
window.WizardUtils = WizardUtils;

// Limpiar progreso al completar wizard
window.addEventListener('beforeunload', function() {
    // Si estamos en la página final de éxito, limpiar el progreso
    if (window.location.search.includes('paso=6') || window.location.pathname.includes('index.php')) {
        clearWizardProgress();
    }
});