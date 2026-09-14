<?php

declare(strict_types=1);

/**
 * CIF PDF Generator — builds filled CIF forms as clean PDF documents.
 *
 * Uses FPDF to generate professional, well-formatted PDF documents for each
 * service type (Pardon, CR Rehab, TRP, Waiver, etc.) based on the client's
 * CIF responses. Each PDF includes:
 *   - Company header with logo placeholder and form title
 *   - Grouped sections matching the CIF question schema
 *   - Field-value pairs and formatted tables (addresses, employment, offences)
 *   - Proper pagination with headers/footers
 *
 * @see src/controllers/CifController.php  — calls this service
 * @see src/config/cif_questions.php       — question schema
 */

namespace App\Services;

class CifPdfGenerator extends \FPDF
{
    /** Company / form title shown in header */
    private string $formTitle = '';

    /** Client full name (shown in header) */
    private string $clientName = '';

    /** Date generated */
    private string $generatedDate = '';

    /** Service-type colour accent (RGB) */
    private array $accentColor = [50, 100, 200];

    /* ──────── colour presets per form type ──────── */
    private const FORM_COLORS = [
        'pardon'         => [107, 91, 58],
        'criminal-rehab' => [61, 26, 92],
        'trp'            => [12, 74, 80],
        'expunging'      => [26, 62, 92],
        'waiver'         => [26, 92, 42],
        'waiver-renewal' => [26, 92, 42],
    ];

    private const FORM_LABELS = [
        'pardon'         => 'Record Suspension (Pardon)',
        'criminal-rehab' => 'Criminal Rehabilitation',
        'trp'            => 'Temporary Resident Permit (TRP)',
        'expunging'      => 'Record Expungement',
        'waiver'         => 'US Entry Waiver',
        'waiver-renewal' => 'US Waiver Renewal',
    ];

