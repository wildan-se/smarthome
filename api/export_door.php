<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get parameters
$format = $_GET['format'] ?? 'excel';
$daterange = $_GET['daterange'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$sql = "SELECT 
          id,
          status,
          DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i:%s') as updated_at_formatted,
          updated_at
        FROM door_status
        WHERE 1=1";

// Filter by date range
if ($daterange) {
  $dates = explode(' s/d ', $daterange);
  if (count($dates) == 2) {
    $start = $dates[0];
    $end = $dates[1];
    $sql .= " AND DATE(updated_at) BETWEEN '$start' AND '$end'";
  }
}

// Filter by status
if ($status) {
  $sql .= " AND status = '$status'";
}

$sql .= " ORDER BY updated_at DESC";

$result = $conn->query($sql);
$data = [];
$prevTime = null;
while ($row = $result->fetch_assoc()) {
  // Calculate duration if there's a previous record
  if ($prevTime !== null) {
    $duration = strtotime($prevTime) - strtotime($row['updated_at']);
    $data[count($data) - 1]['duration'] = $duration;
  }
  $row['duration'] = null;
  $data[] = $row;
  $prevTime = $row['updated_at'];
}

if ($format === 'excel') {
  exportToExcel($data, $daterange, $status);
} else {
  exportToPDF($data, $daterange, $status);
}

function formatDuration($seconds)
{
  if ($seconds === null) return '-';

  $hours = floor($seconds / 3600);
  $minutes = floor(($seconds % 3600) / 60);
  $secs = $seconds % 60;

  if ($hours > 0) {
    return sprintf('%dj %dm %ds', $hours, $minutes, $secs);
  } elseif ($minutes > 0) {
    return sprintf('%dm %ds', $minutes, $secs);
  } else {
    return sprintf('%ds', $secs);
  }
}

function exportToExcel($data, $daterange, $status)
{
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();

  // Set document properties
  $spreadsheet->getProperties()
    ->setCreator('Smart Home IoT')
    ->setTitle('Log Status Pintu')
    ->setSubject('Log Perubahan Status Pintu')
    ->setDescription('Export data log status pintu (terbuka/tertutup)');

  // Title
  $sheet->setCellValue('A1', 'LAPORAN LOG STATUS PINTU');
  $sheet->mergeCells('A1:D1');
  $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
  $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Info
  $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
  $sheet->mergeCells('A2:D2');

  $infoRow = 3;
  if ($daterange) {
    $sheet->setCellValue('A' . $infoRow, 'Periode: ' . $daterange);
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $infoRow++;
  }

  if ($status) {
    $statusText = ucfirst($status);
    $sheet->setCellValue('A' . $infoRow, 'Filter Status: ' . $statusText);
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $infoRow++;
  }

  // Statistics
  if (count($data) > 0) {
    $terbukaCount = count(array_filter($data, fn($d) => $d['status'] === 'terbuka'));
    $tertutupCount = count(array_filter($data, fn($d) => $d['status'] === 'tertutup'));

    $sheet->setCellValue('A' . $infoRow, 'Statistik: Total Perubahan: ' . count($data) . ' | Terbuka: ' . $terbukaCount . ' | Tertutup: ' . $tertutupCount);
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
    $infoRow++;
  }

  // Header row
  $headerRow = $infoRow + 1;
  $headers = ['No', 'Waktu', 'Status', 'Durasi'];
  $col = 'A';
  foreach ($headers as $header) {
    $sheet->setCellValue($col . $headerRow, $header);
    $sheet->getStyle($col . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle($col . $headerRow)->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('FFC107');
    $sheet->getStyle($col . $headerRow)->getFont()->getColor()->setRGB('000000');
    $sheet->getStyle($col . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
  }

  // Data rows
  $row = $headerRow + 1;
  $no = 1;
  foreach (array_reverse($data) as $item) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $item['updated_at_formatted']);

    $statusText = strtoupper($item['status']);
    $sheet->setCellValue('C' . $row, $statusText);

    // Color status cell
    if ($item['status'] === 'terbuka') {
      $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('28A745');
    } else {
      $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('DC3545');
    }
    $sheet->getStyle('C' . $row)->getFont()->setBold(true);

    $sheet->setCellValue('D' . $row, formatDuration($item['duration']));

    $row++;
  }

  // Apply borders
  $lastRow = $row - 1;
  $sheet->getStyle('A' . $headerRow . ':D' . $lastRow)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

  // Auto-size columns
  foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
  }

  // Summary
  $row += 2;
  $sheet->setCellValue('A' . $row, 'Total Records: ' . count($data));
  $sheet->getStyle('A' . $row)->getFont()->setBold(true);

  // Output
  $filename = 'Log_Door_' . date('YmdHis') . '.xlsx';
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="' . $filename . '"');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;
}

