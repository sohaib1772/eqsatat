<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerDebtResource;
use App\Http\Resources\CustomerReceiptResource;
use App\Http\Requests\CustomerRequest\FilteringCustomerData;
use App\Http\Requests\CustomerRequest\StoreCustomerData;
use App\Http\Requests\CustomerRequest\UpdateCustomerData;

/**
 * **CustomerController**
 *
 * This controller manages customer-related operations, including:
 * - Retrieving customer lists
 * - Viewing customer details (including debts)
 * - Creating, updating, and deleting customer records
 */
class CustomerController extends Controller
{
    /**
     * Customer service instance responsible for handling customer-related logic.
     *
     * @var CustomerService
     */
    protected CustomerService $customerService;

    /**
     * **Constructor for CustomerController**
     *
     * Injects `CustomerService` to handle customer-related operations.
     *
     * @param CustomerService $customerService Handles business logic for customers.
     */
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * **Retrieve and paginate a list of customers**
     *
     * @param FilteringCustomerData $request Validated filtering criteria.
     * @return JsonResponse Paginated customer list or error message.
     */
    public function index(FilteringCustomerData $request): JsonResponse
    {
        $result = $this->customerService->getAllCustomers($request->validated());

        return $result['status'] === 200
            ? $this->paginated($result['data'], CustomerResource::class, $result['message'], $result['status']) // تمرير البيانات الأصلية
            : $this->error($result['data'], $result['message'], $result['status']);
    }


    /**
     * **Retrieve debts related to a specific customer**
     *
     * @param int $id Customer ID.
     * @return JsonResponse Customer's debts or error message.
     */
    public function getCustomerDebts($id): JsonResponse
    {
        $result = $this->customerService->getCustomerDebts($id);

        return $result['status'] === 200
            ? $this->success(CustomerDebtResource::collection($result['data']), $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * **Create a new customer record**
     *
     * @param StoreCustomerData $request Validated customer data.
     * @return JsonResponse Operation result.
     */
    public function store(StoreCustomerData $request): JsonResponse
    {
        $result = $this->customerService->createCustomer($request->validated());

        return $result['status'] === 200
            ? $this->success(null, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * **Update an existing customer record**
     *
     * @param UpdateCustomerData $request Validated update data.
     * @param Customer $customer The customer to be updated.
     * @return JsonResponse Operation result.
     */
    public function update(UpdateCustomerData $request, Customer $customer): JsonResponse
    {
        $result = $this->customerService->updateCustomer($request->validated(), $customer);

        return $result['status'] === 200
            ? $this->success(null, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * **Delete a customer record**
     *
     * @param Customer $customer The customer to be deleted.
     * @return JsonResponse Operation result.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('deleteCustomer', $customer);

        $result = $this->customerService->deleteCustomer($customer);

        return $result['status'] === 200
            ? $this->success(null, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * **Retrieve all receipts related to a customer**
     *
     * @param int $id Customer ID.
     * @return JsonResponse List of receipts or error message.
     */
    public function getCustomerReceipt($id): JsonResponse
    {
        $result = $this->customerService->getCustomerReceipt($id);

        return $result['status'] === 200
            ? $this->paginated($result["data"], CustomerReceiptResource::class, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * **Retrieve products associated with customer receipts**
     *
     * @param int $id Customer ID.
     * @return JsonResponse List of products or error message.
     */
    public function getCustomerReceiptProducts($id): JsonResponse
    {
        $result = $this->customerService->getCustomerReceiptProducts($id);

        return $result['status'] === 200
            ? $this->success($result['data'], $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }


    /**
 * **Retrieve a customer by their ID**
 *
 * @param int $id The ID of the customer.
 * @return JsonResponse Customer details or error message.
 */
public function show($id): JsonResponse
{
    $result = $this->customerService->getCustomerById($id);

    return $result['status'] === 200
        ? $this->success(new CustomerResource($result['data']), $result['message'], $result['status'])
        : $this->error(null, $result['message'], $result['status']);
}

    /**
     * Convert customer currency and historical records.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id Customer ID
     * @return JsonResponse
     */
    public function convertCurrency(\Illuminate\Http\Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'exchange_rate'   => 'required|numeric|min:0.0001',
            'target_currency' => 'nullable|in:دولار,دينار',
        ]);

        $result = $this->customerService->convertCustomerCurrency($id, $validated);

        return $result['status'] === 200
            ? $this->success(isset($result['data']) ? new CustomerResource($result['data']) : null, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

}
