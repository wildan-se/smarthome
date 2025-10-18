<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

// Get parameters
$format = $_GET['format'] ?? 'excel';
$daterange = $_GET['daterange'] ?? '';
$temp_min = $_GET['temp_min'] ?? '';
$temp_max = $_GET['temp_max'] ?? '';

// Build query
$sql = "SELECT 
          id,
          temperature,
          humidity,
          DATE_FORMAT(log_time, '%d/%m/%Y %H:%i:%s') as log_time_formatted,
          log_time
        FROM dht_logs
        WHERE temperature > 0 AND humidity > 0";

// Filter by date range
if ($daterange) {
  $dates = explode(' s/d ', $daterange);
  if (count($dates) == 2) {
    $start = $dates[0];
    $end = $dates[1];
    $sql .= " AND DATE(log_time) BETWEEN '$start' AND '$end'";
  }
}

// Filter by temperature
if ($temp_min !== '') {
  $sql .= " AND temperature >= $temp_min";
}
if ($temp_max !== '') {
  $sql .= " AND temperature <= $temp_max";
}

$sql .= " ORDER BY log_time DESC";

$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

if ($format === 'excel') {
  exportToExcel($data, $daterange, $temp_min, $temp_max);
} else {
  exportToPDF($data, $daterange, $temp_min, $temp_max);
}

