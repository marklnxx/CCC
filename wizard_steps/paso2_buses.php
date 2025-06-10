<?php
// wizard_steps/paso2_buses.php

// Obtener buses ya existentes
$sql_buses_existentes = "SELECT id FROM coches ORDER BY id";
$result_buses = $conn->query($sql_buses_existentes);
$buses_existentes = [];
if ($result_buses->num_rows > 0) {
    while ($row = $result_buses->fetch_assoc()) {
        $buses_existentes[] = $row['id'];
    }
}

// Procesar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paso_actual']) && $_POST['paso_actual'] == '2') {
    $buses_creados = 0;
    $errores = [];
    
    if (isset($_POST['metodo_buses'])) {
        $conn->begin_transaction();
        
        try {
            if ($_POST['metodo_buses'] === 'rango' && isset($_POST['bus_inicio']) && isset($_POST['bus_fin'])) {
                // Crear buses en rango
                $inicio = (int)$_POST['bus_inicio'];
                $fin = (int)$_POST['bus_fin'];
                
                if ($inicio > $fin) {
                    throw new Exception("El bus inicial debe ser menor o igual al final");
                }
                
                if (($fin - $inicio + 1) > 500) {
                    throw new Exception("No se pueden crear más de 500 buses a la vez");
                }
                
                for ($i = $inicio; $i <= $fin; $i++) {
                    $sql = "INSERT IGNORE INTO coches (id) VALUES (?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $i);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $buses_creados++;
                    }
                    $stmt->close();
                }
                
            } elseif ($_POST['metodo_buses'] === 'lista' && isset($_POST['lista_buses'])) {
                // Crear buses desde lista
                $lista_text = trim($_POST['lista_buses']);
                if (empty($lista_text)) {
                    throw new Exception("La lista de buses no puede estar vacía");
                }
                
                $buses_lista = array_filter(array_map('trim', explode("\n", $lista_text)));
                
                if (count($buses_lista) > 500) {
                    throw new Exception("No se pueden crear más de 500 buses a la vez");
                }
                
                foreach ($buses_lista as $bus_id) {
                    if (is_numeric($bus_id) && $bus_id > 0) {
                        $sql = "INSERT IGNORE INTO coches (id) VALUES (?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $bus_id);
                        
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            $buses_creados++;
                        }
                        $stmt->close();
                    } else {
                        $errores[] = "ID de bus inválido: '$bus_id'";
                    }
                }
            }
            
            $conn->commit();
            
            if ($buses_creados > 0) {
                echo "<div class='mensaje-exito'>";
                echo "<i class='fas fa-check-circle'></i> ";
                echo "Se crearon $buses_creados buses correctamente.";
                if (!empty($errores)) {
                    echo " Algunos IDs fueron omitidos por ser inválidos.";
                }
                echo "</div>";
            } else {
                echo "<div class='mensaje-advertencia'>";
                echo "<i class='fas fa-info-circle'></i> ";
                echo "No se crearon buses nuevos. Es posible que ya existieran en el sistema.";
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
    
    // Actualizar la lista de buses existentes después de la inserción
    $result_buses = $conn->query($sql_buses_existentes);
    $buses_existentes = [];
    if ($result_buses->num_rows > 0) {
        while ($row = $result_buses->fetch_assoc()) {
            $buses_existentes[] = $row['id'];
        }
    }
}
?>

