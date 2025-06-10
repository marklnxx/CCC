<?php
// vbi.php - Vista Vertical del Bus
// Este archivo NO debe contener DOCTYPE, html, head o body tags
// ya que será incluido dentro de index.php
?>

<!-- Estilos específicos para la vista vertical del bus -->
<style>
    /* Variables globales para la vista vertical */
    :root {
        --bg-color: transparent; /* Cambiado a transparente para integrarse con el fondo de index.php */
        --card-bg: #222;
        --text-color: #ffffff;
        --neon-blue: #3498db;
        --neon-red: #e74c3c;
        --neon-green: #2ecc71;
        --wheel-glow: rgba(52, 152, 219, 0.8);
        --corner-radius: 4px;
        --gradient-top: linear-gradient(90deg, #3498db, #9b59b6, #e74c3c);
    }
    
    /* Tarjeta principal para la vista vertical */
    .vb-card {
        position: relative;
        width: 100%;
        max-width: 155px;
        background-color: var(--card-bg);
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.5);
        padding: 20px;
    }
    
    /* Barra de gradiente superior */
    .vb-gradient-bar {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2.5px;
        background: var(--gradient-top);
    }
    
    /* Título */
    .vb-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--neon-blue);
        margin: 7.5px 0 12.5px;
        text-align: center;
        text-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }
    
    .vb-title i {
        margin-right: 2.5px;
    }
    
    /* Contenedor del bus - Proporción */
    .vb-bus-container {
        position: relative;
        width: 90%;
        aspect-ratio: 7/16;
        border: 1px solid var(--neon-blue);
        border-radius: 5px;
        padding: 10px;
        margin: 10px auto; /* Centrado horizontalmente */
        box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
        overflow: hidden;
    }
    
    /* Bus */
    .vb-bus-body {
        position: absolute;
        background-color: #333;
        border: 2px solid var(--neon-blue);
        box-shadow: 0 0 2.5px var(--wheel-glow);
    }
    
    /* Cuerpo principal del bus - ajustado para el formato vertical */
    .vb-bus-main {
        top: 5%;
        left: 20%;
        width: 60%;
        height: 80%;
        border-radius: 4px 4px 0 0;
    }
    
    /* Espacios para las ruedas */
    .vb-wheel-space {
        position: absolute;
        background-color: #1a1a1a;
        border: 1px solid #3498db;
        border-radius: var(--corner-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        cursor: pointer;
        box-shadow: 0 0 2.5px rgba(52, 152, 219, 0.8);
        height: 5%;
    }
    
    /* Ruedas según la nueva imagen de referencia */
    /* Ruedas superiores (posiciones 1 y 2) */
    .vb-wheel-space-1 {
        top: 10%;
        left: 20%;
        width: 13%;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .vb-wheel-space-2 {
        top: 10%;
        right: 20%;
        width: 13%;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
    }               
    
    /* Ruedas inferiores (posiciones 3, 4, 5 y 6) */
    .vb-wheel-space-3 {
        bottom: 22%;
        left: 20%;
        width: 13%;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .vb-wheel-space-4 {
        bottom: 22%;
        left: 35%;
        width: 13%;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .vb-wheel-space-5 {
        bottom: 22%;
        right: 35%;
        width: 13%;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .vb-wheel-space-6 {
        bottom: 22%;
        right: 20%;
        width: 13%;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    /* Números en las ruedas */
    .vb-wheel-space::after {
        content: attr(data-number);
        color: #ffffff;
        font-weight: bold;
        font-size: 15px;
    }
    
    /* Línea central del bus */
    .vb-bus-line {
        position: absolute;
        top: 15%;
        left: 50%;
        height: 60%;
        width: 2px;
        background-color: #fff;
        transform: translateX(-50%);
    }

    /* Línea horizontal secundaria */
    .vb-bus-line-horizontal {
        position: absolute;
        top: 15%;
        left: 33%;
        height: 2px;
        width: 34%;
        background-color: #fff;
        transform: translateY(-50%);
    }
    
    /* Efecto hover para ruedas */
    .vb-wheel-space.active {
        background-color: #333;
        border-color: var(--neon-blue);
        box-shadow: 
            0 0 5px var(--neon-blue),
            0 0 10px var(--neon-blue),
            inset 0 0 7.5px var(--neon-blue);
        animation: vbPulseGlow 1.5s infinite alternate;
    }
    
    @keyframes vbPulseGlow {
        0% {
            box-shadow: 
                0 0 5px var(--neon-blue),
                0 0 7.5px var(--neon-blue),
                inset 0 0 5px var(--neon-blue);
        }
        100% {
            box-shadow: 
                0 0 7.5px var(--neon-blue),
                0 0 12.5px var(--neon-blue),
                0 0 17.5px var(--neon-blue),
                inset 0 0 7.5px var(--neon-blue);
        }
    }
    
    /* Footer */
    .vb-footer {
        text-align: center;
        font-size: 0.375rem;
        color: #666;
        margin-top: 10px;
    }
    
    /* Ícono de coche - ajustamos tamaño */
    .vb-car-icon {
        display: inline-block;
        color: var(--neon-blue);
        font-size: 0.75rem;
    }
</style>

<!-- Vista Vertical del Bus -->
<div class="vb-card">
    <!-- Barra de gradiente superior -->
    <div class="vb-gradient-bar"></div>
    
    <!-- Título -->
    <div class="vb-title">
        
    </div>
    
    <!-- Contenedor del bus -->
    <div class="vb-bus-container">
        <!-- Cuerpo del bus -->
        <div class="vb-bus-body vb-bus-main"></div>
        
        <!-- Base del bus -->
        <div class="vb-bus-body vb-bus-base"></div>
        
        <!-- Línea central del bus -->
        <div class="vb-bus-line"></div>
        
        <!-- Ruedas con números -->
        <div class="vb-wheel-space vb-wheel-space-1" data-number="1" id="wheel1"></div>
        <div class="vb-wheel-space vb-wheel-space-2" data-number="2" id="wheel2"></div>
        <div class="vb-wheel-space vb-wheel-space-3" data-number="3" id="wheel3"></div>
        <div class="vb-wheel-space vb-wheel-space-4" data-number="4" id="wheel4"></div>
        <div class="vb-wheel-space vb-wheel-space-5" data-number="5" id="wheel5"></div>
        <div class="vb-wheel-space vb-wheel-space-6" data-number="6" id="wheel6"></div>
        <div class="vb-bus-line-horizontal"></div>
    </div>
</div>