// Script para manejar la selección de cubiertas
document.addEventListener('DOMContentLoaded', function() {
    console.log("Inicializando controlador de selectores...");
    
    // Función para ocultar opciones ya seleccionadas en los selectores de cubiertas
    function ocultarOpcionesSeleccionadas() {
        console.log("Ejecutando ocultarOpcionesSeleccionadas");
        
        // Para asignación inicial de cubiertas
        const selectoresAsignacion = document.querySelectorAll('.selector-cubiertas');
        if (selectoresAsignacion.length > 0) {
            console.log("Procesando", selectoresAsignacion.length, "selectores de asignación");
            
            // Recopilar todos los valores seleccionados
            const valoresSeleccionados = [];
            selectoresAsignacion.forEach(selector => {
                if (selector.value) {
                    valoresSeleccionados.push(selector.value);
                    console.log("Valor seleccionado:", selector.value);
                }
            });
            
            // Ocultar opciones ya seleccionadas en todos los selectores
            selectoresAsignacion.forEach(selector => {
                Array.from(selector.options).forEach(opcion => {
                    if (opcion.value && valoresSeleccionados.includes(opcion.value) && opcion.value !== selector.value) {
                        opcion.style.display = 'none';
                        opcion.disabled = true;
                        console.log("Ocultando opción", opcion.value, "en selector", selector.id);
                    } else if (opcion.value) {
                        opcion.style.display = '';
                        opcion.disabled = false;
                    }
                });
            });
        }
        
        // Para cambio de cubiertas
        const selectoresCambio = document.querySelectorAll('.selector-cambio-cubiertas');
        if (selectoresCambio.length > 0) {
            console.log("Procesando", selectoresCambio.length, "selectores de cambio");
            
            // Recopilar todos los valores seleccionados
            const valoresSeleccionados = [];
            selectoresCambio.forEach(selector => {
                if (selector.value) {
                    valoresSeleccionados.push(selector.value);
                    console.log("Valor seleccionado (cambio):", selector.value);
                }
            });
            
            // Ocultar opciones ya seleccionadas en todos los selectores
            selectoresCambio.forEach(selector => {
                Array.from(selector.options).forEach(opcion => {
                    if (opcion.value && valoresSeleccionados.includes(opcion.value) && opcion.value !== selector.value) {
                        opcion.style.display = 'none';
                        opcion.disabled = true;
                        console.log("Ocultando opción (cambio)", opcion.value, "en selector", selector.id);
                    } else if (opcion.value) {
                        opcion.style.display = '';
                        opcion.disabled = false;
                    }
                });
            });
        }
    }
    
    // Añadir event listeners a todos los selectores
    function configurarEventListeners() {
        // Para asignación inicial
        document.querySelectorAll('.selector-cubiertas').forEach(selector => {
            selector.addEventListener('change', function() {
                console.log("Selector cambiado:", this.id, "Valor:", this.value);
                ocultarOpcionesSeleccionadas();
            });
        });
        
        // Para cambio de cubiertas
        document.querySelectorAll('.selector-cambio-cubiertas').forEach(selector => {
            selector.addEventListener('change', function() {
                console.log("Selector de cambio cambiado:", this.id, "Valor:", this.value);
                ocultarOpcionesSeleccionadas();
            });
        });
    }
    
    // Inicializar todo
    function inicializar() {
        console.log("Inicializando...");
        ocultarOpcionesSeleccionadas();
        configurarEventListeners();
        console.log("Inicialización completa");
    }
    
    // Ejecutar inicialización después de un pequeño retraso para asegurar que todo el DOM está cargado
    setTimeout(inicializar, 500);
});