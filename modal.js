// modal.js - Corregido para asegurar que las funciones sean globales
// Declarar las funciones en el ámbito global
window.mostrarModalRespaldo = function(event) {
    if (event) {
        event.preventDefault();
    }
    
    // Verificar si ya existe el modal, si no, crearlo
    let modalOverlay = document.getElementById('modal-respaldo-overlay');
    if (!modalOverlay) {
        crearModalRespaldo();
        modalOverlay = document.getElementById('modal-respaldo-overlay');
    }
    
    // Mostrar el modal con una animación suave
    setTimeout(function() {
        modalOverlay.classList.add('active');
    }, 10);
};

window.cerrarModalRespaldo = function() {
    const modalOverlay = document.getElementById('modal-respaldo-overlay');
    if (modalOverlay) {
        modalOverlay.classList.remove('active');
        
        // Quitar el modal del DOM después de la animación
        setTimeout(function() {
            if (modalOverlay.parentNode) {
                modalOverlay.parentNode.removeChild(modalOverlay);
            }
        }, 300);
    }
};

function crearModalRespaldo() {
    // Crear el contenedor principal del modal
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'modal-respaldo-overlay';
    modalOverlay.id = 'modal-respaldo-overlay';
    
    // Crear el contenido del modal
    const modalHTML = `
        <div class="modal-respaldo">
            <div class="modal-header">
                <h3><i class="fas fa-database"></i> Opciones de Respaldo</h3>
                <button class="close-modal" id="close-modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="opciones-respaldo">
                    <a href="tools/config_backup_simple.php" class="opcion-respaldo">
                        <i class="fas fa-cog"></i>
                        <div class="opcion-content">
                            <h4>Configurar Respaldo</h4>
                            <p>Establezca programación y parámetros para respaldos automáticos</p>
                        </div>
                    </a>
                    <a href="tools/respaldo_simple_fixed.php" class="opcion-respaldo">
                        <i class="fas fa-download"></i>
                        <div class="opcion-content">
                            <h4>Ejecutar Respaldo Manual</h4>
                            <p>Generar un respaldo completo del sistema ahora</p>
                        </div>			
					</a>
                    <a href="tools/restaura_directo.php" class="opcion-respaldo">
                        <i class="fas fa-download"></i>
                        <div class="opcion-content">
                            <h4>Restaurar Respaldo</h4>
                            <p>Restaurara un respaldo completo del sistema ahora</p>
                        </div>
                    </a>
					
					
                </div>
            </div>
        </div>
    `;
    
    // Insertar el HTML en el overlay
    modalOverlay.innerHTML = modalHTML;
    
    // Añadir el modal al body
    document.body.appendChild(modalOverlay);
    
    // Configurar eventos
    const closeModalBtn = document.getElementById('close-modal');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', window.cerrarModalRespaldo);
    }
    
    // Cerrar modal al hacer clic fuera
    modalOverlay.addEventListener('click', function(event) {
        if (event.target === modalOverlay) {
            window.cerrarModalRespaldo();
        }
    });
    
    // Cerrar con ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            window.cerrarModalRespaldo();
        }
    });
}

// Configurar los event listeners cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM cargado - Configurando modal de respaldo");
    
    // MÉTODO 1: Buscar directamente por ID (más eficiente)
    let botonRespaldo = document.getElementById('boton-respaldo');
    
    // MÉTODO 2: Si no se encuentra por ID, buscar por clase y texto (compatibilidad con versiones anteriores)
    if (!botonRespaldo) {
        // Buscar en botones con clase 'boton' (versión anterior)
        const botonesRespaldoOld = document.querySelectorAll('button.boton');
        botonesRespaldoOld.forEach(function(boton) {
            if (boton.textContent.includes('RESPALDO')) {
                botonRespaldo = boton;
            }
        });
        
        // Si aún no se encuentra, buscar en botones con clase 'navbar-button' (versión nueva)
        if (!botonRespaldo) {
            const botonesRespaldoNew = document.querySelectorAll('button.navbar-button');
            botonesRespaldoNew.forEach(function(boton) {
                if (boton.textContent.includes('RESPALDO')) {
                    botonRespaldo = boton;
                }
            });
        }
    }
    
    if (botonRespaldo) {
        console.log("Botón de respaldo encontrado:", botonRespaldo);
        
        // Quitar onclick si existe
        botonRespaldo.removeAttribute('onclick');
        
        // Añadir event listener
        botonRespaldo.addEventListener('click', function(event) {
            event.preventDefault();
            console.log("Botón de respaldo clickeado");
            window.mostrarModalRespaldo(event);
        });
    } else {
        console.log("Botón de respaldo NO encontrado");
        console.log("Botones disponibles:", document.querySelectorAll('button'));
    }
});