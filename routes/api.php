<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\TopicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    categoryController,
    SubCategoryController,
    UomController,
    VoucherController,
    BusinessReportsController,
    StoreController,
    PersonController,
    ItemController,
    PurchaseOrderController,
    InvoiceController,
    QuotationController,
    salePoController,
    ItemInventoryController,
    ItemTypeController,
    CoaGroupController,
    CoaSubGroupController,
    CoaAccountController,
    DashboardController,
    SendEmailController,
    InvoiceReturnController,
    ReturnPurhaseController,
    PdfSettingController,
    StrengthUnitController,
    AdjustInventoryController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * Registring user
 *
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request email
 * @param \Illuminate\Http\Request password
 * @param \Illuminate\Http\Request role_id
 * @param \Illuminate\Http\Request company_id
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */

Route::post('register', [AuthController::class, 'register']);

/**
 *This is a login route
 * Return success or error message
 * @param  \Illuminate\Http\Request  $email
 * @param  \Illuminate\Http\Request  $password
 * @return string $result
 */

Route::post('login', [AuthController::class, 'login']);
/**
 *
 * @param  \Illuminate\Http\Request  $token
 * @return string $result
 */
Route::post('logout', [AuthController::class, 'logout']);

// Route::group(['middleware' => ['jwt.verify']], function () {

/**
 * getting users list
 * @param Illuminate\Http\Request records
 * @param Illuminate\Http\Request pageNo
 * @param Illuminate\Http\Request colName
 * @param Illuminate\Http\Request sort
 * @return string $users
 */
Route::get('getUsers', [AuthController::class, 'getUsers']);

/**
 * deleting user
 * @param Illuminate\Http\Request id
 * @return string result
 */
Route::delete('deleteUser', [AuthController::class, 'deleteUser']);
/**
 * edit user
 * @param Illuminate\Http\Request id

 */
Route::get('editUser', [AuthController::class, 'editUser']);
/**
 * update user
 * @param Illuminate\Http\Request id
 * @return string result
 */
Route::post('updateUser', [AuthController::class, 'updateUser']);
/**
 * Remove the specified resource from storage.
 *
 * @param  int  $email
 * @param  int  $password
 * @return \Illuminate\Http\Response
 */

Route::post('changePassword', [AuthController::class, 'changePassword']);

Route::get('getRoles', [AuthController::class, 'getRoles']);


/**
 * Active UnActine user user
 *
 * @param \Illuminate\Http\Request user_id
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::get('togalSystemUser', [AuthController::class, 'togalSystemUser']);
//---------------------Chart of accounts routes-----------------------
//------------------------coa sub group routes---------------------------

/**
 * Display a listing of the resource.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getCoaSubGroups', [CoaSubGroupController::class, 'index']);

/**
 * Store a newly created CoaSubGroup in storage.
 *
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request coa_group_id
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('addCoaSubGroups', [CoaSubGroupController::class, 'store']);


/**
 * Display a listing of the resource.
 * @param \Illuminate\Http\Request coa_group_id
 * @return \Illuminate\Http\Response
 */
Route::get('coaSubGroupsByGroup', [CoaSubGroupController::class, 'coaSubGroupsByGroup']);

/**
 * Making sub group active or incactive
 *
 * @param \Illuminate\Http\Request sub_group_id
 * @return \Illuminate\Http\Response
 */
Route::get('makeSubGroupActiveOrInactive', [CoaSubGroupController::class, 'makeSubGroupActiveOrInactive']);

/**
 * Display a listing of the resource.
 * @param \Illuminate\Http\Request type(optional)
 * @return \Illuminate\Http\Response
 */
Route::get('getRequiredCoaSubGroups', [CoaSubGroupController::class, 'getRequiredSubGroups']);

/**
 * editing coa sub group
 *
 * @param \Illuminate\Http\Request sub_group_id
 * @return \Illuminate\Http\Response
 */
Route::get('editCoaSubGroup', [CoaSubGroupController::class, 'edit']);

/**
 * Updating coa subgroup.
 *
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request coa_group_id
 * @param \Illuminate\Http\Request coa_sub_group_id
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('updateCoaSubGroup', [CoaSubGroupController::class, 'update']);

/**
 * deleting coa sub group
 *
 * @param \Illuminate\Http\Request coa_sub_group_id
 * @return \Illuminate\Http\Response
 */
Route::get('deleteCoaSubGroup', [CoaSubGroupController::class, 'delete']);


//----------------------------------------coa accounts routes-----------------------------------

/**
 * Display a listing of the resource.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getCoaAccounts', [CoaAccountController::class, 'index']);

/**
 * Store a newly created CoaAccount in storage.
 *
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request coa_group_id
 * @param \Illuminate\Http\Request person_id (optional)
 * @param \Illuminate\Http\Request coa_sub_group_id (optional)
 * @param \Illuminate\Http\Request description (optional)
 * @param \Illuminate\Http\Request code
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('addCoaAccount', [CoaAccountController::class, 'store']);

/**
 * Getting coaAccounts by coaGroup.
 * @param \Illuminate\Http\Request coa_group_id
 * @return \Illuminate\Http\Response
 */
Route::get('getAccountsByGroup', [CoaAccountController::class, 'getAccountsByGroup']);

/**
 * Getting coaAccounts by coaSubGroup.
 * @param \Illuminate\Http\Request coa_sub_group_id
 * @return \Illuminate\Http\Response
 */
Route::get('getAccountsBySubGroup', [CoaAccountController::class, 'getAccountsBySubGroup']);

/**
 * Getting coaAccount ledger
 ** @param \Illuminate\Http\Request account_id
 ** @param \Illuminate\Http\Request from
 ** @param \Illuminate\Http\Request to
 * @return \Illuminate\Http\Response
 */
