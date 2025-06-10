<?php
// wizard_steps/paso4_asignaciones.php

// Obtener todos los buses
$sql_buses = "SELECT id FROM coches ORDER BY id";
$result_buses = $conn->query($sql_buses);
$buses = [];
if ($result_buses->num_rows > 0) {
    while ($row = $result_buses->fetch_assoc()) {
        $buses[] = $row['id'];
    }
}

// Obtener todas las cubiertas disponibles
$sql_cubiertas = "SELECT id, nombre FROM cubiertas WHERE coche_id IS NULL AND estado = 'casanova' ORDER BY nombre";
$result_cubiertas = $conn->query($sql_cubiertas);
$cubiertas_disponibles = [];
if ($result_cubiertas->num_rows > 0) {
    while ($row = $result_cubiertas->fetch_assoc()) {
        $cubiertas_disponibles[] = $row;
    }
}

// Posiciones estándar de cubiertas
$posiciones = [
    "DELANTERA CHOFER",
    "DELANTERA PUERTA", 
    "TRASERA CHOFER AFUERA",
    "TRASERA PUERTA AFUERA",
    "TRASERA CHOFER ADENTRO",
    "TRASERA PUERTA ADENTRO"
];

// Procesar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paso_actual']) && $_POST['paso_actual'] == '4') {
    $asignaciones_realizadas = 0;
    $errores = [];
    
    if (isset($_POST['hacer_asignaciones']) && $_POST['hacer_asignaciones'] === 'si') {
        if (isset($_POST['asignaciones']) && is_array($_POST['asignaciones'])) {
            $conn->begin_transaction();
            
            try {
                foreach ($_POST['asignaciones'] as $bus_id => $posiciones_bus) {
                    $km_inicial = isset($_POST['km_inicial'][$bus_id]) ? (int)$_POST['km_inicial'][$bus_id] : 0;
                    
                    if ($km_inicial <= 0) {
                        $errores[] = "Bus $bus_id: Kilometraje inicial inválido";
                        continue;
                    }
                    
                    foreach ($posiciones_bus as $posicion => $cubierta_id) {
                        if (!empty($cubierta_id)) {
                            // Verificar que la cubierta está disponible
                            $sql_check = "SELECT id FROM cubiertas WHERE id = ? AND coche_id IS NULL AND estado = 'casanova'";
                            $stmt_check = $conn->prepare($sql_check);
                            $stmt_check->bind_param("i", $cubierta_id);
                            $stmt_check->execute();
                            $result_check = $stmt_check->get_result();
                            
                            if ($result_check->num_rows > 0) {
                                // Asignar cubierta
                                $sql_asignar = "UPDATE cubiertas SET coche_id = ?, posicion = ? WHERE id = ?";
                                $stmt_asignar = $conn->prepare($sql_asignar);
                                $stmt_asignar->bind_param("isi", $bus_id, $posicion, $cubierta_id);
                                
                                if ($stmt_asignar->execute()) {
                                    // Crear historial
                                    $sql_hist = "INSERT INTO historial_cubiertas 
                                                (cubierta_id, coche_id, fecha_colocacion, kilometraje_colocacion) 
                                                VALUES (?, ?, NOW(), ?)";
                                    $stmt_hist = $conn->prepare($sql_hist);
                                    $stmt_hist->bind_param("iii", $cubierta_id, $bus_id, $km_inicial);
                                    
                                    if ($stmt_hist->execute()) {
                                        $asignaciones_realizadas++;
                                    }
                                    $stmt_hist->close();
                                }
                                $stmt_asignar->close();
                            } else {
                                $errores[] = "Cubierta ID $cubierta_id no disponible para bus $bus_id";
                            }
                            $stmt_check->close();
                        }
                    }
                    
                    // Actualizar kilometraje_diario si existe la tabla
                    $sql_check_table = "SHOW TABLES LIKE 'kilometraje_diario'";
                    $result_check_table = $conn->query($sql_check_table);
                    
                    if ($result_check_table->num_rows > 0) {
                        $fecha_actual = date('Y-m-d');
                        
                        $sql_check_km = "SELECT id FROM kilometraje_diario WHERE coche_id = ? AND fecha = ?";
                        $stmt_check_km = $conn->prepare($sql_check_km);
                        $stmt_check_km->bind_param("is", $bus_id, $fecha_actual);
                        $stmt_check_km->execute();
                        $result_check_km = $stmt_check_km->get_result();
                        
                        if ($result_check_km->num_rows > 0) {
                            $sql_update_km = "UPDATE kilometraje_diario SET kilometraje = ? WHERE coche_id = ? AND fecha = ?";
                            $stmt_update_km = $conn->prepare($sql_update_km);
                            $stmt_update_km->bind_param("iis", $km_inicial, $bus_id, $fecha_actual);
                            $stmt_update_km->execute();
                            $stmt_update_km->close();
                        } else {
                            $sql_insert_km = "INSERT INTO kilometraje_diario (coche_id, fecha, kilometraje) VALUES (?, ?, ?)";
                            $stmt_insert_km = $conn->prepare($sql_insert_km);
                            $stmt_insert_km->bind_param("isi", $bus_id, $fecha_actual, $km_inicial);
                            $stmt_insert_km->execute();
                            $stmt_insert_km->close();
                        }
                        $stmt_check_km->close();
                    }
                }
                
                $conn->commit();
                
                if ($asignaciones_realizadas > 0) {
                    echo "<div class='mensaje-exito'>";
                    echo "<i class='fas fa-check-circle'></i> ";
                    echo "Se realizaron $asignaciones_realizadas asignaciones correctamente.";
                    if (!empty($errores)) {
                        echo " Algunas asignaciones tuvieron problemas.";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='mensaje-advertencia'>";
                    echo "<i class='fas fa-info-circle'></i> ";
                    echo "No se realizaron asignaciones.";
                    echo "</div>";
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                echo "<div class='mensaje-error'>";
                echo "<i class='fas fa-exclamation-circle'></i> ";
                echo "Error: " . $e->getMessage();
                echo "</div>";
            }
        }
    }
    
    // Actualizar cubiertas disponibles después de las asignaciones
    $result_cubiertas = $conn->query($sql_cubiertas);
    $cubiertas_disponibles = [];
    if ($result_cubiertas->num_rows > 0) {
        while ($row = $result_cubiertas->fetch_assoc()) {
            $cubiertas_disponibles[] = $row;
        }
    }
}
?>

<div class="step-content">
    <div class="step-header">
        <i class="fas fa-link"></i>
        <h2>Asignaciones Iniciales</h2>
        <p>Opcionalmente asigna cubiertas a buses desde el inicio</p>
    </div>

    <div class="assignment-overview">
        <div class="overview-cards">
            <div class="overview-card">
                <i class="fas fa-bus"></i>
                <div class="card-info">
                    <h3><?php echo count($buses); ?></h3>
                    <p>Buses Registrados</p>
                </div>
            </div>
            
            <div class="overview-card">
                <i class="fas fa-circle"></i>
                <div class="card-info">
                    <h3><?php echo count($cubiertas_disponibles); ?></h3>
                    <p>Cubiertas Disponibles</p>
                </div>
            </div>
            
            <div class="overview-card info">
                <i class="fas fa-info-circle"></i>
                <div class="card-info">
                    <h3>6</h3>
                    <p>Cubiertas por Bus</p>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" class="wizard-form" id="form-asignaciones">
        <input type="hidden" name="paso_actual" value="4">
        
        <div class="assignment-decision">
            <h3>¿Quieres asignar cubiertas ahora?</h3>
            <p>Puedes hacer las asignaciones ahora o dejarlas para después desde el sistema principal.</p>
            
            <div class="decision-cards">
                <label class="decision-card">
                    <input type="radio" name="hacer_asignaciones" value="no" checked>
                    <div class="card-content">
                        <i class="fas fa-clock"></i>
                        <h4>Asignar Después</h4>
                        <p>Terminar la configuración y hacer las asignaciones desde el sistema principal</p>
                        <div class="pros-cons">
                            <strong>Ventajas:</strong>
                            <ul>
                                <li>Configuración más rápida</li>
                                <li>Interface visual completa</li>
                                <li>Flexibilidad total</li>
                            </ul>
                        </div>
                    </div>
                </label>
                
                <label class="decision-card">
                    <input type="radio" name="hacer_asignaciones" value="si">
                    <div class="card-content">
                        <i class="fas fa-play"></i>
                        <h4>Asignar Ahora</h4>
                        <p>Configurar las asignaciones básicas durante la configuración inicial</p>
                        <div class="pros-cons">
                            <strong>Ventajas:</strong>
                            <ul>
                                <li>Sistema listo para usar</li>
                                <li>Configuración completa</li>
                                <li>Menos pasos después</li>
                            </ul>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Sección de asignaciones -->
        <div id="assignment-section" class="assignment-section" style="display: none;">
            <h3>Configuración de Asignaciones</h3>
            
            <?php if (count($cubiertas_disponibles) < 6): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Cubiertas insuficientes:</strong> Tienes solo <?php echo count($cubiertas_disponibles); ?> cubiertas disponibles. 
                    Se necesitan al menos 6 cubiertas por bus para hacer asignaciones completas.
                </div>
            <?php endif; ?>
            
            <div class="assignment-controls">
                <div class="control-group">
                    <label for="buses-para-asignar">Buses a configurar:</label>
                    <select id="buses-para-asignar" multiple size="8">
                        <?php foreach ($buses as $bus_id): ?>
                            <option value="<?php echo $bus_id; ?>">Bus #<?php echo $bus_id; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Selecciona los buses que quieres configurar ahora (mantén Ctrl/Cmd para seleccionar varios)</small>
                </div>
                
                <div class="control-group">
                    <button type="button" id="auto-assign-btn" class="btn btn-secondary">
                        <i class="fas fa-magic"></i>
                        Asignación Automática
                    </button>
                    <small>Asigna automáticamente las primeras cubiertas disponibles a los buses seleccionados</small>
                    <br><br>
                    <button type="button" id="clear-assignments-btn" class="btn btn-warning">
                        <i class="fas fa-eraser"></i>
                        Limpiar Asignaciones
                    </button>
                    <small>Limpiar todas las asignaciones actuales</small>
                </div>
            </div>

            <div id="manual-assignments" class="manual-assignments">
                <p class="no-selection">Selecciona al menos un bus para configurar asignaciones.</p>
            </div>
        </div>

        <div class="form-actions">
            <a href="?paso=3" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Paso Anterior
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i>
                Finalizar Configuración
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const decisionRadios = document.querySelectorAll('input[name="hacer_asignaciones"]');
    const assignmentSection = document.getElementById('assignment-section');
    const busesSelect = document.getElementById('buses-para-asignar');
    const autoAssignBtn = document.getElementById('auto-assign-btn');
    const clearAssignmentsBtn = document.getElementById('clear-assignments-btn');
    const manualAssignments = document.getElementById('manual-assignments');
    
    // Datos de PHP para JavaScript
    const buses = <?php echo json_encode($buses); ?>;
    const cubiertas = <?php echo json_encode($cubiertas_disponibles); ?>;
    const posiciones = <?php echo json_encode($posiciones); ?>;
    
    // Manejar decisión de asignaciones
    decisionRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'si') {
                assignmentSection.style.display = 'block';
                generateAssignmentInterface();
            } else {
                assignmentSection.style.display = 'none';
            }
        });
    });
    
    // Generar interface de asignaciones
    function generateAssignmentInterface() {
        const selectedBuses = Array.from(busesSelect.selectedOptions).map(option => option.value);
        
        if (selectedBuses.length === 0) {
            manualAssignments.innerHTML = '<p class="no-selection">Selecciona al menos un bus para configurar asignaciones.</p>';
            return;
        }
        
        let html = '<div class="assignments-grid">';
        
        selectedBuses.forEach(busId => {
            html += `<div class="bus-assignment-card" data-bus-id="${busId}">`;
            html += `<div class="bus-header">`;
            html += `<h4><i class="fas fa-bus"></i> Bus #${busId}</h4>`;
            html += `<div class="km-input-group">`;
            html += `<label for="km_inicial_${busId}">KM inicial:</label>`;
            html += `<input type="number" name="km_inicial[${busId}]" id="km_inicial_${busId}" min="0" value="0" class="km-input" required>`;
            html += `</div>`;
            html += `</div>`;
            
            html += `<div class="positions-grid">`;
            posiciones.forEach((posicion, index) => {
                const selectId = `asignacion_${busId}_${index}`;
                html += `<div class="position-assignment">`;
                html += `<label for="${selectId}">${posicion}:</label>`;
                html += `<select name="asignaciones[${busId}][${posicion}]" id="${selectId}" class="cubierta-select">`;
                html += `<option value="">-- Sin asignar --</option>`;
                cubiertas.forEach(cubierta => {
                    html += `<option value="${cubierta.id}" data-nombre="${cubierta.nombre}">${cubierta.nombre}</option>`;
                });
                html += `</select>`;
                html += `</div>`;
            });
            html += `</div>`;
            html += `</div>`;
        });
        
        html += '</div>';
        manualAssignments.innerHTML = html;
        
        // Agregar event listeners para evitar selecciones duplicadas
        addDuplicateValidation();
    }
    
    // Asignación automática
    autoAssignBtn.addEventListener('click', function() {
        const selectedBuses = Array.from(busesSelect.selectedOptions).map(option => option.value);
        
        if (selectedBuses.length === 0) {
            alert('Por favor, selecciona al menos un bus primero.');
            return;
        }
        
        const cubiertasNecesarias = selectedBuses.length * 6;
        if (cubiertas.length < cubiertasNecesarias) {
            if (!confirm(`No hay suficientes cubiertas para asignar 6 a cada bus (Disponibles: ${cubiertas.length}, Necesarias: ${cubiertasNecesarias}). ¿Continuar con asignación parcial?`)) {
                return;
            }
        }
        
        let cubiertaIndex = 0;
        const cubiertasUsadas = new Set();
        
        selectedBuses.forEach(busId => {
            posiciones.forEach((posicion, posIndex) => {
                const selectId = `asignacion_${busId}_${posIndex}`;
                const select = document.getElementById(selectId);
                
                if (select && cubiertaIndex < cubiertas.length) {
                    // Buscar la siguiente cubierta disponible
                    while (cubiertaIndex < cubiertas.length && cubiertasUsadas.has(cubiertas[cubiertaIndex].id)) {
                        cubiertaIndex++;
                    }
                    
                    if (cubiertaIndex < cubiertas.length) {
                        select.value = cubiertas[cubiertaIndex].id;
                        cubiertasUsadas.add(cubiertas[cubiertaIndex].id);
                        cubiertaIndex++;
                    }
                }
            });
        });
        
        alert('Asignación automática completada.');
        addDuplicateValidation(); // Revalidar después de la asignación
    });
    
    // Limpiar asignaciones
    clearAssignmentsBtn.addEventListener('click', function() {
        if (confirm('¿Estás seguro de limpiar todas las asignaciones?')) {
            const selects = document.querySelectorAll('.cubierta-select');
            selects.forEach(select => {
                select.value = '';
                select.style.borderColor = '';
                select.title = '';
            });
            alert('Asignaciones limpiadas.');
        }
    });
    
    // Cambios en selección de buses
    busesSelect.addEventListener('change', function() {
        if (document.querySelector('input[name="hacer_asignaciones"]:checked').value === 'si') {
            generateAssignmentInterface();
        }
    });
    
    // Validación de cubiertas duplicadas
    function addDuplicateValidation() {
        const selects = document.querySelectorAll('.cubierta-select');
        
        selects.forEach(select => {
            select.addEventListener('change', function() {
                validateDuplicates();
            });
        });
        
        validateDuplicates();
    }
    
    function validateDuplicates() {
        const selects = document.querySelectorAll('.cubierta-select');
        const selectedValues = [];
        const duplicates = new Set();
		
		// Encontrar duplicados
    function validateDuplicates() {
        const selects = document.querySelectorAll('.cubierta-select');
        const selectedValues = [];
        const duplicates = new Set();
        
        // Encontrar duplicados
        selects.forEach(select => {
            if (select.value) {
                if (selectedValues.includes(select.value)) {
                    duplicates.add(select.value);
                } else {
                    selectedValues.push(select.value);
                }
            }
        });
        
        // Marcar selects con duplicados
        selects.forEach(select => {
            if (select.value && duplicates.has(select.value)) {
                select.style.borderColor = '#e74c3c';
                select.title = 'Esta cubierta ya está asignada a otra posición';
            } else {
                select.style.borderColor = '';
                select.title = '';
            }
        });
    }
    
    // Validación del formulario
    document.getElementById('form-asignaciones').addEventListener('submit', function(e) {
        const hacerAsignaciones = document.querySelector('input[name="hacer_asignaciones"]:checked').value;
        
        if (hacerAsignaciones === 'si') {
            // Validar que no haya cubiertas duplicadas
            const selects = document.querySelectorAll('.cubierta-select');
            const selectedValues = [];
            const duplicates = [];
            
            selects.forEach(select => {
                if (select.value) {
                    if (selectedValues.includes(select.value)) {
                        duplicates.push(select.value);
                    } else {
                        selectedValues.push(select.value);
                    }
                }
            });
            
            if (duplicates.length > 0) {
                e.preventDefault();
                alert('Error: Hay cubiertas asignadas a múltiples posiciones. Por favor, corrige las asignaciones duplicadas.');
                return false;
            }
            
            // Validar kilometrajes
            const kmInputs = document.querySelectorAll('.km-input');
            let kmValidos = true;
            
            kmInputs.forEach(input => {
                const km = parseInt(input.value);
                if (isNaN(km) || km < 0) {
                    input.style.borderColor = '#e74c3c';
                    kmValidos = false;
                } else {
                    input.style.borderColor = '';
                }
            });
            
            if (!kmValidos) {
                e.preventDefault();
                alert('Error: Todos los kilometrajes deben ser números válidos mayores o iguales a 0.');
                return false;
            }
        }
        
        return true;
    });
});
</script>

