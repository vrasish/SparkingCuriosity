<?php

declare(strict_types=1);

function site_copyright_text(): string
{
    return '© 2026 Sparking Curiosity. All rights reserved.';
}

function site_logo_disk_path(): string
{
    return __DIR__ . '/assets/sparking-curiosity-logo.png';
}

function site_logo_url(): string
{
    return asset_url('assets/sparking-curiosity-logo.png');
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

function brand_pdf_file(string $absolutePath): bool
{
    $absolutePath = trim($absolutePath);
    if ($absolutePath === '' || !is_file($absolutePath)) {
        return false;
    }

    $logoPath = site_logo_disk_path();
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

    $cmd = escapeshellarg($python) . ' '
        . escapeshellarg($scriptPath) . ' '
        . escapeshellarg($absolutePath) . ' '
        . escapeshellarg($logoPath) . ' '
        . escapeshellarg(site_copyright_text());

    exec($cmd, $output, $code);

    return $code === 0 && is_file($absolutePath);
}

/**
 * Stamp logo onto every page of a TCPDF document before saving.
 */
function brand_tcpdf_document(TCPDF $pdf): void
{
    $logoPath = site_logo_disk_path();
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
        $margin = 5.0;
        $logoW = min(56.0, $pageW * 0.22);
        $info = @getimagesize($logoPath);
        $aspect = ($info && (int) $info[0] > 0) ? ((float) $info[1] / (float) $info[0]) : 0.35;
        $logoH = $logoW * $aspect;
        $logoX = $pageW - $margin - $logoW;
        $logoY = $pageH - $margin - $logoH;
        $pad = 1.5;

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($logoX - $pad, $logoY - $pad, $logoW + (2 * $pad), $logoH + (2 * $pad), 'F');

        @$pdf->Image($logoPath, $logoX, $logoY, $logoW, 0, 'PNG');
    }
}