    /* ================================================================
     *  Constructor
     * ================================================================ */
    public function __construct(string $formType, string $clientName)
    {
        parent::__construct('P', 'mm', 'Letter');
        $this->formTitle     = self::FORM_LABELS[$formType] ?? ucfirst($formType);
        $this->clientName    = $clientName;
        $this->generatedDate = date('F j, Y');
        $this->accentColor   = self::FORM_COLORS[$formType] ?? [50, 100, 200];

        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 15, 15);
        $this->AliasNbPages();
    }

    /* ================================================================
     *  FPDF overrides — Header / Footer
     * ================================================================ */
    public function Header(): void
    {
        // Accent bar at top
        [$r, $g, $b] = $this->accentColor;
        $this->SetFillColor($r, $g, $b);
        $this->Rect(0, 0, 216, 4, 'F');

        // Company name
        $this->SetY(8);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor($r, $g, $b);
        $this->Cell(0, 8, 'FPWS - Pardon and Waiver Services', 0, 1, 'L');

        // Form title
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Client Information Form - ' . $this->formTitle, 0, 1, 'L');

        // Client name + date on the right
        $this->SetY(8);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, 'Client: ' . $this->clientName, 0, 1, 'R');
        $this->Cell(0, 5, 'Generated: ' . $this->generatedDate, 0, 1, 'R');

        // Separator line
        $this->SetY(22);
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.5);
        $this->Line(15, 22, 201, 22);

        $this->SetY(26);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(
            0,
            10,
            'FPWS Client Information Form - ' . $this->formTitle .
                '  |  Page ' . $this->PageNo() . '/{nb}',
            0,
            0,
            'C'
        );
    }

    /* ================================================================
     *  Public API — build the full PDF
     * ================================================================ */

    /**
     * Generate the complete PDF for one form type.
     *
     * @param string $formType   Service type key
     * @param array  $data       CIF response data (key => value)
     * @param array  $schema     Full question schema from cif_questions.php
     * @param string $outputPath Absolute path for the output file
     */
    public function generate(string $formType, array $data, array $schema, string $outputPath): void
    {
        $this->AddPage();

        foreach ($schema['sections'] as $section) {
            // Collect questions applicable to this form
            $applicable = [];
            foreach ($section['questions'] as $q) {
                if (in_array($formType, $q['forms'], true)) {
                    $applicable[] = $q;
                }
            }
            if (empty($applicable)) continue;

            $this->renderSectionHeader($section['title']);

            foreach ($applicable as $q) {
                // Skip conditionally hidden fields
                if (!CifVisibility::isVisible($q, $data, [$formType])) continue;

                if ($q['type'] === 'table') {
                    $this->renderTable($q, $data[$q['key']] ?? []);
                } else {
                    $val = $this->formatValue($q, $data[$q['key']] ?? '');
                    $this->renderField($q['label'], $val, !empty($q['required']));
                }
            }

            $this->Ln(2);
        }

        $this->Output('F', $outputPath);
    }

    /* ================================================================
     *  Rendering helpers
     * ================================================================ */

    /** Section header with accent-coloured background strip */
    private function renderSectionHeader(string $title): void
    {
        // Page break if less than 25mm left
        if ($this->GetY() > 245) {
            $this->AddPage();
        }

        $this->Ln(3);
        [$r, $g, $b] = $this->accentColor;
        $this->SetFillColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, '  ' . $this->utf8($title), 0, 1, 'L', true);
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Render a single field-value pair.
     * Label on the left (bold), value on the right.
     */
    private function renderField(string $label, string $value, bool $required): void
    {
        // Page break check
        if ($this->GetY() > 255) {
            $this->AddPage();
        }

        $labelW = 80;
        $valueW = 106;
        $startY = $this->GetY();

        // Label
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(60, 60, 60);
        $labelText = $this->utf8($label);
        if ($required) $labelText .= ' *';
        $this->MultiCell($labelW, 5, $labelText, 0, 'L');
        $labelEndY = $this->GetY();

        // Value
        $this->SetXY(15 + $labelW, $startY);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(0, 0, 0);

        $displayVal = $value !== '' ? $this->utf8($value) : 'N/A';
        $this->MultiCell($valueW, 5, $displayVal, 0, 'L');
        $valueEndY = $this->GetY();

        // Draw subtle underline
        $endY = max($labelEndY, $valueEndY);
        $this->SetY($endY);
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.2);
        $this->Line(15, $endY, 201, $endY);
        $this->Ln(1);
    }

    /**
     * Render a table (addresses, employment, offences, etc.)
     */
    private function renderTable(array $question, array $rows): void
    {
        $columns = $question['columns'] ?? [];
        if (empty($columns)) return;

        // Label
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(60, 60, 60);
        $label = $this->utf8($question['label']);
        if (!empty($question['required'])) $label .= ' *';
        $this->Cell(0, 6, $label, 0, 1, 'L');

        if (!empty($question['help'])) {
            $this->SetFont('Helvetica', 'I', 7);
            $this->SetTextColor(120, 120, 120);
            $this->MultiCell(0, 4, $this->utf8($question['help']), 0, 'L');
        }
        $this->Ln(1);

        // Calculate column widths based on content
        $colCount = count($columns);
        $availW = 186; // 216 - 15 - 15 margins
        $numColW = 10;  // row number column
        $remainW = $availW - $numColW;

        // Give proportional widths: text columns get more, date columns get less
        $colWidths = [];
        $totalWeight = 0;
        foreach ($columns as $col) {
            $weight = $this->getColumnWeight($col);
            $totalWeight += $weight;
            $colWidths[] = $weight;
        }
        foreach ($colWidths as &$w) {
            $w = round(($w / $totalWeight) * $remainW, 1);
        }
        unset($w);

        // Table header
        $this->renderTableHeader($columns, $colWidths, $numColW);

        // Table rows
        if (empty($rows)) {
            // Single empty row
            $this->renderTableRow([], $columns, $colWidths, $numColW, 1);
        } else {
            foreach ($rows as $idx => $row) {
                // Page break check — if near bottom, re-print header
                if ($this->GetY() > 248) {
                    $this->AddPage();
                    $this->renderTableHeader($columns, $colWidths, $numColW);
                }
                $this->renderTableRow($row, $columns, $colWidths, $numColW, $idx + 1);
            }
        }

        $this->Ln(3);
    }

    /** Render table header row */
    private function renderTableHeader(array $columns, array $colWidths, float $numColW): void
    {
        [$r, $g, $b] = $this->accentColor;

        // Lighter accent for header background
        $this->SetFillColor(
            min(255, $r + 160),
            min(255, $g + 160),
            min(255, $b + 160)
        );
        $this->SetFont('Helvetica', 'B', 7);
        $this->SetTextColor(40, 40, 40);

        // # column
        $this->Cell($numColW, 6, '#', 1, 0, 'C', true);

        foreach ($columns as $i => $col) {
            $this->Cell($colWidths[$i], 6, $this->utf8($col['label']), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    /** Render a single table data row */
    private function renderTableRow(array $row, array $columns, array $colWidths, float $numColW, int $rowNum): void
    {
        // Alternating row colour
        if ($rowNum % 2 === 0) {
            $this->SetFillColor(248, 248, 248);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);

        // Calculate row height based on longest cell content
        $maxLines = 1;
        $cellTexts = [];
        foreach ($columns as $i => $col) {
            $val = $this->getCellDisplayValue($row, $col);
            $cellTexts[] = $val;
            $lines = $this->estimateLines($val, $colWidths[$i]);
            if ($lines > $maxLines) $maxLines = $lines;
        }
        $rowH = max(6, $maxLines * 4.5);

        // Row number
        $startY = $this->GetY();
        $this->Cell($numColW, $rowH, (string)$rowNum, 1, 0, 'C', true);

        // Data cells
        foreach ($cellTexts as $i => $text) {
            $x = $this->GetX();
            $y = $startY;

            // Draw cell background + border
            $this->Rect($x, $y, $colWidths[$i], $rowH, 'DF');

            // Write text inside cell with padding
            $this->SetXY($x + 1, $y + 1);
            $this->MultiCell($colWidths[$i] - 2, 4, $this->utf8($text), 0, 'L');

            // Move X to next cell
            $this->SetXY($x + $colWidths[$i], $startY);
        }

        $this->SetY($startY + $rowH);
    }

    /* ================================================================
     *  Utility methods
     * ================================================================ */

    /** Get display value for a table cell */
    private function getCellDisplayValue(array $row, array $col): string
    {
        $val = $row[$col['key']] ?? '';
        if ($col['type'] === 'checkbox') {
            return !empty($val) ? 'Yes' : 'No';
        }
        $s = trim((string)$val);
        return $s !== '' ? $s : 'N/A';
    }

    /** Format a scalar CIF value for PDF display */
    private function formatValue(array $question, $value): string
    {
        if (is_array($value)) {
            // Checkbox groups
            return implode(', ', $value);
        }
        return (string)$value;
    }

    /** Determine column weight for proportional width calculation */
    private function getColumnWeight(array $col): float
    {
        $label = strtolower($col['label'] ?? '');
        if (strpos($label, 'from') !== false || strpos($label, 'to') !== false) {
            return 1.2; // date columns — narrower
        }
        if (strpos($label, 'statute') !== false || strpos($label, 'police') !== false || strpos($label, 'prov') !== false) {
            return 1.0;
        }
        if (strpos($label, 'address') !== false || strpos($label, 'description') !== false || strpos($label, 'employer') !== false) {
            return 3.0; // wide text columns
        }
        return 1.8; // default
    }

    /** Estimate how many lines a text value will take in a given column width */
    private function estimateLines(string $text, float $colWidth): int
    {
        if ($text === '') return 1;
        $charWidth = 2.2; // approximate char width at font size 8
        $charsPerLine = max(1, (int)floor(($colWidth - 2) / $charWidth));
        return max(1, (int)ceil(mb_strlen($text) / $charsPerLine));
    }

    /**
     * Convert UTF-8 to ISO-8859-1 for FPDF compatibility.
     * Falls back to original string if conversion fails.
     */
    private function utf8(string $str): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
        return $converted !== false ? $converted : $str;
    }
}
