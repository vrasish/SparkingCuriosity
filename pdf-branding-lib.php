<?php

declare(strict_types=1);

function site_brand_name(): string
{
    return 'Science Fables';
}

function site_page_title(string $pageName): string
{
    return $pageName . ' | ' . site_brand_name();
}

function site_copyright_text(bool $localized = true): string
{
    $rights = 'All rights reserved.';
    if ($localized && function_exists('t')) {
        $rights = t('common.rights_reserved');
    }

    return '© 2026 ' . site_brand_name() . '. ' . $rights;
}

function site_logo_disk_path(): string
{
    return __DIR__ . '/assets/science-fables-logo.png';
}

function site_logo_pdf_disk_path(): string
{
    $transparent = __DIR__ . '/assets/science-fables-logo-transparent.png';

    return is_file($transparent) ? $transparent : site_logo_disk_path();
}

function site_logo_url(): string
{
    return asset_url('assets/science-fables-logo.png');
}

function find_python_with_pymupdf(): ?string
{
    $candidates = ['/tmp/pdfvenv/bin/python3', '/tmp/pdfvenv/bin/python', 'python3', 'python'];
    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' -c ' . escapeshellarg('import fitz');
        exec($cmd, $out, $code);
        if ($code === 0) {
            return $bin;
        }
    }

    return null;
}

function pdf_compact_brand_book_ids(): array
{
    return [
        54, // The Stem That Carried a River
        97, // The Tiny Building Blocks
        98, // Types of Microscopes
        99, // The Secret Inside an Atom
        100, // The Mystery of Everyday Changes
        101, // The Mystery of the Invisible Building Blocks
        102, // The Secret Map of the Elements
        103, // The Mystery of the Electron Shells
        104, // The Atom's Family Tree
    ];
}

function brand_pdf_file(string $absolutePath, bool $compact = false): bool
{
    $absolutePath = trim($absolutePath);
    if ($absolutePath === '' || !is_file($absolutePath)) {
        return false;
    }

    // Spanish story PDFs already include the SciFables logo — do not stamp another one.
    $base = basename($absolutePath);
    if (str_starts_with($base, 'book_es_') || str_contains(strtolower($base), '_es.') || str_contains(strtolower($base), 'spanish') || str_contains(strtolower($base), 'espanol')) {
        return false;
    }

    $logoPath = site_logo_pdf_disk_path();
    if (!is_file($logoPath)) {
        return false;
    }

    $python = find_python_with_pymupdf();
    if ($python === null) {
        return false;
    }

    $scriptPath = __DIR__ . '/tools/brand_pdf.py';
    if (!is_file($scriptPath)) {
        return false;
    }

    $mode = $compact ? 'compact' : 'standard';

    $cmd = escapeshellarg($python) . ' '
        . escapeshellarg($scriptPath) . ' '
        . escapeshellarg($absolutePath) . ' '
        . escapeshellarg($logoPath) . ' '
        . escapeshellarg(site_copyright_text(false)) . ' '
        . escapeshellarg($mode);

    exec($cmd, $output, $code);

    return $code === 0 && is_file($absolutePath);
}

/**
 * Stamp logo onto every page of a TCPDF document before saving.
 */
function brand_tcpdf_document(TCPDF $pdf): void
{
    // Prefer opaque logo for TCPDF: alpha PNGs need GD/Imagick, which may be unavailable.
    $logoPath = site_logo_disk_path();
    $transparent = __DIR__ . '/assets/science-fables-logo-transparent.png';
    if ((extension_loaded('gd') || extension_loaded('imagick')) && is_file($transparent)) {
        $logoPath = $transparent;
    }
    if (!is_file($logoPath)) {
        return;
    }

    $pageCount = $pdf->getNumPages();
    if ($pageCount < 1) {
        return;
    }

    $autoBreak = $pdf->getAutoPageBreak();
    $breakMargin = $pdf->getBreakMargin();
    $pdf->SetAutoPageBreak(false);

    for ($page = 1; $page <= $pageCount; $page++) {
        $pdf->setPage($page);

        $pageW = $pdf->getPageWidth();
        $pageH = $pdf->getPageHeight();
        $sideMargin = 12.0;
        $bottomMargin = 16.0;
        $logoW = min(32.0, $pageW * 0.14);
        $info = @getimagesize($logoPath);
        $aspect = ($info && (int) $info[0] > 0) ? ((float) $info[1] / (float) $info[0]) : 0.44;
        $logoH = $logoW * $aspect;
        $logoX = $pageW - $sideMargin - $logoW;
        $logoY = $pageH - $bottomMargin - $logoH;

        @$pdf->Image($logoPath, $logoX, $logoY, $logoW, $logoH, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }

    // Image placement must never create extra pages.
    while ($pdf->getNumPages() > $pageCount) {
        $pdf->deletePage($pdf->getNumPages());
    }

    $pdf->SetAutoPageBreak($autoBreak, $breakMargin);
}
