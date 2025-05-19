<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     public function index(Request $request)
    {
         // For a full list of companies, possibly paginated
         return CompanyResource::collection(Company::paginate(15));
    }

    public function indexList()
    {
        // For dropdowns
        return CompanyResource::collection(Company::where('status', true)->orderBy('name')->get());
        // Or: return Company::where('status', true)->orderBy('name')->get(['id', 'name']);
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
    public function show(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
