<?php
// Simulación de precios de mercado actualizados
function getMarketPrices() {
    // En un sistema real, estos datos vendrían de APIs o bases de datos actualizadas
    $baseDate = date('Y-m-d');
    
    return [
        'novillo_gordo' => [
            'min' => 3200 + rand(-100, 200),
            'max' => 3800 + rand(-100, 200),
            'trend' => rand(0, 1) ? 'up' : 'down',
            'change' => rand(50, 150)
        ],
        'vaca_gorda' => [
            'min' => 2800 + rand(-100, 150),
            'max' => 3400 + rand(-100, 150),
            'trend' => rand(0, 1) ? 'up' : 'down',
            'change' => rand(30, 120)
        ],
        'ternero_destete' => [
            'min' => 3600 + rand(-150, 300),
            'max' => 4200 + rand(-150, 300),
            'trend' => rand(0, 1) ? 'up' : 'down',
            'change' => rand(100, 250)
        ],
        'reproductor' => [
            'min' => 8,
            'max' => 25,
            'trend' => 'up',
            'change' => 2
        ],
        'last_update' => $baseDate
    ];
}

function formatPrice($amount, $isMillions = false) {
    if ($isMillions) {
        return '$' . number_format($amount, 0) . 'M';
    } else {
        return '$' . number_format($amount, 0);
    }
}

function getTrendIcon($trend) {
    return $trend === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
}

function getTrendColor($trend) {
    return $trend === 'up' ? '#28a745' : '#dc3545';
}
?>