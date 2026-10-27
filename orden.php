<?php
/**
 * Seguimiento público de una orden.
 * Entra el cliente con el enlace que le mandaron por WhatsApp; no requiere sesión.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['t'] ?? '');
$o = null;

if (strlen($token) >= 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT o.*,
               CONCAT(c.nombre,' ',c.apellido) AS cliente,
               m.marca_texto, m.modelo, m.placa, m.color,
               COALESCE(CONCAT(t.nombre,' ',t.apellido),'') AS tecnico,
               s.nombre AS taller, s.telefono AS taller_tel, s.direccion AS taller_dir,
               s.logo_path, s.color_primario, s.horario
        FROM ordenes o
        JOIN clientes c       ON c.id = o.cliente_id
        JOIN motocicletas m   ON m.id = o.motocicleta_id
        LEFT JOIN usuarios t  ON t.id = o.tecnico_id
        JOIN sucursales s     ON s.id = o.sucursal_id
        WHERE o.token_publico = ? LIMIT 1");
    $stmt->execute([$token]);
    $o = $stmt->fetch();
}

if (!$o) {
    http_response_code(404);
    $colorPrim = '#1d4ed8';
} else {
    $colorPrim = $o['color_primario'] ?: '#1d4ed8';

    $detalle = $db->prepare("
        SELECT od.cantidad, od.precio_unit, od.subtotal, p.nombre, p.tipo
        FROM orden_detalle od JOIN productos p ON p.id = od.producto_id
        WHERE od.orden_id = ?");
    $detalle->execute([$o['id']]);
    $detalle = $detalle->fetchAll();

    $pagos = $db->prepare("SELECT monto, metodo_pago, created_at FROM anticipos WHERE orden_id=? ORDER BY created_at");
    $pagos->execute([$o['id']]);
    $pagos = $pagos->fetchAll();
}

// Etapas del trabajo, en orden
$etapas = [
    'abierta'    => ['Recibida',   'La moto ingresó al taller',          'fa-clipboard-check'],
    'en_proceso' => ['En trabajo', 'El técnico está trabajando en ella', 'fa-screwdriver-wrench'],
    'lista'      => ['Lista',      'Terminada, puedes venir por ella',   'fa-circle-check'],
    'entregada'  => ['Entregada',  'La moto ya fue entregada',           'fa-flag-checkered'],
];
$orden_etapas = array_keys($etapas);
$indiceActual = $o ? array_search($o['estado'], $orden_etapas) : -1;

$telLimpio = $o ? preg_replace('/[^0-9]/', '', $o['taller_tel'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $o ? 'Orden ' . sanitize($o['numero_orden']) : 'Orden no encontrada' ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= $colorPrim ?>">
    <link rel="icon" href="<?= BASE_URL ?>/assets/icons/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/icon-180.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{ --cp: <?= $colorPrim ?>; }
        body{
            font-family:'Inter',system-ui,sans-serif;
            background:#f6f8fb; color:#0f172a;
            -webkit-font-smoothing:antialiased;
        }
        .tabular{ font-variant-numeric: tabular-nums; }
        .marca{ background: color-mix(in srgb, var(--cp) 16%, #0b1220); }
        .acento{ color: var(--cp); }
        .bg-acento{ background: var(--cp); }
        @media (prefers-reduced-motion: reduce){ *{transition:none!important;animation:none!important} }
    </style>
</head>
<body class="min-h-screen">

<?php if (!$o): ?>
    <!-- Enlace inválido -->
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-8 text-center">
            <img src="<?= BASE_URL ?>/assets/brand/logo.png" alt="" class="h-9 w-auto object-contain mx-auto mb-6 opacity-70">
            <div class="w-14 h-14 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4 text-xl">
                <i class="fas fa-link-slash"></i>
            </div>
            <h1 class="text-[18px] font-semibold mb-2">No encontramos esta orden</h1>
            <p class="text-[14px] text-slate-500 leading-relaxed">
                El enlace puede estar incompleto o haber cambiado. Pídele al taller que te lo envíe de nuevo.
            </p>
        </div>
    </div>

<?php else: ?>

<!-- Cabecera del taller -->
<header class="marca text-white">
    <div class="max-w-lg mx-auto px-5 pt-7 pb-8">
        <div class="flex items-center gap-3 mb-7">
            <?php if ($o['logo_path']): ?>
                <img src="<?= BASE_URL ?>/<?= sanitize($o['logo_path']) ?>" alt=""
                     class="w-10 h-10 object-contain rounded-lg bg-white/10 p-1">
                <div class="min-w-0">
                    <div class="text-[14px] font-semibold truncate"><?= sanitize($o['taller']) ?></div>
                    <?php if ($o['taller_tel']): ?>
                    <div class="text-[12px] text-white/50"><?= sanitize($o['taller_tel']) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <img src="<?= BASE_URL ?>/assets/brand/logo-light.png" alt="<?= sanitize($o['taller']) ?>"
                     class="h-9 w-auto object-contain">
                <?php if ($o['taller_tel']): ?>
                <div class="text-[12px] text-white/50 ml-auto flex-shrink-0"><?= sanitize($o['taller_tel']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="text-[12.5px] text-white/50 mb-1">Orden <?= sanitize($o['numero_orden']) ?></div>
        <h1 class="text-[26px] font-semibold tracking-tight leading-tight">
            <?= sanitize(trim($o['marca_texto'] . ' ' . $o['modelo'])) ?>
        </h1>
        <div class="text-[13.5px] text-white/60 mt-1">
            <?= sanitize($o['cliente']) ?><?= $o['placa'] ? ' · ' . sanitize($o['placa']) : '' ?>
        </div>
    </div>
</header>

<main class="max-w-lg mx-auto px-5 -mt-4 pb-12 space-y-4">

    <!-- Estado -->
    <section class="bg-white border border-slate-200 rounded-2xl p-5">
        <?php if ($o['estado'] === 'cancelada'): ?>
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-ban"></i>
                </span>
                <div>
                    <div class="text-[16px] font-semibold text-red-700">Orden cancelada</div>
                    <p class="text-[13.5px] text-slate-500 mt-0.5">Comunícate con el taller si tienes dudas.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="text-[12.5px] font-medium text-slate-400 mb-4">Estado del trabajo</div>
            <ol class="space-y-0">
                <?php foreach ($orden_etapas as $i => $clave):
                    [$titulo, $desc, $icono] = $etapas[$clave];
                    $hecho  = $i <  $indiceActual;
                    $actual = $i === $indiceActual;
                    $ultimo = $i === count($orden_etapas) - 1;
                ?>
                <li class="flex gap-3.5">
                    <div class="flex flex-col items-center flex-shrink-0">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-[12px] border
                            <?= $actual ? 'bg-acento text-white border-transparent'
                                : ($hecho ? 'bg-emerald-50 text-emerald-600 border-emerald-100'
                                          : 'bg-slate-50 text-slate-300 border-slate-200') ?>">
                            <i class="fas <?= $hecho ? 'fa-check' : $icono ?>"></i>
                        </span>
                        <?php if (!$ultimo): ?>
                        <span class="w-px flex-1 min-h-[26px] <?= $hecho ? 'bg-emerald-200' : 'bg-slate-200' ?>"></span>
                        <?php endif; ?>
                    </div>
                    <div class="<?= $ultimo ? 'pb-0' : 'pb-5' ?> pt-1">
                        <div class="text-[14.5px] <?= $actual ? 'font-semibold text-slate-900' : ($hecho ? 'font-medium text-slate-700' : 'text-slate-400') ?>">
                            <?= $titulo ?>
                        </div>
                        <?php if ($actual): ?>
                        <p class="text-[13px] text-slate-500 mt-0.5"><?= $desc ?></p>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <!-- Fechas -->
    <section class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="grid grid-cols-2 gap-4 text-[13.5px]">
            <div>
                <div class="text-[12px] text-slate-400 mb-0.5">Ingresó</div>
                <div class="font-medium"><?= formatDate($o['fecha_ingreso']) ?></div>
            </div>
            <div>
                <div class="text-[12px] text-slate-400 mb-0.5">
                    <?= $o['fecha_salida_real'] ? 'Entregada' : 'Entrega estimada' ?>
                </div>
                <div class="font-medium">
                    <?= $o['fecha_salida_real']
                        ? formatDate($o['fecha_salida_real'])
                        : ($o['fecha_salida_esperada'] ? formatDate($o['fecha_salida_esperada']) : 'Por confirmar') ?>
                </div>
            </div>
            <?php if ($o['tecnico']): ?>
            <div>
                <div class="text-[12px] text-slate-400 mb-0.5">Técnico</div>
                <div class="font-medium"><?= sanitize($o['tecnico']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($o['kilometraje_ingreso']): ?>
            <div>
                <div class="text-[12px] text-slate-400 mb-0.5">Kilometraje</div>
                <div class="font-medium tabular"><?= number_format($o['kilometraje_ingreso']) ?> km</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($o['diagnostico']): ?>
        <div class="mt-4 pt-4 border-t border-slate-100">
            <div class="text-[12px] text-slate-400 mb-1">Trabajo solicitado</div>
            <p class="text-[13.5px] text-slate-600 leading-relaxed"><?= nl2br(sanitize($o['diagnostico'])) ?></p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Detalle y cuenta -->
    <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 pt-5 pb-3">
            <div class="text-[12.5px] font-medium text-slate-400">Detalle del trabajo</div>
        </div>

        <?php if (empty($detalle)): ?>
        <p class="px-5 pb-5 text-[13.5px] text-slate-500">
            Todavía no se ha agregado nada. El monto puede cambiar conforme avance la revisión.
        </p>
        <?php else: ?>
        <ul class="px-5 divide-y divide-slate-100">
            <?php foreach ($detalle as $d): ?>
            <li class="py-3 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[13.5px] text-slate-800 leading-snug"><?= sanitize($d['nombre']) ?></div>
                    <div class="text-[12px] text-slate-400 tabular">
                        <?= rtrim(rtrim(number_format($d['cantidad'],2),'0'),'.') ?> × <?= formatMoney($d['precio_unit']) ?>
                        · <?= $d['tipo']==='servicio' ? 'Servicio' : 'Repuesto' ?>
                    </div>
                </div>
                <div class="text-[13.5px] font-medium tabular flex-shrink-0"><?= formatMoney($d['subtotal']) ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="px-5 py-4 mt-1 bg-slate-50 border-t border-slate-100 space-y-2">
            <div class="flex justify-between text-[13.5px]">
                <span class="text-slate-500">Total</span>
                <span class="font-medium tabular"><?= formatMoney($o['total']) ?></span>
            </div>
            <?php if ($o['anticipo'] > 0): ?>
            <div class="flex justify-between text-[13.5px]">
                <span class="text-slate-500">Ya pagaste</span>
                <span class="font-medium text-emerald-700 tabular">− <?= formatMoney($o['anticipo']) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between pt-2 border-t border-slate-200">
                <span class="text-[14px] font-semibold">
                    <?= $o['saldo'] > 0 ? 'Saldo pendiente' : 'Saldo' ?>
                </span>
                <span class="text-[17px] font-semibold tabular <?= $o['saldo'] > 0 ? 'text-slate-900' : 'text-emerald-700' ?>">
                    <?= $o['saldo'] > 0 ? formatMoney($o['saldo']) : 'Pagado' ?>
                </span>
            </div>
        </div>
    </section>

    <?php if ($pagos): ?>
    <section class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="text-[12.5px] font-medium text-slate-400 mb-3">Pagos recibidos</div>
        <ul class="space-y-2.5">
            <?php foreach ($pagos as $p): ?>
            <li class="flex items-center justify-between text-[13.5px]">
                <span class="text-slate-500"><?= formatDate($p['created_at']) ?> · <span class="capitalize"><?= sanitize($p['metodo_pago']) ?></span></span>
                <span class="font-medium text-emerald-700 tabular"><?= formatMoney($p['monto']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- Contacto -->
    <?php if ($telLimpio): ?>
    <a href="https://wa.me/<?= strlen($telLimpio) <= 8 ? '502' . $telLimpio : $telLimpio ?>?text=<?= rawurlencode('Hola, consulto por mi orden ' . $o['numero_orden']) ?>"
       target="_blank" rel="noopener"
       class="flex items-center justify-center gap-2.5 bg-[#25D366] text-white font-medium text-[14.5px] py-3.5 rounded-xl">
        <i class="fab fa-whatsapp text-lg"></i> Escribir al taller
    </a>
    <?php endif; ?>

    <p class="text-center text-[12px] text-slate-400 px-4 leading-relaxed">
        Esta página se actualiza sola conforme avanza el trabajo.
        <?php if ($o['horario']): ?><br>Horario: <?= sanitize($o['horario']) ?><?php endif; ?>
    </p>
</main>

<?php endif; ?>

</body>
</html>
