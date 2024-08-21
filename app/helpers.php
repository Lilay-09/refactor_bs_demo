<?php
class ApiResponse
{
    static function ValidateFail($message=null,$errors=[]){
        return response()->json([
            'error' => true,
            'status' => 'Unprocessable ',
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

    static function JsonResult($data,$error=false,$message=null,$errors=[],$statusCode=200,$status="OK"){
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
    static function Pagination($data,$filter=null,$message=null){
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
        return response()->json([
            'status' => "OK",
            'error' => false,
            'data' => $limitation->values(),
            'per_page' => $perPage,
            'total' => $count,
            'total_page' => $total_page,
            'page_no' => $currentPage,
            'errors'=>[],
            'message'=> $message
        ],200);
    }
}

class Helper{
    static function isValidStartAndEndDate($startDate,$endDate):bool{
        $sd = date('Y-m-d',strtotime($startDate));
        $ed = date('Y-m-d',strtotime($endDate));
        if($sd > $ed) return false;
        return true;
    }

    static function dateYMD($date){
        return date('Y-m-d',strtotime($date));
    }

    static function dateDMY($date){
        return $date ? date('d-M-Y',strtotime($date)):null;
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

    /**
     * Summary of base64ToImageFile
     * @param mixed $base64String
     * @param mixed $companyId
     * @param mixed $dirName
     * @return string
     * Note* folder structure => public/uploads/images/companyId/dirname
     */
    static function base64ToImageFile($base64String, $companyId, $dirName,$ext=null)
    {
        // Construct the base directory path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);

        // Check if the directory exists, if not, create it
        if (!file_exists($baseFolder)) {
            if (!mkdir($baseFolder, 0755, true)) {
                throw new Exception('Failed to create directory: ' . $baseFolder);
            }
        }

        // Split the base64 string to get the format and the data
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
            $fileExtension = $matches[1]; // e.g., png, jpg, jpeg
            list(, $imageData) = explode(';base64,', $base64String);
            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                throw new Exception('Base64 decode failed.');
            }
            $fileExtension = $ext ? $ext : $fileExtension;
            // Generate a unique file name
            // => company_id+YMdHis+uniqid+extension
            $fileName = $companyId.date('YmdHis').uniqid() . '.' . $fileExtension;

            // Save the image file
            $filePath = $baseFolder . '/' . $fileName;
            if (file_put_contents($filePath, $imageData) === false) {
                throw new Exception('Failed to save file to path: ' . $filePath);
            }
            // Return the file name
            return $fileName;
        } else {
            throw new Exception('Invalid base64 string.');
        }
    }


    static function deleteImageFile($fileName, $companyId, $dirName)
    {
        // Construct the base directory path
        $baseFolder = public_path('uploads/images/' . $companyId . '/' . $dirName);

        // Construct the full file path
        $filePath = $baseFolder . '/' . $fileName;

        // Check if the file exists
        if (file_exists($filePath)) {
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

        // Construct the full file path on the server
        $filePath = public_path($relativeFilePath);

        // Check if the file exists
        if (file_exists($filePath)) {
            // File exists, return the public URL
            return asset($relativeFilePath);
        }

        // File does not exist, return a default placeholder URL or null
        return asset('uploads/images/default-placeholder.png'); // Adjust with your placeholder image path
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
            // Add more cases as needed for other types
            default:
                throw new Exception('Invalid file type specified.');
        }

        // Construct the full file path on the server
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

    static function formatNumber($num,$len)
    {
        if ($len<=0) $len =5;
        return str_pad($num, $len, '0', STR_PAD_LEFT);
    }

    static function setRefCode($tbl_code_control,$target_tbl,$target_col,$branch_id,$company_id,$newID,$issue_date = null,$prefix='CODE', $len = null,$onSuccess = null){
        if (!$len) $len = 5;
        if (!$newID) return DataResponse::ValidateFail('Identity should be input');
        $year = $issue_date?date('Y', strtotime($issue_date)):date('Y');

        $row = DB::table($tbl_code_control . " as c")->where('c.branch_id', $branch_id)->where('c.company_id',$company_id)->where('c.issue_year',$year)->selectRaw("c.last_id,c.prefix,c.issue_year")->take(1)->get()->first();

        $next_num = 0;
            if ($row && $row->issue_year == $year){
                $next_num = $row->last_id;
                $year = $row->issue_year;
            }
            $next_num++;
            //example ref number => 2300001 || prefix-2300001
            $new_code = substr($year,-2) . self::formatNumber($next_num, $len);
            if($prefix) $new_code = $prefix.'-'.$new_code;

            $x = DB::table($target_tbl)->where('id', $newID)->update([$target_col => $new_code]);
            if ($x || $x === 1) {
            $updated = DB::table($tbl_code_control)->where('branch_id', $branch_id)->where('company_id',$company_id)->where('issue_year', $year)->update(['last_id' => $next_num]);
            $insert_arr = ['branch_id' => $branch_id, 'issue_year' => $year,'last_id' => $next_num,'company_id' => $company_id,'prefix'=>$prefix];
            if (!$updated) DB::table($tbl_code_control)->insert($insert_arr);
            if ($onSuccess) $onSuccess();
            return (object)['status_code' => 200, 'status' => 'OK', 'code' => $new_code];
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

    static function Pagination($data, $filter = null, $usage = "do not provide with get()")
    {
        $filter = (object)$filter;
        $perPage = isset($filter->per_page) ? $filter->per_page : 10;
        $currentPage = isset($filter->current_page) ? $filter->current_page : 1;
        $skip_row = $perPage * ($currentPage - 1);
        if(isset($filter->search_value)){
            $skip_row = 0;
        }
        $limitation = $data->slice($skip_row,$perPage);

        $count = $data->count();
        $total_page = ceil($count/$perPage);
        return (object)[
            'status' => "OK",
            'status_code' => 200,
            'error' => false,
            'data' => $limitation->values(),
            'per_page' => $perPage,
            'total' => $count,
            'total_page' => $total_page,
            'page_no' => $currentPage,
            'errors'=>[],
            'message'=> null
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