Route::get('getAccountLedger', [CoaAccountController::class, 'getAccountLedger']);

/**
 * Getting accounts related to cash and bank .
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getCashAccounts', [CoaAccountController::class, 'getCashAccounts']);

/**
 * Getting accounts except  cash and bank .
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getAccountsExceptCash', [CoaAccountController::class, 'getAccountsExceptCash']);

Route::get('getAccounts', [CoaAccountController::class, 'getAccounts']);



Route::get('getAccountsExceptCashAndBanks', [CoaAccountController::class, 'getAccountsExceptCashAndBanks']);


/**
 * Getting mouza accounts.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getMouzaAccounts', [CoaAccountController::class, 'getMouzaAccounts']);

/**
 * Getting person accounts
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getPersonCoaAccounts', [CoaAccountController::class, 'getPersonCoaAccounts']);

/**
 * Getting person and mouza accounts
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getPersonAndMouzaAccounts', [CoaAccountController::class, 'getPersonAndMouzaAccounts']);

/**
 * Making account active or incactive
 *
 * @param \Illuminate\Http\Request account_id
 * @return \Illuminate\Http\Response
 */
Route::get('makeAccountActiveOrInactive', [CoaAccountController::class, 'makeAccountActiveOrInactive']);

/**
 * Display a listing of the resource.
 * @param \Illuminate\Http\Request group_id(optional)
 * @param \Illuminate\Http\Request sub_group_id(optional)
 * @param \Illuminate\Http\Request type(optional)
 * @return \Illuminate\Http\Response
 */
Route::get('getRequiredAccounts', [CoaAccountController::class, 'getRequiredAccounts']);

/**
 * editing coa account
 *
 * @param \Illuminate\Http\Request account_id
 * @return \Illuminate\Http\Response
 */
Route::get('editCoaAccount', [CoaAccountController::class, 'edit']);

/**
 * Updating coa account.
 *
 * @param \Illuminate\Http\Request account_id
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request coa_group_id
 * @param \Illuminate\Http\Request person_id (optional)
 * @param \Illuminate\Http\Request coa_sub_group_id (optional)
 * @param \Illuminate\Http\Request description (optional)
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('updateCoaAccount', [CoaAccountController::class, 'update']);

/**
 * Deleting coa account
 *
 * @param \Illuminate\Http\Request account_id
 * @return \Illuminate\Http\Response
 */
Route::get('deleteCoaAccount', [CoaAccountController::class, 'delete']);

/**
 * Getting files and payment heads by coaaccount
 *
 * @param \Illuminate\Http\Request account_id
 * @return \Illuminate\Http\Response
 */
Route::get('getFilesByAccount', [CoaAccountController::class, 'getFilesByAccount']);

/**
 * Getting files and payment heads by coaaccount
 *
 * @param \Illuminate\Http\Request account_id
 * @param \Illuminate\Http\Request file_id
 * @return \Illuminate\Http\Response
 */
Route::get('getPaymentHeadsByFileAndAccount', [CoaAccountController::class, 'getPaymentHeads']);

/**
 * Getting accounts except cash and bank .
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getAccountsExceptCashAndBank', [CoaAccountController::class, 'getAccountsExceptCashAndBank']);



//---------------------------------------------------Coa Group Routes---------------------------
Route::get('getCoaGroups', [CoaGroupController::class, 'index']);
//--------------------------------------------Accounting Routes--------------------------

/**
 * Balance sheet
 *
 * @param \Illuminate\Http\Request date
 * @return \Illuminate\Http\Response
 */
Route::get('getBalanceSheet', [BusinessReportsController::class, 'getBalanceSheet']);


/**
 * Trail Balance
 *
 * @param \Illuminate\Http\request from
 * @param \Illuminate\Http\request to
 * @return \Illuminate\Http\Response
 */
Route::get('getTrailBalance', [BusinessReportsController::class, 'getTrailBalance']);

/**
 * Displaying GeneralJournal
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getGeneralJournal', [BusinessReportsController::class, 'getGeneralJournal']);


/**
 *  daily closing report
 *
 * @param \Illuminate\Http\date
 * @return \Illuminate\Http\Response
 */
Route::post('getDailyClosingReport', [BusinessReportsController::class, 'getDailyClosingReport']);

/* --------- New Reports for to show of balance -------------- */
/**
 * Customer Receive Balance
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getCustomerReceivebleBalance', [BusinessReportsController::class, 'getCustomerReceivebleBalance']);
/**
 * Supplier Payable Balance
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getSupplierPayableBalance', [BusinessReportsController::class, 'getSupplierPayableBalance']);


//----------------------------------------------Vouchers Routes---------------------------
/**
 * Display a listing of the resource.
 *
 * @param \Illuminate\Http\Request type
 * @return \Illuminate\Http\Response
 */
Route::get('getVouchers', [VoucherController::class, 'index']);

/**
 * Display a listing of the resource.
 *
 * @param \Illuminate\Http\Request colName
 * @param \Illuminate\Http\Request sort
 * @param \Illuminate\Http\Request records
 * @param \Illuminate\Http\Request pageNo
 * @return \Illuminate\Http\Response
 */
Route::get('getVouchers2', [VoucherController::class, 'index2']);