function exportToPDF($data, $daterange, $status)
{
  require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

  $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

  // Set document information
  $pdf->SetCreator('Smart Home IoT');
  $pdf->SetAuthor('Smart Home System');
  $pdf->SetTitle('Log Status Pintu');
  $pdf->SetSubject('Log Perubahan Status Pintu');

  // Remove default header/footer
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);

  // Set margins
  $pdf->SetMargins(15, 15, 15);
  $pdf->SetAutoPageBreak(TRUE, 15);

  // Add a page
  $pdf->AddPage();

  // Title
  $pdf->SetFont('helvetica', 'B', 18);
  $pdf->Cell(0, 10, 'LAPORAN LOG STATUS PINTU', 0, 1, 'C');

  $pdf->SetFont('helvetica', '', 10);
  $pdf->Cell(0, 6, 'Tanggal Export: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

  if ($daterange) {
    $pdf->Cell(0, 6, 'Periode: ' . $daterange, 0, 1, 'C');
  }

  if ($status) {
    $statusText = ucfirst($status);
    $pdf->Cell(0, 6, 'Filter Status: ' . $statusText, 0, 1, 'C');
  }

  // Statistics
  if (count($data) > 0) {
    $terbukaCount = count(array_filter($data, fn($d) => $d['status'] === 'terbuka'));
    $tertutupCount = count(array_filter($data, fn($d) => $d['status'] === 'tertutup'));

    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Total Perubahan: ' . count($data) . ' | Terbuka: ' . $terbukaCount . ' | Tertutup: ' . $tertutupCount, 0, 1, 'L');
  }

  $pdf->Ln(3);

  // Table header
  $pdf->SetFont('helvetica', 'B', 9);
  $pdf->SetFillColor(255, 193, 7);
  $pdf->SetTextColor(0, 0, 0);

  $pdf->Cell(15, 8, 'No', 1, 0, 'C', true);
  $pdf->Cell(55, 8, 'Waktu', 1, 0, 'C', true);
  $pdf->Cell(35, 8, 'Status', 1, 0, 'C', true);
  $pdf->Cell(30, 8, 'Durasi', 1, 1, 'C', true);

  // Table data
  $pdf->SetFont('helvetica', '', 8);
  $pdf->SetTextColor(0, 0, 0);

  $no = 1;
  foreach (array_reverse($data) as $item) {
    $pdf->Cell(15, 7, $no++, 1, 0, 'C');
    $pdf->Cell(55, 7, $item['updated_at_formatted'], 1, 0, 'C');

    // Status with color
    $statusText = strtoupper($item['status']);
    if ($item['status'] === 'terbuka') {
      $pdf->SetTextColor(40, 167, 69);
    } else {
      $pdf->SetTextColor(220, 53, 69);
    }
    $pdf->Cell(35, 7, $statusText, 1, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Cell(30, 7, formatDuration($item['duration']), 1, 1, 'C');
  }

  // Summary
  $pdf->Ln(5);
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->Cell(0, 6, 'Total Records: ' . count($data), 0, 1, 'L');

  // Output
  $filename = 'Log_Door_' . date('YmdHis') . '.pdf';
  $pdf->Output($filename, 'D');
  exit;
}

$conn->close();
