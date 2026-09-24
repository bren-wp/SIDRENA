<?php
declare(strict_types=1);

final class SidrenaStatic
{
    public const VERSION = '1.7.0';

    private $config;
    private $baseDir;
    private $storageDir;
    private $sourceDir;
    private $generatedDir;
    private $archiveDir;

    public static function fromConfigFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException('Sidrena config.php nije pronađen.');
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('Sidrena config.php mora vratiti PHP array.');
        }

        return new self($config, dirname($path));
    }

    public function __construct(array $config, string $baseDir)
    {
        $defaults = [
            'site_name' => 'Sidrena cjenik',
            'timezone' => 'Europe/Zagreb',
            'currency' => 'EUR',
            'strict_validation' => true,
            'auto_generate_on_request' => true,
            'auto_generate_min_interval' => 60,
            'retention_days' => 30,
            'csv_delimiter' => ';',
            'web_generate_token' => '',
            'location' => [
                'id' => 'webshop',
                'kind' => 'webshop',
                'code' => 'WEB-01',
                'address' => 'online',
                'sequence_products' => 1,
                'sequence_services' => 2,
            ],
            'sources' => [
                'products' => 'storage/source/products.csv',
                'services' => 'storage/source/services.csv',
            ],
        ];

        $this->config = $this->mergeRecursive($defaults, $config);
        if (!in_array($this->config['csv_delimiter'], [';', ',', "\t"], true)) {
            $this->config['csv_delimiter'] = ';';
        }
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->storageDir = $this->baseDir . DIRECTORY_SEPARATOR . 'storage';
        $this->sourceDir = $this->storageDir . DIRECTORY_SEPARATOR . 'source';
        $this->generatedDir = $this->storageDir . DIRECTORY_SEPARATOR . 'generated';
        $this->archiveDir = $this->storageDir . DIRECTORY_SEPARATOR . 'archive';

        foreach ([$this->storageDir, $this->sourceDir, $this->generatedDir, $this->archiveDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Nije moguće kreirati Sidrena direktorij: ' . $dir);
            }
        }
    }

    public function config(): array
    {
        return $this->config;
    }

    public function runtimeInfo(): array
    {
        return [
            'edition' => 'static-php',
            'version' => self::VERSION,
            'php' => PHP_VERSION,
            'timezone' => (string) $this->config['timezone'],
            'storage_writable' => is_writable($this->storageDir),
            'products_source' => $this->sourcePath('products'),
            'services_source' => $this->sourcePath('services'),
        ];
    }

    public function generateIfNeeded(): array
    {
        if (empty($this->config['auto_generate_on_request'])) {
            return $this->status();
        }

        if (!$this->needsGeneration()) {
            return $this->status();
        }

        $attemptFile = $this->storageDir . DIRECTORY_SEPARATOR . 'last-auto-attempt';
        $minInterval = max(10, (int) $this->config['auto_generate_min_interval']);
        if (is_file($attemptFile) && (time() - (int) @filemtime($attemptFile)) < $minInterval) {
            return $this->status();
        }

        @touch($attemptFile);
        try {
            return $this->generate();
        } catch (Throwable $e) {
            $this->writeStatus([
                'ok' => false,
                'generated_at' => null,
                'message' => $e->getMessage(),
            ]);
            return $this->status();
        }
    }

    public function needsGeneration(): bool
    {
        $snapshot = $this->generatedDir . DIRECTORY_SEPARATOR . 'snapshot.json';
        if (!is_file($snapshot)) {
            return true;
        }

        $snapshotMtime = (int) filemtime($snapshot);
        foreach (['products', 'services'] as $type) {
            $source = $this->sourcePath($type);
            if (is_file($source) && (int) filemtime($source) > $snapshotMtime) {
                return true;
            }
        }

        $configPath = $this->baseDir . DIRECTORY_SEPARATOR . 'config.php';
        return is_file($configPath) && (int) filemtime($configPath) > $snapshotMtime;
    }

    public function generate(): array
    {
        $lockPath = $this->storageDir . DIRECTORY_SEPARATOR . 'generation.lock';
        $lock = fopen($lockPath, 'c+');
        if (!$lock) {
            throw new RuntimeException('Nije moguće otvoriti generation.lock.');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Generiranje Sidrena cjenika je već u tijeku.');
        }

        try {
            $products = $this->loadProducts();
            $services = $this->loadServices();

            $issues = array_merge(
                $this->validateProducts($products),
                $this->validateServices($services)
            );

            if ($issues && !empty($this->config['strict_validation'])) {
                throw new RuntimeException('Objava je zaustavljena: ' . implode(' | ', array_slice($issues, 0, 20)));
            }

            $tz = new DateTimeZone((string) $this->config['timezone']);
            $now = new DateTimeImmutable('now', $tz);
            $stamp = $now->format('YmdHis');
            $generatedAt = $now->format(DATE_ATOM);

            $files = [];
            $files['products_csv'] = $this->writeCatalogCsv('products', $products, $stamp);
            $files['products_xml'] = $this->writeCatalogXml('products', $products, $stamp);
            $files['services_csv'] = $this->writeCatalogCsv('services', $services, $stamp);
            $files['services_xml'] = $this->writeCatalogXml('services', $services, $stamp);

            $snapshot = [
                'schema' => 1,
                'generator' => 'Sidrena Static PHP ' . self::VERSION,
                'generated_at' => $generatedAt,
                'location' => $this->publicLocation(),
                'products' => $this->publicRows($products),
                'services' => $this->publicRows($services),
                'warnings' => $issues,
            ];
            $this->writeJsonAtomic($this->generatedDir . DIRECTORY_SEPARATOR . 'snapshot.json', $snapshot);

            $archiveEntries = [];
            foreach ($files as $key => $file) {
                $archiveEntries[] = $this->archiveCurrentFile($key, $file, $now);
            }
            $this->appendArchiveIndex($archiveEntries);
            $this->pruneArchive();

            $manifest = [
                'schema' => 1,
                'edition' => 'static-php',
                'version' => self::VERSION,
                'generated_at' => $generatedAt,
                'location' => $this->publicLocation(),
                'counts' => [
                    'products' => count($products),
                    'services' => count($services),
                ],
                'files' => $files,
                'warnings' => $issues,
            ];
            $this->writeJsonAtomic($this->generatedDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);

            $status = [
                'ok' => true,
                'generated_at' => $generatedAt,
                'message' => 'Sidrena cjenik je uspješno generiran.',
                'counts' => $manifest['counts'],
                'warnings' => $issues,
            ];
            $this->writeStatus($status);
            return $status;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function status(): array
    {
        $path = $this->generatedDir . DIRECTORY_SEPARATOR . 'status.json';
        if (!is_file($path)) {
            return [
                'ok' => false,
                'generated_at' => null,
                'message' => 'Cjenik još nije generiran.',
            ];
        }

        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [
            'ok' => false,
            'generated_at' => null,
            'message' => 'Status cjenika nije čitljiv.',
        ];
    }

    public function readSnapshot(): array
    {
        $path = $this->generatedDir . DIRECTORY_SEPARATOR . 'snapshot.json';
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || 1 !== (int) ($data['schema'] ?? 0)) {
            return [];
        }
        $data['products'] = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
        $data['services'] = isset($data['services']) && is_array($data['services']) ? $data['services'] : [];
        return $data;
    }

    public function readManifest(): array
    {
        $path = $this->generatedDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public function readArchiveIndex(int $limit = 50): array
    {
        $path = $this->archiveDir . DIRECTORY_SEPARATOR . 'index.json';
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return [];
        }
        $data = array_values(array_filter($data, 'is_array'));
        usort($data, static function (array $a, array $b): int {
            return strcmp((string) ($b['generated_at'] ?? ''), (string) ($a['generated_at'] ?? ''));
        });
        return array_slice($data, 0, max(1, min(500, $limit)));
    }

    public function resolveDownload(string $key, string $archiveId = ''): ?array
    {
        if ($archiveId !== '') {
            foreach ($this->readArchiveIndex(500) as $entry) {
                if (hash_equals((string) ($entry['id'] ?? ''), $archiveId)) {
                    $path = (string) ($entry['path'] ?? '');
                    if ($this->isSafeStorageFile($path) && is_file($path)) {
                        return [
                            'path' => $path,
                            'name' => basename((string) ($entry['public_name'] ?? basename($path))),
                            'mime' => $this->mimeForFormat((string) ($entry['format'] ?? '')),
                        ];
                    }
                    return null;
                }
            }
            return null;
        }

        $manifest = $this->readManifest();
        if (!isset($manifest['files'][$key]) || !is_array($manifest['files'][$key])) {
            return null;
        }
        $file = $manifest['files'][$key];
        $path = (string) ($file['path'] ?? '');
        if (!$this->isSafeStorageFile($path) || !is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => basename((string) ($file['public_name'] ?? basename($path))),
            'mime' => $this->mimeForFormat((string) ($file['format'] ?? '')),
        ];
    }

    private function loadProducts(): array
    {
        $rows = $this->loadCsv($this->sourcePath('products'));
        $out = [];
        $codes = [];
        foreach ($rows as $index => $row) {
            $item = $this->canonicalProduct($row);
            if ($this->rowIsEmpty($item)) {
                continue;
            }
            $codeKey = $this->lower((string) $item['sifra']);
            if ($codeKey !== '') {
                if (isset($codes[$codeKey])) {
                    throw new RuntimeException('Duplicirana šifra proizvoda: ' . $item['sifra']);
                }
                $codes[$codeKey] = true;
            }
            $item['_row'] = $index + 2;
            $out[] = $item;
        }
        return $out;
    }

    private function loadServices(): array
    {
        $rows = $this->loadCsv($this->sourcePath('services'));
        $out = [];
        foreach ($rows as $index => $row) {
            $item = $this->canonicalService($row);
            if ($this->rowIsEmpty($item)) {
                continue;
            }
            $item['_row'] = $index + 2;
            $out[] = $item;
        }
        return $out;
    }

    private function loadCsv(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        if ((int) filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('CSV datoteka je veća od dopuštenih 25 MB.');
        }

        $raw = (string) file_get_contents($path);
        $raw = $this->normalizeTextEncoding($raw);
        if ($raw === '') {
            return [];
        }

        $firstLine = strtok($raw, "\r\n");
        $delimiter = $this->detectDelimiter((string) $firstLine);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $raw);
        rewind($stream);

        $headers = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!is_array($headers)) {
            fclose($stream);
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($headers as $header) {
            $key = $this->importHeaderKey((string) $header);
            if ($key === '') {
                $key = '_empty_' . count($normalized);
            }
            if (isset($seen[$key])) {
                fclose($stream);
                throw new RuntimeException('CSV sadrži duplicirano zaglavlje nakon normalizacije: ' . $key);
            }
            $seen[$key] = true;
            $normalized[] = $key;
        }

        $rows = [];
        while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if (count($rows) >= 50000) {
                fclose($stream);
                throw new RuntimeException('CSV ima više od dopuštenih 50.000 redaka.');
            }
            $row = [];
            foreach ($normalized as $i => $key) {
                if (strpos($key, '_empty_') === 0) {
                    continue;
                }
                $row[$key] = isset($values[$i]) ? trim((string) $values[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }

    private function canonicalProduct(array $row): array
    {
        $current = $this->decimal($this->pick($row, ['maloprodajna_cijena', 'cijena', 'price']));
        $anchor = $this->decimal($this->pick($row, ['sidrena_cijena', 'anchor_price', 'referentna_cijena']));
        $quantityRaw = $this->pick($row, ['kolicina_pakiranja', 'neto_kolicina', 'quantity']);
        $quantity = $this->decimal($quantityRaw);
        $quantityUnit = $this->normalizeUnit($this->pick($row, ['jedinica_pakiranja', 'quantity_unit']));
        if (($quantity === '' || $quantityUnit === '') && $quantityRaw !== '') {
            $parsed = $this->parseQuantityWithUnit($quantityRaw);
            if ($parsed) {
                $quantity = $quantity !== '' ? $quantity : $parsed['quantity'];
                $quantityUnit = $quantityUnit !== '' ? $quantityUnit : $parsed['unit'];
            }
        }

        $unitStatus = $this->normalizeUnitStatus($this->pick($row, ['jedinicna_cijena_status', 'unit_price_status', 'primjenjivost_jedinicne_cijene']));
        $unit = $this->pick($row, ['jedinica_mjere', 'unit']);
        $unitPrice = $this->decimal($this->pick($row, ['cijena_za_jedinicu_mjere', 'unit_price']));

        if ($unitStatus === 'required' && $unitPrice === '' && $current !== '' && $quantity !== '' && $quantityUnit !== '') {
            $calc = $this->calculateUnitPrice($current, $quantity, $quantityUnit);
            if ($calc) {
                $unit = $calc['unit'];
                $unitPrice = $calc['unit_price'];
            }
        }
        if ($unitStatus === 'not_required' || $unitStatus === 'exception') {
            $unit = '';
            $unitPrice = '';
        }

        $saleName = $this->pick($row, ['naziv_posebnog_oblika_prodaje', 'sale_name']);
        $availability = $this->lower($this->pick($row, ['dostupnost', 'availability']));
        if (!in_array($availability, ['dostupno', 'nedostupno'], true)) {
            $availability = 'dostupno';
        }

        return [
            'naziv' => $this->clean($this->pick($row, ['naziv', 'naziv_proizvoda', 'name'])),
            'sifra' => $this->clean($this->pick($row, ['sifra', 'sifra_proizvoda', 'sku', 'code'])),
            'marka' => $this->clean($this->pick($row, ['marka', 'brand'])),
            'jedinica_mjere' => $this->clean($unit),
            'cijena_za_jedinicu_mjere' => $unitPrice,
            'maloprodajna_cijena' => $current,
            'posebni_oblik_prodaje' => $saleName !== '' ? 'da' : $this->yesNo($this->pick($row, ['posebni_oblik_prodaje', 'sale'])),
            'naziv_posebnog_oblika_prodaje' => $this->clean($saleName),
            'sidrena_cijena' => $anchor,
            'datum_sidrene_cijene' => $this->dateValue($this->pick($row, ['datum_sidrene_cijene', 'anchor_date', 'referentni_datum'])),
            'barkod' => $this->clean($this->pick($row, ['barkod', 'barcode', 'ean', 'gtin'])),
            'dostupnost' => $availability,
            '_unit_status' => $unitStatus,
            '_quantity' => $quantity,
            '_quantity_unit' => $quantityUnit,
        ];
    }

    private function canonicalService(array $row): array
    {
        $saleName = $this->pick($row, ['naziv_posebnog_oblika_prodaje', 'sale_name']);
        return [
            'naziv_usluge' => $this->clean($this->pick($row, ['naziv_usluge', 'naziv', 'name'])),
            'vrsta_usluge' => $this->clean($this->pick($row, ['vrsta_usluge', 'vrsta', 'type'])),
            'opseg_usluge' => $this->clean($this->pick($row, ['opseg_usluge', 'opseg', 'scope'])),
            'pripadajuci_troskovi' => $this->clean($this->pick($row, ['pripadajuci_troskovi', 'troskovi', 'costs'])),
            'ugradbena_zamjenska_roba' => $this->clean($this->pick($row, ['ugradbena_zamjenska_roba', 'ugradbena_roba', 'included_goods'])),
            'maloprodajna_cijena' => $this->decimal($this->pick($row, ['maloprodajna_cijena', 'cijena', 'price'])),
            'posebni_oblik_prodaje' => $saleName !== '' ? 'da' : $this->yesNo($this->pick($row, ['posebni_oblik_prodaje', 'sale'])),
            'naziv_posebnog_oblika_prodaje' => $this->clean($saleName),
            'sidrena_cijena' => $this->decimal($this->pick($row, ['sidrena_cijena', 'anchor_price', 'referentna_cijena'])),
            'datum_sidrene_cijene' => $this->dateValue($this->pick($row, ['datum_sidrene_cijene', 'anchor_date', 'referentni_datum'])),
        ];
    }

    private function publicRows(array $rows): array
    {
        $public = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $key) {
                if (strpos((string) $key, '_') === 0) {
                    unset($row[$key]);
                }
            }
            $public[] = $row;
        }
        return $public;
    }

    private function validateProducts(array $rows): array
    {
        $issues = [];
        foreach ($rows as $row) {
            $label = $row['naziv'] !== '' ? $row['naziv'] : 'redak #' . (int) $row['_row'];
            foreach (['naziv' => 'naziv', 'sifra' => 'šifra', 'marka' => 'marka', 'maloprodajna_cijena' => 'maloprodajna cijena', 'sidrena_cijena' => 'sidrena cijena', 'barkod' => 'barkod', 'dostupnost' => 'dostupnost'] as $key => $name) {
                if (trim((string) ($row[$key] ?? '')) === '') {
                    $issues[] = $label . ': nedostaje ' . $name;
                }
            }
            $status = (string) ($row['_unit_status'] ?? 'review');
            if ($status === 'review') {
                $issues[] = $label . ': primjenjivost jedinične cijene nije pregledana';
            } elseif ($status === 'required' && ($row['jedinica_mjere'] === '' || $row['cijena_za_jedinicu_mjere'] === '')) {
                $issues[] = $label . ': obvezna jedinična cijena nije potpuno unesena';
            }
            if ($row['posebni_oblik_prodaje'] === 'da' && $row['naziv_posebnog_oblika_prodaje'] === '') {
                $issues[] = $label . ': posebni oblik prodaje nema naziv';
            }
        }
        return $issues;
    }

    private function validateServices(array $rows): array
    {
        $issues = [];
        foreach ($rows as $row) {
            $label = $row['naziv_usluge'] !== '' ? $row['naziv_usluge'] : 'usluga redak #' . (int) $row['_row'];
            foreach (['naziv_usluge' => 'naziv usluge', 'maloprodajna_cijena' => 'maloprodajna cijena', 'sidrena_cijena' => 'sidrena cijena'] as $key => $name) {
                if (trim((string) ($row[$key] ?? '')) === '') {
                    $issues[] = $label . ': nedostaje ' . $name;
                }
            }
            if ($row['posebni_oblik_prodaje'] === 'da' && $row['naziv_posebnog_oblika_prodaje'] === '') {
                $issues[] = $label . ': posebni oblik prodaje nema naziv';
            }
        }
        return $issues;
    }

    private function writeCatalogCsv(string $catalog, array $rows, string $stamp): array
    {
        $headers = $catalog === 'products'
            ? ['naziv','sifra','marka','jedinica_mjere','cijena_za_jedinicu_mjere','maloprodajna_cijena','posebni_oblik_prodaje','naziv_posebnog_oblika_prodaje','sidrena_cijena','datum_sidrene_cijene','barkod','dostupnost']
            : ['naziv_usluge','vrsta_usluge','opseg_usluge','pripadajuci_troskovi','ugradbena_zamjenska_roba','maloprodajna_cijena','posebni_oblik_prodaje','naziv_posebnog_oblika_prodaje','sidrena_cijena','datum_sidrene_cijene'];

        $path = $this->generatedDir . DIRECTORY_SEPARATOR . $catalog . '.csv';
        $tmp = $path . '.tmp';
        $handle = fopen($tmp, 'wb');
        if (!$handle) {
            throw new RuntimeException('Nije moguće otvoriti privremeni CSV za zapis.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        $delimiter = (string) $this->config['csv_delimiter'];
        fputcsv($handle, $headers, $delimiter, '"', '');
        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = $this->csvSafeCell((string) ($row[$header] ?? ''));
            }
            if (fputcsv($handle, $values, $delimiter, '"', '') === false) {
                fclose($handle);
                @unlink($tmp);
                throw new RuntimeException('Greška pri zapisu CSV retka.');
            }
        }
        fflush($handle);
        fclose($handle);
        $this->replaceAtomic($tmp, $path);

        return $this->fileDescriptor($path, $catalog, 'csv', $stamp, count($rows));
    }

    private function writeCatalogXml(string $catalog, array $rows, string $stamp): array
    {
        $headers = $catalog === 'products'
            ? ['naziv','sifra','marka','jedinica_mjere','cijena_za_jedinicu_mjere','maloprodajna_cijena','posebni_oblik_prodaje','naziv_posebnog_oblika_prodaje','sidrena_cijena','datum_sidrene_cijene','barkod','dostupnost']
            : ['naziv_usluge','vrsta_usluge','opseg_usluge','pripadajuci_troskovi','ugradbena_zamjenska_roba','maloprodajna_cijena','posebni_oblik_prodaje','naziv_posebnog_oblika_prodaje','sidrena_cijena','datum_sidrene_cijene'];

        $path = $this->generatedDir . DIRECTORY_SEPARATOR . $catalog . '.xml';
        $tmp = $path . '.tmp';
        $handle = fopen($tmp, 'wb');
        if (!$handle) {
            throw new RuntimeException('Nije moguće otvoriti privremeni XML za zapis.');
        }

        $root = $catalog === 'products' ? 'proizvodi' : 'usluge';
        $item = $catalog === 'products' ? 'proizvod' : 'usluga';
        fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<{$root}>\n");
        foreach ($rows as $row) {
            fwrite($handle, "  <{$item}>\n");
            foreach ($headers as $header) {
                $value = htmlspecialchars((string) ($row[$header] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
                fwrite($handle, "    <{$header}>{$value}</{$header}>\n");
            }
            fwrite($handle, "  </{$item}>\n");
        }
        fwrite($handle, "</{$root}>\n");
        fflush($handle);
        fclose($handle);
        $this->replaceAtomic($tmp, $path);

        return $this->fileDescriptor($path, $catalog, 'xml', $stamp, count($rows));
    }

    private function fileDescriptor(string $path, string $catalog, string $format, string $stamp, int $rows): array
    {
        return [
            'catalog' => $catalog,
            'format' => $format,
            'path' => $path,
            'public_name' => $this->buildPublicFilename($catalog, $format, $stamp),
            'size' => (int) filesize($path),
            'sha256' => hash_file('sha256', $path),
            'rows' => $rows,
        ];
    }

    private function archiveCurrentFile(string $key, array $file, DateTimeImmutable $now): array
    {
        $subdir = $this->archiveDir . DIRECTORY_SEPARATOR . $now->format('Y') . DIRECTORY_SEPARATOR . $now->format('m');
        if (!is_dir($subdir) && !mkdir($subdir, 0755, true) && !is_dir($subdir)) {
            throw new RuntimeException('Nije moguće kreirati arhivski direktorij.');
        }

        $id = $now->format('YmdHis') . '-' . $key . '-' . substr((string) $file['sha256'], 0, 12);
        $archivePath = $subdir . DIRECTORY_SEPARATOR . $id . '.' . $file['format'];
        if (!copy((string) $file['path'], $archivePath)) {
            throw new RuntimeException('Nije moguće arhivirati generirani cjenik.');
        }

        return [
            'id' => $id,
            'generated_at' => $now->format(DATE_ATOM),
            'catalog' => $file['catalog'],
            'format' => $file['format'],
            'public_name' => $file['public_name'],
            'path' => $archivePath,
            'size' => (int) filesize($archivePath),
            'sha256' => hash_file('sha256', $archivePath),
            'rows' => (int) $file['rows'],
        ];
    }

    private function appendArchiveIndex(array $entries): void
    {
        $path = $this->archiveDir . DIRECTORY_SEPARATOR . 'index.json';
        $current = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $current = is_array($decoded) ? $decoded : [];
        }
        foreach ($entries as $entry) {
            $current[] = $entry;
        }
        $this->writeJsonAtomic($path, $current);
    }

    private function pruneArchive(): void
    {
        $days = max(30, (int) $this->config['retention_days']);
        $cutoff = time() - ($days * 86400);
        $path = $this->archiveDir . DIRECTORY_SEPARATOR . 'index.json';
        $entries = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $entries = is_array($decoded) ? $decoded : [];
        }
        $keep = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $ts = strtotime((string) ($entry['generated_at'] ?? '')) ?: 0;
            $file = (string) ($entry['path'] ?? '');
            if ($ts > 0 && $ts < $cutoff) {
                if ($this->isSafeStorageFile($file) && is_file($file)) {
                    @unlink($file);
                }
                continue;
            }
            $keep[] = $entry;
        }
        $this->writeJsonAtomic($path, $keep);
    }

    private function writeStatus(array $status): void
    {
        $this->writeJsonAtomic($this->generatedDir . DIRECTORY_SEPARATOR . 'status.json', $status);
    }

    private function writeJsonAtomic(string $path, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            throw new RuntimeException('JSON kodiranje nije uspjelo.');
        }
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Nije moguće zapisati privremeni JSON.');
        }
        $this->replaceAtomic($tmp, $path);
    }

    private function replaceAtomic(string $tmp, string $target): void
    {
        if (!rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Atomska objava datoteke nije uspjela.');
        }
    }

    private function buildPublicFilename(string $catalog, string $format, string $stamp): string
    {
        $location = $this->config['location'];
        $sequenceKey = $catalog === 'services' ? 'sequence_services' : 'sequence_products';
        $sequence = max(1, (int) ($location[$sequenceKey] ?? 1));
        return sprintf(
            '%s_%s_%s_%06d_%s.%s',
            $this->filenamePart((string) ($location['kind'] ?? 'objekt')),
            $this->filenamePart((string) ($location['address'] ?? 'online')),
            $this->filenamePart((string) ($location['code'] ?? '01')),
            $sequence,
            $stamp,
            $format
        );
    }

    private function publicLocation(): array
    {
        $location = $this->config['location'];
        return [
            'id' => $this->clean((string) ($location['id'] ?? 'webshop')),
            'kind' => $this->clean((string) ($location['kind'] ?? 'webshop')),
            'code' => $this->clean((string) ($location['code'] ?? 'WEB-01')),
            'address' => $this->clean((string) ($location['address'] ?? 'online')),
        ];
    }

    private function sourcePath(string $type): string
    {
        $relative = (string) ($this->config['sources'][$type] ?? '');
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
        return $this->baseDir . DIRECTORY_SEPARATOR . $relative;
    }

    private function isSafeStorageFile(string $path): bool
    {
        $realStorage = realpath($this->storageDir);
        $realPath = realpath($path);
        return $realStorage !== false && $realPath !== false && strpos($realPath, $realStorage . DIRECTORY_SEPARATOR) === 0;
    }

    private function mimeForFormat(string $format): string
    {
        if ($format === 'csv') {
            return 'text/csv; charset=UTF-8';
        }
        if ($format === 'xml') {
            return 'application/xml; charset=UTF-8';
        }
        return 'application/octet-stream';
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);
        $delimiter = (string) key($counts);
        return reset($counts) > 0 ? $delimiter : (string) $this->config['csv_delimiter'];
    }

    private function importHeaderKey(string $header): string
    {
        $header = trim($header);
        $header = strtr($header, [
            'č'=>'c','ć'=>'c','đ'=>'d','š'=>'s','ž'=>'z',
            'Č'=>'C','Ć'=>'C','Đ'=>'D','Š'=>'S','Ž'=>'Z',
        ]);
        $header = $this->lower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim((string) $header, '_');
    }

    private function normalizeTextEncoding(string $contents): string
    {
        if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
            $contents = substr($contents, 3);
        }
        if (preg_match('//u', $contents) === 1) {
            return $contents;
        }

        $encodings = preg_match('/[\xA9\xAE\xB9\xBE]/', $contents)
            ? ['ISO-8859-2', 'Windows-1250']
            : ['Windows-1250', 'ISO-8859-2'];

        foreach ($encodings as $encoding) {
            if (function_exists('mb_convert_encoding')) {
                try {
                    $converted = @mb_convert_encoding($contents, 'UTF-8', $encoding);
                    if (is_string($converted) && preg_match('//u', $converted) === 1) {
                        return $converted;
                    }
                } catch (Throwable $e) {
                }
            }
            if (function_exists('iconv')) {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $contents);
                if (is_string($converted) && preg_match('//u', $converted) === 1) {
                    return $converted;
                }
            }
        }
        throw new RuntimeException('CSV nije moguće pretvoriti u valjani UTF-8.');
    }

    private function decimal(string $value): string
    {
        $value = trim(str_replace(["\xC2\xA0", ' '], '', $value));
        if ($value === '') {
            return '';
        }
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && $dot !== false) {
            if ($comma > $dot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($comma !== false) {
            $value = str_replace(',', '.', $value);
        }
        if (!is_numeric($value)) {
            return '';
        }
        $number = (float) $value;
        if (!is_finite($number) || abs($number) > 99999999999999.0) {
            return '';
        }
        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }

    private function normalizeUnit(string $unit): string
    {
        $unit = $this->lower(trim($unit));
        $unit = str_replace([' ', '.', '²', '^2', '³', '^3'], ['', '', '2', '2', '3', '3'], $unit);
        $aliases = [
            'miligram'=>'mg','miligrami'=>'mg','gram'=>'g','grama'=>'g','grami'=>'g',
            'dekagram'=>'dag','dekagrama'=>'dag','kilogram'=>'kg','kilograma'=>'kg',
            'mililitar'=>'ml','mililitara'=>'ml','centilitar'=>'cl','centilitara'=>'cl',
            'decilitar'=>'dl','decilitara'=>'dl','lit'=>'l','litra'=>'l','litre'=>'l','litara'=>'l','liter'=>'l',
            'metar'=>'m','metra'=>'m','metara'=>'m','komad'=>'kom','komada'=>'kom','pcs'=>'kom','pc'=>'kom',
        ];
        return isset($aliases[$unit]) ? $aliases[$unit] : $unit;
    }

    private function parseQuantityWithUnit(string $value): array
    {
        $value = trim(str_replace("\xC2\xA0", ' ', $value));
        if ($value === '' || !preg_match('/^([0-9][0-9\s.,]*)\s*([^0-9\s.,].*)?$/u', $value, $matches)) {
            return [];
        }
        $quantity = $this->decimal($matches[1]);
        if ($quantity === '' || (float) $quantity <= 0) {
            return [];
        }
        return [
            'quantity' => $quantity,
            'unit' => isset($matches[2]) ? $this->normalizeUnit($matches[2]) : '',
        ];
    }

    private function calculateUnitPrice(string $retailPrice, string $quantity, string $quantityUnit): array
    {
        $retailPrice = $this->decimal($retailPrice);
        $quantity = $this->decimal($quantity);
        $unitKey = $this->normalizeUnit($quantityUnit);
        if ($retailPrice === '' || $quantity === '' || (float) $quantity <= 0) {
            return [];
        }
        $units = [
            'mg'=>['base'=>'kg','multiplier'=>0.000001],'g'=>['base'=>'kg','multiplier'=>0.001],
            'dag'=>['base'=>'kg','multiplier'=>0.01],'kg'=>['base'=>'kg','multiplier'=>1.0],
            'ml'=>['base'=>'l','multiplier'=>0.001],'cl'=>['base'=>'l','multiplier'=>0.01],
            'dl'=>['base'=>'l','multiplier'=>0.1],'l'=>['base'=>'l','multiplier'=>1.0],
            'mm'=>['base'=>'m','multiplier'=>0.001],'cm'=>['base'=>'m','multiplier'=>0.01],
            'dm'=>['base'=>'m','multiplier'=>0.1],'m'=>['base'=>'m','multiplier'=>1.0],
            'mm2'=>['base'=>'m²','multiplier'=>0.000001],'cm2'=>['base'=>'m²','multiplier'=>0.0001],
            'dm2'=>['base'=>'m²','multiplier'=>0.01],'m2'=>['base'=>'m²','multiplier'=>1.0],
            'cm3'=>['base'=>'m³','multiplier'=>0.000001],'dm3'=>['base'=>'m³','multiplier'=>0.001],
            'm3'=>['base'=>'m³','multiplier'=>1.0],'kom'=>['base'=>'kom','multiplier'=>1.0],
        ];
        if (!isset($units[$unitKey])) {
            return [];
        }
        $baseAmount = (float) $quantity * (float) $units[$unitKey]['multiplier'];
        if ($baseAmount <= 0) {
            return [];
        }
        $price = (float) $retailPrice / $baseAmount;
        if (!is_finite($price) || $price < 0 || $price > 99999999999999.0) {
            return [];
        }
        return [
            'unit' => $units[$unitKey]['base'],
            'unit_price' => $this->decimal(number_format($price, 4, '.', '')),
        ];
    }

    private function normalizeUnitStatus(string $status): string
    {
        $status = $this->importHeaderKey($status);
        $map = [
            'required'=>'required','obvezna'=>'required','obvezno'=>'required','da'=>'required',
            'not_required'=>'not_required','nije_primjenjiva'=>'not_required','nije_primjenjivo'=>'not_required',
            'exception'=>'exception','iznimka'=>'exception',
            'review'=>'review','provjera'=>'review','potrebna_provjera'=>'review',''=>'review',
        ];
        return isset($map[$status]) ? $map[$status] : 'review';
    }

    private function dateValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        foreach (['!Y-m-d', '!d.m.Y.', '!d.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }
        return $this->clean($value);
    }

    private function csvSafeCell(string $value): string
    {
        if ($value === '' || is_numeric(str_replace([' ', ','], ['', '.'], $value))) {
            return $value;
        }
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value) || preg_match('/^[\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function filenamePart(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F\/\\:*?"<>|]+/u', ' ', strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        $value = trim((string) $value, " .\t\n\r\0\x0B_-");
        return $value !== '' ? $value : 'objekt';
    }

    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->importHeaderKey($key);
            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }
        return '';
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === '_row' || strpos((string) $key, '_') === 0) {
                continue;
            }
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function yesNo(string $value): string
    {
        $value = $this->lower(trim($value));
        return in_array($value, ['da', 'yes', '1', 'true'], true) ? 'da' : 'ne';
    }

    private function clean(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value);
        return trim((string) $value);
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function mergeRecursive(array $defaults, array $config): array
    {
        foreach ($config as $key => $value) {
            if (isset($defaults[$key]) && is_array($defaults[$key]) && is_array($value)) {
                $defaults[$key] = $this->mergeRecursive($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }
        return $defaults;
    }
}
