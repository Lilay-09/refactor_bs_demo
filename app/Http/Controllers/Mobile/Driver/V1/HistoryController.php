<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mobile\ReusableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HistoryController extends Controller
{
    //
    public function getHistoryPackages(Request $req){
        return ApiResponse::flex(ReusableService::getPackageHistory($req));
    }

    // public function getHistoryPdf(Request $req){
    //     return AppSetting::generatePDF($req);
    // }

    public function getHistoryPdf(Request $request)
    {
        // Example data for the PDF
        $data = [
            'title' => 'Dynamic PDF Example',
            'date' => now()->toDateTimeString(),
            'content' => 'This PDF was generated dynamically when requested.',
        ];

        // return ApiResponse::JsonRaw($data);

        // Load the Blade view and pass data to it
        $pdf = Pdf::loadView('pdf.package_history', $data);

        // Save the PDF to a storage disk (public or a custom disk)
        $fileName = 'dynamic-pdf-' . time() . '.pdf';
        $filePath = 'pdfs/' . $fileName;

        Storage::disk('public')->put($filePath, $pdf->output());

        // Generate the URL to the PDF
        $fileUrl = Storage::url($filePath);

        // Return the URL in JSON format
        return response()->json([
            'pdf_url' => asset($fileUrl)
        ]);
    }
}
