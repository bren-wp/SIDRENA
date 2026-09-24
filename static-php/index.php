<?php
declare(strict_types=1);

require __DIR__ . '/src/SidrenaStatic.php';
$sidrena = SidrenaStatic::fromConfigFile(__DIR__ . '/config.php');
$status = $sidrena->generateIfNeeded();
$snapshot = $sidrena->readSnapshot();
$archive = $sidrena->readArchiveIndex(20);
$config = $sidrena->config();

function sidrena_h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function sidrena_money($value, string $currency): string {
    if ($value === '' || $value === null) return '—';
    return number_format((float) $value, 2, ',', '.') . ' ' . $currency;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
$currency = (string) ($config['currency'] ?? 'EUR');
$products = isset($snapshot['products']) && is_array($snapshot['products']) ? $snapshot['products'] : [];
$services = isset($snapshot['services']) && is_array($snapshot['services']) ? $snapshot['services'] : [];
?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= sidrena_h($config['site_name'] ?? 'Sidrena cjenik') ?></title>
<meta name="robots" content="index,follow">
<link rel="stylesheet" href="assets/style.css?v=<?= sidrena_h(SidrenaStatic::VERSION) ?>">
</head>
<body>
<main class="sid-shell">
<header class="sid-hero">
    <div>
        <span class="sid-kicker">SIDRENA STATIC PHP <?= sidrena_h(SidrenaStatic::VERSION) ?></span>
        <h1><?= sidrena_h($config['site_name'] ?? 'Javni cjenik') ?></h1>
        <p>Javni cjenik za web stranice bez CMS-a i bez admin panela.</p>
    </div>
    <div class="sid-status <?= !empty($status['ok']) ? 'is-ok' : 'is-warn' ?>">
        <strong><?= !empty($status['ok']) ? 'Cjenik aktivan' : 'Potrebna provjera' ?></strong>
        <span><?= sidrena_h($status['generated_at'] ?? 'nije generiran') ?></span>
    </div>
</header>

<?php if (empty($snapshot)): ?>
<section class="sid-card sid-empty">
    <h2>Cjenik još nije generiran</h2>
    <p><?= sidrena_h($status['message'] ?? 'Pokrenite php generate.php ili uredite source CSV datoteke.') ?></p>
</section>
<?php else: ?>
<section class="sid-toolbar">
    <label class="sid-search">
        <span>Pretraži cjenik</span>
        <input id="sid-search" type="search" placeholder="Naziv, šifra, marka…" autocomplete="off">
    </label>
    <div class="sid-downloads">
        <a href="download.php?file=products_csv">Proizvodi CSV</a>
        <a href="download.php?file=products_xml">Proizvodi XML</a>
        <a href="download.php?file=services_csv">Usluge CSV</a>
        <a href="download.php?file=services_xml">Usluge XML</a>
        <a href="api.php">JSON API</a>
    </div>
</section>

<div class="sid-tabs" role="tablist" aria-label="Vrsta cjenika">
    <button class="is-active" type="button" data-tab="products">Proizvodi <span><?= count($products) ?></span></button>
    <button type="button" data-tab="services">Usluge <span><?= count($services) ?></span></button>
</div>

<section class="sid-card sid-panel is-active" data-panel="products">
<div class="sid-table-wrap">
<table>
<thead><tr><th>Naziv</th><th>Šifra</th><th>Marka</th><th>Jedinica</th><th>Jed. cijena</th><th>Cijena</th><th>Sidrena cijena</th><th>Dostupnost</th></tr></thead>
<tbody>
<?php foreach ($products as $row): ?>
<tr data-search="<?= sidrena_h(strtolower(implode(' ', [$row['naziv'] ?? '',$row['sifra'] ?? '',$row['marka'] ?? '',$row['barkod'] ?? '']))) ?>">
<td><strong><?= sidrena_h($row['naziv'] ?? '') ?></strong><?php if (($row['posebni_oblik_prodaje'] ?? '') === 'da'): ?><small><?= sidrena_h($row['naziv_posebnog_oblika_prodaje'] ?? '') ?></small><?php endif; ?></td>
<td><?= sidrena_h($row['sifra'] ?? '') ?></td>
<td><?= sidrena_h($row['marka'] ?? '') ?></td>
<td><?= sidrena_h($row['jedinica_mjere'] ?? '') ?></td>
<td><?= sidrena_money($row['cijena_za_jedinicu_mjere'] ?? '', $currency) ?></td>
<td><?= sidrena_money($row['maloprodajna_cijena'] ?? '', $currency) ?></td>
<td><?= sidrena_money($row['sidrena_cijena'] ?? '', $currency) ?><small><?= sidrena_h($row['datum_sidrene_cijene'] ?? '') ?></small></td>
<td><span class="sid-pill"><?= sidrena_h($row['dostupnost'] ?? '') ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p class="sid-no-results" hidden>Nema stavki za zadanu pretragu.</p>
</section>

<section class="sid-card sid-panel" data-panel="services">
<div class="sid-table-wrap">
<table>
<thead><tr><th>Usluga</th><th>Vrsta</th><th>Opseg</th><th>Troškovi</th><th>Cijena</th><th>Sidrena cijena</th></tr></thead>
<tbody>
<?php foreach ($services as $row): ?>
<tr data-search="<?= sidrena_h(strtolower(implode(' ', [$row['naziv_usluge'] ?? '',$row['vrsta_usluge'] ?? '',$row['opseg_usluge'] ?? '']))) ?>">
<td><strong><?= sidrena_h($row['naziv_usluge'] ?? '') ?></strong><?php if (($row['posebni_oblik_prodaje'] ?? '') === 'da'): ?><small><?= sidrena_h($row['naziv_posebnog_oblika_prodaje'] ?? '') ?></small><?php endif; ?></td>
<td><?= sidrena_h($row['vrsta_usluge'] ?? '') ?></td>
<td><?= sidrena_h($row['opseg_usluge'] ?? '') ?></td>
<td><?= sidrena_h($row['pripadajuci_troskovi'] ?? '') ?></td>
<td><?= sidrena_money($row['maloprodajna_cijena'] ?? '', $currency) ?></td>
<td><?= sidrena_money($row['sidrena_cijena'] ?? '', $currency) ?><small><?= sidrena_h($row['datum_sidrene_cijene'] ?? '') ?></small></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p class="sid-no-results" hidden>Nema stavki za zadanu pretragu.</p>
</section>

<?php if ($archive): ?>
<section class="sid-card sid-archive">
<h2>Arhiva objava</h2>
<div class="sid-archive-list">
<?php foreach ($archive as $entry): ?>
<a href="download.php?archive=<?= sidrena_h($entry['id'] ?? '') ?>">
<span><?= sidrena_h(($entry['catalog'] ?? '') === 'products' ? 'Proizvodi' : 'Usluge') ?> · <?= strtoupper(sidrena_h($entry['format'] ?? '')) ?></span>
<strong><?= sidrena_h($entry['generated_at'] ?? '') ?></strong>
<small>SHA-256 <?= sidrena_h(substr((string) ($entry['sha256'] ?? ''), 0, 16)) ?>…</small>
</a>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<?php endif; ?>

<footer>
<span>Sidrena Static PHP <?= sidrena_h(SidrenaStatic::VERSION) ?></span>
<a href="https://sidrene-cijene.com.hr/" rel="noopener">sidrene-cijene.com.hr</a>
</footer>
</main>
<script src="assets/app.js?v=<?= sidrena_h(SidrenaStatic::VERSION) ?>" defer></script>
</body>
</html>
