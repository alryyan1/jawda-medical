<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinanceAccountResource;
use App\Models\FinanceAccount;
use Illuminate\Http\Request;

class FinanceAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 public function indexList()
    {
        return FinanceAccountResource::collection(FinanceAccount::orderBy('name')->get());
        // Or: return FinanceAccount::orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
