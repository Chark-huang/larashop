<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/','/products')->name('root');
Route::get('products','ProductsController@index')->name('products.index');

Auth::routes(['verify' => true]);

Route::group(['middleware' => ['auth', 'verified']], function () {
    //收货地址
    Route::get('user_addresses', 'UserAddressesController@index')->name('user_addresses.index');
    Route::get('user_addresses/create', 'UserAddressesController@create')->name('user_addresses.create');
    Route::post('user_addresses', 'UserAddressesController@store')->name('user_addresses.store');
    //隐式路由绑定, 该参数名要等于Controller-fun的参数名
    Route::get('user_addresses/{user_address}', 'UserAddressesController@edit')->name('user_addresses.edit');
    Route::put('user_addresses/{user_address}', 'UserAddressesController@update')->name('user_addresses.update');
    Route::delete('user_addresses/{user_address}', 'UserAddressesController@destroy')->name('user_addresses.destroy');

    //商品收藏
    Route::post('products/{product}/favorite','ProductsController@favor')->name('products.favor');
    Route::delete('products/{product}/favorite','ProductsController@disfavor')->name('products.disfavor');
    Route::get('products/favorites','ProductsController@favorites')->name('products.favorites');

    //购物车
    Route::post('cart','CartController@add')->name('cart.add');
    Route::get('cart','CartController@index')->name('cart.index');
    Route::delete('cart/{sku}','CartController@remove')->name('cart.remove');

    //订单
    Route::get('orders','OrdersController@index')->name('orders.index');
    Route::post('orders','OrdersController@store')->name('orders.store');
    Route::get('orders/{order}','OrdersController@show')->name('orders.show');
    Route::get('orders/{order}/review', 'OrdersController@review')->name('orders.review.show');
    Route::post('orders/{order}/review', 'OrdersController@sendReview')->name('orders.review.store');

    //支付宝
    //支付路由
    Route::get('payment/{order}/alipay','PaymentController@payByAlipay')->name('payment.alipay');
    //回调路由
    Route::get('payment/alipay/return','PaymentController@alipayReturn')->name('payment.alipay.return');
    Route::post('orders/{order}/received', 'OrdersController@received')->name('orders.received'); //评价
    Route::post('orders/{order}/apply_refund', 'OrdersController@applyRefund')->name('orders.apply_refund'); //申请退款

    //优惠券路由
    Route::get('coupon_codes/{code}', 'CouponCodesController@show')->name('coupon_codes.show');

    //众筹订单路由
    Route::post('crowdfunding_orders', 'OrdersController@crowdfunding')->name('crowdfunding_orders.store');

    //分期付款
    Route::get('installments','InstallmentsController@index')->name('installments.index');
    Route::get('installments/{installment}','InstallmentsController@show')->name('installments.show');
    Route::post('payment/{order}/installment', 'PaymentController@payByInstallment')->name('payment.installment');
    Route::get('installments/{installment}/alipay', 'InstallmentsController@payByAlipay')->name('installments.alipay');
    Route::get('installments/alipay/return', 'InstallmentsController@alipayReturn')->name('installments.alipay.return');

});

Route::post('installments/alipay/notify', 'InstallmentsController@alipayNotify')->name('installments.alipay.notify');
Route::post('payment/alipay/notify','PaymentController@alipayNotify')->name('payment.alipay.notify');
Route::get('products/{product}','ProductsController@show')->name('products.show');


