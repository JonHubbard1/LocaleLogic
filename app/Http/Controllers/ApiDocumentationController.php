<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ApiDocumentationController extends Controller
{
    public function index(): View
    {
        return view('api-documentation');
    }
}
