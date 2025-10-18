<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
  die('Unauthorized access');
}

require_once '../config/config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get parameters
$format = $_GET['format'] ?? 'excel';
$search = $_GET['search'] ?? '';

// Build query with JOIN to get username
$sql = "SELECT 
          r.id,
          r.uid,
          r.name,
          DATE_FORMAT(r.added_at, '%d/%m/%Y %H:%i:%s') as created_at_formatted,
          COALESCE(u.username, 'System') as added_by_name
        FROM rfid_cards r
        LEFT JOIN users u ON r.added_by = u.id
        WHERE 1=1";

// Filter by search
if ($search) {
  $search_escaped = $conn->real_escape_string($search);
  $sql .= " AND (r.uid LIKE '%$search_escaped%' OR r.name LIKE '%$search_escaped%' OR u.username LIKE '%$search_escaped%')";
}

$sql .= " ORDER BY r.added_at DESC";

$result = $conn->query($sql);
if (!$result) {
  die('Database error: ' . $conn->error);
}

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

if ($format === 'excel') {
  exportToExcel($data, $search);
} else {
  exportToPDF($data, $search);
}

function exportToExcel($data, $search)
{
  global $conn;
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();

  // Set document properties
  $spreadsheet->getProperties()
    ->setCreator('Smart Home IoT')
    ->setTitle('Daftar Kartu RFID')
    ->setSubject('Master Data Kartu RFID Terdaftar')
    ->setDescription('Export daftar kartu RFID yang terdaftar di sistem');

  // Title
  $sheet->setCellValue('A1', 'DAFTAR KARTU RFID TERDAFTAR');
  $sheet->mergeCells('A1:E1');
  $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
  $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Info
  $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
  $sheet->mergeCells('A2:E2');

  $infoRow = 3;
  if ($search) {
    $sheet->setCellValue('A' . $infoRow, 'Filter Pencarian: ' . $search);
    $sheet->mergeCells('A' . $infoRow . ':E' . $infoRow);
    $infoRow++;
  }

  // Statistics
  if (count($data) > 0) {
    $sheet->setCellValue('A' . $infoRow, 'Total Kartu Terdaftar: ' . count($data));
    $sheet->mergeCells('A' . $infoRow . ':E' . $infoRow);
    $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
    $infoRow++;
  }

  // Header row
  $headerRow = $infoRow + 1;
  $headers = ['No', 'UID Kartu', 'Nama Pengguna', 'Tanggal Ditambahkan', 'Ditambahkan Oleh'];
  $col = 'A';
  foreach ($headers as $header) {
    $sheet->setCellValue($col . $headerRow, $header);
    $sheet->getStyle($col . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle($col . $headerRow)->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('007BFF');
    $sheet->getStyle($col . $headerRow)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($col . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
  }

  // Data rows
  $row = $headerRow + 1;
  $no = 1;
  foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $no++);

    // UID with monospace font
    $sheet->setCellValue('B' . $row, $item['uid']);
    $sheet->getStyle('B' . $row)->getFont()->setName('Courier New');

    $sheet->setCellValue('C' . $row, $item['name'] ?: '-');
    $sheet->setCellValue('D' . $row, $item['created_at_formatted']);
    $sheet->setCellValue('E' . $row, $item['added_by_name']);

    // Alternate row colors
    if ($no % 2 == 0) {
      $sheet->getStyle('A' . $row . ':E' . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('F8F9FA');
    }

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
  $sheet->setCellValue('A' . $row, 'Total Kartu: ' . count($data));
  $sheet->getStyle('A' . $row)->getFont()->setBold(true);

  // Footer note
  $row++;
  $sheet->setCellValue('A' . $row, 'Catatan: Semua kartu yang terdaftar di sistem dapat digunakan untuk akses pintu');
  $sheet->mergeCells('A' . $row . ':E' . $row);
  $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(9);

  // Output
  $filename = 'Daftar_Kartu_RFID_' . date('YmdHis') . '.xlsx';

  // Clear any output buffers
  if (ob_get_level()) {
    ob_end_clean();
  }

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="' . $filename . '"');
  header('Cache-Control: max-age=0');
  header('Cache-Control: max-age=1');
  header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
  header('Cache-Control: cache, must-revalidate');
  header('Pragma: public');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;
}

function exportToPDF($data, $search)
{
  global $conn;
  require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

  $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

  // Set document information
  $pdf->SetCreator('Smart Home IoT');
  $pdf->SetAuthor('Smart Home System');
  $pdf->SetTitle('Daftar Kartu RFID');
  $pdf->SetSubject('Master Data Kartu RFID Terdaftar');

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
  $pdf->Cell(0, 10, 'DAFTAR KARTU RFID TERDAFTAR', 0, 1, 'C');

  $pdf->SetFont('helvetica', '', 10);
  $pdf->Cell(0, 6, 'Tanggal Export: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

  if ($search) {
    $pdf->Cell(0, 6, 'Filter Pencarian: ' . $search, 0, 1, 'C');
  }

  // Statistics
  if (count($data) > 0) {
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Total Kartu Terdaftar: ' . count($data), 0, 1, 'L');
  }

  $pdf->Ln(3);

  // Table header
  $pdf->SetFont('helvetica', 'B', 9);
  $pdf->SetFillColor(0, 123, 255);
  $pdf->SetTextColor(255, 255, 255);

  $pdf->Cell(12, 8, 'No', 1, 0, 'C', true);
  $pdf->Cell(35, 8, 'UID Kartu', 1, 0, 'C', true);
  $pdf->Cell(40, 8, 'Nama Pengguna', 1, 0, 'C', true);
  $pdf->Cell(40, 8, 'Tanggal Ditambahkan', 1, 0, 'C', true);
  $pdf->Cell(35, 8, 'Ditambahkan Oleh', 1, 1, 'C', true);

  // Table data
  $pdf->SetFont('courier', '', 7);
  $pdf->SetTextColor(0, 0, 0);

  $no = 1;
  foreach ($data as $item) {
    // Alternate row colors
    if ($no % 2 == 0) {
      $pdf->SetFillColor(248, 249, 250);
      $fill = true;
    } else {
      $fill = false;
    }

    $pdf->Cell(12, 7, $no++, 1, 0, 'C', $fill);
    $pdf->SetFont('courier', 'B', 7);
    $pdf->Cell(35, 7, $item['uid'], 1, 0, 'C', $fill);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(40, 7, substr($item['name'] ?: '-', 0, 18), 1, 0, 'L', $fill);
    $pdf->Cell(40, 7, $item['created_at_formatted'], 1, 0, 'C', $fill);
    $pdf->Cell(35, 7, substr($item['added_by_name'], 0, 15), 1, 1, 'C', $fill);
  }

  // Summary
  $pdf->Ln(5);
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->Cell(0, 6, 'Total Kartu: ' . count($data), 0, 1, 'L');

  // Footer note
  $pdf->Ln(3);
  $pdf->SetFont('helvetica', 'I', 8);
  $pdf->MultiCell(0, 5, 'Catatan: Semua kartu yang terdaftar di sistem dapat digunakan untuk akses pintu. Pastikan untuk menghapus kartu yang sudah tidak digunakan untuk keamanan.', 0, 'L');

  // Output
  $filename = 'Daftar_Kartu_RFID_' . date('YmdHis') . '.pdf';
  $pdf->Output($filename, 'D');
  exit;
}
