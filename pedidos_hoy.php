<?php

declare(strict_types=1);

// Compatibilidad: redirige a la vista única.
header('Location: /pedidos?scope=today', true, 302);
exit;