<style>
.assignment-overview {
    margin: 25px 0;
}

.overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

.overview-card {
    background: #34495e;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 2px solid transparent;
}

.overview-card.info {
    border-color: #3498db;
    background: rgba(52, 152, 219, 0.1);
}

.overview-card i {
    font-size: 28px;
    color: #3498db;
}

.card-info h3 {
    margin: 0 0 5px 0;
    font-size: 24px;
    color: #ecf0f1;
}

.card-info p {
    margin: 0;
    color: #bdc3c7;
    font-size: 13px;
}

.decision-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.decision-card {
    background: #2c3e50;
    border: 2px solid #34495e;
    border-radius: 8px;
    padding: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.decision-card:hover {
    border-color: #3498db;
    transform: translateY(-2px);
}

.decision-card input[type="radio"] {
    position: absolute;
    top: 15px;
    right: 15px;
    transform: scale(1.2);
    accent-color: #3498db;
}

.decision-card input[type="radio"]:checked + .card-content {
    color: #3498db;
}

.card-content i {
    font-size: 32px;
    color: #3498db;
    margin-bottom: 15px;
    display: block;
}

.card-content h4 {
    margin: 10px 0 8px 0;
    color: #ecf0f1;
    font-size: 18px;
}

.card-content p {
    margin: 0 0 15px 0;
    color: #bdc3c7;
    font-size: 14px;
    line-height: 1.4;
}

.pros-cons {
    margin-top: 15px;
    font-size: 13px;
}

.pros-cons strong {
    color: #2ecc71;
    display: block;
    margin-bottom: 5px;
}

.pros-cons ul {
    margin: 0;
    padding-left: 15px;
    color: #bdc3c7;
}

.pros-cons li {
    margin: 3px 0;
}

.assignment-section {
    background: #34495e;
    padding: 25px;
    border-radius: 8px;
    margin: 25px 0;
}

.assignment-controls {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
    align-items: start;
}

.control-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #ecf0f1;
}