/**
 * Store a newly created voucher in storage.
 *
 * @param \Illuminate\Http\Request type
 * @param \Illuminate\Http\Request voucher_no
 * @param \Illuminate\Http\Request date
 * @param \Illuminate\Http\Request total_amount
 * @param \Illuminate\Http\Request cheque_no
 * @param \Illuminate\Http\Request list
 * @param \Illuminate\Http\Request debit_account_id
 * @param \Illuminate\Http\Request credit_account_id
 * @param \Illuminate\Http\Request amount
 * @param \Illuminate\Http\Request description
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('addVoucher', [VoucherController::class, 'store']);
Route::post('invoicePaymentVoucher', [VoucherController::class, 'invoicePaymentVoucher']);
Route::post('poPaymentVoucher', [VoucherController::class, 'poPaymentVoucher']);

//------------------------------------------------------------------------------
/**
 * Store a newly created voucher in storage.
 *
 * @param \Illuminate\Http\Request type
 * @param \Illuminate\Http\Request voucher_no
 * @param \Illuminate\Http\Request date
 * @param \Illuminate\Http\Request total_amount
 * @param \Illuminate\Http\Request cheque_no
 * @param \Illuminate\Http\Request file_id(optional)
 * @param \Illuminate\Http\Request stage_id(optional)
 * @param \Illuminate\Http\Request transaction_array
 * @param \Illuminate\Http\Request debit_account_id
 * @param \Illuminate\Http\Request credit_account_id
 * @param \Illuminate\Http\Request amount
 * @param \Illuminate\Http\Request description
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('storeExtendedJv', [VoucherController::class, 'storeExtendedJv']);


/**
 * Displaying voucher details
 *
 * @param \Illuminate\Http\Request voucher_id
 * @return \Illuminate\Http\Response
 */
Route::get('getVoucherDetails', [VoucherController::class, 'getVoucherDetails']);

/**
 * Approve or unapprove voucher
 *
 * @param \Illuminate\Http\Request voucher_id
 * @return \Illuminate\Http\Response
 */
Route::get('approveOrUnapproveVoucher', [VoucherController::class, 'approveOrUnapproveVoucher']);

/**
 * Delete voucher
 *
 * @param \Illuminate\Http\Request voucher_id
 * @return \Illuminate\Http\Response
 */
Route::get('deleteVoucher', [VoucherController::class, 'delete']);

/**
 * Edit voucher
 *
 * @param \Illuminate\Http\Request voucher_id
 * @return \Illuminate\Http\Response
 */
Route::get('editVoucher', [VoucherController::class, 'edit']);

/**
 * updating voucher
 *
 * @param \Illuminate\Http\Request voucher_id
 * @param \Illuminate\Http\Request type
 * @param \Illuminate\Http\Request type
 * @param \Illuminate\Http\Request voucher_no
 * @param \Illuminate\Http\Request date
 * @param \Illuminate\Http\Request total_amount
 * @param \Illuminate\Http\Request cheque_no
 * @param \Illuminate\Http\Request list
 * @param \Illuminate\Http\Request debit_account_id
 * @param \Illuminate\Http\Request credit_account_id
 * @param \Illuminate\Http\Request amount
 * @param \Illuminate\Http\Request description
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('updateVoucher', [VoucherController::class, 'update']);

/**
 * Getting land transactions
 *
 * @param \Illuminate\Http\Request land_id
 * @return \Illuminate\Http\Response
 */
Route::get('getLandTransactions', [VoucherController::class, 'getLandTransactions']);
/**
 * Getting land Payment Schedule
 *
 * @param \Illuminate\Http\Request date
 * @return \Illuminate\Http\Response
 */
Route::get('getPayableSchedule', [VoucherController::class, 'getPaymentSchedule']);
/**
 * Getting plot Receivable Schedule
 *
 * @param \Illuminate\Http\Request date
 * @return \Illuminate\Http\Response
 */
Route::get('getReceivableSchedule', [VoucherController::class, 'getReceivableSchedule']);

/**
 * Clearing or rejecting post dated vouchers
 *
 * @param \Illuminate\Http\Request voucher_id
 * @param \Illuminate\Http\Request is_post_dated
 * @return \Illuminate\Http\Response
 */
Route::post('clearPostDatedVoucher', [VoucherController::class, 'clearPostDatedVoucher']);


Route::get('deleteSubGroupAccounts', [VoucherController::class, 'deleteSubGroupAccounts']);

//------------------------------Category routes------------------------------
/**
 * getting Categories list
 * @return \Illuminate\Http\Response Categories
 */
Route::get('getCategories', [categoryController::class, 'index']);

/**
 * getting Categories list
 * @return \Illuminate\Http\Response Categories
 */
Route::get('getCategoriesDropDown', [categoryController::class, 'getCategoriesDropDown']);
/**
 * adding Category
 * @param \Illuminate\Http\Response name
 * @return \Illuminate\Http\Response message
 */
Route::post('addCategory', [categoryController::class, 'store']);

/**
 * Show the form for editing the specified resource.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */
Route::get('editCategory', [categoryController::class, 'edit']);

/**
 * updating catgory
 * @param \Illuminate\Http\Response id
 * @param \Illuminate\Http\Response name
 * @return \Illuminate\Http\Response message
 */
Route::post('updateCategory', [categoryController::class, 'update']);

/**
 * Remove the specified resource from storage.
 *
 * @param  int  id
 * @return \Illuminate\Http\Response status
 * @return \Illuminate\Http\Response message
 */
Route::delete('deleteCategory', [categoryController::class, 'destroy']);


//-----------------------------------------------subcategory routes--------------------------------------
/**
 * Store a newly created resource in storage.
 *@param \Illuminate\Http\Response name
 *@param \Illuminate\Http\Response category_id
 * @return \Illuminate\Http\Response
 */
Route::post('addSubCategories', [SubCategoryController::class, 'store']);
/**
 * Subcategories listing.

 * @param \Illuminate\Http\Response colName
 * @param \Illuminate\Http\Response sort
 * @param \Illuminate\Http\Response records
 * @param \Illuminate\Http\Response pageNo
 */
