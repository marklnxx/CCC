<?php
/**
 * SOLUCIÓN MEJORADA PARA VALIDACIÓN DE KILOMETRAJE
 * Implementa validación cruzada inteligente considerando múltiples factores
 */

class KilometrajeValidator {
    private $conn;
    private $config;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->config = [
            'km_diario_maximo' => 1000,      // Máximo KM por día
            'km_mensual_maximo' => 25000,    // Máximo KM por mes
            'tolerancia_retroceso' => 50,    // KM permitidos hacia atrás (errores de lectura)
            'km_minimo_cambio' => 10,        // Mínimo cambio para registrar
            'dias_revision_historica' => 30   // Días hacia atrás para validar patrones
        ];
    }
    
    /**
     * VALIDACIÓN PRINCIPAL MEJORADA
     * Valida contra múltiples criterios antes de permitir el cambio
     */
    public function validarCambioKilometraje($coche_id, $cubierta_id, $nuevo_km, $usuario_id, $ignorar_validacion = false) {
        $resultados = [];
        
        try {
            // 1. VALIDACIÓN BÁSICA
            $validacion_basica = $this->validacionBasica($nuevo_km);
            if (!$validacion_basica['valido']) {
                return $validacion_basica;
            }
            
            // 2. VALIDACIÓN ESPECÍFICA DEL COCHE
            $validacion_coche = $this->validarContraHistorialCoche($coche_id, $nuevo_km);
            $resultados['coche'] = $validacion_coche;
            
            // 3. VALIDACIÓN ESPECÍFICA DE LA CUBIERTA
            $validacion_cubierta = $this->validarContraHistorialCubierta($cubierta_id, $nuevo_km);
            $resultados['cubierta'] = $validacion_cubierta;
            
            // 4. VALIDACIÓN TEMPORAL (patrones de uso)
            $validacion_temporal = $this->validarPatronTemporal($coche_id, $nuevo_km);
            $resultados['temporal'] = $validacion_temporal;
            
            // 5. VALIDACIÓN CRUZADA ENTRE COCHES
            $validacion_cruzada = $this->validarConsistenciaFlota($nuevo_km);
            $resultados['flota'] = $validacion_cruzada;
            
            // 6. EVALUACIÓN FINAL
            return $this->evaluarResultadosFinales($resultados, $ignorar_validacion, $usuario_id, $coche_id, $nuevo_km);
            
        } catch (Exception $e) {
            error_log("Error en validación de kilometraje: " . $e->getMessage());
            return [
                'valido' => false,
                'error' => 'Error interno de validación',
                'requiere_autorizacion' => true
            ];
        }
    }
    
    /**
     * VALIDACIÓN BÁSICA DE ENTRADA
     */
    private function validacionBasica($km) {
        if (!is_numeric($km)) {
            return ['valido' => false, 'mensaje' => 'El kilometraje debe ser numérico'];
        }
        
        if ($km < 0) {
            return ['valido' => false, 'mensaje' => 'El kilometraje no puede ser negativo'];
        }
        
        if ($km > 2000000) { // 2 millones de KM es irreal para un bus
            return ['valido' => false, 'mensaje' => 'Kilometraje demasiado alto (máximo: 2,000,000 km)'];
        }
        
        return ['valido' => true];
    }
    
    /**
     * VALIDACIÓN CONTRA HISTORIAL DEL COCHE
     */
    private function validarContraHistorialCoche($coche_id, $nuevo_km) {
        $sql = "SELECT 
                    MAX(kilometraje_retiro) as ultimo_km,
                    MIN(fecha_retiro) as fecha_ultimo_km,
                    COUNT(*) as total_registros
                FROM historial_cubiertas 
                WHERE coche_id = ? AND fecha_retiro IS NOT NULL
                ORDER BY fecha_retiro DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $coche_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$data || $data['ultimo_km'] === null) {
            return [
                'valido' => true,
                'mensaje' => 'Primer registro para este coche',
                'tipo' => 'info'
            ];
        }
        
        $ultimo_km = (int)$data['ultimo_km'];
        $diferencia = $nuevo_km - $ultimo_km;
        
        // Verificar retroceso significativo
        if ($diferencia < -$this->config['tolerancia_retroceso']) {
            return [
                'valido' => false,
                'mensaje' => "KM menor al último registrado. Último: " . number_format($ultimo_km) . " km, Nuevo: " . number_format($nuevo_km) . " km",
                'tipo' => 'error',
                'requiere_autorizacion' => true,
                'data' => ['ultimo_km' => $ultimo_km, 'diferencia' => $diferencia]
            ];
        }
        
        // Verificar incremento diario excesivo
        if ($data['fecha_ultimo_km']) {
            $dias_transcurridos = $this->calcularDiasTranscurridos($data['fecha_ultimo_km']);
            $km_diarios = $dias_transcurridos > 0 ? $diferencia / $dias_transcurridos : $diferencia;
            
            if ($km_diarios > $this->config['km_diario_maximo']) {
                return [
                    'valido' => false,
                    'mensaje' => "Incremento diario excesivo: " . round($km_diarios) . " km/día (máximo: {$this->config['km_diario_maximo']})",
                    'tipo' => 'warning',
                    'requiere_revision' => true,
                    'data' => ['km_diarios' => $km_diarios, 'dias' => $dias_transcurridos]
                ];
            }
        }
        
        return [
            'valido' => true,
            'mensaje' => 'Validación del coche exitosa',
            'data' => ['incremento' => $diferencia, 'ultimo_km' => $ultimo_km]
        ];
    }
    
    /**
     * VALIDACIÓN CONTRA HISTORIAL DE LA CUBIERTA
     */
    private function validarContraHistorialCubierta($cubierta_id, $nuevo_km) {
        $sql = "SELECT 
                    c.nombre,
                    hc.kilometraje_colocacion as km_inicial_actual,
                    hc.fecha_colocacion,
                    SUM(CASE WHEN hc2.fecha_retiro IS NOT NULL 
                        THEN GREATEST(0, hc2.kilometraje_retiro - hc2.kilometraje_colocacion) 
                        ELSE 0 END) as km_historicos
                FROM cubiertas c
                LEFT JOIN historial_cubiertas hc ON c.id = hc.cubierta_id AND hc.fecha_retiro IS NULL
                LEFT JOIN historial_cubiertas hc2 ON c.id = hc2.cubierta_id AND hc2.fecha_retiro IS NOT NULL
                WHERE c.id = ?
                GROUP BY c.id, c.nombre, hc.kilometraje_colocacion, hc.fecha_colocacion";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $cubierta_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$data || !$data['km_inicial_actual']) {
            return [
                'valido' => true,
                'mensaje' => 'Cubierta sin historial previo',
                'tipo' => 'info'
            ];
        }
        
        $km_inicial = (int)$data['km_inicial_actual'];
        $km_historicos = (int)($data['km_historicos'] ?? 0);
        $km_ciclo_actual = $nuevo_km - $km_inicial;
        $km_totales_cubierta = $km_historicos + $km_ciclo_actual;
        
        // Validar que el KM actual sea mayor al inicial del ciclo
        if ($nuevo_km < $km_inicial) {
            return [
                'valido' => false,
                'mensaje' => "KM menor al inicial del ciclo actual. Inicial: " . number_format($km_inicial) . " km",
                'tipo' => 'error',
                'requiere_autorizacion' => true
            ];
        }
        
        // Advertir si la cubierta está cerca del límite de vida útil
        if ($km_totales_cubierta > 45000) {
            $tipo = $km_totales_cubierta > 50000 ? 'error' : 'warning';
            return [
                'valido' => $km_totales_cubierta <= 55000, // Límite absoluto
                'mensaje' => "Cubierta con alto kilometraje: " . number_format($km_totales_cubierta) . " km totales",
                'tipo' => $tipo,
                'requiere_revision' => true,
                'data' => [
                    'km_totales' => $km_totales_cubierta,
                    'km_ciclo_actual' => $km_ciclo_actual,
                    'km_historicos' => $km_historicos
                ]
            ];
        }
        
        return [
            'valido' => true,
            'mensaje' => 'Validación de cubierta exitosa',
            'data' => [
                'km_totales' => $km_totales_cubierta,
                'km_ciclo_actual' => $km_ciclo_actual
            ]
        ];
    }
    
    /**
     * VALIDACIÓN DE PATRONES TEMPORALES
     */
    private function validarPatronTemporal($coche_id, $nuevo_km) {
        $fecha_limite = date('Y-m-d', strtotime("-{$this->config['dias_revision_historica']} days"));
        
        $sql = "SELECT 
                    DATE(fecha_retiro) as fecha,
                    kilometraje_retiro,
                    LAG(kilometraje_retiro) OVER (ORDER BY fecha_retiro) as km_anterior
                FROM historial_cubiertas 
                WHERE coche_id = ? 
                AND fecha_retiro >= ?
                AND kilometraje_retiro IS NOT NULL
                ORDER BY fecha_retiro DESC
                LIMIT 10";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $coche_id, $fecha_limite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $patrones = [];
        $km_diarios = [];
        
        while ($row = $result->fetch_assoc()) {
            if ($row['km_anterior']) {
                $diff = $row['kilometraje_retiro'] - $row['km_anterior'];
                $km_diarios[] = $diff;
            }
            $patrones[] = $row;
        }
        $stmt->close();
        
        if (empty($km_diarios)) {
            return ['valido' => true, 'mensaje' => 'Sin patrones previos para analizar'];
        }
        
        // Calcular estadísticas
        $promedio = array_sum($km_diarios) / count($km_diarios);
        $max_historico = max($km_diarios);
        
        // Si el último registro existe, calcular diferencia
        if (!empty($patrones)) {
            $ultimo_km = $patrones[0]['kilometraje_retiro'];
            $diferencia_actual = $nuevo_km - $ultimo_km;
            
            // Detectar anomalías
            if ($diferencia_actual > $max_historico * 2 || $diferencia_actual > $promedio * 3) {
                return [
                    'valido' => false,
                    'mensaje' => "Patrón anómalo detectado. Incremento: " . number_format($diferencia_actual) . " km (promedio histórico: " . round($promedio) . " km)",
                    'tipo' => 'warning',
                    'requiere_revision' => true,
                    'data' => [
                        'diferencia_actual' => $diferencia_actual,
                        'promedio_historico' => $promedio,
                        'maximo_historico' => $max_historico
                    ]
                ];
            }
        }
        
        return [
            'valido' => true,
            'mensaje' => 'Patrón temporal normal',
            'data' => ['promedio_diario' => round($promedio)]
        ];
    }
    
    /**
     * VALIDACIÓN DE CONSISTENCIA DE FLOTA
     */
    private function validarConsistenciaFlota($nuevo_km) {
        // Obtener estadísticas de la flota para detectar outliers
        $sql = "SELECT 
                    AVG(kilometraje_retiro) as promedio_flota,
                    STDDEV(kilometraje_retiro) as desviacion_flota,
                    MAX(kilometraje_retiro) as max_flota,
                    MIN(kilometraje_retiro) as min_flota
                FROM historial_cubiertas 
                WHERE fecha_retiro >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND kilometraje_retiro IS NOT NULL";
        
        $result = $this->conn->query($sql);
        $stats = $result->fetch_assoc();
        
        if (!$stats || $stats['promedio_flota'] === null) {
            return ['valido' => true, 'mensaje' => 'Sin datos de flota para comparar'];
        }
        
        $promedio = (float)$stats['promedio_flota'];
        $desviacion = (float)$stats['desviacion_flota'];
        $limite_superior = $promedio + (3 * $desviacion); // 3 sigma
        $limite_inferior = max(0, $promedio - (3 * $desviacion));
        
        if ($nuevo_km > $limite_superior) {
            return [
                'valido' => false,
                'mensaje' => "KM muy alto comparado con la flota (promedio: " . number_format($promedio) . " km)",
                'tipo' => 'warning',
                'requiere_revision' => true,
                'data' => [
                    'promedio_flota' => $promedio,
                    'limite_superior' => $limite_superior
                ]
            ];
        }
        
        return [
            'valido' => true,
            'mensaje' => 'Consistente con el promedio de flota'
        ];
    }
    
    /**
     * EVALUACIÓN FINAL DE TODOS LOS RESULTADOS
     */
    private function evaluarResultadosFinales($resultados, $ignorar_validacion, $usuario_id, $coche_id, $nuevo_km) {
        $errores = [];
        $advertencias = [];
        $requiere_autorizacion = false;
        $requiere_revision = false;
        
        foreach ($resultados as $tipo => $resultado) {
            if (!$resultado['valido']) {
                if ($resultado['tipo'] === 'error') {
                    $errores[] = "[$tipo] " . $resultado['mensaje'];
                } else {
                    $advertencias[] = "[$tipo] " . $resultado['mensaje'];
                }
                
                if (isset($resultado['requiere_autorizacion'])) {
                    $requiere_autorizacion = true;
                }
                if (isset($resultado['requiere_revision'])) {
                    $requiere_revision = true;
                }
            }
        }
        
        // Si hay errores críticos y no se ignora validación
        if (!empty($errores) && !$ignorar_validacion) {
            $this->registrarValidacion($usuario_id, $coche_id, $nuevo_km, 'RECHAZADO', implode('; ', $errores));
            return [
                'valido' => false,
                'errores' => $errores,
                'advertencias' => $advertencias,
                'requiere_autorizacion' => $requiere_autorizacion
            ];
        }
        
        // Si se ignora validación, registrar el motivo
        if ($ignorar_validacion) {
            $motivo = "Validación ignorada por usuario ID: $usuario_id. Errores: " . implode('; ', array_merge($errores, $advertencias));
            $this->registrarValidacion($usuario_id, $coche_id, $nuevo_km, 'IGNORADO', $motivo);
        }
        
        // Si solo hay advertencias
        if (!empty($advertencias)) {
            $this->registrarValidacion($usuario_id, $coche_id, $nuevo_km, 'ACEPTADO_CON_ADVERTENCIAS', implode('; ', $advertencias));
            return [
                'valido' => true,
                'advertencias' => $advertencias,
                'requiere_revision' => $requiere_revision,
                'mensaje' => 'Aceptado con advertencias'
            ];
        }
        
        // Todo OK
        $this->registrarValidacion($usuario_id, $coche_id, $nuevo_km, 'ACEPTADO', 'Validación exitosa');
        return [
            'valido' => true,
            'mensaje' => 'Validación exitosa en todos los criterios'
        ];
    }
    
    /**
     * REGISTRAR VALIDACIÓN PARA AUDITORÍA
     */
    private function registrarValidacion($usuario_id, $coche_id, $kilometraje, $estado, $detalles) {
        $sql = "INSERT INTO log_validaciones_kilometraje 
                (usuario_id, coche_id, kilometraje, estado, detalles, ip_address, user_agent, fecha) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("iiissss", $usuario_id, $coche_id, $kilometraje, $estado, $detalles, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * CALCULAR DÍAS TRANSCURRIDOS
     */
    private function calcularDiasTranscurridos($fecha_inicial) {
        $fecha1 = new DateTime($fecha_inicial);
        $fecha2 = new DateTime();
        return $fecha1->diff($fecha2)->days;
    }
}

/**
 * TABLA DE AUDITORÍA REQUERIDA
 * Ejecutar este SQL para crear la tabla de logs
 */
/*
CREATE TABLE log_validaciones_kilometraje (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    coche_id INT NOT NULL,
    kilometraje INT NOT NULL,
    estado ENUM('ACEPTADO', 'RECHAZADO', 'IGNORADO', 'ACEPTADO_CON_ADVERTENCIAS') NOT NULL,
    detalles TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha DATETIME NOT NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_coche (coche_id),
    INDEX idx_usuario (usuario_id)
);
*/

/**
 * EJEMPLO DE USO EN EL CÓDIGO PRINCIPAL
 */
/*
// En index.php, reemplazar la validación actual:

// Instanciar el validador
$validator = new KilometrajeValidator($conn);

// Validar el cambio
$validacion = $validator->validarCambioKilometraje(
    $coche_id, 
    $cubierta_vieja_id, 
    $kilometraje, 
    $usuario_id, // Obtener del session
    isset($_POST['ignorar_validacion'])
);

if (!$validacion['valido']) {
    echo "<div class='mensaje-error'>";
    echo "<i class='fas fa-exclamation-circle'></i> Validación fallida:<br>";
    foreach ($validacion['errores'] as $error) {
        echo "• $error<br>";
    }
    if ($validacion['requiere_autorizacion']) {
        echo "<br><strong>Se requiere autorización de supervisor para continuar.</strong>";
    }
    echo "</div>";
} else {
    // Proceder con el cambio
    if (!empty($validacion['advertencias'])) {
        echo "<div class='mensaje-advertencia'>";
        echo "<i class='fas fa-exclamation-triangle'></i> Advertencias:<br>";
        foreach ($validacion['advertencias'] as $advertencia) {
            echo "• $advertencia<br>";
        }
        echo "</div>";
    }
    
    // Continuar con la transacción...
}
*/
?>