<?php
use Illuminate\Http\UploadedFile;
use Milon\Barcode\DNS1D;
class ApiResponse
{

    static function ValidateFail($message=null,$errors=[]){
        return response()->json([
            'error' => true,
            'status' => 'Unprocessable',
            'errors' => $errors,
            'message' => $message,
        ],422);
    }

    static function Duplicated($message=null,$errors=[]){
        return response()->json([
            'error' => true,
            'status' => 'Conflict',
            'errors' => $errors,
            'message' => $message,
        ],409);
    }

    static function Unauthorized($err_msg='Unauthorized',$errors=[]){
        return response()->json([
            'error' => true,
            'status' => 'Unauthorized',
            'errors' => $errors,
            'message' => $err_msg
        ],401);
    }

    static function JsonResult($data,$message=null,$error=false,$errors=[],$statusCode=200,$status="OK"){
        return response()->json([
            'error' => $error,
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
            'data' => $data,
        ],$statusCode);
    }

    static function JsonRaw($json,$statusCode=null){
        $status_code = is_array($json) ? (isset($data['status_code']) ? $json['status_code']:200): (isset($data->status_code)?$json->status_code:200); //($data['status_code'] ?? 200) : ($data->status_code ?? 200);
        return response()->json($json,$status_code ?? $statusCode ?? 200);
    }
    static function NotFound($message='Not found',$errors=[]){
        return response()->json([
            'error' => true,
            'status' => "Not Found",
            'message' => $message,
            'errors' => $errors
        ],404);
    }

    static function Error($message){
        return response()->json([
            'error' => true,
            'status' => 'Error',
            'message' => $message,
            'errors' => []
        ],500);
    }
    static function Pagination($data,$filter=null,$message=null,$additionalKey=[]){
        $filter = (object)$filter;
        $perPage = isset($filter->per_page) ? $filter->per_page : 10;
        $currentPage = isset($filter->page_no) ? $filter->page_no : 1;
        $skip_row = $perPage * ($currentPage - 1);
        if(isset($filter->search_value)){
            $skip_row = 0;
        }
        $limitation = $data->slice($skip_row,$perPage);

        $count = $data->count();
        $total_page = ceil($count/$perPage);
        $obj = (object)[
            'status' => "OK",
            'error' => false,
            'message'=> $message,
            'data' => $limitation->values(),
            'per_page' => $perPage,
            'total' => $count,
            'total_page' => $total_page,
            'page_no' => $currentPage,
            'errors'=>[],
        ];
        foreach ((object)$additionalKey as $key => $value) {
            $obj->$key = $value;
        }
        return response()->json($obj,200);
    }

    static function Forbidden($message='Has no permmision to access')
    {
        return response()->json([
            'error' => true,
            'status' => 'Forbidden',
            'message' => $message,
            'errors' => []
        ],403);
    }

    static function flex($object=null,$status_code=null){
        $status_code = $status_code ?? $object?->status_code ?? $object?->data->status_code;
        unset($object->data->status_code,$object->status_code);
        return response()->json($object,$status_code);
    }
}

class Helper{
    static function isValidStartAndEndDate($startDate,$endDate):bool{
        $sd = date('Y-m-d',strtotime($startDate));
        $ed = date('Y-m-d',strtotime($endDate));
        if($sd > $ed) return false;
        return true;
    }

    static function dateYMD($date,$format='Y-m-d'){
        $datetime = str_replace(" PM", "", $date);
        $datetime = str_replace(" AM", "", $date);
        $date = preg_replace('/\s+\(.*?\)/', '', $date); // Remove "(Indochina Time)"

        // Format the GMT string to a compatible format for DateTime
        $date = str_replace('GMT', '', $date); // Remove GMT
        $date = str_replace('0700', '+0700', $date); // Ensure the offset is correctly formatted

        // Create a DateTime object from the cleaned GMT date string
        $gmtDateTime = DateTime::createFromFormat('D M d Y H:i:s O', trim($date));

        // Check if the DateTime object was created successfully
        if ($gmtDateTime === false) {
            return date($format,strtotime($date));
        }

        // Set the timezone to ICT (Indochina Time)
        $ictTimezone = new DateTimeZone('Asia/Phnom_Penh');

        // Convert to ICT
        $gmtDateTime->setTimezone($ictTimezone);

        // Return the date in Y-m-d H:i:s format
        return $gmtDateTime->format($format);
    }