<div class="step-content">
    <div class="step-header">
        <i class="fas fa-bus"></i>
        <h2>Configuración de Buses</h2>
        <p>Define los buses que forman parte de tu flota</p>
    </div>

    <?php if (!empty($buses_existentes)): ?>
        <div class="existing-buses">
            <h3>Buses ya registrados (<?php echo count($buses_existentes); ?>)</h3>
            <div class="buses-list">
                <?php foreach ($buses_existentes as $bus_id): ?>
                    <span class="bus-badge">Bus #<?php echo $bus_id; ?></span>
                <?php endforeach; ?>
            </div>
            <p class="note">Los buses nuevos se agregarán a los existentes.</p>
        </div>
    <?php endif; ?>

    <form method="POST" class="wizard-form" id="form-buses">
        <input type="hidden" name="paso_actual" value="2">
        
        <div class="bus-config-options">
            <h3>¿Cómo quieres agregar los buses?</h3>
            
            <div class="option-cards">
                <label class="option-card">
                    <input type="radio" name="metodo_buses" value="rango" checked>
                    <div class="card-content">
                        <i class="fas fa-sort-numeric-up"></i>
                        <h4>Por Rango Numérico</h4>
                        <p>Ideal para buses numerados consecutivamente (ej: del 100 al 150)</p>
                    </div>
                </label>
                
                <label class="option-card">
                    <input type="radio" name="metodo_buses" value="lista">
                    <div class="card-content">
                        <i class="fas fa-list"></i>
                        <h4>Lista Personalizada</h4>
                        <p>Para buses con números específicos no consecutivos</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Método por rango -->
        <div id="rango-input" class="input-method">
            <h4>Configuración por Rango</h4>
            <div class="range-inputs">
                <div class="form-group">
                    <label for="bus_inicio">Bus inicial (número):</label>
                    <input type="number" name="bus_inicio" id="bus_inicio" min="1" value="100" required>
                </div>
                <div class="form-group">
                    <label for="bus_fin">Bus final (número):</label>
                    <input type="number" name="bus_fin" id="bus_fin" min="1" value="150" required>
                </div>
            </div>
            <div class="range-preview">
                <strong>Vista previa:</strong> 
                <span id="preview-text">Se crearán buses del 100 al 150 (51 buses)</span>
            </div>
        </div>

        <!-- Método por lista -->
        <div id="lista-input" class="input-method" style="display: none;">
            <h4>Lista de Buses</h4>
            <p>Ingresa los números de bus, uno por línea:</p>
            <textarea name="lista_buses" placeholder="Ejemplo:&#10;10&#10;11&#10;58&#10;100&#10;101&#10;102" rows="10"></textarea>
            <small>Cada línea debe contener solo un número de bus.</small>
            
            <div class="lista-preview">
                <strong>Vista previa:</strong>
                <div id="lista-preview-content">Ingresa los números para ver la vista previa</div>
            </div>
        </div>

        <div class="config-tips">
            <h4>💡 Consejos:</h4>
            <ul>
                <li><strong>Números únicos:</strong> Cada bus debe tener un ID único</li>
                <li><strong>Solo números:</strong> Los IDs de bus deben ser números enteros positivos</li>
                <li><strong>Sin duplicados:</strong> Si un bus ya existe, se ignorará</li>
                <li><strong>Agregar después:</strong> Puedes agregar más buses después desde el sistema principal</li>
                <li><strong>Límite:</strong> Máximo 500 buses por operación</li>
            </ul>
        </div>

        <div class="form-actions">
            <a href="?paso=1" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Paso Anterior
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i>
                Crear Buses y Continuar
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="metodo_buses"]');
    const methods = document.querySelectorAll('.input-method');
    const busInicio = document.getElementById('bus_inicio');
    const busFin = document.getElementById('bus_fin');
    const previewText = document.getElementById('preview-text');
    const listaTextarea = document.querySelector('textarea[name="lista_buses"]');
    const listaPreviewContent = document.getElementById('lista-preview-content');
    
    // Manejar cambio de método
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            methods.forEach(method => method.style.display = 'none');
            
            if (this.value === 'rango') {
                document.getElementById('rango-input').style.display = 'block';
                updatePreview();
            } else if (this.value === 'lista') {
                document.getElementById('lista-input').style.display = 'block';
                updateListaPreview();
            }
        });
    });
    
    // Actualizar vista previa del rango
    function updatePreview() {
        const inicio = parseInt(busInicio.value) || 0;
        const fin = parseInt(busFin.value) || 0;
        
        if (inicio > 0 && fin > 0 && fin >= inicio) {
            const total = fin - inicio + 1;
            previewText.textContent = `Se crearán buses del ${inicio} al ${fin} (${total} buses)`;
            previewText.className = 'preview-success';
            
            if (total > 500) {
                previewText.textContent += ' - ¡ATENCIÓN: Máximo 500 buses permitidos!';
                previewText.className = 'preview-error';
            }
        } else if (inicio > fin) {
            previewText.textContent = 'Error: El bus final debe ser mayor o igual al inicial';
            previewText.className = 'preview-error';
        } else {
            previewText.textContent = 'Ingresa los números para ver la vista previa';
            previewText.className = 'preview-neutral';
        }
    }
    
    // Actualizar vista previa de la lista
    function updateListaPreview() {
        const texto = listaTextarea.value.trim();
        
        if (!texto) {
            listaPreviewContent.textContent = 'Ingresa los números para ver la vista previa';
            listaPreviewContent.className = 'preview-neutral';
            return;
        }
        
        const lineas = texto.split('\n').filter(l => l.trim());
        const buses = lineas.filter(linea => {
            const num = linea.trim();
            return num && !isNaN(num) && parseInt(num) > 0;
        });
        
        const busesInvalidos = lineas.length - buses.length;
        
        let mensaje = `${buses.length} buses válidos`;
        if (busesInvalidos > 0) {
            mensaje += `, ${busesInvalidos} inválidos`;
        }
        
        listaPreviewContent.textContent = mensaje;
        
        if (buses.length > 500) {
            listaPreviewContent.textContent += ' - ¡ATENCIÓN: Máximo 500 buses permitidos!';
            listaPreviewContent.className = 'preview-error';
        } else if (busesInvalidos > 0) {
            listaPreviewContent.className = 'preview-warning';
        } else {
            listaPreviewContent.className = 'preview-success';
        }
    }
    
    // Event listeners para vista previa
    busInicio.addEventListener('input', updatePreview);
    busFin.addEventListener('input', updatePreview);
    listaTextarea.addEventListener('input', updateListaPreview);
    
    // Validación del formulario
    document.getElementById('form-buses').addEventListener('submit', function(e) {
        const metodo = document.querySelector('input[name="metodo_buses"]:checked').value;
        
        if (metodo === 'rango') {
            const inicio = parseInt(busInicio.value);
            const fin = parseInt(busFin.value);
            
            if (!inicio || !fin || inicio > fin) {
                e.preventDefault();
                alert('Por favor, ingresa un rango válido de buses.');
                return false;
            }
            
            if (fin - inicio + 1 > 500) {
                e.preventDefault();
                alert('No se pueden crear más de 500 buses a la vez. Reduce el rango.');
                return false;
            }
            
            if (fin - inicio > 200) {
                if (!confirm(`Vas a crear ${fin - inicio + 1} buses. ¿Estás seguro?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        } else if (metodo === 'lista') {
            const lista = listaTextarea.value.trim();
            if (!lista) {
                e.preventDefault();
                alert('Por favor, ingresa al menos un número de bus.');
                return false;
            }
            
            const lineas = lista.split('\n').filter(l => l.trim());
            const buses = lineas.filter(linea => {
                const num = linea.trim();
                return num && !isNaN(num) && parseInt(num) > 0;
            });
            
            if (buses.length === 0) {
                e.preventDefault();
                alert('No se encontraron números de bus válidos en la lista.');
                return false;
            }
            
            if (buses.length > 500) {
                e.preventDefault();
                alert('No se pueden crear más de 500 buses a la vez. Reduce la lista.');
                return false;
            }
            
            if (buses.length > 50) {
                if (!confirm(`Vas a crear ${buses.length} buses. ¿Estás seguro?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    });
    
    // Inicializar vista previa
    updatePreview();
});
</script>

<style>
.existing-buses {
    background: #34495e;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.buses-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 15px 0;
    max-height: 200px;
    overflow-y: auto;
}

.bus-badge {
    background: #3498db;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
}

.note {
    color: #bdc3c7;
    font-style: italic;
    margin-top: 10px;
}

.option-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.option-card {
    background: #2c3e50;
    border: 2px solid #34495e;
    border-radius: 8px;
    padding: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.option-card:hover {
    border-color: #3498db;
    transform: translateY(-2px);
}

.option-card input[type="radio"] {
    position: absolute;
    top: 15px;
    right: 15px;
    transform: scale(1.2);
    accent-color: #3498db;
}

.option-card input[type="radio"]:checked + .card-content {
    color: #3498db;
}

.card-content {
    text-align: center;
}

.card-content i {
    font-size: 36px;
    margin-bottom: 15px;
    display: block;
    color: #3498db;
}

.card-content h4 {
    margin: 10px 0 8px 0;
    color: #ecf0f1;
    font-size: 18px;
}

.card-content p {
    margin: 0;
    color: #bdc3c7;
    font-size: 14px;
    line-height: 1.4;
}

.input-method {
    background: #34495e;
    padding: 25px;
    border-radius: 8px;
    margin: 25px 0;
}

.range-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.range-preview, .lista-preview {
    margin: 15px 0;
    padding: 15px;
    background: #2c3e50;
    border-radius: 6px;
    border-left: 4px solid #3498db;
}

.preview-success {
    color: #2ecc71 !important;
    border-left-color: #2ecc71 !important;
}

.preview-error {
    color: #e74c3c !important;
    border-left-color: #e74c3c !important;
}

.preview-warning {
    color: #f39c12 !important;
    border-left-color: #f39c12 !important;
}

.preview-neutral {
    color: #bdc3c7 !important;
}

.input-method textarea {
    width: 100%;
    min-height: 200px;
    background: #2c3e50;
    border: 1px solid #4a5f7a;
    color: #ecf0f1;
    padding: 15px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 14px;
    resize: vertical;
}

.input-method textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.config-tips {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid #3498db;
    border-radius: 8px;
    padding: 20px;
    margin: 25px 0;
}

.config-tips h4 {
    color: #3498db;
    margin-top: 0;
    margin-bottom: 15px;
}

.config-tips ul {
    margin: 0;
    padding-left: 20px;
}

.config-tips li {
    margin: 8px 0;
    color: #ecf0f1;
}

.config-tips strong {
    color: #3498db;
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
    .range-inputs {
        grid-template-columns: 1fr;
    }
    
    .option-cards {
        grid-template-columns: 1fr;
    }
    
    .buses-list {
        max-height: 120px;
    }
}
</style>