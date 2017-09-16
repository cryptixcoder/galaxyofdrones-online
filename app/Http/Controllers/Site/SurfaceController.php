<?php

namespace Koodilab\Http\Controllers\Site;

use Koodilab\Http\Controllers\Controller;

class SurfaceController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('player');
    }

    /**
     * Show the surface.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('site.surface.index');
    }
}
