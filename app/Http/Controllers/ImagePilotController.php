<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ImagePilotController extends Controller
{
    public function optimizer(): View
    {
        return view('imagepilot.optimizer');
    }
}
