<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\LabRequest;
use App\Models\PaymentMethod;
use App\Models\RequestedService;
use App\Models\RequestedServiceDeposit;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentMethod::query()->orderBy('sort_order')->orderBy('id');

        if ($request->boolean('active')) {
            $query->active();
        }

        return PaymentMethodResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|alpha_dash|unique:payment_methods,code',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $paymentMethod = PaymentMethod::create($validated);

        return new PaymentMethodResource($paymentMethod);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:50|alpha_dash|unique:payment_methods,code,'.$paymentMethod->id,
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        if ($paymentMethod->is_default && array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            return response()->json(['message' => 'لا يمكن تعطيل طريقة الدفع الافتراضية.'], 422);
        }

        $paymentMethod->update($validated);

        return new PaymentMethodResource($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->is_default) {
            return response()->json(['message' => 'لا يمكن حذف طريقة الدفع الافتراضية.'], 422);
        }

        $isUsed = LabRequest::where('payment_method_id', $paymentMethod->id)->exists()
            || RequestedService::where('payment_method_id', $paymentMethod->id)->exists()
            || RequestedServiceDeposit::where('payment_method_id', $paymentMethod->id)->exists();

        if ($isUsed) {
            return response()->json(['message' => 'لا يمكن حذف طريقة الدفع لوجود عمليات دفع مرتبطة بها. يمكنك تعطيلها بدلاً من ذلك.'], 422);
        }

        $paymentMethod->delete();

        return response()->json(null, 204);
    }
}
