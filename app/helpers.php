<?php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
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

    static function JsonRaw($json,$statusCode=200){
        // $status_code = $statusCode ?? is_array($json) ? (isset($data['status_code']) ? $json['status_code']:200): (isset($data->status_code)?$json->status_code:200); //($data['status_code'] ?? 200) : ($data->status_code ?? 200);
        return response()->json($json,$statusCode);
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
            'data' => null,
            'message' => $message,
            'errors' => []
        ],500);
    }
    static function Pagination($data,$filter=null,$message=null,$additionalKey=[],$limit=1000){
        $filter = (object)$filter;
        // Log::error(json_encode($filter->all()));
        $perPage = isset($filter->per_page) ? ($filter->per_page == 0 ? 1:$filter->per_page) : 10;
        $currentPage = isset($filter->page_no) ? $filter->page_no : 1;
        $skip_row = $perPage * ($currentPage - 1);
        $totalCount = $data->count();
        $count = $totalCount > $limit ? $limit : $totalCount;
        if(isset($filter->search_value) || isset($filter->search)){
            $skip_row = 0;
            $perPage = $count > 0 ? $count : 1;
        }
        $limitation = $data->slice($skip_row,$perPage);
        $total_page = ceil($count/$perPage);
        $obj = (object)[
            'status' => "OK",
            'error' => false,
            'message'=> $message,
            'data' => $limitation->values(),
            'per_page' => (int)$perPage,
            'total' => (int)$count,
            'total_page' => (int)$total_page,
            'page_no' => (int)$currentPage,
            'errors'=>[],
        ];
        foreach ((object)$additionalKey as $key => $value) {
            $obj->$key = $value;
        }
        return response()->json($obj,200);
    }

    // static function PaginationV1($query, $filter = null, $message = null, $additionalKey = [], $limit = 1000, callable $transformCallback = null)
    // {
    //     $filter = (object)$filter;
    //     $perPage = isset($filter->per_page) ? ($filter->per_page == 0 ? 1 : min($filter->per_page, $limit)) : min(10, $limit);
    //     $currentPage = isset($filter->page_no) ? $filter->page_no : 1;

    //     $query->take($limit);
    //     // Execute pagination on the query
    //     $data = $query->paginate($perPage, ['*'], 'page', $currentPage);

    //     // Apply transformation if provided
    //     if ($transformCallback) {
    //         $data->getCollection()->transform($transformCallback);
    //     }

    //     // Build response object
    //     $obj = [
    //         'status' => "OK",
    //         'error' => false,
    //         'message' => $message,
    //         'data' => $data->items(),
    //         'per_page' => (int) $data->perPage(),
    //         'total' => (int) $data->total(),
    //         'total_page' => (int) $data->lastPage(),
    //         'page_no' => (int) $data->currentPage(),
    //         'errors' => [],
    //     ];

    //     foreach ((array) $additionalKey as $key => $value) {
    //         $obj[$key] = $value;
    //     }
    //     return response()->json($obj, 200);
    // }

    public static function PaginationV1(
        $query,
        Request $filter,
        $message = null,
        $additionalKey = [],
        $limit = 1000,
        callable $transformCallback = null,
        array $selectCols = ['*'],
        $cache = null,
        $cacheTags = []
    ) {
        // Ensure perPage and currentPage are integers
        $perPage = max(1, min((int)$filter->query('per_page', 10), $limit));
        $currentPage = (int)$filter->query('page_no', 1);

        // Generate a unique cache key based on filter parameters
        $filterArray = $filter->all();
        $cacheKey = 'pagination_' . md5(json_encode($filterArray));

        // Assign cacheTags based on whether tags are supported and provided
        $cacheTags = (!empty($cacheTags)) ? $cacheTags : [];

        // Determine if caching is enabled and Redis is available
        $isCaching = $cache && $cache > 0 && Helper::isRedisAvailable();

        // Attempt to get cached data if caching is enabled
        if ($isCaching) {
            try {
                $store = Cache::getStore();
                // If Redis or a store supporting tags is available, try to fetch from cache
                if ($store instanceof \Illuminate\Cache\RedisStore || method_exists($store, 'tags')) {
                    $cachedData = Cache::tags($cacheTags)->get($cacheKey);
                    if ($cachedData) {
                        // Log::info('test=>'.$cacheKey);
                        return response()->json($cachedData, 200);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Redis/cache error: ' . $e->getMessage());
            }
        }

        // Run the query with pagination
        $data = $query->paginate($perPage, $selectCols, 'page', $currentPage);

        // Optionally apply transformation to the results
        if ($transformCallback) {
            $data->transform($transformCallback);
        }

        // Prepare the response object
        $response = array_merge([
            'status' => "OK",
            'error' => false,
            'message' => $message,
            'data' => $data->items(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'total_page' => $data->lastPage(),
            'page_no' => $data->currentPage(),
            'errors' => [],
        ], $additionalKey);

        // Cache the response if caching is enabled
        if ($isCaching) {
            $cacheTime = is_numeric($cache) ? $cache : 300; // Default cache time: 300 seconds
            $store = Cache::getStore();
                // If Redis or a store supporting tags is available, try to fetch from cache
            if ($store instanceof \Illuminate\Cache\RedisStore || method_exists($store, 'tags')) {
                // Log::info('test cache');
                Cache::tags($cacheTags)->put($cacheKey, $response, $cacheTime);
            }
            // else {
            //     Cache::put($cacheKey, $response, $cacheTime);
            // }
        }

        // Return the response as JSON
        return response()->json($response, 200);
    }



    // public static function PaginationV1(
    //     $query,
    //     Request $filter,
    //     $message = null,
    //     $additionalKey = [],
    //     $limit = 1000,
    //     callable $transformCallback = null,
    //     array $selectCols = ['*'],
    //     $cache = null,
    //     $cacheTags = [] // Added parameter for custom cache tags
    // ) {
    //     // Ensure filter parameters are properly set
    //     $perPage = max(1, min($filter->query('per_page', 10), $limit));
    //     $currentPage = $filter->query('page_no', 1);
    //     // Set the cache key based on filter parameters (e.g., 'per_page', 'page_no', etc.)
    //     $cacheKey = 'pagination_' . md5(json_encode($filter->all()));
    //     // If cacheTags parameter is not provided, use a default value
    //     $cacheTags = empty($cacheTags) ? ['pagination', 'filter_' . md5(json_encode($filter->all()))] : $cacheTags;

    //     // Check if caching is enabled, data is already cached, and Redis is available
    //     if ($cache && $cache > 0 && !empty($cacheTags) && Helper::isRedisAvailable()) {
    //         // Log::info('test');
    //         // config(['cache.default' => 'redis']);
    //         try {
    //             // Check if cache supports tags and use tags if available
    //             if (Cache::getStore()) {
    //                 // Use custom cache tags provided in the parameter
    //                 $cachedData = Cache::tags($cacheTags)->get($cacheKey);
    //                 if ($cachedData) {
    //                     return response()->json($cachedData, 200); // Return cached data if available
    //                 }
    //             }
    //         } catch (\Throwable $e) {
    //             // Optionally log the error if needed
    //             Log::warning('Redis not available or error occurred while accessing cache tags: ' . $e->getMessage());
    //             // Fallback silently without interrupting the flow
    //         }
    //     }

    //     // Execute pagination on the query
    //     $data = $query->paginate($perPage, $selectCols, 'page', $currentPage);

    //     // Apply transformation if provided
    //     if ($transformCallback) {
    //         $data->transform($transformCallback);
    //     }

    //     // Build response object
    //     $obj = [
    //         'status' => "OK",
    //         'error' => false,
    //         'message' => $message,
    //         'data' => $data->items(),
    //         'per_page' => (int) $data->perPage(),
    //         'total' => (int) $data->total(),
    //         'total_page' => (int) $data->lastPage(),
    //         'page_no' => (int) $data->currentPage(),
    //         'errors' => [],
    //     ];

    //     // Add additional custom data if provided
    //     foreach ((array) $additionalKey as $key => $value) {
    //         $obj[$key] = $value;
    //     }

    //     // Cache the result if caching is enabled and Redis is available
    //     if ($cache !== null && Helper::isRedisAvailable()) {
    //         $cacheTime = is_numeric($cache) ? $cache : 300; // Default cache time of 300 seconds (5 minutes)
    //         // Store the response in the cache with tags if Redis is being used
    //         if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
    //             Cache::tags($cacheTags)->put($cacheKey, $obj, $cacheTime);
    //         } else {
    //             // Use regular caching for other drivers
    //             Cache::put($cacheKey, $obj, $cacheTime);
    //         }
    //     }

    //     // Return the response as JSON
    //     return response()->json($obj, 200);
    // }


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
        $status_code = $status_code ?? $object->status_code ?? $object?->data?->status_code ?? 200;
        unset($object->data->status_code,$object->status_code);
        return response()->json($object,$status_code);
    }
}


class Helper{
    protected static $khmerMonths = [
                'Jan' => 'មករា',
                'Feb' => 'កុម្ភៈ',
                'Mar' => 'មីនា',
                'Apr' => 'មេសា',
                'May' => 'ឧសភា',
                'Jun' => 'មិថុនា',
                'Jul' => 'កក្កដា',
                'Aug' => 'សីហា',
                'Sep' => 'កញ្ញា',
                'Oct' => 'តុលា',
                'Nov' => 'វិច្ឆិកា',
                'Dec' => 'ធ្នូ'
            ];
    static function isValidStartAndEndDate($startDate,$endDate):bool{
        $sd = date('Y-m-d',strtotime($startDate));
        $ed = date('Y-m-d',strtotime($endDate));
        if($sd > $ed) return false;
        return true;
    }

    public static function clearCacheByTags($tags)
    {
        try {
            if (self::isRedisAvailable()) {
                // Proceed only if Redis is available
                if (is_array($tags)) {
                    foreach ($tags as $tag) {
                        Cache::tags($tag)->flush();
                    }
                } else {
                    Cache::tags($tags)->flush();
                }
                return true;
            }
        } catch (\Throwable $e) {
            // Optionally log the error or silently fail
            // Log::info("Redis status: Inactive!");
            return false;
        }
    }


    public static function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }



    public static function isRedisAvailable(): bool
    {
        try {
            // Check if Redis connection exists and ping it
            if(config('app.use_redis') == true){
                // Log::info('sdfs');
                $redis = app('redis'); // Works if predis/phpredis is installed and configured
                $connected = $redis->ping() == 'PONG';
                return $connected;
            }
            config(['cache.default' => 'file']);
            return false;
        } catch (\Throwable $e) {
            Log::warning('Redis is not available ' . $e->getMessage());
            return false;
        }
    }




    static function addCountryCode($phoneNumber, $countryCode = '+855') {
        // Remove all non-numeric characters except '+'
        $cleanNumber = preg_replace('/[^\d+]/', '', $phoneNumber);
        // Check if the number already starts with the country code
        if (strpos($cleanNumber, ltrim($countryCode, '+')) === 0) {
            return '+' . $cleanNumber; // Ensure it has a '+' prefix
        }

        // Check if the number starts with a '+', indicating an existing country code
        if (strpos($cleanNumber, '+') === 0) {
            return $cleanNumber; // Return as it is
        }

        // Add the country code
        return $countryCode . $cleanNumber;
    }

    static function generateTelegramLink($phoneNumber, $countryCode = '+855',$isDeepLnk=false) {
        // Remove all non-numeric characters except '+'
        $cleanNumber = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Ensure the number starts with the country code
        if (strpos($cleanNumber, ltrim($countryCode, '+')) !== 0) {
            // Remove leading zero if present
            $cleanNumber = ltrim($cleanNumber, '0');
            $cleanNumber = ltrim($countryCode, '+') . $cleanNumber;
        } else {
            // Ensure the country code is included
            $cleanNumber = ltrim($cleanNumber, '+');
        }

        // Return the Telegram link with the correct format
        return [
            'url' => 'https://t.me/+' . $cleanNumber,
            'deep_link' => 't.me/+'.$cleanNumber
        ];
    }

    static function getDateDaysAgo($days): string
    {
        $date = new DateTime();
        $date->modify("-$days days");
        return $date->format('Y-m-d'); // Format the date as 'YYYY-MM-DD'
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

    public static function dateDMY($date, $format = 'd-M-Y', $lang = 'en')
    {
        if (!$date) return null;

        $datetime = str_replace([' AM', ' PM'], '', $date);
        $formatted = date($format, strtotime($datetime));

        if ($lang === 'km') {
            foreach (self::$khmerMonths as $en => $kh) {
                $formatted = str_replace($en, $kh, $formatted);
            }
        }

        return $formatted;
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

    static function formatCustomDateTime($datetime, $outputFormat = 'd-M-Y h:i:s A', $useMeridiem = false, $lang='en') {
        if (!$datetime) return null;
        if(!$outputFormat) $outputFormat = 'd-M-Y h:i:s A';
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

        // If using meridiem (AM/PM), adjust the output format
        if ($useMeridiem) {
            // If it's 24-hour format, we need to convert it to 12-hour format
            $outputFormat = str_replace('H', 'h', $outputFormat);  // Change 24-hour format to 12-hour format
        }

        // Formatting date
        $formattedDate = $date->format($outputFormat);

        // Translate month names to Khmer if $translateToKhmer is true
        if ($lang == 'km') {
            // Replace English month names with Khmer names
            $formattedDate = str_replace(array_keys(self::$khmerMonths), array_values(self::$khmerMonths), $formattedDate);

            $hour = (int) $date->format('H');

            if (strpos($formattedDate, 'AM') !== false) {
                if ($hour >= 6 && $hour < 12) {
                    $formattedDate = str_replace('AM', 'ព្រឹក', $formattedDate); // Morning
                } else {
                    $formattedDate = str_replace('AM', 'យប់', $formattedDate); // Late night (still considered night)
                }
            } elseif (strpos($formattedDate, 'PM') !== false) {
                if ($hour >= 12 && $hour < 17) {
                    $khmerPm = 'រសៀល'; // Afternoon
                } elseif ($hour >= 17 && $hour < 20) {
                    $khmerPm = 'ល្ងាច'; // Evening
                } else {
                    $khmerPm = 'យប់';   // Night
                }
                $formattedDate = str_replace('PM', $khmerPm, $formattedDate);
            }
        }

        // Return the formatted datetime string
        return $formattedDate;
    }

    public static function removeDuplicateConcat($pmtBreakDown, $paymentType, $removeDuplicate = true)
    {
        // If removeDuplicate is false, simply concatenate the new payment type
        if (!$removeDuplicate) {
            return empty($pmtBreakDown) ? $paymentType : $pmtBreakDown . ', ' . $paymentType;
        }

        // If breakdown is empty, just return the payment type
        if (empty($pmtBreakDown)) {
            return $paymentType;
        }

        // Check if the paymentType is already part of the breakdown string
        if (strpos($pmtBreakDown, $paymentType) === false) {
            // If not found, append the new type
            return $pmtBreakDown . ', ' . $paymentType;
        }

        // If the paymentType already exists, return the original breakdown without modification
        return $pmtBreakDown;
    }

    static function pluckEloCollection($data,$key){
        return Arr::pluck($data,$key);
    }

    static function pluckArrValue($data,$key){
        return array_column($data,$key);
    }

    // static function formatCustomDateTime($datetime, $outputFormat = 'd-M-Y h:i:s A', $useMeridiem = false) {
    //     if (!$datetime) return null;

    //     // Default timezone
    //     $timezone = new DateTimeZone(date_default_timezone_get());

    //     // Check for Indochina Time
    //     if (strpos($datetime, 'Indochina Time') !== false) {
    //         $datetime = str_replace('Indochina Time', '', $datetime);  // Remove the timezone text
    //         $timezone = new DateTimeZone(config('app.timezone'));  // Set the timezone
    //     }

    //     // Parse the datetime
    //     try {
    //         $date = new DateTime(trim($datetime), $timezone);
    //     } catch (Exception $e) {
    //         return "Invalid datetime format";  // Return error if parsing fails
    //     }

    //     // If using meridiem (AM/PM), adjust the output format
    //     if ($useMeridiem) {
    //         // If it's 24-hour format, we need to convert it to 12-hour format
    //         $outputFormat = str_replace('H', 'h', $outputFormat);  // Change 24-hour format to 12-hour format
    //     }

    //     // Return the formatted datetime string
    //     return $date->format($outputFormat);
    // }

    public static function translateOptions(array $translations,$lang = 'en',$labelKey='label',$valueKey='value',$valueType=null) {
        return array_map(fn($key) => [
            $labelKey => $translations[$key][$lang] ?? $translations[$key]['en'],
            $valueKey => $valueType == 'string' ? (string)$key: $key
        ], array_keys($translations));
    }


    static function getLabelByValue($data, $searchValue,$valueKey='value',$labelKey='label',$lang='en') {
        foreach ($data as $item) {
            if ($item[$valueKey] === $searchValue) {
                return $item[$labelKey];
            }
        }
        return null; // Return null if value not found
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
    static function base64ToImageFile($base64String, $companyId, $dirName,$subDir=null,$ext=null): object
    {
        $base64String = self::ensureBase64Prefix($base64String);

        if(!self::isValidBase64Image($base64String)) return (object)[
            'filename' => null
        ];
        // Construct the base directory path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);
        if ($subDir) {
            $baseFolder .= '/' . $subDir;
        }

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

    public static function saveImageFileOrBase64($imageOrBase64, $companyId, $dirName = 'images',$subDir=null){
        if (self::isValidBase64Image($imageOrBase64)) {
            return self::base64ToImageFile($imageOrBase64, $companyId, $dirName,$subDir);
        } elseif ($imageOrBase64 instanceof UploadedFile) {
            return self::saveImageFile($imageOrBase64, $companyId, $dirName,$subDir);
        } else {
            throw new \Exception("Invalid image format.");
        }
    }


    static function getImageInfo($image): object
    {
        try {
            // Check if base64 image
            if (is_string($image) && Helper::isValidBase64Image($image)) {
                $imageData = explode(',', $image)[1];
                $binaryData = base64_decode($imageData);
                $sizeKB = strlen($binaryData) / 1024;

                $img = imagecreatefromstring($binaryData);
                if (!$img) return (object)[
                    'error' => false,
                    'message' => 'Invalid image size'
                ];

                $width = imagesx($img);
                $height = imagesy($img);
                imagedestroy($img);

                return (object)[
                    'error' => false,
                    'type' => 'base64',
                    'size_kb' => round($sizeKB, 2),
                    'width' => $width,
                    'height' => $height,
                ];
            }

            // Check if UploadedFile
            if ($image instanceof UploadedFile) {
                $sizeKB = $image->getSize() / 1024;
                $path = $image->getPathname();
                // Log::info('Uploaded file class: ' . get_class($image));
                // Log::info('Uploaded file path: ' . $image->getPathname());


                if (!$path || !file_exists($path)) {
                    return (object)[
                        'error' => true,
                        'message' => 'Image file not found or path is empty'
                    ];
                }

                $imageSize = @getimagesize($path);
                if ($imageSize === false) {
                    return (object)[
                        'error' => true,
                        'message' => 'Failed to read image dimensions'
                    ];
                }

                [$width, $height] = $imageSize;
                // [$width, $height] = getimagesize($image->getPathname());

                return (object)[
                    'error' => false,
                    'type' => 'file',
                    'size_kb' => round($sizeKB, 2),
                    'width' => $width,
                    'height' => $height,
                ];
            }

            return (object)[
                'error' => true,
                'message' => 'Invalid image size'
            ]; // Not valid
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return (object)[
                'error' => true,
                'message' => 'fallback'
            ]; // Safe fallback
        }
    }

    /**
     * Validate image size and dimensions.
     *
     * @param  string|UploadedFile  $image
     * @param  float  $maxSizeMB
     * @param  int|null  $maxWidth
     * @param  int|null  $maxHeight
     * @return bool
     */
    static function isValidUploadImage($image, float $maxSizeMB = 2.0, int $maxWidth = null, int $maxHeight = null): object
    {
        $info = self::getImageInfo($image);
        if ($info->error) {
            return (object)[
                'error' => true,
                'message' => $info->message ?? 'Invalid image data.'
            ];
        }

        if ($info->size_kb > ($maxSizeMB * 1024)) {
            return (object)[
                'error' => true,
                'message' => "Image size exceeds the maximum allowed limit of {$maxSizeMB}MB."
            ];
        }

        if ($maxWidth && $info->width > $maxWidth) {
            return (object)[
                'error' => true,
                'message' => "Image width ({$info->width}px) exceeds the maximum allowed width of {$maxWidth}px."
            ];
        }

        if ($maxHeight && $info->height > $maxHeight) {
            return (object)[
                'error' => true,
                'message' => "Image height ({$info->height}px) exceeds the maximum allowed height of {$maxHeight}px."
            ];
        }

        return (object)[
            'error' => false,
            'message' => 'Image is valid.',
            'info' => $info
        ];
    }


    static function isShortGoogleMapUrl($url){
        $parsed = parse_url($url);

        return isset($parsed['host']) && (
            Str::contains($parsed['host'], 'maps.app.goo.gl') ||
            Str::contains($parsed['host'], 'maps.google.com')
        );

        // return isset($parsed['host']) &&
        //     Str::contains($parsed['host'], 'maps.app.goo.gl');
    }

    static function enumValuesRule(string $enumClass)
    {
        return Rule::in(array_column($enumClass::cases(), 'value'));
    }

    static function validTotalImageSize(array $images): object
    {
        $totalBytes = 0;
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $totalBytes += $image->getSize();
            }
        }

        // Log::info("total byte $totalBytes");
        $totalSizeMB = $totalBytes / (1024 * 1024); // Convert bytes to MB
        $limitMB = round(
            self::convertPHPSizeToBytes(ini_get('upload_max_filesize')) / (1024 * 1024), 2
        );
        // Log::info('limit => '.$limitMB);
        $isValid = $totalSizeMB <= $limitMB;

        return (object)[
            'error' => !$isValid,
            'message' => $isValid
                ? "Total upload size: {$totalSizeMB}MB (within limit of {$limitMB}MB)."
                : "Total upload size: {$totalSizeMB}MB exceeds the limit of {$limitMB}MB."
        ];
    }

    static function convertPHPSizeToBytes(string $size): int
    {
        $unit = strtoupper(substr($size, -1));
        $bytes = (int) $size;

        switch ($unit) {
            case 'G':
                $bytes *= 1024;
            case 'M':
                $bytes *= 1024;
            case 'K':
                $bytes *= 1024;
        }

        return $bytes;
    }



    public static function saveImageFile(UploadedFile $image, $companyId, $dirName = 'images',$subDir=null)
    {
        // Validate the image type
        $validMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/heic', 'image/heif', 'image/webp','application/octet-stream'];
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
        if($subDir) $baseFolder .= '/'.$subDir;
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


    static function deleteImageFile($fileName, $companyId, $dirName,$subDir=null)
    {
        // Construct the base directory path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);
        if ($subDir) {
            $baseFolder .= '/' . $subDir;
        }
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

    static function getImageUrl($fileName, $companyId, $dirName, $subDir = null)
    {
        // Primary file path
        $relativeFilePath = 'uploads/images/' . $companyId . '/' . $dirName . '/' . $fileName;
        $filePath = public_path($relativeFilePath);

        // Check if the file exists in the main directory
        if (file_exists($filePath) && $fileName) {
            return asset($relativeFilePath);
        }

        // If not found and a subdirectory is provided, check there
        if ($subDir) {
            $relativeFilePath = 'uploads/images/' . $companyId . '/' .$dirName.'/'. $subDir . '/' . $fileName;
            $filePath = public_path($relativeFilePath);
            if (file_exists($filePath)) {
                return asset($relativeFilePath);
            }
        }

        return null; // Adjust with a default placeholder if needed
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


    static function getAnalyzDiffDate($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        // Calculate the difference
        $interval = $start->diff($end);

        // Helper function to pluralize units
        $pluralize = function ($value, $singular) {
            return $value . ' ' . $singular . ($value > 1 ? 's' : '');
        };

        // Get total days in the months
        $daysInMonthStart = $start->format('t'); // Get the number of days in the start month
        $daysInMonthEnd = $end->format('t'); // Get the number of days in the end month

        // Convert total hours to days and remaining hours
        $totalHours = ($interval->days * 24) + $interval->h;
        $days = floor($totalHours / 24);
        $remainingHours = $totalHours % 24;
        $minutes = $interval->i;

        // Adjust for months by checking the number of days in each month
        if ($days >= $daysInMonthStart) {
            $months = floor($days / $daysInMonthStart); // Treat each full month as 1 month
            $remainingDays = $days % $daysInMonthStart;
        } else {
            $months = 0;
            $remainingDays = $days;
        }

        // If the difference is more than 1 month, return months, days, hours, and minutes
        if ($months >= 1) {
            return trim(($months > 0 ? $pluralize($months, 'month') . ' ' : '') .
                        ($remainingDays > 0 ? $pluralize($remainingDays, 'day') . ' ' : '') .
                        ($remainingHours > 0 ? $pluralize($remainingHours, 'hour') . ' ' : '') .
                        ($minutes > 0 ? $pluralize($minutes, 'minute') : ''));
        }

        // If less than 1 month but more than 1 day, return days, hours, and minutes
        if ($remainingDays >= 1) {
            return trim(($remainingDays > 0 ? $pluralize($remainingDays, 'day') . ' ' : '') .
                        ($remainingHours > 0 ? $pluralize($remainingHours, 'hour') . ' ' : '') .
                        ($minutes > 0 ? $pluralize($minutes, 'minute') : ''));
        }

        // If less than 1 day but more than 1 hour, return hours and minutes
        if ($totalHours > 0) {
            return trim(($totalHours > 0 ? $pluralize($totalHours, 'hour') . ' ' : '') .
                        ($minutes > 0 ? $pluralize($minutes, 'minute') : ''));
        }

        // If less than 1 hour, return only minutes
        return $pluralize($minutes, 'minute');
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
            case ($interval->h > 0 || $interval->days > 0): // Convert days to hours
                $hours = $interval->h + ($interval->days * 24);
                return $hours . ' hours';
            default:
                return $interval->i . ' minutes';
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

    static function getNumber($value,$decimalPoint=2,$useThousandSep=false){
        return number_format((float)$value,$decimalPoint,'.',($useThousandSep ? ',':''));
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

    // static function getLatLongFromGoogleMapsUrl($url)
    // {
    //     // Regular expression to capture latitude and longitude from Google Maps URL
    //     $pattern = '/@([-+]?[0-9]*\.?[0-9]+),([-+]?[0-9]*\.?[0-9]+)/';
    //     $placePattern = "/place\/([^\/]+)\/@/";
    //     $lat = null;
    //     $lng = null;
    //     $placeName = null;
    //     if (preg_match($pattern, $url, $matches)) {
    //         $lat = $matches[1];
    //         $lng = $matches[2];
    //     }
    //     if (preg_match($placePattern, $url, $placeMatches)) {
    //         $placeName = str_replace("+", " ", $placeMatches[1]);
    //     }
    //     return (object)[
    //         'latitude' => $lat,
    //         'longitude' => $lng,
    //         'address' => $placeName
    //     ]; // Return null if no coordinates found
    // }

    static function getLatLongFromGoogleMapsUrl($url)
    {
        // Regular expressions for extracting latitude and longitude
        $coordinatePattern = '/@([-+]?[0-9]*\.?[0-9]+),([-+]?[0-9]*\.?[0-9]+)/';
        // Adjusted pattern to optionally capture a place name
        $placePattern = '/place\/([^\/]+)\//';

        $lat = $lng = $placeName = null;

        if (preg_match($coordinatePattern, $url, $matches)) {
            $lat = $matches[1];
            $lng = $matches[2];
        }

        if (preg_match($placePattern, $url, $placeMatches)) {
            $placeName = urldecode(str_replace("+", " ", $placeMatches[1]));
        }

        return (object)[
            'latitude'  => $lat,
            'longitude' => $lng,
            'address'   => $placeName ?? null,
        ];
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

    public static function sanitizeInput(array $data, array $excludeFields = [],$retrieveAs = "array"): array | object
    {
        $sanitizedData = [];

        foreach ($data as $key => $value) {
            // Exclude specific fields from sanitization
            if (in_array($key, $excludeFields, true)) {
                $sanitizedData[$key] = $value;
                continue;
            }

            // Sanitize only string values
            if (is_string($value)) {
                // Remove special characters like `/*\`
                $cleanValue = trim(strip_tags($value));
                $cleanValue = preg_replace('/[^A-Za-z0-9\s\-_.]/', '', $cleanValue); // Allow letters, numbers, space, dash, underscore, dot

                $sanitizedData[$key] = $cleanValue;
            } else {
                $sanitizedData[$key] = $value;
            }

        }
         if ($retrieveAs === 'object') {
            return (object) $sanitizedData;  // Return as object
        }

        return $sanitizedData;
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
        $amount = (float) self::getNumber($amount);
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

    static function Duplicated($message, $errors = []): object
    {
        return (object)[
            'status_code' => 409,
            'error' => true,
            'status' => 'Conflict',
            'errors' => $errors,
            'message' => $message,
        ];
    }

    static function Unauthorized($err_msg = 'Unauthorized'): object
    {
        return (object)[
            'status_code' => 401,
            'error' => true,
            'status' => 'Unauthorized',
            'message' => $err_msg
        ];
    }

    static function JsonResult($data, $error = false, $message = null,$errors=[],$status_code=200,$status="OK" ): object
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

    static function JsonRaw($json, $status = null): object
    {
        $jsonRes = (object)[];
        foreach($json as $key=>$j){
            $jsonRes->{$key} = $j;
        }
        return $jsonRes;
    }

    static function NotFound($message='Not found'): object
    {
        return (object)[
            'status_code' => 404,
            'error' => true,
            'status' => 'Not Found',
            'message' => $message,
            'errors' => []
        ];
    }

    static function Error($message,$errors=[]): object
    {
        return (object)[
            'status_code' => 500,
            'error' => true,
            'status' => 'Error',
            'message' => $message,
            'data' => null,
            'errors' => $errors
        ];
    }

    static function Pagination($data, $filter = null, $message = "get list",$additionalKey=[],$limit=1000): object
    {
        $filter = (object)$filter;
        $perPage = isset($filter->per_page) ? ($filter->per_page == 0 ? 1:$filter->per_page) : 10;
        $currentPage = isset($filter->page_no) ? $filter->page_no : 1;
        $skip_row = $perPage * ($currentPage - 1);
        $totalCount = $data->count();
        $count = $totalCount > $limit ? $limit : $totalCount;
        if(isset($filter->search_value) || isset($filter->search)){
            $skip_row = 0;
            $perPage = $count > 0 ? $count : 1;
        }
        $limitation = $data->slice($skip_row,$perPage);
        $total_page = ceil($count/$perPage);
        $obj = (object)[
            'status' => "OK",
            'status_code' => 200,
            'error' => false,
            'message'=> $message,
            'data' => $limitation->values(),
            'per_page' => (int)$perPage,
            'total' => (int)$count,
            'total_page' => (int) $total_page,
            'page_no' => (int) $currentPage,
            'errors'=>[],
        ];
        foreach ((object)$additionalKey as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    static function Forbidden($message='You has no permmision to access or do the action'): object
    {
        return (object)[
            'status_code' => 403,
            'error' => true,
            'status' => 'Forbidden',
            'message' => $message,
            'errors' => []
        ];
    }

    public static function PaginationV1(
        Builder $query,
        Request $filter = null,
        string $message = '',
        array $additionalKey = [],
        int $limit = 1000,
        callable $transformCallback = null,
        array $select = ['*'],
        int $cache = null,
        array $cacheTags = [] // 🆕 Customizable cache tags
    ) {
        $filter = (object) $filter;
        $perPage = isset($filter->per_page) ? ($filter->per_page == 0 ? 1 : min($filter->per_page, $limit)) : min(10, $limit);
        $currentPage = isset($filter->page_no) ? $filter->page_no : 1;

        // Generate unique cache key from filter
        $cacheKey = 'pagination_' . md5(json_encode($filter));

        // Redis & taggable support check
        $store = Cache::getStore();
        $supportsTags = method_exists($store, 'tags') && $store instanceof \Illuminate\Cache\TaggableStore;

        // Use default tags if not provided
        $cacheTags = ($supportsTags && !empty($cacheTags)) ? $cacheTags : [];


        // Attempt to read from cache
        if ($cache && $cache > 0) {
            try {
                $cached = $supportsTags
                    ? Cache::tags($cacheTags)->get($cacheKey)
                    : Cache::get($cacheKey);

                if ($cached) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                \Log::warning("Pagination cache read failed: " . $e->getMessage());
            }
        }

        // Run query and paginate
        $data = $query->paginate($perPage, $select, 'page', $currentPage);

        // Apply transformation if provided
        if ($transformCallback) {
            $data->getCollection()->transform($transformCallback);
        }

        // Prepare the response object
        $obj = (object)[
            'status' => "OK",
            'error' => false,
            'message' => $message,
            'data' => $data->items(),
            'per_page' => (int) $data->perPage(),
            'total' => (int) $data->total(),
            'total_page' => (int) $data->lastPage(),
            'page_no' => (int) $data->currentPage(),
            'errors' => [],
        ];

        foreach ((array) $additionalKey as $key => $value) {
            $obj->{$key} = $value;
        }

        // Save to cache if enabled
        if ($cache !== null) {
            $cacheTime = is_numeric($cache) ? $cache : 300;

            try {
                if ($supportsTags && !empty($cacheTags)) {
                    Cache::tags($cacheTags)->put($cacheKey, $obj, $cacheTime);
                } else {
                    Cache::put($cacheKey, $obj, $cacheTime);
                }
            } catch (\Throwable $e) {
                \Log::warning("Pagination cache write failed: " . $e->getMessage());
            }
        }

        return $obj;
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