Route::get('getSubCategories', [SubCategoryController::class, 'index']);
/**
 * Edit subcategories
 * @param \Illuminate\Http\Response id
 * @return \Illuminate\Http\Response
 */
Route::get('editSubCategories', [SubCategoryController::class, 'edit']);
/**
 * Update machinepartsmodels data
 *@param \Illuminate\Http\Response name
 *@param \Illuminate\Http\Response category_id

 */
Route::post('updateSubCategories', [SubCategoryController::class, 'update']);
/**
 * Delete subcategory.
 * @param \Illuminate\Http\Response id
 * @return \Illuminate\Http\Response
 */
Route::delete('deleteSubCategories', [SubCategoryController::class, 'destroy']);
/**
 * getting subCategories list
 * @return \Illuminate\Http\Response subCategories
 */
Route::get('getSubCategoriesDropDown', [SubCategoryController::class, 'getsubCategoriesDropDown']);

//-----------------------Store API Start ---------------------
/**
 * Dropdown of store type.
 * @return \Illuminate\Http\Response
 */
Route::get('getStoreTypeDropDown', [StoreController::class, 'getStoreTypeDropDown']);

/**
 * adding Stores data
 * @param \Illuminate\Http\Response tpye_id
 * @param \Illuminate\Http\Response name
 * @param \Illuminate\Http\Response address
 * @return \Illuminate\Http\Response
 */

Route::post('addStore', [StoreController::class, 'store']);

/**
 * Display a listing of the stores.
 * @param \Illuminate\Http\Response colName
 * @param \Illuminate\Http\Response sort
 * @param \Illuminate\Http\Response records
 * @param \Illuminate\Http\Response pageNo
 * @return \Illuminate\Http\Response
 */
Route::get('getStores', [StoreController::class, 'index']);
/**
 * Delete a Store.
 * @param \Illuminate\Http\Response id
 * @return \Illuminate\Http\Response
 */
Route::delete('deleteStore', [StoreController::class, 'destroy']);

//---------------------------------Person Routes--------------------------------------------------
/**
 * Store a newly created person in storage.
 *
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request phone_no
 * @param \Illuminate\Http\Request email
 * @param \Illuminate\Http\Request cnic
 * @param \Illuminate\Http\Request address
 * @param \Illuminate\Http\Request father_name
 * @param \Illuminate\Http\Request person_type_id
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */
Route::post('addPerson', [PersonController::class, 'store']);
Route::post('addTestPerson', [ItemController::class, 'addTestPerson']);
/**
 * Gettng active suppliers.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getActiveSuppliers', [PersonController::class, 'getActiveSuppliers']);
Route::get('testAccount', [PersonController::class, 'testAccount']);
/**
 * Gettng Person Types.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getPersonTypes', [PersonController::class, 'getPersonTypes']);

Route::get('getPersonsDropDown', [PersonController::class, 'getPersonsDropDown']);


/**
 * Gettng Persons.
 *
 * @param \Illuminate\Http\Request person_type_id
 * @return \Illuminate\Http\Response
 */
Route::get('getPersons', [PersonController::class, 'index']);
/**
 * Edit Persons.
 * @param \Illuminate\Http\Request person_id
 * @return \Illuminate\Http\Response
 */
Route::get('editPerson', [PersonController::class, 'edit']);

/**
 * Update Persons
 *
 * @param \Illuminate\Http\Request person_id
 *
 * @param \Illuminate\Http\Request name
 * @param \Illuminate\Http\Request phone_no
 * @param \Illuminate\Http\Request cnic
 * @param \Illuminate\Http\Request email
 * @param \Illuminate\Http\Request address
 * @return \Illuminate\Http\Response message
 * @return \Illuminate\Http\Response status
 */

Route::post('updateperson', [PersonController::class, 'update']);

/**
 * deleting person
 * @param \Illuminate\Http\Request person_id
 * @return \Illuminate\Http\Response
 */
Route::delete('deletePerson', [PersonController::class, 'destroy']);
/**
 * Gettng Persons By Person Type.
 * @param \Illuminate\Http\Request person_type_id
 * @return \Illuminate\Http\Response
 */
Route::get('getPersonsByPersonType', [PersonController::class, 'getPersonsByPersonType']);
/**
 * Gettng Person files.
 *
 * @param \Illuminate\Http\Request account_id
 * @return \Illuminate\Http\Response
 */
Route::get('getFilesByPersonOrMouza', [PersonController::class, 'getFilesByPersonOrMouza']);

/**
 * Getting person all accounts.
 * @param  int  $person_id
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getPersonAllAccounts', [PersonController::class, 'getPersonAllAccounts']);

/**
 * Getting persons accounts balance.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getPersonCoaAccountsBalance', [PersonController::class, 'getPersonCoaAccountsBalance']);

Route::get('getCapitalAccounts', [CoaAccountController::class, 'getCapitalAccounts']);

Route::get('getInventoryAccounts', [CoaAccountController::class, 'getInventoryAccounts']);

Route::get('getDisposeAccounts', [CoaAccountController::class, 'getDisposeAccounts']);

/**
 * Getting accounts with duplicate person_id
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getDuplicatePersonAccounts', [CoaAccountController::class, 'getDuplicatePersonAccounts']);

/**
 * @param \Illuminate\Http\Request person_type_id
 * Display a listing of the resource.
 *
 * @return \Illuminate\Http\Response
 */
Route::get('getRequiredPersons', [PersonController::class, 'getRequiredPersons']);

//-----------------------Item API Start ---------------------
/**
 * Dropdown of Items type.
 * @return \Illuminate\Http\Response
 */
