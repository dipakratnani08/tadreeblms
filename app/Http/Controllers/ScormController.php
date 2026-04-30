<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Peopleaps\Scorm\Manager\ScormManager;
use Peopleaps\Scorm\Model\ScormModel;
use Peopleaps\Scorm\Model\ScormScoModel;

class ScormController extends Controller
{
    public function play($id)
    {
        $scorm = ScormModel::findOrFail($id);

        $firstSco = ScormScoModel::where('scorm_id', $scorm->id)
            ->orderBy('id')
            ->first();
        if (!$firstSco) {
            abort(404, 'No SCO found');
        }
        // Create tracking record
        app(ScormManager::class)->createScoTracking(
            $firstSco->uuid,
            auth()->id(),
            auth()->user()->name
        );
        
        return view('scorm.player', compact(
            'scorm',
            'firstSco'
        ));
    }
}