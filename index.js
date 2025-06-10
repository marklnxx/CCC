// JavaScript para la página de gestión de cubiertas

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM cargado - Inicializando funcionalidad principal");
    
    // Añadir clase de animación a elementos
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
    
// Validar los formularios de cambio de cubierta
const forms = document.querySelectorAll('.formulario-cambio');
forms.forEach(form => {
    form.addEventListener('submit', function(e) {
        const kilometrajeInput = this.querySelector('input[name="kilometraje"]');
        const kilometraje = parseInt(kilometrajeInput.value);
        const ultimoKm = parseInt(kilometrajeInput.dataset.ultimoKm);
        
        // Validar que el kilometraje sea un número positivo
        if (isNaN(kilometraje) || kilometraje <= 0) {
            e.preventDefault();
            alert('El kilometraje debe ser un número positivo mayor que cero.');
            kilometrajeInput.focus();
            return false;
        }
        
        // Validar que el kilometraje sea mayor al último registrado
        if (kilometraje < ultimoKm) {
            e.preventDefault();
            alert(`El nuevo kilometraje (${kilometraje.toLocaleString()} km) es menor que el último registrado (${ultimoKm.toLocaleString()} km).`);
            kilometrajeInput.focus();
            return false;
        }
        
        // Confirmar antes de enviar
        if (!confirm('¿Estás seguro de que deseas cambiar esta cubierta?')) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
    
    // IMPORTANTE: Botón para guardar todos los cambios
    // Con mejora para detectar el botón en diferentes layouts
    const btnGuardarTodos = document.getElementById('guardar-todos-cambios');
    if (btnGuardarTodos) {
        console.log("Botón 'Guardar Todos los Cambios' encontrado y configurado");
        
        btnGuardarTodos.addEventListener('click', function() {
            console.log("Botón 'Guardar Todos los Cambios' clickeado");
            
            const formularios = document.querySelectorAll('.formulario-cambio');
            let cambiosPendientes = false;
            
            // Verificar si hay formularios con cambios pendientes
            formularios.forEach(form => {
                const select = form.querySelector('select[name="nueva_cubierta_id"]');
                if (select && select.value) {
                    cambiosPendientes = true;
                }
            });
            
            if (!cambiosPendientes) {
                alert('No hay cambios pendientes para guardar.');
                return;
            }
            
            if (confirm('¿Estás seguro de que deseas guardar todos los cambios de cubiertas?')) {
                // Crear un formulario de envío múltiple dinámico
                const formData = {};
                
                formularios.forEach(form => {
                    const select = form.querySelector('select[name="nueva_cubierta_id"]');
                    if (select && select.value) {
                        const cocheId = form.querySelector('input[name="coche_id"]').value;
                        const cubiertaViejaId = form.querySelector('input[name="cubierta_vieja_id"]').value;
                        const nuevaCubiertaId = select.value;
                        const kilometraje = form.querySelector('input[name="kilometraje"]').value;
                        const posicionActual = form.querySelector('input[name="posicion_actual"]')?.value;
                        
                        formData[cubiertaViejaId] = {
                            coche_id: cocheId,
                            cubierta_vieja_id: cubiertaViejaId,
                            nueva_cubierta_id: nuevaCubiertaId,
                            kilometraje: kilometraje,
                            posicion: posicionActual
                        };
                    }
                });
                
                console.log("Datos a enviar:", formData);
                
                // Enviar datos mediante fetch API
                fetch('cambiar_cubiertas_multiple.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exito) {
                        alert(data.mensaje);
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.mensaje);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ha ocurrido un error al procesar la solicitud: ' + error);
                });
            }
        });
    } else {
        console.warn("ADVERTENCIA: No se encontró el botón 'Guardar Todos los Cambios'");
    }
    
    // Tooltips para notificaciones
    const notificacion = document.getElementById('notificacion-alerta');
    if (notificacion) {
        notificacion.addEventListener('mouseenter', function() {
            const tooltip = this.querySelector('.tooltip-alerta');
            if (tooltip) {
                tooltip.style.display = 'block';
                
                setTimeout(() => {
                    tooltip.style.opacity = '1';
                    tooltip.style.transform = 'translateY(0)';
                }, 10);
            }
        });
        
        notificacion.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip-alerta');
            if (tooltip) {
                tooltip.style.opacity = '0';
                tooltip.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    tooltip.style.display = 'none';
                }, 300);
            }
        });
    }
});