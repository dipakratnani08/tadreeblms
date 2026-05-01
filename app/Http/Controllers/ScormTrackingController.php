<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Peopleaps\Scorm\Manager\ScormManager;
use Illuminate\Support\Str;
use App\Scorm\Strategies\ScormFieldStrategy;
use App\Scorm\Strategies\Scorm12FieldStrategy;
use App\Scorm\Strategies\Scorm2004FieldStrategy;
use Peopleaps\Scorm\Model\ScormScoTrackingModel;
use App\Scorm\Services\ScormTrackService;



class ScormTrackingController extends Controller
{
    protected $scormService;

    public function __construct(ScormTrackService $scormService)
    {
        $this->scormService = $scormService;
    }
    public function track(Request $request, $scoUuid)
    {
        //$manager = app(ScormManager::class);

        $result = $this->scormService->updateScoTracking(
            $scoUuid,
            auth()->id(),
            $request->input('cmi') ?? $request->all()
        );
\Log::info('Tracking update result: ' . json_encode($result));
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function getTracking($scoUuid, $scormVersion)
    {
        $manager = app(ScormManager::class);

        $sco = $manager->getScoByUuid($scoUuid);

        // $tracking = $manager->getUserResult(
        //     $sco->id,
        //     auth()->id()
        // );

        $scormVersion = Str::ucfirst(Str::camel($scormVersion));
        $strategy = 'App\\Scorm\\Strategies\\' . $scormVersion . 'FieldStrategy';
        $cmistrategy = new ScormFieldStrategy(new $strategy());
        
        $tracking = ScormScoTrackingModel::where('sco_id', $sco->id)
            ->with('sco.scorm')
            ->where('user_id', auth()->id())->first();
        
        $cmiData = [];

            if ($tracking) {
                $cmiData = $cmistrategy->getCmiData($tracking);
            }  
        return response()->json([
            'tracking' => $tracking,
            'details' => $cmiData
        ]);
    }
}