    static function getDateTime($format = 'd-M-Y h:i:s A'){
        return date($format);
    }

    static function dateDMY($date){
        $datetime = str_replace(" PM", "", $date);
        $datetime = str_replace(" AM", "", $datetime);
        return $date ? date('d-M-Y',strtotime($datetime)):null;
    }

    static function formatDateTime($datetime, $format = 'd-M-Y h:i:s', $useMeridiem = true) {
        // Add AM/PM notation if required in the output format
        $meridiemF = $useMeridiem ? ' A' : '';  // Add space before AM/PM if needed

        // Parse the datetime using the fixed 'd-m-Y H:i:s' input format (24-hour format)
        $date = DateTime::createFromFormat('d-m-Y H:i:s', $datetime);

        if ($date) {
            // Adjust the output format (replace 'H' with 'h' for 12-hour format if needed)
            $outputFormat = str_replace('H', 'h', $format) . $meridiemF;
            return $date->format($outputFormat);
        } else {
            return "Invalid datetime format";
        }
    }

    static function formatCustomDateTime($datetime, $outputFormat = 'd-M-Y h:i:s', $useMeridiem = true) {
        if(!$datetime) return null;
        $datetime = str_replace(" PM", "", $datetime);
        $datetime = str_replace(" AM", "", $datetime);
        // Default timezone
        $timezone = new DateTimeZone(date_default_timezone_get());

        // Check for Indochina Time
        if (strpos($datetime, 'Indochina Time') !== false) {
            $datetime = str_replace('Indochina Time', '', $datetime);  // Remove the timezone text
            $timezone = new DateTimeZone(config('app.timezone'));  // Set the timezone
        }

        // Parse the datetime
        try {
            $date = new DateTime(trim($datetime), $timezone);
        } catch (Exception $e) {
            return "Invalid datetime format";  // Return error if parsing fails
        }

        // Adjust the output format (replace 'H' with 'h' for 12-hour format if needed)
        if ($useMeridiem) {
            $outputFormat = str_replace('H', 'h', $outputFormat);
        }
        $meridiem = $useMeridiem ? 'A' : '';
        // Return the formatted datetime string
        return $date->format($outputFormat.' '.$meridiem);
    }






    static function dateBTW($startDate,$endDate,$targetDate):bool{
        $sd = date('Y-m-d',strtotime($startDate));
        $ed = date('Y-m-d',strtotime($endDate));
        $td = date('Y-m-d',strtotime($targetDate));

        if($td >= $sd && $td <=$ed) return true;
        return false;
    }

    static function year($date){
        return date('Y',strtotime($date));
    }

    static function generateRandomPrefix($length = 4,$useUpperCase=true)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $random = substr(str_shuffle($characters), 0, $length);

        if (!$useUpperCase) {
            return strtolower($random);
        }

