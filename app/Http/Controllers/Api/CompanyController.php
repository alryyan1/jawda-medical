<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FinanceAccount; // Assuming you might want to create/link these
use Illuminate\Http\Request;
use App\Http\Resources\CompanyResource;
// use App\Http\Resources\CompanyCollection; // If you have a custom collection resource for pagination
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB; // For transactions if creating related entities

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:list companies')->only('index');
        $this->middleware('can:view companies')->only('show'); // Assuming 'view companies' implies 'show'
        $this->middleware('can:create companies')->only('store');
        $this->middleware('can:edit companies')->only('update');
        $this->middleware('can:delete companies')->only('destroy');
    }

    /**
     * Display a listing of the companies.
     */
    public function index(Request $request)
    {
        $query = Company::withCount('contractedServices') // Get count of service contracts
                          ->with('financeAccount');      // Eager load finance account

        // Example Search Filter (optional)
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        // Example Status Filter (optional)
        if ($request->has('status') && $request->status !== '') { // Check for non-empty string
            $query->where('status', (bool) $request->status);
        }


        $companies = $query->orderBy('name')->paginate($request->get('per_page', 15));

        // If using a custom collection resource:
        // return new CompanyCollection($companies);
        return CompanyResource::collection($companies);
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:companies,email',
            'status' => 'required|boolean',
            'lab_endurance' => 'required|numeric|min:0|max:100', // Assuming percentage
            'service_endurance' => 'required|numeric|min:0|max:100', // Assuming percentage
            'lab_roof' => 'required|numeric|min:0',
            'service_roof' => 'required|numeric|min:0',
            'finance_account_id' => 'nullable|exists:finance_accounts,id',
        ]);

        // Ensure numeric values are correctly typed before creation if needed
        // (Laravel often handles this from request, but explicit casting can be safer)
        $validatedData['lab_endurance'] = (float) $validatedData['lab_endurance'];
        $validatedData['service_endurance'] = (float) $validatedData['service_endurance'];
        $validatedData['lab_roof'] = (float) $validatedData['lab_roof']; // Or int if always whole numbers
        $validatedData['service_roof'] = (float) $validatedData['service_roof'];


        // Example: Create a finance account for the company if one isn't provided
        // This is optional and depends on your business logic
        // if (empty($validatedData['finance_account_id'])) {
        //     DB::transaction(function () use (&$company, $validatedData) {
        //         $financeAccount = FinanceAccount::create([
        //             'name' => $validatedData['name'] . ' - حساب الشركة',
        //             'code' => 'COMP-' . strtoupper(substr(md5($validatedData['name']), 0, 6)), // Example code
        //             'type' => 'revenue', // Or 'liability' depending on context
        //             'debit' => 'credit', // Or 'debit'
        //         ]);
        //         $validatedData['finance_account_id'] = $financeAccount->id;
        //         $company = Company::create($validatedData);
        //     });
        // } else {
        //    $company = Company::create($validatedData);
        // }

        $company = Company::create($validatedData);

        return new CompanyResource($company->load('financeAccount'));
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company)
    {
        // Already loaded with contracts in the CompanyResource by default if index also does it.
        // For a show route, you might want to load more details or specific relationships.
        return new CompanyResource($company->loadMissing('financeAccount', 'contractedServices.serviceGroup'));
    }

    /**
     * Update the specified company in storage.
     */
    public function update(Request $request, Company $company)
    {
        $validatedData = $request->validate([
            'name' => ['sometimes','required','string','max:255', Rule::unique('companies')->ignore($company->id)],
            'phone' => 'nullable|string|max:20',
            'email' => ['nullable','email','max:255', Rule::unique('companies')->ignore($company->id)],
            'status' => 'sometimes|required|boolean',
            'lab_endurance' => 'sometimes|required|numeric|min:0|max:100',
            'service_endurance' => 'sometimes|required|numeric|min:0|max:100',
            'lab_roof' => 'sometimes|required|numeric|min:0',
            'service_roof' => 'sometimes|required|numeric|min:0',
            'finance_account_id' => 'nullable|exists:finance_accounts,id',
        ]);

        // Explicit casting for numeric fields if they might come as strings from the request
        if (isset($validatedData['lab_endurance'])) $validatedData['lab_endurance'] = (float) $validatedData['lab_endurance'];
        if (isset($validatedData['service_endurance'])) $validatedData['service_endurance'] = (float) $validatedData['service_endurance'];
        if (isset($validatedData['lab_roof'])) $validatedData['lab_roof'] = (float) $validatedData['lab_roof'];
        if (isset($validatedData['service_roof'])) $validatedData['service_roof'] = (float) $validatedData['service_roof'];

        $company->update($validatedData);

        return new CompanyResource($company->load('financeAccount'));
    }

    /**
     * Remove the specified company from storage.
     */
    public function destroy(Company $company)
    {
        // Add checks: e.g., cannot delete company if it has active contracts or patients linked,
        // unless you want to handle cascading deletes or soft deletes.
        if ($company->patients()->exists() || $company->contractedServices()->exists()) {
             return response()->json(['message' => 'لا يمكن حذف الشركة لارتباطها بمرضى أو عقود خدمات نشطة.'], 403);
        }

        // DB::transaction(function () use ($company) {
            // Detach all services if any (though the check above might prevent this)
            // $company->contractedServices()->detach();
            // Handle other related data if necessary (e.g., subcompanies)
            // $company->subcompanies()->delete(); // If subcompanies should be deleted with parent
            $company->delete();
        // });

        return response()->json(null, 204);
    }

    /**
     * Simple list for dropdowns (if different from index).
     */
    public function indexList()
    {
        // For dropdowns, usually only active companies
        $companies = Company::where('status', true)->orderBy('name')->get(['id', 'name']);
        // Using CompanyResource for consistency, but could return raw array for performance
        return CompanyResource::collection($companies);
    }
}