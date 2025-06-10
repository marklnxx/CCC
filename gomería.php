<?php
    // **¡IMPORTANTE! Reemplaza con tus credenciales de base de datos**
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "prueba4";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar la conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Verificar si existe la tabla historial_bajas
    $sql_check_tabla = "SHOW TABLES LIKE 'historial_bajas'";
    $result_check_tabla = $conn->query($sql_check_tabla);
    
    if ($result_check_tabla->num_rows == 0) {
        // Crear la tabla historial_bajas si no existe
        $sql_create_table = "CREATE TABLE IF NOT EXISTS historial_bajas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cubierta_id INT NOT NULL,
            cubierta_nombre VARCHAR(100) NOT NULL,
            tipo_operacion ENUM('alta', 'baja') NOT NULL,
            motivo VARCHAR(100) NULL,
            fecha_operacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_colocacion DATE NULL,
            fecha_retiro DATE NULL,
            kilometraje_retiro INT NULL,
            coche_id INT NULL,
            fecha_reconstruccion DATE NULL,
            usuario VARCHAR(50) NULL
        )";
        
        if (!$conn->query($sql_create_table)) {
            echo "Error al crear la tabla: " . $conn->error;
        }
    }

    function validarCoherenciaTemporal($conn, $cubierta_id) {
    $errores = [];
    
    // Obtener todos los registros del historial para esta cubierta
    $sql_historial = "SELECT fecha_colocacion, fecha_retiro, kilometraje_colocacion, kilometraje_retiro 
                      FROM historial_cubiertas 
                      WHERE cubierta_id = ? 
                      ORDER BY fecha_colocacion ASC";
    
    $stmt = $conn->prepare($sql_historial);
    $stmt->bind_param("i", $cubierta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Validación 1: Fecha de retiro no puede ser anterior a fecha de colocación
        if (!empty($row['fecha_retiro']) && !empty($row['fecha_colocacion'])) {
            if (strtotime($row['fecha_retiro']) < strtotime($row['fecha_colocacion'])) {
                $errores[] = "Fecha de retiro ({$row['fecha_retiro']}) es anterior a fecha de colocación ({$row['fecha_colocacion']})";
            }
        }
        
        // Validación 2: Kilometraje de retiro no puede ser menor al de colocación
        if (!empty($row['kilometraje_retiro']) && !empty($row['kilometraje_colocacion'])) {
            if ($row['kilometraje_retiro'] < $row['kilometraje_colocacion']) {
                $errores[] = "Kilometraje de retiro ({$row['kilometraje_retiro']}) es menor al de colocación ({$row['kilometraje_colocacion']})";
            }
        }
        
        // Validación 3: No puede haber kilometraje negativo
        if (!empty($row['kilometraje_colocacion']) && $row['kilometraje_colocacion'] < 0) {
            $errores[] = "No se permite kilometraje de colocación negativo";
        }
        if (!empty($row['kilometraje_retiro']) && $row['kilometraje_retiro'] < 0) {
            $errores[] = "No se permite kilometraje de retiro negativo";
        }
    }
    
    $stmt->close();
    return $errores; // ← AGREGAR ESTA LÍNEA QUE FALTABA
}

    // ✅ NUEVA FUNCIÓN: Verificar coherencia antes de procesar
    function validarAntesDeProcesar($conn, $cubierta_id, $nueva_fecha_retiro = null, $nuevo_km_retiro = null) {
        $errores = [];
        
        // Obtener el último registro activo
        $sql_ultimo = "SELECT fecha_colocacion, kilometraje_colocacion 
                       FROM historial_cubiertas 
                       WHERE cubierta_id = ? AND fecha_retiro IS NULL 
                       ORDER BY fecha_colocacion DESC LIMIT 1";
        
        $stmt = $conn->prepare($sql_ultimo);
        $stmt->bind_param("i", $cubierta_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ultimo_registro = $result->fetch_assoc();
        $stmt->close();
        
        if ($ultimo_registro) {
            // Validar nueva fecha de retiro
            if ($nueva_fecha_retiro && strtotime($nueva_fecha_retiro) < strtotime($ultimo_registro['fecha_colocacion'])) {
                $errores[] = "La fecha de retiro no puede ser anterior a la fecha de colocación ({$ultimo_registro['fecha_colocacion']})";
            }
            
            // Validar nuevo kilometraje de retiro
            if ($nuevo_km_retiro !== null && $nuevo_km_retiro < $ultimo_registro['kilometraje_colocacion']) {
                $errores[] = "El kilometraje de retiro ({$nuevo_km_retiro}) no puede ser menor al de colocación ({$ultimo_registro['kilometraje_colocacion']})";
            }
        }
        
        return $errores;
    }

    // ✅ NUEVA FUNCIÓN: Validar que no haya solapamientos temporales
    function validarSolapamientoCubierta($conn, $cubierta_id, $nueva_fecha_colocacion, $coche_id_destino, $excluir_historial_id = null) {
        $errores = [];
        
        // Convertir fecha a timestamp para comparaciones
        $timestamp_nueva_colocacion = strtotime($nueva_fecha_colocacion);
        
        // Buscar registros activos (sin fecha de retiro) para esta cubierta
        $sql_activos = "SELECT h.id, h.coche_id, h.fecha_colocacion, c.nombre as nombre_coche
                        FROM historial_cubiertas h 
                        LEFT JOIN coches c ON h.coche_id = c.id
                        WHERE h.cubierta_id = ? 
                        AND h.fecha_retiro IS NULL";
        
        // Si estamos editando un registro, excluirlo de la validación
        if ($excluir_historial_id !== null) {
            $sql_activos .= " AND h.id != ?";
        }
        
        $stmt_activos = $conn->prepare($sql_activos);
        
        if ($excluir_historial_id !== null) {
            $stmt_activos->bind_param("ii", $cubierta_id, $excluir_historial_id);
        } else {
            $stmt_activos->bind_param("i", $cubierta_id);
        }
        
        $stmt_activos->execute();
        $result_activos = $stmt_activos->get_result();
        
        // Si hay registros activos, verificar solapamiento
        while ($row = $result_activos->fetch_assoc()) {
            $fecha_colocacion_existente = strtotime($row['fecha_colocacion']);
            
            // Si la nueva colocación es posterior a una colocación activa existente
            if ($timestamp_nueva_colocacion >= $fecha_colocacion_existente) {
                $nombre_coche_actual = $row['nombre_coche'] ? $row['nombre_coche'] : "Coche ID: " . $row['coche_id'];
                $errores[] = "SOLAPAMIENTO DETECTADO: La cubierta ya está activa en '{$nombre_coche_actual}' desde {$row['fecha_colocacion']}. Debe retirarla primero.";
            }
        }
        
        $stmt_activos->close();
        
        // Verificar solapamientos con registros que tienen fecha de retiro
        $sql_historial = "SELECT h.id, h.coche_id, h.fecha_colocacion, h.fecha_retiro, c.nombre as nombre_coche
                          FROM historial_cubiertas h 
                          LEFT JOIN coches c ON h.coche_id = c.id
                          WHERE h.cubierta_id = ? 
                          AND h.fecha_retiro IS NOT NULL";
        
        if ($excluir_historial_id !== null) {
            $sql_historial .= " AND h.id != ?";
        }
        
        $stmt_historial = $conn->prepare($sql_historial);
        
        if ($excluir_historial_id !== null) {
            $stmt_historial->bind_param("ii", $cubierta_id, $excluir_historial_id);
        } else {
            $stmt_historial->bind_param("i", $cubierta_id);
        }
        
        $stmt_historial->execute();
        $result_historial = $stmt_historial->get_result();
        
        while ($row = $result_historial->fetch_assoc()) {
            $fecha_colocacion_hist = strtotime($row['fecha_colocacion']);
            $fecha_retiro_hist = strtotime($row['fecha_retiro']);
            
            // Verificar si la nueva fecha está dentro del rango de un período existente
            if ($timestamp_nueva_colocacion >= $fecha_colocacion_hist && $timestamp_nueva_colocacion <= $fecha_retiro_hist) {
                $nombre_coche_hist = $row['nombre_coche'] ? $row['nombre_coche'] : "Coche ID: " . $row['coche_id'];
                $errores[] = "SOLAPAMIENTO HISTÓRICO: La fecha de colocación ({$nueva_fecha_colocacion}) coincide con un período ya registrado en '{$nombre_coche_hist}' del {$row['fecha_colocacion']} al {$row['fecha_retiro']}.";
            }
        }
        
        $stmt_historial->close();
        
        return $errores;
    }

    // ✅ FUNCIÓN MEJORADA: Validar coherencia temporal completa con anti-solapamiento
    function validarCoherenciaTemporalCompleta($conn, $cubierta_id) {
    $errores = [];
    
    // Primero, ejecutar la validación temporal básica existente
    $errores_temporales = validarCoherenciaTemporal($conn, $cubierta_id);
    if ($errores_temporales !== null) { // ← AGREGAR ESTA VALIDACIÓN
        $errores = array_merge($errores, $errores_temporales);
    }
        
        // Luego, verificar solapamientos
        $sql_todos_registros = "SELECT id, coche_id, fecha_colocacion, fecha_retiro 
                                FROM historial_cubiertas 
                                WHERE cubierta_id = ? 
                                ORDER BY fecha_colocacion ASC";
        
        $stmt_todos = $conn->prepare($sql_todos_registros);
        $stmt_todos->bind_param("i", $cubierta_id);
        $stmt_todos->execute();
        $result_todos = $stmt_todos->get_result();
        
        $registros = [];
        while ($row = $result_todos->fetch_assoc()) {
            $registros[] = $row;
        }
        $stmt_todos->close();
        
        // Verificar solapamientos entre todos los registros
        for ($i = 0; $i < count($registros); $i++) {
            for ($j = $i + 1; $j < count($registros); $j++) {
                $reg1 = $registros[$i];
                $reg2 = $registros[$j];
                
                // Determinar rangos de fechas
                $inicio1 = strtotime($reg1['fecha_colocacion']);
                $fin1 = $reg1['fecha_retiro'] ? strtotime($reg1['fecha_retiro']) : time();
                
                $inicio2 = strtotime($reg2['fecha_colocacion']);
                $fin2 = $reg2['fecha_retiro'] ? strtotime($reg2['fecha_retiro']) : time();
                
                // Verificar solapamiento: Los rangos se solapan si inicio1 <= fin2 && inicio2 <= fin1
                if ($inicio1 <= $fin2 && $inicio2 <= $fin1 && $reg1['coche_id'] != $reg2['coche_id']) {
                    $errores[] = "SOLAPAMIENTO TEMPORAL: Colocación {$reg1['fecha_colocacion']} (Coche {$reg1['coche_id']}) se solapa con colocación {$reg2['fecha_colocacion']} (Coche {$reg2['coche_id']}).";
                }
            }
        }
        
        return $errores;
    }

 // ARCHIVO: gomería.php - MODIFICACIÓN EN LA SECCIÓN DE ENVÍO A SILACOR

// Procesar el envío a SILACOR
if (isset($_POST['enviar_silacor']) && isset($_POST['cubierta_id_silacor'])) {
    $cubierta_id = $_POST['cubierta_id_silacor'];
    
    // ✅ NUEVA VALIDACIÓN: Verificar coherencia temporal COMPLETA (incluye anti-solapamiento)
    $errores_coherencia = validarCoherenciaTemporalCompleta($conn, $cubierta_id);
    
    if (!empty($errores_coherencia)) {
        $mensaje_error = "No se puede enviar a SILACOR. Errores de coherencia encontrados:<br>• " . implode("<br>• ", $errores_coherencia);
    } else {
        // Obtener información de la cubierta antes de enviarla a SILACOR
        $sql_info_cubierta = "SELECT c.nombre, 
                              (SELECT MIN(h.kilometraje_colocacion) FROM historial_cubiertas h WHERE h.cubierta_id = c.id AND h.fecha_retiro IS NULL) as km_inicial,
                              (SELECT MAX(h.kilometraje_retiro) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as km_final
                              FROM cubiertas c WHERE c.id = ?";
        $stmt_info = $conn->prepare($sql_info_cubierta);
        $stmt_info->bind_param("i", $cubierta_id);
        $stmt_info->execute();
        $result_info = $stmt_info->get_result();
        $cubierta_info = $result_info->fetch_assoc();
        $stmt_info->close();
        
        // ✅ VALIDACIÓN ADICIONAL: Verificar que los kilómetros sean coherentes
        if ($cubierta_info['km_inicial'] !== null && $cubierta_info['km_final'] !== null) {
            if ($cubierta_info['km_final'] < $cubierta_info['km_inicial']) {
                $mensaje_error = "Error: El kilometraje final ({$cubierta_info['km_final']}) no puede ser menor al inicial ({$cubierta_info['km_inicial']})";
            }
        }
        
        if (!isset($mensaje_error)) {
            // Iniciar transacción
            $conn->begin_transaction();
            
            try {
                // Registrar el kilometraje en historial_cubiertas si no está ya registrado
                $sql_check_km = "SELECT id FROM historial_cubiertas WHERE cubierta_id = ? AND fecha_retiro IS NULL";
                $stmt_check_km = $conn->prepare($sql_check_km);
                $stmt_check_km->bind_param("i", $cubierta_id);
                $stmt_check_km->execute();
                $result_check_km = $stmt_check_km->get_result();
                
                if ($result_check_km->num_rows > 0) {
                    // ✅ VALIDACIÓN antes de cerrar el registro
                    $errores_procesamiento = validarAntesDeProcesar($conn, $cubierta_id, date('Y-m-d H:i:s'));
                    
                    if (!empty($errores_procesamiento)) {
                        throw new Exception("Error al procesar: " . implode(", ", $errores_procesamiento));
                    } else {
                        // Si hay un registro sin fecha de retiro, cerrarlo
                        $sql_update_km = "UPDATE historial_cubiertas SET fecha_retiro = NOW() WHERE cubierta_id = ? AND fecha_retiro IS NULL";
                        $stmt_update_km = $conn->prepare($sql_update_km);
                        $stmt_update_km->bind_param("i", $cubierta_id);
                        $stmt_update_km->execute();
                        $stmt_update_km->close();
                    }
                }
                $stmt_check_km->close();
                
                // Actualizar el estado a 'silacor'
                $sql_update_silacor = "UPDATE cubiertas SET estado = 'silacor', coche_id = NULL WHERE id = ?";
                $stmt_silacor = $conn->prepare($sql_update_silacor);
                $stmt_silacor->bind_param("i", $cubierta_id);
                $stmt_silacor->execute();
                $stmt_silacor->close();
                
                // 🔧 CORRECCIÓN PRINCIPAL: Registrar en historial_bajas con tipo 'silacor' en lugar de 'baja'
                if (isset($cubierta_info['km_inicial']) && isset($cubierta_info['km_final']) && 
                    $cubierta_info['km_inicial'] !== null && $cubierta_info['km_final'] !== null) {
                    $km_recorridos = $cubierta_info['km_final'] - $cubierta_info['km_inicial'];
                    $motivo = "Enviada a SILACOR con " . number_format($km_recorridos, 0, ',', '.') . " km recorridos";
                    
                    // Registrar en historial_bajas con tipo 'silacor'
                    $sql_historial = "INSERT INTO historial_bajas 
                                     (cubierta_id, cubierta_nombre, tipo_operacion, motivo) 
                                     VALUES (?, ?, 'silacor', ?)";
                    $stmt_historial = $conn->prepare($sql_historial);
                    $stmt_historial->bind_param("iss", $cubierta_id, $cubierta_info['nombre'], $motivo);
                    $stmt_historial->execute();
                    $stmt_historial->close();
                } else {
                    // Si no hay información de kilometraje completa
                    $motivo = "Enviada a SILACOR (sin información completa de kilometraje)";
                    
                    $sql_historial = "INSERT INTO historial_bajas 
                                     (cubierta_id, cubierta_nombre, tipo_operacion, motivo) 
                                     VALUES (?, ?, 'silacor', ?)";
                    $stmt_historial = $conn->prepare($sql_historial);
                    $stmt_historial->bind_param("iss", $cubierta_id, $cubierta_info['nombre'], $motivo);
                    $stmt_historial->execute();
                    $stmt_historial->close();
                }
                
                // Confirmar transacción
                $conn->commit();
                $mensaje_exito = "Cubierta enviada a SILACOR exitosamente.";
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $conn->rollback();
                $mensaje_error = "Error al enviar a SILACOR: " . $e->getMessage();
            }
        }
    }
}

    // Procesar la baja de cubierta
    if (isset($_POST['dar_baja']) && isset($_POST['cubierta_id_baja'])) {
        $cubierta_id = $_POST['cubierta_id_baja'];
        
        // ✅ NUEVA VALIDACIÓN: Verificar coherencia temporal COMPLETA (incluye anti-solapamiento)
        $errores_coherencia = validarCoherenciaTemporalCompleta($conn, $cubierta_id);
        
        if (!empty($errores_coherencia)) {
            $mensaje_error = "No se puede dar de baja. Errores de coherencia encontrados:<br>• " . implode("<br>• ", $errores_coherencia);
        } else {
            // Determinar el motivo de la baja
            if (isset($_POST['motivo_baja']) && !empty($_POST['motivo_baja'])) {
                // Si hay un motivo seleccionado
                if ($_POST['motivo_baja'] === 'Otro') {
                    // Si seleccionó "Otro", usar el motivo personalizado
                    $motivo = isset($_POST['otro_motivo']) && !empty(trim($_POST['otro_motivo'])) 
                              ? trim($_POST['otro_motivo']) 
                              : 'Otro (sin especificar)';
                } else {
                    // Si seleccionó un motivo predefinido
                    $motivo = $_POST['motivo_baja'];
                }
            } else {
                // Si no se seleccionó motivo pero hay texto en "otro_motivo"
                if (isset($_POST['otro_motivo']) && !empty(trim($_POST['otro_motivo']))) {
                    $motivo = trim($_POST['otro_motivo']);
                } else {
                    $motivo = 'Baja sin motivo especificado';
                }
            }
            
            // Obtener información de la cubierta antes de darla de baja
            $sql_info_cubierta = "SELECT c.nombre, c.coche_id, 
                                  (SELECT MAX(h.fecha_colocacion) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as fecha_colocacion,
                                  (SELECT MAX(h.fecha_retiro) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as fecha_retiro,
                                  (SELECT MAX(h.kilometraje_retiro) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as km_retiro
                                  FROM cubiertas c WHERE c.id = ?";
            $stmt_info = $conn->prepare($sql_info_cubierta);
            $stmt_info->bind_param("i", $cubierta_id);
            $stmt_info->execute();
            $result_info = $stmt_info->get_result();
            $cubierta_info = $result_info->fetch_assoc();
            $stmt_info->close();
            
            // ✅ VALIDACIÓN ADICIONAL: Verificar coherencia de fechas obtenidas
            if ($cubierta_info['fecha_colocacion'] && $cubierta_info['fecha_retiro']) {
                if (strtotime($cubierta_info['fecha_retiro']) < strtotime($cubierta_info['fecha_colocacion'])) {
                    $mensaje_error = "Error: La fecha de retiro ({$cubierta_info['fecha_retiro']}) es anterior a la fecha de colocación ({$cubierta_info['fecha_colocacion']})";
                }
            }
            
            if (!isset($mensaje_error)) {
                // Registrar la baja en historial_bajas
                $sql_historial_baja = "INSERT INTO historial_bajas 
                                       (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_colocacion, fecha_retiro, kilometraje_retiro, coche_id) 
                                       VALUES (?, ?, 'baja', ?, ?, ?, ?, ?)";
                $stmt_historial = $conn->prepare($sql_historial_baja);
                $stmt_historial->bind_param("issssii", $cubierta_id, $cubierta_info['nombre'], $motivo, $cubierta_info['fecha_colocacion'], 
                                           $cubierta_info['fecha_retiro'], $cubierta_info['km_retiro'], $cubierta_info['coche_id']);
                $stmt_historial->execute();
                $stmt_historial->close();
                
                // En lugar de eliminar, marcar como "baja" o "inactiva" , la mantiene en historial.php
                $estado_baja = "baja";
                $sql_update = "UPDATE cubiertas 
                      SET estado = ?, coche_id = NULL 
                      WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $estado_baja, $cubierta_id);
                $stmt_update->execute();
                $stmt_update->close();
                
                $mensaje_exito = "Cubierta dada de baja exitosamente y registrada en el historial.";
            }
        }
    }

    // Procesar la reconstrucción lista
    if (isset($_POST['reconstruccion_lista']) && isset($_POST['cubierta_id_reconstruccion'])) {
        $cubierta_id = $_POST['cubierta_id_reconstruccion'];
        // Actualizar el estado a 'casanova'
        $sql_update_casanova = "UPDATE cubiertas SET estado = 'casanova' WHERE id = ?";
        $stmt_casanova = $conn->prepare($sql_update_casanova);
        $stmt_casanova->bind_param("i", $cubierta_id);
        $stmt_casanova->execute();
        $stmt_casanova->close();

        // Registrar la reconstrucción
        $sql_insert_reconstruccion = "INSERT INTO reconstrucciones (cubierta_id, fecha_reconstruccion) VALUES (?, NOW())";
        $stmt_reconstruccion = $conn->prepare($sql_insert_reconstruccion);
        $stmt_reconstruccion->bind_param("i", $cubierta_id);
        $stmt_reconstruccion->execute();
        $stmt_reconstruccion->close();
        
        $mensaje_exito = "Reconstrucción registrada exitosamente. Cubierta devuelta a Gomería Casanova.";
    }

// Código modificado con validación de nombre duplicado :
if (isset($_POST['guardar_nueva_cubierta'])) {
    $nombre_nueva = trim($_POST['nombre_nueva_cubierta']); // Usar trim para eliminar espacios en blanco
    
    if (!empty($nombre_nueva)) {
        // Verificar si ya existe una cubierta con el mismo nombre
        $sql_check_duplicate = "SELECT id FROM cubiertas WHERE LOWER(nombre) = LOWER(?)";
        $stmt_check = $conn->prepare($sql_check_duplicate);
        $stmt_check->bind_param("s", $nombre_nueva);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Ya existe una cubierta con ese nombre
            $mensaje_error = "Error: Ya existe una cubierta con el nombre '{$nombre_nueva}'. Por favor, elija un nombre diferente.";
        } else {
            // El nombre no está duplicado, proceder con la inserción
            $sql_insert_nueva = "INSERT INTO cubiertas (nombre, estado) VALUES (?, 'casanova')";
            $stmt_nueva = $conn->prepare($sql_insert_nueva);
            $stmt_nueva->bind_param("s", $nombre_nueva);
            
            if ($stmt_nueva->execute()) {
                // Obtener el ID de la nueva cubierta insertada
                $nueva_cubierta_id = $conn->insert_id;
                
                // Registrar el alta en historial_bajas
                $sql_historial_alta = "INSERT INTO historial_bajas 
                                      (cubierta_id, cubierta_nombre, tipo_operacion, motivo) 
                                      VALUES (?, ?, 'alta', 'Nueva cubierta agregada')";
                $stmt_historial = $conn->prepare($sql_historial_alta);
                $stmt_historial->bind_param("is", $nueva_cubierta_id, $nombre_nueva);
                $stmt_historial->execute();
                $stmt_historial->close();
                
                $mensaje_exito = "Cubierta '{$nombre_nueva}' agregada exitosamente y registrada en el historial.";
            } else {
                $mensaje_error = "Error al agregar la cubierta: " . $stmt_nueva->error;
            }
            
            $stmt_nueva->close();
        }
        
        $stmt_check->close();
    } else {
        $mensaje_error = "Error: El nombre de la cubierta no puede estar vacío.";
    }
}

    // Obtener las cubiertas en Gomería Casanova con la cantidad de reconstrucciones
    $sql_casanova = "
        SELECT c.id, c.nombre, COUNT(r.id) AS cantidad_reconstrucciones
        FROM cubiertas c
        LEFT JOIN reconstrucciones r ON c.id = r.cubierta_id
        WHERE c.estado = 'casanova' AND c.coche_id IS NULL
        GROUP BY c.id, c.nombre
    ";
    $result_casanova = $conn->query($sql_casanova);
    $cubiertas_casanova = [];
    if ($result_casanova->num_rows > 0) {
        while ($row = $result_casanova->fetch_assoc()) {
            $cubiertas_casanova[] = $row;
        }
    }

    // Obtener las cubiertas en SILACOR con la cantidad de reconstrucciones
    $sql_silacor = "
        SELECT c.id, c.nombre, COUNT(r.id) AS cantidad_reconstrucciones
        FROM cubiertas c
        LEFT JOIN reconstrucciones r ON c.id = r.cubierta_id
        WHERE c.estado = 'silacor' AND c.coche_id IS NULL
        GROUP BY c.id, c.nombre
    ";
    $result_silacor = $conn->query($sql_silacor);
    $cubiertas_silacor = [];
    if ($result_silacor->num_rows > 0) {
        while ($row = $result_silacor->fetch_assoc()) {
            $cubiertas_silacor[] = $row;
        }
    }

    $conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Gomería</title>
    <!-- Enlace a Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Enlace a Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Enlace a archivo CSS externo -->
    <link rel="stylesheet" href="styles-dark.css">
	<link rel="stylesheet" href="header-fix.css">
    <link rel="stylesheet" href="nuevo-header.css">
</head>
<body>
    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1">GESTIÓN DE GOMERÍA</h1>
            </div>
        </header>

        <div class="content">
            <!-- Sección de navegación -->
            <div class="nav-buttons">
                <button class="boton slide-in" onclick="window.location.href='index.php'">
                    <i class="fas fa-home"></i> VOLVER A INICIO
                </button>
            </div>
            
            <?php if(isset($mensaje_exito)): ?>
                <div class="mensaje-exito fade-in pulse">
                    <i class="fas fa-check-circle"></i> <?php echo $mensaje_exito; ?>
                </div>
            <?php endif; ?>
			
			<?php if(isset($mensaje_error)): ?>
			<div class="mensaje-error fade-in shake">
				<i class="fas fa-exclamation-circle"></i> <?php echo $mensaje_error; ?>
				</div>
			<?php endif; ?>

<!-- Formulario para agregar nueva cubierta -->
            <div class="selector-container fade-in delay-4">
                <h2><i class="fas fa-plus-circle"></i> Agregar Nueva Cubierta</h2>
                <form method="post" class="selector-form">
                    <div class="form-group">
                        <label for="nombre_nueva_cubierta"><i class="fas fa-tag"></i> Nombre de la Nueva Cubierta:</label>
                        <input type="text" id="nombre_nueva_cubierta" name="nombre_nueva_cubierta" required 
                            class="text-input" placeholder="Ingrese nombre de cubierta">
                    </div>
                    <button type="submit" name="guardar_nueva_cubierta" class="boton boton-success">
                        <i class="fas fa-save"></i> Guardar Cubierta
                    </button>
                </form>
            </div>

            <!-- Sección de Cubiertas en Gomería Casanova -->
            <div class="cubiertas-container">
                <div class="lista-cubiertas fade-in delay-2">
                    <h2><i class="fas fa-warehouse"></i> CUBIERTAS EN GOMERÍA CASANOVA</h2>
                    <?php if (empty($cubiertas_casanova)): ?>
                        <p class="mensaje-info"><i class="fas fa-info-circle"></i> No hay cubiertas en stock en Gomería Casanova.</p>
                    <?php else: ?>
                        <div class="cards-container">
                            <?php foreach ($cubiertas_casanova as $cubierta): ?>
                                <div class="cubierta-card hover-glow">
                                    <div class="cubierta-title">
                                        <i class="fas fa-warehouse"></i> <?php echo $cubierta['nombre']; ?>
                                    </div>
                                    <div class="cubierta-info">
                                        <p><i class="fas fa-hashtag"></i> <strong>ID:</strong> <?php echo $cubierta['id']; ?></p>
                                        <p><i class="fas fa-sync-alt"></i> <strong>Reconstrucciones:</strong> <?php echo $cubierta['cantidad_reconstrucciones']; ?></p>
                                        <div class="cubierta-imagen">
											<img src="cubierta.png" alt="Imagen de cubierta">
										</div>
										<div class="action-buttons">
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="cubierta_id_silacor" value="<?php echo $cubierta['id']; ?>">
                                                <button type="submit" name="enviar_silacor" class="boton">
                                                    <i class="fas fa-truck"></i> Enviar a SILACOR
                                                </button>
                                            </form>
                                            <button type="button" onclick="abrirModalBaja(<?php echo $cubierta['id']; ?>, '<?php echo $cubierta['nombre']; ?>')" class="boton boton-danger">
                                                <i class="fas fa-trash"></i> Dar de Baja
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sección de Cubiertas en SILACOR -->
                <div class="lista-cubiertas fade-in delay-3">
                    <h2><i class="fas fa-industry"></i> CUBIERTAS EN SILACOR</h2>
                    <?php if (empty($cubiertas_silacor)): ?>
                        <p class="mensaje-info"><i class="fas fa-info-circle"></i> No hay cubiertas en SILACOR.</p>
                    <?php else: ?>
                        <div class="cards-container">
                            <?php foreach ($cubiertas_silacor as $cubierta): ?>
                                <div class="cubierta-card hover-glow">
                                    <div class="cubierta-title">
                                        <i class="fas fa-industry"></i> <?php echo $cubierta['nombre']; ?>
                                    </div>
                                    <div class="cubierta-info">
                                        <p><i class="fas fa-hashtag"></i> <strong>ID:</strong> <?php echo $cubierta['id']; ?></p>
                                        <p><i class="fas fa-sync-alt"></i> <strong>Reconstrucciones:</strong> <?php echo $cubierta['cantidad_reconstrucciones']; ?></p>
                                        <div class="action-buttons">
                                            <button type="button" onclick="abrirModalBaja(<?php echo $cubierta['id']; ?>, '<?php echo $cubierta['nombre']; ?>')" class="boton boton-danger">
                                                <i class="fas fa-trash"></i> Dar de Baja
                                            </button>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="cubierta_id_reconstruccion" value="<?php echo $cubierta['id']; ?>">
                                                <button type="submit" name="reconstruccion_lista" class="boton boton-success">
                                                    <i class="fas fa-check-circle"></i> Reconstrucción Lista
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <footer class="fade-in delay-5">
               <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Empresa Casanova S.A todos los derechos reservados</p>
        </footer>
    </div>

    <!-- Modal para dar de baja una cubierta -->
    <div id="modal-baja" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModalBaja()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-trash-alt"></i> Dar de Baja Cubierta</h3>
            </div>
            <div class="modal-body">
                <p>Está por dar de baja la cubierta <strong id="nombre-cubierta-baja"></strong>.</p>
                <p>Por favor, indique el motivo de la baja:</p>
                
                <form id="form-baja" method="post">
                    <input type="hidden" id="cubierta-id-baja" name="cubierta_id_baja">
                    <div class="form-group">
                        <label for="motivo-baja"><i class="fas fa-question-circle"></i> Motivo de la baja:</label>
                        <select name="motivo_baja" id="motivo-baja" class="form-control" required>
                            <option value="">-- Seleccionar Motivo --</option>
                            <option value="Desgaste">Desgaste</option>
                            <option value="Daño irreparable">Daño irreparable</option>
                            <option value="Error técnico">Error técnico</option>
                            <option value="Obsolescencia">Obsolescencia</option>
                            <option value="Fin de vida útil">Fin de vida útil</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div id="otro-motivo-container" class="form-group" style="display: none;">
                        <label for="otro-motivo"><i class="fas fa-pencil-alt"></i> Especifique el motivo:</label>
                        <input type="text" name="otro_motivo" id="otro-motivo" class="form-control">
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="boton" onclick="cerrarModalBaja()">Cancelar</button>
                        <button type="submit" class="boton boton-danger" name="dar_baja">
                            <i class="fas fa-trash-alt"></i> Confirmar Baja
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Script para manejar animaciones
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Efecto hover para tarjetas de cubiertas
            const cards = document.querySelectorAll('.cubierta-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 0 15px rgba(52, 152, 219, 0.5)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.5)';
                });
            });
            
            // Manejar el cambio en el selector de motivo de baja
            const selectMotivo = document.getElementById('motivo-baja');
            const otroMotivoContainer = document.getElementById('otro-motivo-container');
            const otroMotivoInput = document.getElementById('otro-motivo');
            
            if (selectMotivo) {
                selectMotivo.addEventListener('change', function() {
                    if (this.value === 'Otro') {
                        otroMotivoContainer.style.display = 'block';
                        otroMotivoInput.required = true;
                    } else {
                        otroMotivoContainer.style.display = 'none';
                        otroMotivoInput.required = false;
                    }
                });
            }
            
            // Validar el formulario de baja
            const formBaja = document.getElementById('form-baja');
            if (formBaja) {
                formBaja.addEventListener('submit', function(e) {
                    const motivoSelect = document.getElementById('motivo-baja');
                    
                    if (!motivoSelect.value) {
                        e.preventDefault();
                        alert('Por favor, seleccione un motivo para la baja.');
                        return false;
                    }
                    
                    if (motivoSelect.value === 'Otro') {
                        const otroMotivo = document.getElementById('otro-motivo');
                        if (!otroMotivo.value.trim()) {
                            e.preventDefault();
                            alert('Por favor, especifique el motivo de la baja.');
                            return false;
                        }
                        
                        // Reemplazar el valor del motivo con el texto personalizado
                        motivoSelect.value = otroMotivo.value.trim();
                    }
                    
                    return true;
                });
            }
        });
        
        // Funciones para manejar el modal de baja
        function abrirModalBaja(cubiertaId, cubiertaNombre) {
            document.getElementById('cubierta-id-baja').value = cubiertaId;
            document.getElementById('nombre-cubierta-baja').textContent = cubiertaNombre;
            document.getElementById('modal-baja').style.display = 'block';
        }
        
        function cerrarModalBaja() {
            document.getElementById('modal-baja').style.display = 'none';
            document.getElementById('form-baja').reset();
            document.getElementById('otro-motivo-container').style.display = 'none';
        }
        
        // Cerrar el modal cuando se hace clic fuera de él
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modal-baja');
            if (event.target === modal) {
                cerrarModalBaja();
            }
        });
    </script>

