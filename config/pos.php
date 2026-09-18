<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Descuento máximo manual por pedido (COP)
    |--------------------------------------------------------------------------
    | Tope absoluto que un usuario con permiso 'aplicarDescuento' puede
    | aplicar como descuento manual. El servicio lo acota con min().
    */
    'max_descuento' => (float) env('POS_MAX_DESCUENTO', 50000),
];
