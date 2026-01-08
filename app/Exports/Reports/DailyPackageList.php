<?php
namespace App\Exports\Reports;

use App\Exports\Data\DailyPackageFormatter;
use App\Exports\Data\DailyPackageQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyPackageList implements FromQuery, WithMapping, WithHeadings, WithChunkReading,WithStyles
{
    protected DailyPackageQueryService $queryService;
    protected DailyPackageFormatter $formatter;
    protected array $filters;
    public string $exportId;
    protected int $rowNumber = 0;
    protected int $totalRows = 0;

    public function __construct(
        DailyPackageQueryService $queryService,
        DailyPackageFormatter $formatter,
        array $filters = [],
        $exportId = ''
    ) {
        $this->queryService = $queryService;
        $this->formatter = $formatter;
        $this->filters = $filters;

        $this->exportId = $exportId;
        $countQuery = clone $this->queryService->getQuery($filters);
        $this->totalRows = $countQuery->count();


        // $query = $this->queryService->getQuery($filters);

        // // Safe row count with DB::table() + joins
        // $this->totalRows = $query->toBase()->getCountForPagination();
    }

    public function styles(Worksheet $sheet)
    {
        // Header row height
        $sheet->getRowDimension(1)->setRowHeight(25); // make header taller
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->mergeCells('N1:O1'); // Merchant COD
        $sheet->mergeCells('P1:Q1'); // Driver COD
        // Column widths (adjust as needed)
        $columns = [
            'A' => 7,    'B' => 25, 'C' => 30, 'D' => 30, 'E' => 35,
            'F' => 25,   'G' => 30, 'H' => 30, 'I' => 30, 'J' => 25,
            'K' => 20,   'L' => 70, // Arrived Date
            'M' => 35,   'N' => 20, // Merchant COD
            'O' => 20,   'P' => 20, 'Q' => 20, 'R' => 20, 'S' => 20,
            'T' => 35,   'U' => 35, 'V' => 35,   'W' => 35
        ];

        foreach ($columns as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Header styling (first row)
        $sheet->getStyle('A1:W1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14, // increase font size
                'name' => 'Arial', // optional, set font family
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true, // allow text wrap
            ],
            'borders' => [
                'bottom' => [
                    // 'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ]);

        return [];
    }

    /**
     * Get the query for the export
     */
    public function query(): Builder
    {
        return $this->queryService->getQuery($this->filters);
    }

    /**
     * Map each row to an array for Excel
     */
    public function map($row): array
    {
        $this->rowNumber++; // Increment for each row

        $updateStep = max(1, intval($this->totalRows / 100));

        if ($this->totalRows > 0 && ($this->rowNumber % $updateStep === 0 || $this->rowNumber === $this->totalRows)) {
            // Calculate progress from 0 to 100% dynamically
            $progress = ceil(($this->rowNumber / $this->totalRows) * 100);
            $progress = min(99, $progress); // Safety cap
            Cache::store('redis')->put("export:progress:{$this->exportId}", $progress);
        }
        $data = $this->formatter->rawFormat($row);
        $data['no'] = $this->rowNumber; // Override 'No' column with index + 1
        return $data;
    }

    /**
     * Headings for Excel columns
     */

    public function headings(): array
    {
        return [
            [
                'No','Barcode','Branch','Merchant Name','Receiver','Price List','Driver','Pickup Driver','Transfer Driver',
                'Zone','Zone Code','Arrived Date','Finished Date',
                'Merchant COD','','Driver COD','','Base Fee','Taxi Fee','Other Fee','Status','Remarks','Driver Remarks'
            ],
            [
                '', '', '', '', '', '', '', '', '', '', '', '', '',
                'USD','KHR','USD','KHR','','','',''
            ]
        ];
    }

    /**
     * Chunk size for memory-safe export
     */
    public function chunkSize(): int
    {
        return 500;
    }
}