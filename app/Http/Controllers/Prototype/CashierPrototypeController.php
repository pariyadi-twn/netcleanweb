<?php

namespace App\Http\Controllers\Prototype;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CashierPrototypeController extends Controller
{
    public function __invoke(): View
    {
        return view('prototype.cashier');
    }
}
