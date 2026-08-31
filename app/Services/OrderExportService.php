<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The order list, as a real workbook.
 *
 * The previous export wrote a CSV with English headers and money as text, which
 * Excel opened as one column of quoted strings in a French locale — hence
 * "incohérent" and "la forme n'est pas correcte". A spreadsheet fixes that at
 * the source: amounts are numbers with a currency format, dates are dates, and
 * the header is frozen and filterable so the file is usable as it opens.
 */
class OrderExportService
{
    private const CURRENCY_FORMAT = '#,##0.00 "MAD"';

    private const DATE_FORMAT = 'dd/mm/yyyy hh:mm';

    /**
     * Read in pages so a full-year export does not hydrate the table at once.
     */
    private const CHUNK = 500;

    /**
     * Columns, in the order an operator reads them: identify the parcel, then
     * where it is, then who it is for, then the money, then who is carrying it.
     *
     * @return array<int, array{key: string, label: string, width: int, type?: string}>
     */
    private function columns(): array
    {
        return [
            ['key' => 'tracking_number', 'label' => __('orders.export.tracking_number'), 'width' => 20],
            ['key' => 'created_at', 'label' => __('orders.export.created_at'), 'width' => 18, 'type' => 'date'],
            ['key' => 'status', 'label' => __('orders.export.status'), 'width' => 22],
            ['key' => 'failure_reason', 'label' => __('orders.export.failure_reason'), 'width' => 22],
            ['key' => 'failed_attempts_count', 'label' => __('orders.export.attempts'), 'width' => 10, 'type' => 'int'],
            ['key' => 'customer', 'label' => __('orders.export.customer'), 'width' => 26],
            ['key' => 'phone', 'label' => __('orders.export.phone'), 'width' => 16, 'type' => 'text'],
            ['key' => 'address', 'label' => __('orders.export.address'), 'width' => 34],
            ['key' => 'city', 'label' => __('orders.export.city'), 'width' => 18],
            ['key' => 'sector', 'label' => __('orders.export.sector'), 'width' => 18],
            ['key' => 'payment_method', 'label' => __('orders.export.payment_method'), 'width' => 18],
            ['key' => 'order_value', 'label' => __('orders.export.order_value'), 'width' => 15, 'type' => 'money'],
            ['key' => 'amount_to_collect', 'label' => __('orders.export.amount_to_collect'), 'width' => 15, 'type' => 'money'],
            ['key' => 'delivery_price', 'label' => __('orders.export.delivery_price'), 'width' => 15, 'type' => 'money'],
            ['key' => 'total_amount', 'label' => __('orders.export.total_amount'), 'width' => 15, 'type' => 'money'],
            ['key' => 'driver', 'label' => __('orders.export.driver'), 'width' => 22],
            ['key' => 'seller', 'label' => __('orders.export.seller'), 'width' => 22],
            ['key' => 'delivered_at', 'label' => __('orders.export.delivered_at'), 'width' => 18, 'type' => 'date'],
        ];
    }

    /**
     * Stream the workbook as a download.
     */
    public function download(Builder $query, string $fileName): StreamedResponse
    {
        $spreadsheet = $this->build($query);

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, must-revalidate',
        ]);
    }

    public function build(Builder $query): Spreadsheet
    {
        $columns = $this->columns();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('orders.export.sheet_title'));

        foreach ($columns as $index => $column) {
            $letter = $this->letter($index);

            $sheet->setCellValue($letter.'1', $column['label']);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }

        $row = 2;

        // The caller's ordering is kept: the workbook comes out in the order the
        // operator was looking at when he asked for it.
        $query->with(['city:id,name', 'sector:id,name', 'seller', 'driver'])
            ->chunk(self::CHUNK, function ($orders) use ($sheet, $columns, &$row): void {
                foreach ($orders as $order) {
                    $this->writeRow($sheet, $row++, $columns, $this->map($order));
                }
            });

        $this->style($sheet, count($columns), $row - 1);

        return $spreadsheet;
    }

    /**
     * @return array<string, mixed>
     */
    private function map(Order $order): array
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);

        $payment = $order->payment_method instanceof PaymentMethod
            ? $order->payment_method
            : PaymentMethod::resolve((string) $order->payment_method);

        return [
            'tracking_number' => $order->tracking_number,
            'created_at' => $order->created_at,
            'status' => $status->label(),
            // The status of a parcel that missed an attempt stays "out for
            // delivery" by design, so the motif is a column of its own rather
            // than something the reader has to infer.
            'failure_reason' => $order->failure_reason?->label(),
            'failed_attempts_count' => (int) $order->failed_attempts_count,
            'customer' => $order->customer_full_name,
            'phone' => $order->customer_phone,
            'address' => $order->customer_address,
            'city' => $order->city?->name,
            'sector' => $order->sector?->name,
            'payment_method' => $payment->label(),
            'order_value' => $order->order_value !== null ? (float) $order->order_value : null,
            'amount_to_collect' => $payment->amountToCollect(
                $order->order_amount !== null ? (float) $order->order_amount : null
            ),
            'delivery_price' => (float) $order->delivery_price,
            'total_amount' => (float) $order->total_amount,
            'driver' => $order->driver?->full_name,
            'seller' => $order->seller?->full_name,
            'delivered_at' => $order->delivered_at,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, mixed>  $values
     */
    private function writeRow(Worksheet $sheet, int $row, array $columns, array $values): void
    {
        foreach ($columns as $index => $column) {
            $cell = $this->letter($index).$row;
            $value = $values[$column['key']] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            match ($column['type'] ?? 'string') {
                // A Moroccan number keeps its leading zero, which Excel would
                // strip the moment it decides the cell is numeric.
                'text' => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING),
                // Written as a real date rather than as text, so sorting and
                // filtering by period work in the workbook itself.
                'date' => $sheet->setCellValue($cell, ExcelDate::PHPToExcel($value)),
                default => $sheet->setCellValue($cell, $value),
            };
        }
    }

    private function style(Worksheet $sheet, int $columnCount, int $lastRow): void
    {
        $lastLetter = $this->letter($columnCount - 1);
        $headerRange = 'A1:'.$lastLetter.'1';

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5C']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(26);

        // The header stays put while scrolling, and each column can be filtered:
        // without those two the file has to be reworked before it is usable.
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($headerRange);

        if ($lastRow < 2) {
            return;
        }

        $body = 'A2:'.$lastLetter.$lastRow;

        $sheet->getStyle($body)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D5DDE6']],
            ],
        ]);

        foreach ($this->columns() as $index => $column) {
            $format = match ($column['type'] ?? null) {
                'money' => self::CURRENCY_FORMAT,
                'date' => self::DATE_FORMAT,
                'int' => NumberFormat::FORMAT_NUMBER,
                default => null,
            };

            if ($format === null) {
                continue;
            }

            $letter = $this->letter($index);

            $sheet->getStyle($letter.'2:'.$letter.$lastRow)
                ->getNumberFormat()
                ->setFormatCode($format);
        }
    }

    private function letter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index + 1);
    }
}
