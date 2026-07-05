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

function site_copyright_text(): string
{
    return '© 2026 ' . site_brand_name() . '. All rights reserved.';
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
    return [54]; // The Stem That Carried a River
}

function brand_pdf_file(string $absolutePath, bool $compact = false): bool
{
    $absolutePath = trim($absolutePath);
    if ($absolutePath === '' || !is_file($absolutePath)) {
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
        . escapeshellarg(site_copyright_text()) . ' '
        . escapeshellarg($mode);

    exec($cmd, $output, $code);

    return $code === 0 && is_file($absolutePath);
}

/**
 * Stamp logo onto every page of a TCPDF document before saving.
 */
function brand_tcpdf_document(TCPDF $pdf): void
{
    $logoPath = site_logo_pdf_disk_path();
    if (!is_file($logoPath)) {
        return;
    }

    $pageCount = $pdf->getNumPages();
    if ($pageCount < 1) {
        return;
    }

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

        @$pdf->Image($logoPath, $logoX, $logoY, $logoW, 0, 'PNG');
    }
}
