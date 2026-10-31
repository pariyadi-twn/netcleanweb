<?php

return [
    'quote_ttl_minutes' => (int) env('PRICING_QUOTE_TTL_MINUTES', 15),
    'express_surcharge_percent' => (int) env('PRICING_EXPRESS_SURCHARGE_PERCENT', 50),
    'delivery_fee_rupiah' => (int) env('PRICING_DELIVERY_FEE_RUPIAH', 5000),
];