Route::get('getItemsDropDown', [ItemController::class, 'getItemsDropDown']);

Route::get('getItemsDropDownSale', [ItemController::class, 'getItemsDropDownSale']);

Route::get('getItemsDropDownQuotation', [ItemController::class, 'getItemsDropDownQuotation']);

Route::get('getItemsDropDownPo', [ItemController::class, 'getItemsDropDownPo']);

Route::get('getbatchNo', [ItemController::class, 'getbatchNo']);

/**
 * adding Items data
 * @param \Illuminate\Http\Response subcategory_id
 * @param \Illuminate\Http\Response name
 * @param \Illuminate\Http\Response type
 * @param \Illuminate\Http\Response strength
 * @param \Illuminate\Http\Response manufacture_id
 * @param \Illuminate\Http\Response nomenclature
 * @param \Illuminate\Http\Response minimumlevel
 * @param \Illuminate\Http\Response unit_id
 * @return \Illuminate\Http\Response
 */

Route::post('addItem', [ItemController::class, 'store']);

/**
 * Display a listing of the Items.
 * @param \Illuminate\Http\Response subcategory_id
 * @param \Illuminate\Http\Response manufacture_id
 * @param \Illuminate\Http\Response unit_id
 * @param \Illuminate\Http\Response colName
 * @param \Illuminate\Http\Response sort
 * @param \Illuminate\Http\Response records
 * @param \Illuminate\Http\Response pageNo
 * @return \Illuminate\Http\Response
 */
Route::get('getItems', [ItemController::class, 'index']);

Route::get('itemDetails', [ItemController::class, 'itemDetails']);


Route::get('editItem', [ItemController::class, 'edit']);
Route::get('editItemPrices', [ItemController::class, 'editItemPrices']);

Route::post('updateItem', [ItemController::class, 'update']);
Route::post('updateItemPrices', [ItemController::class, 'updateItemPrices']);
/**
 * Delete a Item.
 * @param \Illuminate\Http\Response id
 * @return \Illuminate\Http\Response
 */
Route::delete('deleteItem', [ItemController::class, 'destroy']);

Route::get('itemInventory', [ItemController::class, 'itemInventory']);

Route::post('activeUnactiveItem', [ItemController::class, 'activeUnactiveItem']);


//Adjust Inventory

Route::get('getAdjustInventory', [AdjustInventoryController::class, 'index']);

Route::get('viewAdjustInventory', [AdjustInventoryController::class, 'view']);

Route::post('addAdjustItemStock', [AdjustInventoryController::class, 'store']);

Route::get('editAdjustItemInventory', [AdjustInventoryController::class, 'edit']);

Route::post('updateAdjustInventory', [AdjustInventoryController::class, 'update']);

Route::delete('deleteAdjustItemInventory', [AdjustInventoryController::class, 'destroy']);

//-----------------------------Uom routes--------------------------------
/**
 * getting Uom list
 * @return \Illuminate\Http\Response Uom
 */
Route::get('getUnitDropdown', [UomController::class, 'getUOmDropdown']);

//--------------Purchase Order API start------------------------

/**
 * Store a newly created resource in storage.
 *
 * @param  \Illuminate\Http\Request  $po_no
 * @param  \Illuminate\Http\Request  $supplier_id
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $request_date
 * @param  \Illuminate\Http\Request  $remarks
 * -------------------childArray
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $pack
 * @param  \Illuminate\Http\Request  $quoted_rate
 * @param  \Illuminate\Http\Request  $rate
 * @param  \Illuminate\Http\Request  $quantity
 * @param  \Illuminate\Http\Request  $total
 * @param  \Illuminate\Http\Request  $remarks
 * @return \Illuminate\Http\Response
 */
Route::post('addPurchaseOrder', [PurchaseOrderController::class, 'store']);

/**
 * Direct purchase order
 *
 * @param  \Illuminate\Http\Request  $po_no
 * @param  \Illuminate\Http\Request  $request_date
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $remarks
 * @param  \Illuminate\Http\Request  $total
 * -------------------childArray
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $rate
 * @param  \Illuminate\Http\Request  $pack
 * @param  \Illuminate\Http\Request  $quantity
 * @param  \Illuminate\Http\Request  $total
 * @param  \Illuminate\Http\Request  $remarks
 * @return \Illuminate\Http\Response
 */
Route::post('directPurchaseOrder', [PurchaseOrderController::class, 'directPurchaseOrder']);
/**
 * Show the form for editing direct Purchase Oder.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */

Route::get('editDirectPurchaseOrder', [PurchaseOrderController::class, 'editDirectPurchaseOrder']);
/*
* get latest po number.
* @return \Illuminate\Http\Response
*/

Route::get('getLatestpono', [PurchaseOrderController::class, 'getLatestpono']);
/*
* get purchase orders list.
 @param \Illuminate\Http\Response colName
 * @param \Illuminate\Http\Response sort
 * @param \Illuminate\Http\Response records
 * @param \Illuminate\Http\Response pageNo

 * @param  \Illuminate\Http\Request  supplier_id
 * @param  \Illuminate\Http\Request  po_no
 * @param  \Illuminate\Http\Request  from_date
 * @param  \Illuminate\Http\Request  to_date
 * @param  \Illuminate\Http\Request  searcField
 * @return \Illuminate\Http\Response purchaseorderlist
*/

Route::get('getPolist', [PurchaseOrderController::class, 'getPolist']);

Route::get('approveOrUnapprovePO', [PurchaseOrderController::class, 'approveOrUnapprovePO']);

/**
 * getting po details
 * @param  \Illuminate\Http\Request  po_id
 * @return \Illuminate\Http\Response po details
 */