<style>
    /* Estilos específicos para gomería */
    .cards-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 15px;
    }
    
    .cubierta-card {
        background-color: var(--color-card-bg);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        width: calc(50% - 10px);
        min-width: 300px;
        overflow: hidden;
        transition: all 0.3s;
    }
    
    @media (max-width: 768px) {
        .cubierta-card {
            width: 100%;
        }
    }
    
    .cubierta-title {
        background: linear-gradient(to right, var(--color-primary), #2980b9);
        color: white;
        padding: 12px 15px;
        font-weight: 600;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .cubierta-info {
        padding: 15px;
    }
    
    .cubierta-info p {
        margin: 10px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .cubierta-info p i {
        color: var(--color-primary);
        width: 20px;
        text-align: center;
    }
    
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
    }
    
    .lista-cubiertas {
        margin-bottom: 30px;
        width: 100%;
    }
    
    .boton-danger {
        background: linear-gradient(to right, #e74c3c, #c0392b);
    }
    
    .boton-success {
        background: linear-gradient(to right, #2ecc71, #27ae60);
    }
    
    .mensaje-info {
        padding: 15px;
        background-color: rgba(52, 152, 219, 0.2);
        border-left: 5px solid var(--color-primary);
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 15px;
        border-radius: var(--border-radius);
    }
    
    .mensaje-info i {
        color: var(--color-primary);
        font-size: 20px;
    }
    
    .text-input {
        width: 40%;
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid var(--color-border);
        border-radius: var(--border-radius);
        font-size: 16px;
        background-color: #333;
        color: var(--color-text);
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s;
    }
    
    .text-input:focus {
        border-color: var(--color-primary);
        outline: none;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
    }
    
    .hover-glow:hover {
        box-shadow: var(--glow);
    }
    
    /* Estilos para el modal de baja */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
    }
    
    .modal-content {
        background-color: #2c3e50;
        border-radius: 8px;
        max-width: 500px;
        margin: 100px auto;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        position: relative;
    }
    
    .close-modal {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        color: #bdc3c7;
        cursor: pointer;
    }
    
    .close-modal:hover {
        color: #e74c3c;
    }
    
    .modal-header {
        border-bottom: 1px solid #34495e;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }
    
    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: #3498db;
        margin: 0;
    }
    
    .modal-body {
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #f5f5f5;
    }
    
    .form-control {
        width: 100%;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #34495e;
        background-color: #34495e;
        color: white;
        font-family: 'Poppins', sans-serif;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .mensaje-exito,
    .mensaje-error {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        text-align: center;
        font-weight: 500;
    }
    
    .mensaje-exito {
        background-color: #27ae60;
        color: white;
    }
    
    .mensaje-error {
        background-color: #e74c3c;
        color: white;
    }
	
	/* Contenedor principal de la tarjeta con posicionamiento relativo */
.cubierta-card {
    position: relative;
    overflow: hidden; /* Para asegurar que la imagen no se salga de la tarjeta */
}

/* Estilo para el contenedor de la imagen */
.cubierta-imagen {
    position: absolute;
    top: 40px; /* Ajusta este valor para mover la imagen verticalmente */
    right: 30px; /* Ajusta este valor para mover la imagen horizontalmente */
    width: 100px; /* Ancho de la imagen - ajusta según necesites */
    height: auto; /* Altura automática para mantener la proporción */
    opacity: 0.8; /* Ajusta la transparencia si lo deseas */
    z-index: 1; /* Para controlar la superposición con otros elementos */
}

/* Estilo para la imagen dentro del contenedor */
.cubierta-imagen img {
    width: 100%;
    height: auto;
    border-radius: 5px; /* Opcional: para bordes redondeados */
    box-shadow: 0 2px 5px rgba(0,0,0,0.2); /* Opcional: para añadir sombra */
}

/* Asegurar que la información de la cubierta no quede tapada por la imagen */
.cubierta-info {
    position: relative;
    z-index: 2; /* Mayor que el z-index de la imagen */
}

/* Estilos responsivos para la imagen */
@media (max-width: 768px) {
    .cubierta-imagen {
        width: 80px; /* Hacer la imagen más pequeña en pantallas pequeñas */
    }
}

@media (max-width: 480px) {
    .cubierta-imagen {
        width: 60px; /* Aún más pequeña en móviles */
        top: 5px;
        right: 5px;
    }
} 
</style>
</body>
</html>