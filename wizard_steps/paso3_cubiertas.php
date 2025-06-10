<?php
// wizard_steps/paso3_cubiertas.php

// Obtener cubiertas existentes
$sql_cubiertas_existentes = "SELECT id, nombre, estado FROM cubiertas ORDER BY nombre";
$result_cubiertas = $conn->query($sql_cubiertas_existentes);
$cubiertas_existentes = [];
if ($result_cubiertas->num_rows > 0) {
    while ($row = $result_cubiertas->fetch_assoc()) {
        $cubiertas_existentes[] = $row;
    }
}

// Obtener total de buses para calcular cubiertas sugeridas
$sql_total_buses = "SELECT COUNT(*) as total FROM coches";
$result_buses = $conn->query($sql_total_buses);
$total_buses = $result_buses->fetch_assoc()['total'];
$cubiertas_sugeridas = $total_buses * 6; // 6 cubiertas por bus

// Procesar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paso_actual']) && $_POST['paso_actual'] == '3') {
    $cubiertas_creadas = 0;
    $errores = [];
    
    if (isset($_POST['metodo_cubiertas'])) {
        $conn->begin_transaction();
        
        try {
            if ($_POST['metodo_cubiertas'] === 'patron' && 
                isset($_POST['patron_prefix']) && 
                isset($_POST['patron_inicio']) && 
                isset($_POST['patron_fin'])) {
                
                // Generar cubiertas con patrón
                $prefix = trim($_POST['patron_prefix']);
                $inicio = (int)$_POST['patron_inicio'];
                $fin = (int)$_POST['patron_fin'];
                $digitos = isset($_POST['patron_digitos']) ? (int)$_POST['patron_digitos'] : 3;
                
                if (empty($prefix)) {
                    throw new Exception("El prefijo no puede estar vacío");
                }
                
                if ($inicio > $fin) {
                    throw new Exception("El número inicial debe ser menor o igual al final");
                }
                
                $total = $fin - $inicio + 1;
                if ($total > 2000) {
                    throw new Exception("No se pueden crear más de 2000 cubiertas a la vez");
                }
                
                for ($i = $inicio; $i <= $fin; $i++) {
                    $numero = str_pad($i, $digitos, '0', STR_PAD_LEFT);
                    $nombre = $prefix . $numero;
                    
                    // Verificar si ya existe una cubierta con este nombre
                    $sql_check = "SELECT id FROM cubiertas WHERE nombre = ?";
                    $stmt_check = $conn->prepare($sql_check);
                    $stmt_check->bind_param("s", $nombre);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    
                    if ($result_check->num_rows == 0) {
                        $sql = "INSERT INTO cubiertas (nombre, estado) VALUES (?, 'casanova')";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $nombre);
                        
                        if ($stmt->execute()) {
                            $cubiertas_creadas++;
                        }
                        $stmt->close();
                    } else {
                        $errores[] = "Cubierta '$nombre' ya existe";
                    }
                    $stmt_check->close();
                }
                
            } elseif ($_POST['metodo_cubiertas'] === 'manual' && isset($_POST['lista_cubiertas'])) {
                // Crear cubiertas desde lista manual
                $lista_text = trim($_POST['lista_cubiertas']);
                if (empty($lista_text)) {
                    throw new Exception("La lista de cubiertas no puede estar vacía");
                }
                
                $cubiertas_lista = array_filter(array_map('trim', explode("\n", $lista_text)));
                
                if (count($cubiertas_lista) > 2000) {
                    throw new Exception("No se pueden crear más de 2000 cubiertas a la vez");
                }
                
                foreach ($cubiertas_lista as $nombre) {
                    if (!empty($nombre)) {
                        // Verificar si ya existe
                        $sql_check = "SELECT id FROM cubiertas WHERE nombre = ?";
                        $stmt_check = $conn->prepare($sql_check);
                        $stmt_check->bind_param("s", $nombre);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        
                        if ($result_check->num_rows == 0) {
                            $sql = "INSERT INTO cubiertas (nombre, estado) VALUES (?, 'casanova')";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("s", $nombre);
                            
                            if ($stmt->execute()) {
                                $cubiertas_creadas++;
                            }
                            $stmt->close();
                        } else {
                            $errores[] = "Cubierta '$nombre' ya existe";
                        }
                        $stmt_check->close();
                    }
                }
            }
            
            $conn->commit();
            
            if ($cubiertas_creadas > 0) {
                echo "<div class='mensaje-exito'>";
                echo "<i class='fas fa-check-circle'></i> ";
                echo "Se crearon $cubiertas_creadas cubiertas correctamente.";
                if (!empty($errores) && count($errores) <= 10) {
                    echo " Algunas cubiertas fueron omitidas por duplicados.";
                } elseif (count($errores) > 10) {
                    echo " " . count($errores) . " cubiertas fueron omitidas por duplicados.";
                }
                echo "</div>";
            } else {
                echo "<div class='mensaje-advertencia'>";
                echo "<i class='fas fa-info-circle'></i> ";
                echo "No se crearon cubiertas nuevas. Es posible que ya existieran en el sistema.";
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
    
    // Actualizar la lista de cubiertas existentes después de la inserción
    $result_cubiertas = $conn->query($sql_cubiertas_existentes);
    $cubiertas_existentes = [];
    if ($result_cubiertas->num_rows > 0) {
        while ($row = $result_cubiertas->fetch_assoc()) {
            $cubiertas_existentes[] = $row;
        }
    }
}
?>

<div class="step-content">
    <div class="step-header">
        <i class="fas fa-circle"></i>
        <h2>Inventario de Cubiertas</h2>
        <p>Configura el inventario completo de cubiertas disponibles</p>
    </div>

    <div class="inventory-overview">
        <div class="overview-cards">
            <div class="overview-card">
                <i class="fas fa-bus"></i>
                <div class="card-info">
                    <h3><?php echo $total_buses; ?></h3>
                    <p>Buses Registrados</p>
                </div>
            </div>
            
            <div class="overview-card">
                <i class="fas fa-circle"></i>
                <div class="card-info">
                    <h3><?php echo count($cubiertas_existentes); ?></h3>
                    <p>Cubiertas Existentes</p>
                </div>
            </div>
            
            <div class="overview-card suggested">
                <i class="fas fa-calculator"></i>
                <div class="card-info">
                    <h3><?php echo $cubiertas_sugeridas; ?></h3>
                    <p>Cubiertas Sugeridas<br><small>(6 por bus)</small></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($cubiertas_existentes)): ?>
        <div class="existing-inventory">
            <h3>Cubiertas ya registradas (<?php echo count($cubiertas_existentes); ?>)</h3>
            <div class="cubiertas-grid">
                <?php 
                $limite_mostrar = 100; // Limitar para evitar sobrecarga visual
                $mostrar = array_slice($cubiertas_existentes, 0, $limite_mostrar);
                foreach ($mostrar as $cubierta): 
                ?>
                    <div class="cubierta-item">
                        <span class="cubierta-name"><?php echo htmlspecialchars($cubierta['nombre']); ?></span>
                        <span class="cubierta-status status-<?php echo $cubierta['estado']; ?>">
                            <?php echo strtoupper($cubierta['estado']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($cubiertas_existentes) > $limite_mostrar): ?>
                    <div class="cubierta-item mas-cubiertas">
                        <span class="cubierta-name">... y <?php echo count($cubiertas_existentes) - $limite_mostrar; ?> más</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="wizard-form" id="form-cubiertas">
        <input type="hidden" name="paso_actual" value="3">
        
        <div class="cubierta-config-options">
            <h3>¿Cómo quieres agregar las cubiertas?</h3>
            
            <div class="option-cards">
                <label class="option-card">
                    <input type="radio" name="metodo_cubiertas" value="patron" checked>
                    <div class="card-content">
                        <i class="fas fa-magic"></i>
                        <h4>Patrón Automático</h4>
                        <p>Generar cubiertas con patrón (ej: CUB001, CUB002, CUB003...)</p>
                    </div>
                </label>
                
                <label class="option-card">
                    <input type="radio" name="metodo_cubiertas" value="manual">
                    <div class="card-content">
                        <i class="fas fa-keyboard"></i>
                        <h4>Lista Manual</h4>
                        <p>Escribir nombres específicos de cubiertas</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Método por patrón -->
        <div id="patron-input" class="input-method">
            <h4>Generación por Patrón</h4>
            <div class="patron-config">
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="patron_prefix">Prefijo:</label>
                        <input type="text" name="patron_prefix" id="patron_prefix" value="CUB" required maxlength="10">
                    </div>
                    
                    <div class="form-group">
                        <label for="patron_inicio">Número inicial:</label>
                        <input type="number" name="patron_inicio" id="patron_inicio" value="1" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="patron_fin">Número final:</label>
                        <input type="number" name="patron_fin" id="patron_fin" value="<?php echo min($cubiertas_sugeridas, 100); ?>" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="patron_digitos">Dígitos:</label>
                        <select name="patron_digitos" id="patron_digitos">
                            <option value="2">2 (01, 02, 03...)</option>
                            <option value="3" selected>3 (001, 002, 003...)</option>
                            <option value="4">4 (0001, 0002, 0003...)</option>
                        </select>
                    </div>
                </div>
                
                <div class="patron-preview">
                    <strong>Vista previa:</strong>
                    <div class="preview-examples">
                        <span id="preview-examples"></span>
                    </div>
                    <div class="preview-total">
                        <span id="preview-total"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Método manual -->
        <div id="manual-input" class="input-method" style="display: none;">
            <h4>Lista Manual de Cubiertas</h4>
            <p>Ingresa los nombres de las cubiertas, uno por línea:</p>
            <textarea name="lista_cubiertas" placeholder="Ejemplo:&#10;CUBIERTA-DELANTERA-01&#10;CUBIERTA-TRASERA-01&#10;CUB-ESPECIAL-A&#10;MICHELIN-205-R16&#10;FIRESTONE-275-80R22.5" rows="15"></textarea>
            <small>Cada línea será una cubierta nueva. Todas se crearán con estado 'casanova'.</small>
            
            <div class="manual-preview">
                <strong>Vista previa:</strong>
                <div id="manual-preview-content">Ingresa los nombres para ver la vista previa</div>
            </div>
        </div>

        <div class="inventory-tips">
            <h4>💡 Recomendaciones:</h4>
            <ul>
                <li><strong>Cantidad:</strong> Para <?php echo $total_buses; ?> buses, se recomiendan al menos <?php echo $cubiertas_sugeridas; ?> cubiertas (6 por bus)</li>
                <li><strong>Stock adicional:</strong> Considera agregar 20-30% extra para rotación y mantenimiento</li>
                <li><strong>Nombres únicos:</strong> Cada cubierta debe tener un nombre único e identificable</li>
                <li><strong>Convención:</strong> Usa un patrón consistente para facilitar la gestión</li>
                <li><strong>Límite:</strong> Máximo 2000 cubiertas por operación</li>
            </ul>
        </div>

        <div class="form-actions">
            <a href="?paso=2" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Paso Anterior
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i>
                Crear Cubiertas y Continuar
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="metodo_cubiertas"]');
    const methods = document.querySelectorAll('.input-method');
    
    // Elementos del patrón
    const prefixInput = document.getElementById('patron_prefix');
    const inicioInput = document.getElementById('patron_inicio');
    const finInput = document.getElementById('patron_fin');
    const digitosSelect = document.getElementById('patron_digitos');
    const previewExamples = document.getElementById('preview-examples');
    const previewTotal = document.getElementById('preview-total');
    
    // Elementos manuales
    const manualTextarea = document.querySelector('textarea[name="lista_cubiertas"]');
    const manualPreviewContent = document.getElementById('manual-preview-content');
    
    // Manejar cambio de método
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            methods.forEach(method => method.style.display = 'none');
            
            if (this.value === 'patron') {
                document.getElementById('patron-input').style.display = 'block';
                updatePatronPreview();
            } else if (this.value === 'manual') {
                document.getElementById('manual-input').style.display = 'block';
                updateManualPreview();
            }
        });
    });
    
    // Actualizar vista previa del patrón
    function updatePatronPreview() {
        const prefix = prefixInput.value || 'CUB';
        const inicio = parseInt(inicioInput.value) || 1;
        const fin = parseInt(finInput.value) || 1;
        const digitos = parseInt(digitosSelect.value) || 3;
        
        if (fin >= inicio) {
            const total = fin - inicio + 1;
            
            // Generar ejemplos
            const ejemplos = [];
            for (let i = inicio; i <= Math.min(inicio + 4, fin); i++) {
                const numero = i.toString().padStart(digitos, '0');
                ejemplos.push(`${prefix}${numero}`);
            }
            
            if (total > 5) {
                ejemplos.push('...');
                const ultimoNumero = fin.toString().padStart(digitos, '0');
                ejemplos.push(`${prefix}${ultimoNumero}`);
            }
            
            previewExamples.innerHTML = ejemplos.map(e => 
                `<span class="preview-example">${e}</span>`
            ).join('');
            
            let mensaje = `<strong>Total: ${total} cubiertas</strong>`;
            if (total > 2000) {
                mensaje += ' - ¡ATENCIÓN: Máximo 2000 cubiertas permitidas!';
                previewTotal.className = 'preview-error';
            } else if (total > 1000) {
                mensaje += ' - Cantidad alta, considera dividir en lotes';
                previewTotal.className = 'preview-warning';
            } else {
                previewTotal.className = 'preview-success';
            }
            
            previewTotal.innerHTML = mensaje;
            
        } else {
            previewExamples.innerHTML = '<span class="preview-error">Error: El número final debe ser mayor o igual al inicial</span>';
            previewTotal.innerHTML = '';
        }
    }
    
    // Actualizar vista previa manual
    function updateManualPreview() {
        const texto = manualTextarea.value.trim();
        
        if (!texto) {
            manualPreviewContent.textContent = 'Ingresa los nombres para ver la vista previa';
            manualPreviewContent.className = 'preview-neutral';
            return;
        }
        
        const lineas = texto.split('\n').filter(l => l.trim());
        const nombres = lineas.map(l => l.trim()).filter(n => n);
        
        // Detectar duplicados
        const nombresSinDuplicados = [...new Set(nombres)];
        const duplicados = nombres.length - nombresSinDuplicados.length;
        
        let mensaje = `${nombresSinDuplicados.length} cubiertas únicas`;
        if (duplicados > 0) {
            mensaje += `, ${duplicados} duplicados`;
        }
        
        manualPreviewContent.textContent = mensaje;
        
        if (nombresSinDuplicados.length > 2000) {
            manualPreviewContent.textContent += ' - ¡ATENCIÓN: Máximo 2000 cubiertas permitidas!';
            manualPreviewContent.className = 'preview-error';
        } else if (duplicados > 0) {
            manualPreviewContent.className = 'preview-warning';
        } else {
            manualPreviewContent.className = 'preview-success';
        }
    }
    
    // Event listeners para vista previa
    [prefixInput, inicioInput, finInput, digitosSelect].forEach(input => {
        input.addEventListener('input', updatePatronPreview);
        input.addEventListener('change', updatePatronPreview);
    });
    
    manualTextarea.addEventListener('input', updateManualPreview);
    
    // Validación del formulario
    document.getElementById('form-cubiertas').addEventListener('submit', function(e) {
        const metodo = document.querySelector('input[name="metodo_cubiertas"]:checked').value;
        
        if (metodo === 'patron') {
            const inicio = parseInt(inicioInput.value);
            const fin = parseInt(finInput.value);
            const prefix = prefixInput.value.trim();
            
            if (!prefix) {
                e.preventDefault();
                alert('Por favor, ingresa un prefijo para las cubiertas.');
                return false;
            }
            
            if (!inicio || !fin || inicio > fin) {
                e.preventDefault();
                alert('Por favor, ingresa un rango válido de números.');
                return false;
            }
            
            const total = fin - inicio + 1;
            if (total > 2000) {
                e.preventDefault();
                alert('No se pueden crear más de 2000 cubiertas a la vez. Reduce el rango.');
                return false;
            }
            
            if (total > 500) {
                if (!confirm(`Vas a crear ${total} cubiertas. ¿Estás seguro?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            
        } else if (metodo === 'manual') {
            const lista = manualTextarea.value.trim();
            if (!lista) {
                e.preventDefault();
                alert('Por favor, ingresa al menos un nombre de cubierta.');
                return false;
            }
            
            const lineas = lista.split('\n').filter(l => l.trim());
            const nombres = lineas.map(l => l.trim()).filter(n => n);
            
            if (nombres.length === 0) {
                e.preventDefault();
                alert('No se encontraron nombres de cubierta válidos en la lista.');
                return false;
            }
            
            if (nombres.length > 2000) {
                e.preventDefault();
                alert('No se pueden crear más de 2000 cubiertas a la vez. Reduce la lista.');
                return false;
            }
            
            if (nombres.length > 200) {
                if (!confirm(`Vas a crear ${nombres.length} cubiertas. ¿Estás seguro?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    });
    
    // Inicializar vista previa
    updatePatronPreview();
});
</script>

<style>
.inventory-overview {
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

.overview-card.suggested {
    border-color: #f39c12;
    background: rgba(243, 156, 18, 0.1);
}

.overview-card i {
    font-size: 28px;
    color: #3498db;
}

.overview-card.suggested i {
    color: #f39c12;
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

.existing-inventory {
    background: #2c3e50;
    padding: 20px;
    border-radius: 8px;
    margin: 25px 0;
}

.cubiertas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    margin-top: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.cubierta-item {
    background: #34495e;
    padding: 8px 12px;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.cubierta-item.mas-cubiertas {
    background: #4a6741;
    font-style: italic;
}

.cubierta-name {
    font-size: 13px;
    color: #ecf0f1;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cubierta-status {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
    flex-shrink: 0;
}

.status-casanova {
    background: #2ecc71;
    color: white;
}

.status-silacor {
    background: #3498db;
    color: white;
}

.status-baja {
    background: #e74c3c;
    color: white;
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

.patron-config {
    margin: 20px 0;
}

.form-group-inline {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.patron-preview, .manual-preview {
    background: #2c3e50;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #3498db;
}

.preview-examples {
    margin: 10px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.preview-example {
    background: #3498db;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-family: monospace;
}

.preview-total {
    margin-top: 10px;
    font-weight: 600;
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
    min-height: 250px;
    background: #2c3e50;
    border: 1px solid #4a6741;
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

.inventory-tips {
    background: rgba(241, 196, 15, 0.1);
    border: 1px solid #f1c40f;
    border-radius: 8px;
    padding: 20px;
    margin: 25px 0;
}

.inventory-tips h4 {
    color: #f1c40f;
    margin-top: 0;
    margin-bottom: 15px;
}

.inventory-tips ul {
    margin: 0;
    padding-left: 20px;
}

.inventory-tips li {
    margin: 8px 0;
    color: #ecf0f1;
}

.inventory-tips strong {
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
    .form-group-inline {
        grid-template-columns: 1fr;
    }
    
    .overview-cards {
        grid-template-columns: 1fr;
    }
    
    .option-cards {
        grid-template-columns: 1fr;
    }
    
    .cubiertas-grid {
        grid-template-columns: 1fr;
        max-height: 200px;
    }
}
</style>