Route::get('getPoDetails', [PurchaseOrderController::class, 'getPoDetails']);

/**
 * Show the form for editing the Purchase Oder.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */

Route::get('editPurchaseOrder', [PurchaseOrderController::class, 'edit']);

Route::get('receivePObyid', [PurchaseOrderController::class, 'receivePObyid']);

Route::get('supplierPurchaseOrder', [PurchaseOrderController::class, 'supplierPurchaseOrder']);
/**
 * Purchase Oder children.
 *

 * @return \Illuminate\Http\Response po child
 */
Route::get('getPoChild', [PurchaseOrderController::class, 'getPoChild']);
/**
 * get po history.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */

Route::get('getPoHistory', [PurchaseOrderController::class, 'getPoHistory']);
/**
 *  Purchase Oder Complete Details.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */

Route::get('ViewPurchaseOrderDetails', [PurchaseOrderController::class, 'ViewPurchaseOrderDetails']);

Route::post('updatePurchaseOrder', [PurchaseOrderController::class, 'update']);

/**
 * Delete the Purchase Oder.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */
Route::delete('deletePurchaseOrder', [PurchaseOrderController::class, 'destroy']);

/**
 * average of item price.
 *
 * @param  int  machine_part_id
 * @param  int  item_part_id
 * @param  int  sub_category_id
 * @param  int  category_id
 * @return \Illuminate\Http\Response average of item price
 */
Route::get('getItemsRates', [PurchaseOrderController::class, 'getItemsRates']);


/**
 * receiving purchase order
 *
 * @param  \Illuminate\Http\Request  $id
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $total
 * @param  \Illuminate\Http\Request  $discount
 * @param  \Illuminate\Http\Request  $tax
 * @param  \Illuminate\Http\Request  $tax_in_figure
 * @param  \Illuminate\Http\Request  $total_after_discount
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $remarks
 * -------------------childArray
 * @param  \Illuminate\Http\Request  $id
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $received_quantity
 * @param  \Illuminate\Http\Request  $rate
 * @param  \Illuminate\Http\Request  $pack
 * @param  \Illuminate\Http\Request  $total
 * @param  \Illuminate\Http\Request  $remarks
 * @return \Illuminate\Http\Response
 */
Route::post('receivePurchaseOrder', [PurchaseOrderController::class, 'receivePurchaseOrder']);

//---------------Purchase order reports start------------------------

/**
 * Purchase Report.
 * @param  \Illuminate\Http\Request  $supplier_id
 * @param  \Illuminate\Http\Request  $from
 * @param  \Illuminate\Http\Request  $to
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $item_id
 * @return \Illuminate\Http\Response
 */
Route::get('getPurchaseReport', [PurchaseOrderController::class, 'getPurchaseReport']);

Route::get('getPurchaseReportSupplierWise', [PurchaseOrderController::class, 'getPurchaseReportSupplierWise']);
// routes/api.php
Route::get('/getItemsBySupplier/{personId}', [ItemController::class, 'getItemsBySupplier']);




Route::post('receivePurchaseOrderComplete',[PurchaseOrderController::class, 'receivePurchaseOrderComplete']);

//---------------------Quotation start-----------------

/**
 * Store a newly created resource in storage.
 *
 * @param  \Illuminate\Http\Request  $customer_id
 * @param  \Illuminate\Http\Request  $quotation_no
 * @param  \Illuminate\Http\Request  $ref_no
 * @param  \Illuminate\Http\Request  $date
 * @param  \Illuminate\Http\Request  $termcondition
 * -------------------childArray
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $manufacture_id
 * @param  \Illuminate\Http\Request  $pack
 * @param  \Illuminate\Http\Request  $retail_price
 * @param  \Illuminate\Http\Request  $trade_price
 * @param  \Illuminate\Http\Request  $quantity
 * @param  \Illuminate\Http\Request  $quoted_price
 * @param  \Illuminate\Http\Request  $gst
 * @param  \Illuminate\Http\Request  $gst_amount
 * @return \Illuminate\Http\Response
 */

Route::post('addQuotation', [QuotationController::class, 'store']);

Route::get('getLatestQuotationNo', [QuotationController::class, 'getLatestQuotationNo']);

/*
* get Quotation list.
 @param \Illuminate\Http\Response colName
 * @param \Illuminate\Http\Response sort
 * @param \Illuminate\Http\Response records
 * @param \Illuminate\Http\Response pageNo

 * @param  \Illuminate\Http\Request  customer_id
 * @param  \Illuminate\Http\Request  quotation_no
 * @param  \Illuminate\Http\Request  from
 * @param  \Illuminate\Http\Request  to
 * @return \Illuminate\Http\Response quotationlist
*/
Route::get('getQuotationlist', [QuotationController::class, 'index']);

Route::get('editQuotation', [QuotationController::class, 'edit']);

Route::get('getQuotationForIntiaite', [QuotationController::class, 'getQuotationForIntiaite']);
Route::get('ViewQuotationDetails', [QuotationController::class, 'ViewQuotationDetails']);

Route::post('updateQuotation', [QuotationController::class, 'update']);

Route::delete('deleteQuotation', [QuotationController::class, 'destroy']);

Route::get('approveOrUnapproveQuotation', [QuotationController::class, 'approveOrUnapproveQuotation']);

