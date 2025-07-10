<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MainTest;
use App\Models\CompanyMainTest; // The pivot model
use Illuminate\Http\Request;
use App\Http\Resources\CompanyMainTestResource;
use App\Http\Resources\MainTestStrippedResource; // For available tests
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CompanyMainTestController extends Controller
{
    public function __construct() {
        // Permissions: e.g., 'view company_contracts', 'manage company_contracts'
    }

    // List contracted tests for a specific company
    public function index(Request $request, Company $company)
    {
        // $this->authorize('view', $company); // Or specific contract view permission
        $query = $company->contractedMainTests()->with('container')->orderBy('id','asc');
        
        if($request->filled('search')) {
            $query->where('main_test_name', 'LIKE', '%' . $request->search . '%');
        }

        $contractedTests = $query->paginate($request->get('per_page', 20));
        return CompanyMainTestResource::collection($contractedTests);
    }

    // List main tests NOT yet contracted by this company
    public function availableMainTests(Company $company)
    {
        // $this->authorize('update', $company); // Or manage_contracts permission
        $contractedTestIds = $company->contractedMainTests()->pluck('main_tests.id');
        $availableTests = MainTest::where('available', true) // Only active main tests
                                  ->whereNotIn('id', $contractedTestIds)
                                  ->orderBy('main_test_name')
                                  ->get(['id', 'main_test_name', 'price']); // Include default price
        return MainTestStrippedResource::collection($availableTests);
    }

    // Add a new test contract to a company
    public function store(Request $request, Company $company)
    {
        // $this->authorize('update', $company);
        $validatedData = $request->validate([
            'main_test_id' => [
                'required','integer','exists:main_tests,id',
                Rule::unique('company_main_test')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'status' => 'required|boolean',
            'price' => 'required|numeric|min:0',
            'approve' => 'required|boolean',
            'endurance_static' => 'required|integer|min:0',
            'endurance_percentage' => 'required|numeric|min:0|max:100',
            'use_static' => 'required|boolean',
        ]);

        $company->contractedMainTests()->attach($validatedData['main_test_id'], [
            'status' => $validatedData['status'],
            'price' => $validatedData['price'],
            'approve' => $validatedData['approve'],
            'endurance_static' => $validatedData['endurance_static'],
            'endurance_percentage' => $validatedData['endurance_percentage'],
            'use_static' => $validatedData['use_static'],
        ]);

        // Fetch the newly attached MainTest with its pivot data
        $newlyContractedTest = $company->contractedMainTests()
                                     ->where('main_tests.id', $validatedData['main_test_id'])
                                     ->first();
        return new CompanyMainTestResource($newlyContractedTest);
    }

    // Update an existing test contract for a company
    public function update(Request $request, Company $company, MainTest $mainTest) // Route model binding for MainTest
    {
        // $this->authorize('update', $company);
        if (!$company->contractedMainTests()->where('main_tests.id', $mainTest->id)->exists()) {
            return response()->json(['message' => 'Test contract not found for this company.'], 404);
        }
        $validatedData = $request->validate([
            'status' => 'sometimes|required|boolean',
            'price' => 'sometimes|required|numeric|min:0',
            'approve' => 'sometimes|required|boolean',
            'endurance_static' => 'sometimes|required|integer|min:0',
            'endurance_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'use_static' => 'sometimes|required|boolean',
        ]);
        
        $company->contractedMainTests()->updateExistingPivot($mainTest->id, $validatedData);
        
        $updatedContractedTest = $company->contractedMainTests()
                                       ->where('main_tests.id', $mainTest->id)
                                       ->first();
        return new CompanyMainTestResource($updatedContractedTest);
    }

    // Remove a test contract from a company
    public function destroy(Company $company, MainTest $mainTest)
    {
        // $this->authorize('update', $company);
         if (!$company->contractedMainTests()->where('main_tests.id', $mainTest->id)->exists()) {
            return response()->json(['message' => 'Test contract not found for this company.'], 404);
        }
        $company->contractedMainTests()->detach($mainTest->id);
        return response()->json(null, 204);
    }

    // Import all active main tests to this company's contract (similar to services)
    public function importAllMainTests(Request $request, Company $company)
    {
        // $this->authorize('update', $company);
        $allActiveMainTests = MainTest::where('available', true)->get();
        // return $allActiveMainTests;
        $existingContractedTestIds = $company->contractedMainTests()->pluck('main_tests.id')->toArray();
        
        $testsToImport = $allActiveMainTests->reject(fn ($test) => in_array($test->id, $existingContractedTestIds));
        // return ['testsToImport' => $testsToImport,'availableTests' => $allActiveMainTests]; 
        if ($testsToImport->isEmpty()) {
            return response()->json(['message' => 'جميع الفحوصات النشطة مضافة بالفعل.', 'imported_count' => 0]);
        }

        $defaultContractTerms = [
            
            'status' => $request->input('default_status', true),
            'approve' => $request->input('default_approve', true),
            'endurance_static' => $request->input('default_endurance_static', 0),
            'endurance_percentage' => $request->input('default_endurance_percentage', $company->lab_endurance ?? 0), // Use company default
            'use_static' => $request->input('default_use_static', false),
        ];
        
        $attachData = [];
        foreach ($testsToImport as $test) {
            // return $test;
            $attachData[$test->id] = array_merge(
                $defaultContractTerms,
                ['price' => $test->price ?? 0] // Default contract price to test's standard price
            );
        }
        
        $company->contractedMainTests()->attach($attachData);
        return response()->json([
            'message' => "تم استيراد " . count($attachData) . " فحص بنجاح.",
            'imported_count' => count($attachData),
        ]);
    }
}