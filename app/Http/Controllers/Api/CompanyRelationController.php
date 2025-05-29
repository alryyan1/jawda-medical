<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyRelation;
use App\Models\Company; // To scope relations to a company
use Illuminate\Http\Request;
use App\Http\Resources\CompanyRelationResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CompanyRelationController extends Controller
{
    public function __construct()
    {
        // Permissions: e.g., 'manage company_relations', 'list company_relations'
        // These permissions might be part of managing a specific company's details.
    }

    /**
     * Display a listing of the resource, optionally filtered by company.
     * Used for dropdowns in PatientRegistrationForm.
     */
    public function index(Request $request)
    {
        // $this->authorize('list company_relations'); // Or relevant company management permission

        $query = CompanyRelation::query();

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        } else {
            // If not company_id is provided, what to return?
            // All relations from all companies? Or none?
            // For "quick add" context in Patient form, company_id should be present.
            // For a general admin page of all relations, this is fine.
        }

        $relations = $query->orderBy('name')->get();
        return CompanyRelationResource::collection($relations);
    }

    /**
     * Store a newly created company relation (scoped to a company).
     * This is for the "quick add" dialog.
     */
    public function store(Request $request)
    {
        // $this->authorize('create company_relations'); 
        // Or check if user can edit the parent company:
        // $company = Company::findOrFail($request->input('company_id'));
        // $this->authorize('update', $company);


        $validatedData = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('company_relations')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->company_id);
                }),
            ],
            'lab_endurance' => 'required|numeric|min:0',
            'service_endurance' => 'required|numeric|min:0',
        ]);

        $relation = CompanyRelation::create($validatedData);
        return new CompanyRelationResource($relation->load('company'));
    }

    /**
     * Display the specified resource. (If managing individual relations)
     */
    public function show(CompanyRelation $companyRelation)
    {
        // $this->authorize('view', $companyRelation);
        return new CompanyRelationResource($companyRelation->load('company'));
    }

    /**
     * Update the specified resource in storage. (If managing individual relations)
     */
    public function update(Request $request, CompanyRelation $companyRelation)
    {
        // $this->authorize('update', $companyRelation);
        $validatedData = $request->validate([
            'company_id' => 'sometimes|required|integer|exists:companies,id', // Usually company_id doesn't change
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('company_relations')->ignore($companyRelation->id)->where(function ($query) use ($request, $companyRelation) {
                    return $query->where('company_id', $request->input('company_id', $companyRelation->company_id));
                }),
            ],
            'lab_endurance' => 'sometimes|required|numeric|min:0',
            'service_endurance' => 'sometimes|required|numeric|min:0',
        ]);

        $companyRelation->update($validatedData);
        return new CompanyRelationResource($companyRelation->load('company'));
    }

    /**
     * Remove the specified resource from storage. (If managing individual relations)
     */
    public function destroy(CompanyRelation $companyRelation)
    {
        // $this->authorize('delete', $companyRelation);
        // Check for dependencies (e.g., if patients are using this relation)
        if ($companyRelation->patients()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف هذه العلاقة لارتباطها بمرضى حاليين.'], 403);
        }
        $companyRelation->delete();
        return response()->json(null, 204);
    }
}