/**
 * Store a newly created Inovice.
 *
 * @param  \Illuminate\Http\Request  $id
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $customer_id
 * @param  \Illuminate\Http\Request  $quotation_id
 * @param  \Illuminate\Http\Request  $invoice_no
 * @param  \Illuminate\Http\Request  $amount_received
 * @param  \Illuminate\Http\Request  $total_amount
 * @param  \Illuminate\Http\Request  $walk_in_customer_name
 * @param  \Illuminate\Http\Request  $date
 * @param  \Illuminate\Http\Request  $remarks
 * -------------------childArray
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $batch_no
 * @param  \Illuminate\Http\Request  $expiry_date
 * @param  \Illuminate\Http\Request  $rate
 * @param  \Illuminate\Http\Request  $sales_tax
 * @param  \Illuminate\Http\Request  $quantity
 * @param  \Illuminate\Http\Request  $amount
 * @return \Illuminate\Http\Response
 */

Route::post('generateInvoiceQuotation', [QuotationController::class, 'generateInvoiceQuotation']);


//-------------------Sales Routes stats-----------------
/**
 * Display the specified resource.
 *
 * @param  int  customer_id
 * @param  int  invoice_no
 * @param  int  walk_in_customer_name
 * @param  int  store_id
 * @param  int  item_id
 * @return \Illuminate\Http\Response
 */

Route::get('getSales', [InvoiceController::class, 'index']);

/**
 * Store a newly created resource in storage.
 *@param \Illuminate\Http\Request customer_id
 *@param \Illuminate\Http\Request walk_in_customer_name
 *@param \Illuminate\Http\Request store_id
 *@param \Illuminate\Http\Request total_amount
 *@param \Illuminate\Http\Request amount_received
 *@param \Illuminate\Http\Request date
 *@param \Illuminate\Http\Request remarks
 *@param \Illuminate\Http\Request childArray
 *@param \Illuminate\Http\Request item_id
 *@param \Illuminate\Http\Request quantity
 *@param  \Illuminate\Http\Request batch_no
 *@param  \Illuminate\Http\Request expiry_date
 *@param  \Illuminate\Http\Request rate
 *@param  \Illuminate\Http\Request sales_tax
 *@param  \Illuminate\Http\Request amount
 *@return \Illuminate\Http\Response
 */
Route::post('addNewSale', [InvoiceController::class, 'store']);
Route::post('storeDummyInvoice', [InvoiceController::class, 'storeDummyInvoice']);
Route::post('updateDummyInvoice', [InvoiceController::class, 'updateDummyInvoice']);

/**
 * Display the specified resource.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */
Route::get('getInvoiceDetail', [InvoiceController::class, 'show']);

Route::get('editSale', [InvoiceController::class, 'edit']);

Route::post('updateSale', [InvoiceController::class, 'update']);

/**
 * Delete a sale.
 * @param \Illuminate\Http\Response id
 * @return \Illuminate\Http\Response
 */
Route::delete('deleteSale', [InvoiceController::class, 'destroy']);

//-----------------Sale Reports routes start------------------

/**
 * Sales Report.
 * @param  \Illuminate\Http\Request  $sale_type
 * @param  \Illuminate\Http\Request  $walk_in_customer_name
 * @param  \Illuminate\Http\Request  $customer_id
 * @param  \Illuminate\Http\Request  $item_id
 * @return \Illuminate\Http\Response
 */
Route::get('getSalesReport', [InvoiceController::class, 'getSalesReport']);

Route::get('getInoicesByCutomer', [InvoiceController::class, 'getInoicesByCutomer']);
Route::get('getPoBySupplier', [InvoiceController::class, 'getPoBySupplier']);

/**
 * Manufacturewise Report.
 * @param  \Illuminate\Http\Request  $sale_type
 * @param  \Illuminate\Http\Request  $walk_in_customer_name
 * @param  \Illuminate\Http\Request  $customer_id
 * @param  \Illuminate\Http\Request  $manufacturer_id
 * @param  \Illuminate\Http\Request  $from
 * @param  \Illuminate\Http\Request  $to
 * @return \Illuminate\Http\Response
 */

Route::get('getSalesReportManufacturewise', [InvoiceController::class, 'getSalesReportManufacturewise']);

/**
 * Customer Wise Sales Report.
 * @param  \Illuminate\Http\Request  $sale_type
 * @param  \Illuminate\Http\Request  $customer_id
 * @param  \Illuminate\Http\Request  $from
 * @param  \Illuminate\Http\Request  $to
 * @return \Illuminate\Http\Response
 */
Route::get('getSalesReportCustomerWise', [InvoiceController::class, 'getSalesReportCustomerWise']);
/**
 * sales rep Wise Sales Report.
 * @param  \Illuminate\Http\Request  $sale_type
 * @param  \Illuminate\Http\Request  $sales_rep_id
 * @param  \Illuminate\Http\Request  $from
 * @param  \Illuminate\Http\Request  $to
 * @return \Illuminate\Http\Response
 */
Route::get('getSalesReportSalesRepWise', [InvoiceController::class, 'getSalesReportSalesRepWise']);

/**
 * get sales history.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */
Route::get('getSalesHistory', [InvoiceController::class, 'getSalesHistory']);

//----------------Sale Purchase Order-------------------------

/**
 * Store a newly created resource in storage.
 *
 * @param  \Illuminate\Http\Request  $po_no
 * @param  \Illuminate\Http\Request  $customer_id
 * @param  \Illuminate\Http\Request  $store_id
 * @param  \Illuminate\Http\Request  $request_date
 * @param  \Illuminate\Http\Request  $remarks
 * -------------------childArray
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $batch_no
 * @param  \Illuminate\Http\Request  $pack
 * @param  \Illuminate\Http\Request  $quoted_rate
 * @param  \Illuminate\Http\Request  $rate
 * @param  \Illuminate\Http\Request  $quantity
 * @param  \Illuminate\Http\Request  $total
 * @param  \Illuminate\Http\Request  $remarks
 * @return \Illuminate\Http\Response
 */
Route::post('addSalePurchaseOrder', [salePoController::class, 'store']);

