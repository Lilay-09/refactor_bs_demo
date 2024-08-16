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