.control-group select {
    width: 100%;
    padding: 8px;
    background: #2c3e50;
    border: 1px solid #4a6741;
    border-radius: 4px;
    color: #ecf0f1;
}

.control-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.control-group small {
    display: block;
    margin-top: 5px;
    color: #bdc3c7;
    font-size: 12px;
}

.control-group .btn {
    width: 100%;
    margin-bottom: 10px;
}

.assignments-grid {
    display: grid;
    gap: 20px;
}

.bus-assignment-card {
    background: #2c3e50;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #4a6741;
}

.bus-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #4a6741;
}

.bus-header h4 {
    margin: 0;
    color: #3498db;
    display: flex;
    align-items: center;
    gap: 8px;
}

.km-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.km-input-group label {
    font-size: 13px;
    color: #bdc3c7;
    margin: 0;
}

.km-input {
    width: 80px;
    padding: 6px;
    background: #34495e;
    border: 1px solid #4a6741;
    border-radius: 4px;
    color: #ecf0f1;
    font-size: 13px;
}

.km-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.positions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.position-assignment label {
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
    color: #ecf0f1;
    font-weight: 500;
}

.cubierta-select {
    width: 100%;
    padding: 8px;
    background: #34495e;
    border: 1px solid #4a6741;
    border-radius: 4px;
    color: #ecf0f1;
    font-size: 13px;
}

.cubierta-select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.no-selection {
    text-align: center;
    color: #bdc3c7;
    font-style: italic;
    padding: 40px;
}

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-warning {
    background: rgba(241, 196, 15, 0.2);
    border: 1px solid #f1c40f;
    color: #f1c40f;
}

.mensaje-exito, .mensaje-error, .mensaje-advertencia {
    padding: 15px 20px;
    border-radius: 6px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mensaje-exito {
    background: rgba(46, 204, 113, 0.2);
    border: 1px solid #2ecc71;
    color: #2ecc71;
}

.mensaje-error {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

.mensaje-advertencia {
    background: rgba(241, 196, 15, 0.2);
    border: 1px solid #f1c40f;
    color: #f1c40f;
}

@media (max-width: 768px) {
    .decision-cards {
        grid-template-columns: 1fr;
    }
    
    .assignment-controls {
        grid-template-columns: 1fr;
    }
    
    .bus-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .positions-grid {
        grid-template-columns: 1fr;
    }
}
</style>