/*
* get latest Sale po number.
* @return \Illuminate\Http\Response
*/

Route::get('getLatestSalepono', [salePoController::class, 'getLatestSalepono']);
/*
* get Sale purchase orders list.
 @param \Illuminate\Http\Response colName
 * @param \Illuminate\Http\Response sort
 * @param \Illuminate\Http\Response records
 * @param \Illuminate\Http\Response pageNo

 * @param  \Illuminate\Http\Request  customer_id
 * @param  \Illuminate\Http\Request  po_no
 * @param  \Illuminate\Http\Request  store_id
 * @return \Illuminate\Http\Response purchaseorderlist
*/

Route::get('getSalesPolist', [salePoController::class, 'index']);

Route::post('approveSalePo', [salePoController::class, 'approveSalePo']);

Route::post('generateInvoiceSalePo', [salePoController::class, 'generateInvoiceSalePo']);

/**
 * Show the form for editing the Sale Purchase Oder.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */

Route::get('editSalePurchaseOrder', [salePoController::class, 'edit']);
Route::get('editSalePurchaseOrderForInitiate', [salePoController::class, 'editSalePurchaseOrderForInitiate']);

/**
 *  Sale Purchase Oder Complete Details.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */

Route::get('ViewSalePODetails', [salePoController::class, 'ViewSalePODetails']);

Route::post('updateSalePo', [salePoController::class, 'update']);

/**
 * Delete the Sale Purchase Oder.
 *
 * @param  int  $id
 * @return \Illuminate\Http\Response
 */
Route::delete('deleteSalePo', [salePoController::class, 'destroy']);

//  --------------Listing of Item Inventory---------------

/**
 *  Show The Details of Item Inventory.
 *
 * @param  int  $item_id
 * @param  int  $manufacture_id
 * @param  int  $batch_no
 * @return \Illuminate\Http\Response
 */
Route::get('getItemsInventory', [ItemInventoryController::class, 'index']);
Route::get('getExpiredItemsInventory', [ItemInventoryController::class, 'getExpiredItemsInventory']);

Route::get('getDisposedStockItemsInventory', [ItemInventoryController::class, 'getDisposedStockItemsInventory']);


Route::get('getBatchNo', [ItemInventoryController::class, 'getBatchNo']);
Route::get('getStockReport', [ItemInventoryController::class, 'getStockReport']);
Route::get('getNotifications', [ItemInventoryController::class, 'getNotifications']);

/**
 * dispose expired stock.
 *
 * @param  \Illuminate\Http\Request  $item_id
 * @param  \Illuminate\Http\Request  $batch_no
 * @param  \Illuminate\Http\Request  $manufacture_id
 * @param  \Illuminate\Http\Request  $quantity
 * @param  \Illuminate\Http\Request  $expiry_date


 */
Route::post('disposeExpiredStock', [ItemInventoryController::class, 'disposeExpiredStock']);

Route::get('getAdjustItemId', [ItemInventoryController::class, 'getAdjustItemId']);

//-----------------------Item Routes Start----------------
Route::get('getItemTypeDropDown', [ItemTypeController::class, 'getItemTypeDropDown']);

Route::get('getDashboardData', [DashboardController::class, 'index']);

Route::get('getTrailBalanceForDash', [DashboardController::class, 'getTrailBalanceForDash']);

/**
 * Send Email to supplier
 * @param \Illuminate\Http\Response id
 * @param \Illuminate\Http\Response link
 * @return \Illuminate\Http\Response message
 */
Route::post('sendPOMail', [SendEmailController::class, 'index']);

Route::post('activeUnactivePerson', [PersonController::class, 'activeUnactivePerson']);

//-----------------------Invoice Return routes start ------------

Route::post('invoiceReturn', [InvoiceReturnController::class, 'store']);
Route::get('getReturnInvoices', [InvoiceReturnController::class, 'index']);
Route::get('getInvoiceForReturn', [InvoiceReturnController::class, 'edit']);
Route::get('getReturnInvoiceDetails', [InvoiceReturnController::class, 'show']);
Route::delete('deleteReturnInvoice', [InvoiceReturnController::class, 'destroy']);


Route::post('testapi', [InvoiceReturnController::class, 'testapi']);
Route::get('testapi2', [InvoiceReturnController::class, 'testapi2']);
Route::post('testapi3', [InvoiceReturnController::class, 'testapi3']);

//-----------------------Invoice Return routes start ------------

Route::get('getReturnPurchaseOrders', [ReturnPurhaseController::class, 'index']);
Route::get('getPurchaseOrderForReturn', [ReturnPurhaseController::class, 'edit']);
Route::get('getReturnPoDetails', [ReturnPurhaseController::class, 'show']);
Route::post('purchaseOrderReturn', [ReturnPurhaseController::class, 'store']);
Route::delete('deleteReturnPO', [ReturnPurhaseController::class, 'destroy']);

//-----------------------Pdf Setting routes start ------------
Route::get('getPdfSettingData', [PdfSettingController::class, 'index']);
Route::post('updatePdfSettingData', [PdfSettingController::class, 'store']);

//---------------------StrengthUnit routes start here-----------------

Route::get('StrengthUnitDropdown', [StrengthUnitController::class, 'StrengthUnitDropdown']);
Route::post('addStrengthUnit', [StrengthUnitController::class, 'store']);
Route::post('updateStrengthUnit', [StrengthUnitController::class, 'update']);
Route::get('getStrengthUnits', [StrengthUnitController::class, 'index']);
Route::get('editStrengthUnits', [StrengthUnitController::class, 'edit']);
Route::delete('deleteStrengthUnits', [StrengthUnitController::class, 'destroy']);





// });