function exportToExcel($data, $daterange, $temp_min, $temp_max)
{
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();

  // Set document properties
  $spreadsheet->getProperties()
    ->setCreator('Smart Home IoT')
    ->setTitle('Log Suhu & Kelembapan')
    ->setSubject('Log Sensor DHT22')
    ->setDescription('Export data log sensor suhu dan kelembapan');

  // Title
  $sheet->setCellValue('A1', 'LAPORAN LOG SUHU & KELEMBAPAN');
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

  if ($temp_min !== '' || $temp_max !== '') {
    $filterText = 'Filter Suhu: ';
    if ($temp_min !== '' && $temp_max !== '') {
      $filterText .= $temp_min . '°C - ' . $temp_max . '°C';
    } elseif ($temp_min !== '') {
      $filterText .= '>= ' . $temp_min . '°C';
    } else {
      $filterText .= '<= ' . $temp_max . '°C';
    }
    $sheet->setCellValue('A' . $infoRow, $filterText);
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $infoRow++;
  }

  // Statistics
  if (count($data) > 0) {
    $temps = array_column($data, 'temperature');
    $hums = array_column($data, 'humidity');

    $avgTemp = array_sum($temps) / count($temps);
    $avgHum = array_sum($hums) / count($hums);
    $minTemp = min($temps);
    $maxTemp = max($temps);
    $minHum = min($hums);
    $maxHum = max($hums);

    $sheet->setCellValue('A' . $infoRow, 'Statistik:');
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
    $infoRow++;

    $sheet->setCellValue('A' . $infoRow, 'Suhu Rata-rata: ' . number_format($avgTemp, 1) . '°C | Min: ' . number_format($minTemp, 1) . '°C | Max: ' . number_format($maxTemp, 1) . '°C');
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $infoRow++;

    $sheet->setCellValue('A' . $infoRow, 'Kelembapan Rata-rata: ' . number_format($avgHum, 1) . '% | Min: ' . number_format($minHum, 1) . '% | Max: ' . number_format($maxHum, 1) . '%');
    $sheet->mergeCells('A' . $infoRow . ':D' . $infoRow);
    $infoRow++;
  }

  // Header row
  $headerRow = $infoRow + 1;
  $headers = ['No', 'Waktu', 'Suhu (°C)', 'Kelembapan (%)'];
  $col = 'A';
  foreach ($headers as $header) {
    $sheet->setCellValue($col . $headerRow, $header);
    $sheet->getStyle($col . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle($col . $headerRow)->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('28A745');
    $sheet->getStyle($col . $headerRow)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($col . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
  }

  // Data rows
  $row = $headerRow + 1;
  $no = 1;
  foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $item['log_time_formatted']);
    $sheet->setCellValue('C' . $row, number_format($item['temperature'], 1));
    $sheet->setCellValue('D' . $row, number_format($item['humidity'], 1));

    // Color temperature cell based on value
    $temp = $item['temperature'];
    if ($temp > 30) {
      $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('DC3545'); // Red
    } elseif ($temp < 20) {
      $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('007BFF'); // Blue
    } else {
      $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('28A745'); // Green
    }

    // Color humidity cell
    $hum = $item['humidity'];
    if ($hum > 70) {
      $sheet->getStyle('D' . $row)->getFont()->getColor()->setRGB('17A2B8'); // Info
    }

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
  $filename = 'Log_DHT_' . date('YmdHis') . '.xlsx';
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="' . $filename . '"');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;
}

function exportToPDF($data, $daterange, $temp_min, $temp_max)
{
  require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

  $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

  // Set document information
  $pdf->SetCreator('Smart Home IoT');
  $pdf->SetAuthor('Smart Home System');
  $pdf->SetTitle('Log Suhu & Kelembapan');
  $pdf->SetSubject('Log Sensor DHT22');

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
  $pdf->Cell(0, 10, 'LAPORAN LOG SUHU & KELEMBAPAN', 0, 1, 'C');

  $pdf->SetFont('helvetica', '', 10);
  $pdf->Cell(0, 6, 'Tanggal Export: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

  if ($daterange) {
    $pdf->Cell(0, 6, 'Periode: ' . $daterange, 0, 1, 'C');
  }

  if ($temp_min !== '' || $temp_max !== '') {
    $filterText = 'Filter Suhu: ';
    if ($temp_min !== '' && $temp_max !== '') {
      $filterText .= $temp_min . '°C - ' . $temp_max . '°C';
    } elseif ($temp_min !== '') {
      $filterText .= '>= ' . $temp_min . '°C';
    } else {
      $filterText .= '<= ' . $temp_max . '°C';
    }
    $pdf->Cell(0, 6, $filterText, 0, 1, 'C');
  }

  // Statistics
  if (count($data) > 0) {
    $temps = array_column($data, 'temperature');
    $hums = array_column($data, 'humidity');

    $avgTemp = array_sum($temps) / count($temps);
    $avgHum = array_sum($hums) / count($hums);
    $minTemp = min($temps);
    $maxTemp = max($temps);
    $minHum = min($hums);
    $maxHum = max($hums);

    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Statistik:', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Suhu - Rata-rata: ' . number_format($avgTemp, 1) . '°C | Min: ' . number_format($minTemp, 1) . '°C | Max: ' . number_format($maxTemp, 1) . '°C', 0, 1, 'L');
    $pdf->Cell(0, 5, 'Kelembapan - Rata-rata: ' . number_format($avgHum, 1) . '% | Min: ' . number_format($minHum, 1) . '% | Max: ' . number_format($maxHum, 1) . '%', 0, 1, 'L');
  }

  $pdf->Ln(3);

  // Table header
  $pdf->SetFont('helvetica', 'B', 9);
  $pdf->SetFillColor(40, 167, 69);
  $pdf->SetTextColor(255, 255, 255);

  $pdf->Cell(15, 8, 'No', 1, 0, 'C', true);
  $pdf->Cell(50, 8, 'Waktu', 1, 0, 'C', true);
  $pdf->Cell(30, 8, 'Suhu (°C)', 1, 0, 'C', true);
  $pdf->Cell(35, 8, 'Kelembapan (%)', 1, 1, 'C', true);

  // Table data
  $pdf->SetFont('helvetica', '', 8);
  $pdf->SetTextColor(0, 0, 0);

  $no = 1;
  foreach ($data as $item) {
    $pdf->Cell(15, 7, $no++, 1, 0, 'C');
    $pdf->Cell(50, 7, $item['log_time_formatted'], 1, 0, 'C');

    // Temperature with color
    $temp = $item['temperature'];
    if ($temp > 30) {
      $pdf->SetTextColor(220, 53, 69); // Red
    } elseif ($temp < 20) {
      $pdf->SetTextColor(0, 123, 255); // Blue
    } else {
      $pdf->SetTextColor(40, 167, 69); // Green
    }
    $pdf->Cell(30, 7, number_format($temp, 1), 1, 0, 'C');

    // Humidity
    $hum = $item['humidity'];
    if ($hum > 70) {
      $pdf->SetTextColor(23, 162, 184); // Info
    } else {
      $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->Cell(35, 7, number_format($hum, 1), 1, 1, 'C');

    $pdf->SetTextColor(0, 0, 0);
  }

  // Summary
  $pdf->Ln(5);
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->Cell(0, 6, 'Total Records: ' . count($data), 0, 1, 'L');

  // Output
  $filename = 'Log_DHT_' . date('YmdHis') . '.pdf';
  $pdf->Output($filename, 'D');
  exit;
}

$conn->close();
