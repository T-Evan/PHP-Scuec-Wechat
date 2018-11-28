<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ByeController extends Controller
{
    public function wishWall(Request $request)
    {
        return view('bye.wish_wall');
    }
}