        return $random;
    }

    static function setBarcode($tableName,$pkId,$companyId,$targetCol='qr_code'){
        return DB::table($tableName)->where('id',$pkId)->update([
            $targetCol => self::generateBarcodeString($pkId,$companyId)
        ]);
    }

    static function generateBarcodeString($uniqueKey,$companyId,$prefix='JPK'){
        $genCode = substr(strtoupper(string: uniqid($prefix)).self::generateRandomPrefix(5),0,12-strlen($uniqueKey));
        $barcode = $genCode.$companyId.$uniqueKey;
        return $barcode;
    }

    static function generateCode($prefix,$uniqueKey,$splitSign='-',$len=8){
        $code = str_pad($uniqueKey, $len, "0", STR_PAD_LEFT);
        return $prefix.$splitSign.$code;
    }

    static function setFleetNumber($branchId,$controlTable,$targetTable,$targetId,$targetCol){
        $strtotime = strtotime(now());
        $year = date('Y',$strtotime);
        $month = date('m',$strtotime);
        $startIdx = 1;
        $error = false;
        $codeControl = DB::table($controlTable)->where('branch_id',$branchId)->where('year',$year)->where('month',$month)->first();
        if(!$codeControl){
            $create = DB::table($controlTable)->insert([
                'last_idx' => $startIdx,
                'branch_id' => $branchId,
                'year' => $year,
                'month' => $month,
            ]);
            if(!$create) $error = true;
        }else{
            $startIdx = $codeControl->last_idx + 1;
            DB::table($controlTable)->where('branch_id',$branchId)->where('year',$year)->where('month',$month)->update([
                'last_idx' => $startIdx
            ]);
        }

        $code = $branchId.substr($year,2).$month.str_pad($startIdx, 8, "0", STR_PAD_LEFT);

        $setCode = DB::table($targetTable)->where('id',$targetId)->update([
            $targetCol => $code
        ]);
        if(!$setCode) $error = true;
        return (object)[
            'error' => $error,
            'code' => $code
        ];
    }

    /**
     * Summary of base64ToImageFile
     * @param mixed $base64String
     * @param mixed $companyId
     * @param mixed $dirName
     * @return string
     * Note* folder structure => public/uploads/images/companyId/dirname
     */
    static function base64ToImageFile($base64String, $companyId, $dirName,$ext=null): object
    {
        $base64String = self::ensureBase64Prefix($base64String);

        if(!self::isValidBase64Image($base64String)) return (object)[
            'filename' => null
        ];
        // Construct the base directory path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);

        // Check if the directory exists, if not, create it
        if (!file_exists($baseFolder)) {
            if (!mkdir($baseFolder, 0755, true)) {
                // throw new Exception('Failed to create directory: ' . $baseFolder);
                return (object)[
                    'filename' => null
                ];
            }
        }

        // Split the base64 string to get the format and the data
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
            $fileExtension = $matches[1]; // e.g., png, jpg, jpeg
            list(, $imageData) = explode(';base64,', $base64String);
            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                // throw new Exception('Base64 decode failed.');
                return (object)[
                    'filename' => null
                ];
            }
            $fileExtension = $ext ? $ext : $fileExtension;
            // Generate a unique file name
            // => company_id+YMdHis+uniqid+extension
            $fileName = $companyId.date('YmdHis').uniqid() . '.' . $fileExtension;

            // Save the image file
            $filePath = $baseFolder . '/' . $fileName;
            if (file_put_contents($filePath, $imageData) === false) {
                // throw new Exception('Failed to save file to path: ' . $filePath);
                return (object)[
                    'filename' => null
                ];
            }
            // Return the file name
            return (object)[
                'filename' => $fileName,
            ];
        } else {
            // throw new Exception('Invalid base64 string.');
            return (object)[
                    'filename' => null
                ];

        }
    }

    public static function saveImageFileOrBase64($imageOrBase64, $companyId, $dirName = 'images'){
        if (self::isValidBase64Image($imageOrBase64)) {
            return self::base64ToImageFile($imageOrBase64, $companyId, $dirName);
        } elseif ($imageOrBase64 instanceof UploadedFile) {
            return self::saveImageFile($imageOrBase64, $companyId, $dirName);
        } else {
            throw new \Exception("Invalid image format.");
        }
    }


    public static function saveImageFile(UploadedFile $image, $companyId, $dirName = 'images')
    {
        // Validate the image type
        $validMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/heic', 'image/heif', 'image/webp'];
        if (!in_array($image->getClientMimeType(), $validMimeTypes)) {
            return (object)[
                'filename' => null,
                'ext' => null
            ];
        }

        $originalFilename = $image->getClientOriginalName();
        $extension = $image->getClientOriginalExtension();
        // Generate a unique filename based on the current timestamp
        $filename = $companyId.date('YmdHis').uniqid() . $originalFilename;

        // Define the base folder path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);

        // Create the directory if it does not exist
        if (!is_dir($baseFolder)) {
            mkdir($baseFolder, 0755, true); // Create the directory with the appropriate permissions
        }

        // Move the uploaded file to the specified directory
        $image->move($baseFolder, $filename);

        // Return the public URL of the stored image
        return (object)[
            'filename' => $filename,
            'ext' => $extension
        ];
    }


    static function deleteImageFile($fileName, $companyId, $dirName)
    {
        // Construct the base directory path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);

        // Construct the full file path
        $filePath = $baseFolder . '/' . $fileName;

        // Check if the file exists
        if (file_exists($filePath) && $fileName) {
            // Attempt to delete the file
            if (unlink($filePath)) {
                return true; // File deleted successfully
            } else {
                throw new Exception('Failed to delete file: ' . $filePath);
            }
        }

        // If file does not exist, return false instead of throwing an exception
        return false;
    }

    static function getImageUrl($fileName, $companyId, $dirName)
    {
        // Construct the relative file path for the URL
        $relativeFilePath = 'uploads/images/' . $companyId . '/' . $dirName . '/' . $fileName;
        // var_dump($relativeFilePath);

        // Construct the full file path on the server
        $filePath = public_path($relativeFilePath);

        // Check if the file exists
        if (file_exists($filePath) && $fileName) {
            // File exists, return the public URL
            return asset($relativeFilePath);
        }
        // File does not exist, return a default placeholder URL or null
        return null; // Adjust with your placeholder image path
    }

    static function getFileUrl($fileName, $companyId, $dirName, $type = 'image')
    {
        // Initialize variables for base directory and URL
        $baseFolder = '';
        $baseUrl = '';

        // Determine the base directory and URL based on the file type
        switch ($type) {
            case 'image':
                $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);
                $baseUrl = 'uploads/images/';
                break;
            case 'document':
                $baseFolder = public_path('uploads/documents/' . $companyId . '/' . $dirName);
                $baseUrl = 'uploads/documents/';
                break;
            default:
                throw new Exception('Invalid file type specified.');
        }

        // full path
        $filePath = $baseFolder . '/' . $fileName;
        // Construct the URL for the file
        $fileUrl = asset($baseUrl . $companyId . '/' . $dirName . '/' . $fileName);

        // Check if the file exists
        if (file_exists($filePath)) {
            return $fileUrl;
        } else {
            // Return null or an empty array if the file doesn't exist, without throwing an exception
            return null;
        }
    }


    // check full path base 64
    static function isValidBase64Image($base64String)
    {
        // Check if the string has the correct base64 format for an image
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
            // Extract the base64 data if the prefix is present
            $imageData = substr($base64String, strpos($base64String, ',') + 1);
        } else {
            // If no prefix, assume the entire string is base64 encoded image data
            $imageData = $base64String;
        }
        // Decode the base64 data
        $imageData = base64_decode($imageData, true);
        // Ensure that base64_decode did not return false (indicating a decoding failure)
        if ($imageData === false) {
            return false;
        }
        // Check if the image data is a valid image using GD or Imagick
        $img = @imagecreatefromstring($imageData);
        if ($img !== false) {
            // The image is valid
            imagedestroy($img);
            return true;
        }
        // If the string does not match the pattern or the image data is invalid, return false
        return false;
    }

    static function  getDateDifference($startDate, $endDate, $unit='days,months,years') {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        // Calculate the difference
        $interval = $start->diff($end);

        // Return the difference based on the specified unit
        switch (strtolower($unit)) {
            case 'days':
                return $interval->days; // Total number of days
            case 'months':
                return $interval->m + ($interval->y * 12); // Total number of months
            case 'years':
                return $interval->y; // Total number of years
            default:
                throw new InvalidArgumentException('Invalid unit specified. Use "days", "months", or "years".');
        }
    }

    static function newOTP($length=6)
    {
        return join('', array_map(function($value) { return $value == 1 ? mt_rand(1, 9) : mt_rand(0, 9); }, range(1, $length)));
    }

    static function getEndDate($days=0,$months=0,$years=0) {
        // Get the current date
        $currentDate = new DateTime();

        // Add the specified number of days
        $currentDate->modify("+$days days");
        $currentDate->modify("+$months months");
        $currentDate->modify("+$years years");

        // Return the end date in a desired format (e.g., 'Y-m-d')
        return $currentDate->format('Y-m-d h:i:s');
    }

    static function formatNumber($num,$len)
    {
        if ($len<=0) $len =5;
        return str_pad($num, $len, '0', STR_PAD_LEFT);
    }

    static function formatPhoneNumber($phone){
        $new_num = null;
        if (empty($phone)) return null;
        $phone = trim(str_replace(' ','',$phone));
        if (substr($phone,0,1) =='0')
        {
            $new_num = '855'.substr($phone,1,strlen($phone)-1);
        }else if (substr($phone,0,3) =='855'){
            $new_num = $phone;
        }else if(substr($phone,0,4) =='+855'){
            $new_num = substr($phone,1,strlen($phone)-1);
        }
        return $new_num;

    }

    static function convertJsonTextToJson($jsonString,$assoc=true) {
        $correctedJson = str_replace("'", '"', $jsonString);

        // Decode the JSON string
        $decodedJson = json_decode($correctedJson, $assoc);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return (object)[
                'error' => true,
                'message' => 'Invalid JSON string: ' . json_last_error_msg(),
                'result' => null
            ];
        }

        return (object)[
            'error' => false,
            'message' => 'Success',
            'result' => $decodedJson
        ];
    }

    static function getLatLongFromGoogleMapsUrl($url)
    {
        // Regular expression to capture latitude and longitude from Google Maps URL
        $pattern = '/@([-+]?[0-9]*\.?[0-9]+),([-+]?[0-9]*\.?[0-9]+)/';
        $placePattern = "/place\/([^\/]+)\/@/";
        $lat = null;
        $lng = null;
        $placeName = null;
        if (preg_match($pattern, $url, $matches)) {
            $lat = $matches[1];
            $lng = $matches[2];
        }
        if (preg_match($placePattern, $url, $placeMatches)) {
            $placeName = str_replace("+", " ", $placeMatches[1]);
        }
        return (object)[
            'latitude' => $lat,
            'longitude' => $lng,
            'address' => $placeName
        ]; // Return null if no coordinates found
    }

    static function setRefCode($tbl_code_control,$target_tbl,$target_col,$branch_id,$company_id,$newID,$issue_date = null,$prefix='CODE', $len = 5,$onSuccess = null){
        if (!$len) $len = 5;
        if (!$newID) return DataResponse::ValidateFail('Identity should be input');
        $year = $issue_date?date('Y', strtotime($issue_date)):date('Y');
        $qRow = DB::table($tbl_code_control . " as c")->where('c.branch_id', $branch_id)
        ->where('prefix',$prefix)
        ->where('c.company_id',$company_id)
        ->selectRaw("c.last_id,c.prefix,c.issue_year");
        if($issue_date) $qRow->where('c.issue_year',$year);
        $row = $qRow->first();

        $next_num = 0;
        if ($row){
            $next_num = $row->last_id;
            if($row->issue_year == $year) $year = $row->issue_year;
        }
        $next_num++;
        //example ref number => 2300001 || prefix-2300001
        $new_code = substr($year,-2) . self::formatNumber($next_num, $len);
        if($prefix) $new_code = $prefix.'-'.$new_code;
        $x = DB::table($target_tbl)->where('id', $newID)->update([$target_col => $new_code]);
        if ($x || $x === 1) {
            $Qupdated = DB::table($tbl_code_control)->where('branch_id', $branch_id)->where('prefix',$prefix);
            if($issue_date) $Qupdated->where('issue_year', $year);
            $updated = $Qupdated->where('company_id',$company_id)->update(['last_id' => $next_num]);
            $insert_arr = ['branch_id' => $branch_id, 'issue_year' => $year,'last_id' => $next_num,'company_id' => $company_id,'prefix'=>$prefix];
            if (!$updated) DB::table($tbl_code_control)->insert($insert_arr);
            if ($onSuccess) $onSuccess();
            return (object)['status_code' => 200, 'status' => 'OK', 'code' => $new_code];
        }
    }

    static function ensureBase64Prefix($base64String, $imageType = 'png')
    {
        // Define a pattern to match the existing base64 prefix
        $prefixPattern = '/^data:image\/(\w+);base64,/';

        // Check if the base64 string has a prefix
        if (preg_match($prefixPattern, $base64String, $matches)) {
            // If it has a prefix, but the image type is different, replace it with the correct one
            $existingType = $matches[1];
            if ($existingType !== $imageType) {
                $base64String = preg_replace($prefixPattern, "data:image/{$imageType};base64,", $base64String);
            }
        } else {
            // If no prefix is found, add the correct one
            $base64String = "data:image/{$imageType};base64," . $base64String;
        }

        return $base64String;
    }

    public static function filterSpecialChars($str) {
        // This regex will match any character that is not a letter (a-z, A-Z), a digit (0-9), or a space
        return preg_replace('/[^a-zA-Z0-9\s]/', '', $str);
    }

    static function timeAgo($datetime,$useSecond=true) {
        // Convert the datetime string into a timestamp
        $datetime = str_replace(" PM", "", $datetime);
        $datetime = str_replace(" AM", "", $datetime);
        $timestamp = strtotime($datetime);

        // Check if the conversion was successful
        if ($timestamp === false) {
            return 'Invalid date format';
        }

        // Calculate the time difference in seconds
        $timeDifference = time() - $timestamp; // Current time minus given time
        $units = [
            'year' => 365 * 24 * 60 * 60,
            'month' => 30 * 24 * 60 * 60,
            'week' => 7 * 24 * 60 * 60,
            'day' => 24 * 60 * 60,
            'hour' => 60 * 60,
            'minute' => 60,
        ];

        // Include seconds if the parameter is true
        if ($useSecond) {
            $units['second'] = 1;
        }

        $result = [];

        // Iterate through each time unit
        foreach ($units as $unit => $value) {
            if ($timeDifference >= $value) {
                $count = floor($timeDifference / $value);
                $result[] = $count . ' ' . $unit . ($count > 1 ? 's' : '');
                $timeDifference -= $count * $value; // Subtract the calculated time
            }
        }

        // Return a formatted string
        return !empty($result) ? implode(', ', $result) . ' ago' : 'just now';
    }

    static function displayMoney($amount, $code = 'USD') {
        $symbols = [
            'USD' => '$',
            'KHR' => '៛'
        ];

        if ($code === 'USD') {
            return isset($symbols[$code]) ? ($symbols[$code] . $amount) : null;
        } else if ($code === 'KHR') {
            return isset($symbols[$code]) ? ($amount . $symbols[$code]) : null;
        } else {
            return null; // Return null if the currency code is not found
        }
    }

}


