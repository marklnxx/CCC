/**
 * Script para manejar el registro de kilometraje
 * Este archivo contiene toda la lógica de interacción de usuario para la página de registro de kilometraje
 */

document.addEventListener('DOMContentLoaded', function() {
    // Variables para el modal individual
    const modalIndividual = document.getElementById('modal-individual');
    const btnModalIndividual = document.getElementById('btn-modal-individual');
    const closeModalIndividual = modalIndividual.querySelector('.close-modal');
    const btnSiguiente = document.getElementById('btn-siguiente');
    const btnOmitir = document.getElementById('btn-omitir');
    const kmInputContainer = document.getElementById('km-input-container');
    const progressBar = document.getElementById('progress-bar');
    const resumenContainer = document.getElementById('resumen-container');
    const resumenItems = document.getElementById('resumen-items');
    const alertasContainer = document.getElementById('alertas-container');
    const alertasItems = document.getElementById('alertas-items');
    const btnGuardarTodo = document.getElementById('btn-guardar-todo');
    const formKmIndividual = document.getElementById('form-km-individual');
    
    // Variables para el modal todos juntos
    const modalTodos = document.getElementById('modal-todos');
    const btnModalTodos = document.getElementById('btn-modal-todos');
    const closeModalTodos = modalTodos.querySelector('.close-modal');
    const formKmTodos = document.getElementById('form-km-todos');
    const btnGuardarTodos = document.getElementById('btn-guardar-todos');
    
    // Obtener todos los coches
    const coches = Array.from(document.querySelectorAll('.coche-card')).map(card => {
        return {
            id: card.dataset.cocheId,
            ultimoKm: parseInt(card.dataset.ultimoKm, 10)
        };
    });
    
    // Variables para el proceso de ingreso individual
    let cocheActual = 0;
    let totalCoches = coches.length;
    let kilometrajes = {};
    let cochesOmitidos = [];
    
    // Función para actualizar la barra de progreso
    function actualizarProgreso() {
        const progreso = (cocheActual / totalCoches) * 100;
        progressBar.style.width = progreso + '%';
    }
    
    // Función para mostrar el coche actual
    function mostrarCocheActual() {
        // Limpiar el contenedor
        kmInputContainer.innerHTML = '';
        
        if (cocheActual >= totalCoches) {
            // Mostrar resumen final
            mostrarResumen();
            return;
        }
        
        const coche = coches[cocheActual];
        
        // Crear elementos para el coche actual
        const label = document.createElement('div');
        label.className = 'km-label';
        label.textContent = `BUS ${coche.id}:`;
        
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'km-input';
        input.name = `kilometraje[${coche.id}]`;
        input.id = `km-${coche.id}`;
        input.min = coche.ultimoKm;
        input.placeholder = `${coche.ultimoKm.toLocaleString()} km`;
        input.required = true;
        
        // Si hay kilometraje guardado para este coche, rellenarlo
        if (kilometrajes[coche.id]) {
            input.value = kilometrajes[coche.id];
        }
        
        // Añadir elementos al contenedor
        kmInputContainer.appendChild(label);
        kmInputContainer.appendChild(input);
        
        // Añadir información del último kilometraje
        const ultimoKmInfo = document.createElement('div');
        ultimoKmInfo.className = 'ultimo-km-valor';
        ultimoKmInfo.textContent = `${coche.ultimoKm.toLocaleString()} km`;
        kmInputContainer.appendChild(ultimoKmInfo);
        
        // Actualizar barra de progreso
        actualizarProgreso();
        
        // Enfocar el input
        input.focus();
    }
    
    // Función para avanzar al siguiente coche
    function siguienteCoche() {
        const cocheId = coches[cocheActual].id;
        const input = document.getElementById(`km-${cocheId}`);
        
        if (input && input.value) {
            const kilometraje = parseInt(input.value, 10);
            const ultimoKm = coches[cocheActual].ultimoKm;
            
            // Validar que el kilometraje sea mayor que el último
            if (kilometraje < ultimoKm) {
                alert(`El kilometraje debe ser mayor o igual a ${ultimoKm.toLocaleString()} km`);
                input.focus();
                return;
            }
            
            // Guardar el kilometraje
            kilometrajes[cocheId] = kilometraje;
        }
        
        // Avanzar al siguiente coche
        cocheActual++;
        mostrarCocheActual();
    }
    
    // Función para omitir el coche actual
    function omitirCoche() {
        const cocheId = coches[cocheActual].id;
        cochesOmitidos.push(cocheId);
        
        // Avanzar al siguiente coche
        cocheActual++;
        mostrarCocheActual();
    }
    
    // Función para mostrar el resumen de kilometrajes
    function mostrarResumen() {
        // Ocultar elementos de ingreso y mostrar resumen
        kmInputContainer.style.display = 'none';
        document.getElementById('btn-siguiente').style.display = 'none';
        document.getElementById('btn-omitir').style.display = 'none';
        resumenContainer.style.display = 'block';
        
        // Limpiar el resumen
        resumenItems.innerHTML = '';
        
        // Limpiar formulario
        formKmIndividual.innerHTML = '';
        
        // Añadir cada coche al resumen
        for (let i = 0; i < coches.length; i++) {
            const cocheId = coches[i].id;
            const resumenItem = document.createElement('div');
            resumenItem.className = 'resumen-item';
            
            if (cochesOmitidos.includes(cocheId)) {
                resumenItem.innerHTML = `
                    <span>BUS ${cocheId}:</span>
                    <span>OMITIDO</span>
                `;
            } else if (kilometrajes[cocheId]) {
                resumenItem.innerHTML = `
                    <span>BUS ${cocheId}:</span>
                    <span>${parseInt(kilometrajes[cocheId]).toLocaleString()} km</span>
                `;
                
                // Crear input oculto para enviar el formulario
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `kilometraje[${cocheId}]`;
                input.value = kilometrajes[cocheId];
                formKmIndividual.appendChild(input);
            }
            
            resumenItems.appendChild(resumenItem);
        }
        
        // Crear inputs ocultos para los coches omitidos
        cochesOmitidos.forEach(cocheId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'omitidos[]';
            input.value = cocheId;
            formKmIndividual.appendChild(input);
        });
        
        // Añadir campo oculto para indicar que se debe guardar
        const guardarInput = document.createElement('input');
        guardarInput.type = 'hidden';
        guardarInput.name = 'guardar_kilometrajes';
        guardarInput.value = '1';
        formKmIndividual.appendChild(guardarInput);
    }
    
    // Event listeners para el modal individual
    btnModalIndividual.addEventListener('click', function() {
        modalIndividual.style.display = 'block';
        cocheActual = 0;
        kilometrajes = {};
        cochesOmitidos = [];
        kmInputContainer.style.display = 'flex';
        resumenContainer.style.display = 'none';
        document.getElementById('btn-siguiente').style.display = 'block';
        document.getElementById('btn-omitir').style.display = 'block';
        
        // Cargar progreso guardado si existe
        const progresoGuardado = JSON.parse(localStorage.getItem('progresoKilometraje') || '{}');
        if (Object.keys(progresoGuardado).length > 0) {
            if (confirm('Se encontraron datos de kilometraje que no fueron guardados. ¿Desea restaurarlos?')) {
                kilometrajes = progresoGuardado;
            } else {
                localStorage.removeItem('progresoKilometraje');
            }
        }
        
        mostrarCocheActual();
    });
    
    closeModalIndividual.addEventListener('click', function() {
        if (Object.keys(kilometrajes).length > 0) {
            if (confirm('¿Está seguro de salir? Los datos ingresados se guardarán temporalmente.')) {
                // Guardar progreso en localStorage
                localStorage.setItem('progresoKilometraje', JSON.stringify(kilometrajes));
                modalIndividual.style.display = 'none';
            }
        } else {
            modalIndividual.style.display = 'none';
        }
    });
    
    btnSiguiente.addEventListener('click', siguienteCoche);
    btnOmitir.addEventListener('click', omitirCoche);
    
    // Event listener para enviar formulario al presionar Enter
    kmInputContainer.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            siguienteCoche();
        }
    });
    
    // Event listeners para el modal todos juntos
    btnModalTodos.addEventListener('click', function() {
        modalTodos.style.display = 'block';
        
        // Llenar con valores guardados si existen
        const progresoGuardado = JSON.parse(localStorage.getItem('progresoKilometraje') || '{}');
        if (Object.keys(progresoGuardado).length > 0) {
            if (confirm('Se encontraron datos de kilometraje que no fueron guardados. ¿Desea restaurarlos?')) {
                const inputs = formKmTodos.querySelectorAll('.km-input');
                inputs.forEach(input => {
                    const cocheId = input.name.match(/\[(\d+)\]/)[1];
                    if (progresoGuardado[cocheId]) {
                        input.value = progresoGuardado[cocheId];
                    }
                });
            } else {
                localStorage.removeItem('progresoKilometraje');
            }
        }
    });
    
    closeModalTodos.addEventListener('click', function() {
        // Guardar progreso en localStorage
        const inputs = formKmTodos.querySelectorAll('.km-input');
        const progreso = {};
        
        inputs.forEach(input => {
            if (input.value) {
                const cocheId = input.name.match(/\[(\d+)\]/)[1];
                progreso[cocheId] = input.value;
            }
        });
        
        if (Object.keys(progreso).length > 0) {
            localStorage.setItem('progresoKilometraje', JSON.stringify(progreso));
        }
        
        modalTodos.style.display = 'none';
    });
    
    // Event listener para omitir/incluir coches desde el grid
    document.querySelectorAll('.toggle-omitir').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const cocheCard = this.closest('.coche-card-container');
            
            if (cocheCard.querySelector('.omitido')) {
                // Si ya está omitido, quitar la marca
                cocheCard.querySelector('.omitido').remove();
            } else {
                // Si no está omitido, añadir la marca
                const omitidoDiv = document.createElement('div');
                omitidoDiv.className = 'omitido';
                omitidoDiv.innerHTML = '<span class="omitido-text">OMITIDO</span>';
                cocheCard.appendChild(omitidoDiv);
            }
        });
    });
    
    // Event listener para cerrar el modal al hacer clic fuera
    window.addEventListener('click', function(e) {
        if (e.target === modalIndividual) {
            closeModalIndividual.click();
        }
        if (e.target === modalTodos) {
            closeModalTodos.click();
        }
    });
    
    // Event listener para enviar el formulario desde el resumen
    btnGuardarTodo.addEventListener('click', function() {
        if (confirm('¿Está seguro de guardar los kilometrajes? Esta acción no se puede deshacer.')) {
            formKmIndividual.submit();
        }
    });
    
    // Event listener para confirmar antes de guardar todos los kilometrajes
    btnGuardarTodos.addEventListener('click', function(e) {
        if (!confirm('¿Está seguro de guardar todos los kilometrajes? Esta acción no se puede deshacer.')) {
            e.preventDefault();
        }
    });
    
    // Actualizar localStorage cuando se ingresa un valor
    document.body.addEventListener('input', function(e) {
        if (e.target.classList.contains('km-input')) {
            // Obtener cocheId del nombre del input (formato: kilometraje[cocheId])
            let cocheId;
            const match = e.target.name.match(/\[(\d+)\]/);
            
            if (match) {
                cocheId = match[1];
                const kilometraje = e.target.value;
                
                // Guardar en localStorage
                const progresoActual = JSON.parse(localStorage.getItem('progresoKilometraje') || '{}');
                if (kilometraje) {
                    progresoActual[cocheId] = kilometraje;
                } else {
                    delete progresoActual[cocheId];
                }
                
                localStorage.setItem('progresoKilometraje', JSON.stringify(progresoActual));
            }
        }
    });
    
    // Limpiar localStorage después de guardar correctamente
    if (document.querySelector('.mensaje-exito')) {
        localStorage.removeItem('progresoKilometraje');
    }
    
    // Mejorar la validación de formularios al enviar
    [formKmIndividual, formKmTodos].forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('.km-input:not([disabled])');
            let formValido = true;
            
            inputs.forEach(input => {
                const isOmitido = input.closest('.km-input-container').querySelector('.omitir-check')?.checked;
                
                if (!isOmitido && input.value) {
                    // Extraer el cocheId del nombre del input
                    const cocheIdMatch = input.name.match(/\[(\d+)\]/);
                    if (cocheIdMatch) {
                        const cocheId = cocheIdMatch[1];
                        
                        // Buscar el último kilometraje para este coche
                        const coche = coches.find(c => c.id == cocheId);
                        
                        if (coche && parseInt(input.value, 10) < coche.ultimoKm) {
                            alert(`El kilometraje para el Bus ${cocheId} debe ser mayor o igual a ${coche.ultimoKm.toLocaleString()} km`);
                            input.focus();
                            formValido = false;
                            e.preventDefault();
                            return false;
                        }
                    }
                }
            });
            
            if (!formValido) {
                e.preventDefault();
                return false;
            }
            
            // Si todo está bien, limpiar localStorage
            if (formValido) {
                localStorage.removeItem('progresoKilometraje');
            }
        });
    });
    
    // Mostrar animaciones al cargar
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        setTimeout(() => {
            element.style.opacity = '1';
        }, 100 * index);
    });
    
    const slideElements = document.querySelectorAll('.slide-in');
    slideElements.forEach((element, index) => {
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateX(0)';
        }, 100 * index);
    });
});