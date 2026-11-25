<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IndexController extends Controller
{
    /**
     * Depois do login a Senha Única volta para "/": admin cai nos pendentes, solicitante nas suas solicitações
     */
    public function index(): View|RedirectResponse
    {
        if (Gate::allows('admin')) {
            return redirect()->route('admin.pendentes');
        }
        if (Gate::allows('solicitante')) {
            return redirect()->route('dominios.index');
        }

        return view('index');
    }
}