class DataResponse //extends Model
{
    // use HasFactory;
    static function ValidateFail($message = null,$errors = [])
    {
        return (object)[
            'status_code' => 422,
            'error' => true,
            'status' => 'Unprocessable',
            'errors' => $errors,
            'message' => $message,
        ];
    }

    static function Duplicated($message, $errors = [])
    {
        return (object)[
            'status_code' => 409,
            'error' => true,
            'status' => 'Conflict',
            'errors' => $errors,
            'message' => $message,
        ];
    }

    static function Unauthorized($err_msg = 'Unauthorized')
    {
        return (object)[
            'status_code' => 401,
            'error' => true,
            'status' => 'Unauthorized',
            'message' => $err_msg
        ];
    }

    static function JsonResult($data, $error = false, $message = null,$errors=[],$status_code=200,$status="OK" )
    {
        return (object)[
            'error' => $error,
            'status' => $status,
            'message' => $message,
            'status_code' => $status_code,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    static function JsonRaw($json, $status = null)
    {
        $jsonRes = (object)[];
        foreach($json as $key=>$j){
            $jsonRes->{$key} = $j;
        }
        return $jsonRes;
    }

    static function NotFound($message)
    {
        return (object)[
            'status_code' => 404,
            'error' => true,
            'status' => 'Not Found',
            'message' => $message,
            'errors' => []
        ];
    }

    static function Error($message,$errors=[])
    {
        return (object)[
            'status_code' => 500,
            'error' => true,
            'status' => 'Error',
            'message' => $message,
            'errors' => $errors
        ];
    }

    static function Pagination($data, $filter = null, $message = "get list",$additionalKey=[])
    {
        $filter = (object)$filter;
        $perPage = isset($filter->per_page) ? $filter->per_page : 10;
        $currentPage = isset($filter->page_no) ? $filter->page_no : 1;
        $skip_row = $perPage * ($currentPage - 1);
        if(isset($filter->search_value) || isset($filter->search)){
            $skip_row = 0;
        }
        $limitation = $data->slice($skip_row,$perPage);

        $count = $data->count();
        $total_page = ceil($count/$perPage);
        $obj = (object)[
            'status' => "OK",
            'status_code' => 200,
            'error' => false,
            'message'=> $message,
            'data' => $limitation->values(),
            'per_page' => $perPage,
            'total' => $count,
            'total_page' => $total_page,
            'page_no' => $currentPage,
            'errors'=>[],
        ];
        foreach ((object)$additionalKey as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    static function Forbidden($message='You has no permmision to access or do the action')
    {
        return (object)[
            'status_code' => 403,
            'error' => true,
            'status' => 'Forbidden',
            'message' => $message,
            'errors' => []
        ];
    }
}

// class UseDBContext{
//     public static function CreateGetId($table,$data,$getCols=[]){
//         $user = UserService::getAuthUser();
//         if(!$user) return (object)[
//             'error'=> true,
//             'message' => 'User not found',
//         ];

//             $data['create_uid'] = $user->id;
//             $data['update_uid'] = $user->id;
//             $data['branch_id'] = $user->branch_id;
//             $data['company_id'] = $user->company_id;
//             $newID = DB::table($table)->insertGetId($data);
//             if($newID){
//                 $cols = (object)[];
//                 if(isset($getCols[0])){
//                     $cols = DB::table($table)->where('id',$newID)->select($getCols)->get();
//                 }
//                 $cols->id = $newID;
//                 return (object)[
//                     'error'=>false,
//                     // 'result' => $cols,
//                     'cols' => $cols,
//                     'id' => $newID,
//                     'message'=> null,
//                 ];
//             }
//             return (object)[
//                 'error' => true,
//                 'message' => 'Fail to save',
//             ];

//     }
//     public static function Update($table,$updateWheres=[],$data=[],$getCols=[]){
//         $user = UserService::getAuthUser();
//         if(!$user) return (object)[
//             'error'=> true,
//             'message' => 'User not found',
//         ];

//         $data['update_uid'] = $user->id;
//         $data['branch_id'] = $user->branch_id;
//         $data['company_id'] = $user->company_id;
//         $updated = DB::table($table)->where($updateWheres)->update($data);
//         if($updated){
//             $cols = null;
//             if(isset($getCols[0])){
//                 $cols = DB::table($table)->where($updateWheres)->select($getCols)->get();
//             }
//             return (object)[
//                 'error'=>false,
//                 // 'result' => $cols,
//                 'cols' => $cols,
//                 'message'=> null,
//             ];
//         }
//         return (object)[
//             'error' => true,
//             'message' => 'Fail to update',
//         ];

//     }
//     public static function Delete($table,$wheres=[]){
//         $deleted = DB::table($table)->where($wheres)->delete();
//         if($deleted){
//             return (object)[
//                 'error'=>false,
//                 'message'=> 'Deleted',
//             ];
//         }
//         return (object)[
//             'error' => true,
//             'message' => 'Fail to delete',
//         ];
//     }
// }
