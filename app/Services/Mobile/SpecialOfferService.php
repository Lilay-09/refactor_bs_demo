<?php

namespace App\Services\Mobile;

use App\Models\ScoringReward;
use App\Services\GeneralSettingService;
use DataResponse;
use Helper;
use Illuminate\Http\Request;

class SpecialOfferService
{
    // Your service methods go here
    public function getSpecialOffers(Request $filter,$user,string $channel='merchant'){
        $select = ['id','image','claim_type_id','message','list'];
        $lang = $filter->lang;
        $qRw = ScoringReward::query()->where('id','>',1)
        ->where('channel',$channel)
        ->where('is_deleted',0);
        $callback = function($offer) use($lang,$user){
            $offer->list = json_decode($offer->list);
            $offer->image = Helper::getImageUrl($offer->image,$user->company_id,'reward');
            $offer->claim_type = Helper::getLabelByValue(GeneralSettingService::optionsClaimType($lang),$offer->claim_type_id);//GeneralSettingService::optionsClaimType();
            return $offer;
        };
        return DataResponse::PaginationV1($qRw,$filter,'',[],200,$callback,$select);
    }
}
