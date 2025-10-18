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
          l.id,
          l.uid,
          COALESCE(c.name, 'Tidak Terdaftar') as name,
          DATE_FORMAT(l.access_time, '%d/%m/%Y %H:%i:%s') as access_time,
          l.status
        FROM rfid_logs l
        LEFT JOIN rfid_cards c ON l.uid = c.uid
        WHERE l.uid != 'MANUAL_CONTROL'";

// Filter by date range
if ($daterange) {
  $dates = explode(' s/d ', $daterange);
  if (count($dates) == 2) {
    $start = $dates[0];
    $end = $dates[1];
    $sql .= " AND DATE(l.access_time) BETWEEN '$start' AND '$end'";
  }
}

// Filter by status
if ($status) {
  $sql .= " AND l.status = '$status'";
}

$sql .= " ORDER BY l.access_time DESC";

$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

if ($format === 'excel') {
  exportToExcel($data, $daterange, $status);
} else {
  exportToPDF($data, $daterange, $status);
}

function exportToExcel($data, $daterange, $status)
{
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();

  // Set document properties
  $spreadsheet->getProperties()
    ->setCreator('Smart Home IoT')
    ->setTitle('Log Akses RFID')
    ->setSubject('Log Akses Kartu RFID')
    ->setDescription('Export data log akses kartu RFID');

  // Title
  $sheet->setCellValue('A1', 'LAPORAN LOG AKSES RFID');
  $sheet->mergeCells('A1:E1');
  $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
  $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Info
  $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
  $sheet->mergeCells('A2:E2');

  if ($daterange) {
    $sheet->setCellValue('A3', 'Periode: ' . $daterange);
    $sheet->mergeCells('A3:E3');
  }

  if ($status) {
    $statusText = $status === 'granted' ? 'Akses Diterima' : 'Akses Ditolak';
    $row = $daterange ? 4 : 3;
    $sheet->setCellValue('A' . $row, 'Filter Status: ' . $statusText);
    $sheet->mergeCells('A' . $row . ':E' . $row);
  }

  // Header row
  $headerRow = 5;
  if ($daterange) $headerRow++;
  if ($status) $headerRow++;

  $headers = ['No', 'UID Kartu', 'Nama Pengguna', 'Waktu Akses', 'Status'];
  $col = 'A';
  foreach ($headers as $header) {
    $sheet->setCellValue($col . $headerRow, $header);
    $sheet->getStyle($col . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle($col . $headerRow)->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($col . $headerRow)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($col . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
  }

  // Data rows
  $row = $headerRow + 1;
  $no = 1;
  foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $item['uid']);
    $sheet->setCellValue('C' . $row, $item['name']);
    $sheet->setCellValue('D' . $row, $item['access_time']);

    $statusText = $item['status'] === 'granted' ? 'Akses Diterima' : 'Akses Ditolak';
    $sheet->setCellValue('E' . $row, $statusText);

    // Color status cell
    if ($item['status'] === 'granted') {
      $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('28A745');
    } else {
      $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('DC3545');
    }
    $sheet->getStyle('E' . $row)->getFont()->setBold(true);

    $row++;
  }

  // Apply borders
  $lastRow = $row - 1;
  $sheet->getStyle('A' . $headerRow . ':E' . $lastRow)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

  // Auto-size columns
  foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
  }

  // Summary
  $row += 2;
  $sheet->setCellValue('A' . $row, 'Total Records: ' . count($data));
  $sheet->getStyle('A' . $row)->getFont()->setBold(true);

  // Output
  $filename = 'Log_RFID_' . date('YmdHis') . '.xlsx';
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
  $pdf->SetTitle('Log Akses RFID');
  $pdf->SetSubject('Log Akses Kartu RFID');

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
  $pdf->Cell(0, 10, 'LAPORAN LOG AKSES RFID', 0, 1, 'C');

  $pdf->SetFont('helvetica', '', 10);
  $pdf->Cell(0, 6, 'Tanggal Export: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

  if ($daterange) {
    $pdf->Cell(0, 6, 'Periode: ' . $daterange, 0, 1, 'C');
  }

  if ($status) {
    $statusText = $status === 'granted' ? 'Akses Diterima' : 'Akses Ditolak';
    $pdf->Cell(0, 6, 'Filter Status: ' . $statusText, 0, 1, 'C');
  }

  $pdf->Ln(5);

  // Table header
  $pdf->SetFont('helvetica', 'B', 9);
  $pdf->SetFillColor(68, 114, 196);
  $pdf->SetTextColor(255, 255, 255);

  $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
  $pdf->Cell(30, 8, 'UID Kartu', 1, 0, 'C', true);
  $pdf->Cell(40, 8, 'Nama Pengguna', 1, 0, 'C', true);
  $pdf->Cell(40, 8, 'Waktu Akses', 1, 0, 'C', true);
  $pdf->Cell(30, 8, 'Status', 1, 1, 'C', true);

  // Table data
  $pdf->SetFont('helvetica', '', 8);
  $pdf->SetTextColor(0, 0, 0);
  $pdf->SetFillColor(255, 255, 255);

  $no = 1;
  foreach ($data as $item) {
    $pdf->Cell(10, 7, $no++, 1, 0, 'C');
    $pdf->Cell(30, 7, $item['uid'], 1, 0, 'C');
    $pdf->Cell(40, 7, substr($item['name'], 0, 25), 1, 0, 'L');
    $pdf->Cell(40, 7, $item['access_time'], 1, 0, 'C');

    // Status with color
    $statusText = $item['status'] === 'granted' ? 'Diterima' : 'Ditolak';
    if ($item['status'] === 'granted') {
      $pdf->SetTextColor(40, 167, 69);
    } else {
      $pdf->SetTextColor(220, 53, 69);
    }
    $pdf->Cell(30, 7, $statusText, 1, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
  }

  // Summary
  $pdf->Ln(5);
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->Cell(0, 6, 'Total Records: ' . count($data), 0, 1, 'L');

  // Output
  $filename = 'Log_RFID_' . date('YmdHis') . '.pdf';
  $pdf->Output($filename, 'D');
  exit;
}

$conn->close();
