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
Route::get('alipay', function (){
   return app('alipay')->web([
      'out_trade_no' => time(),
       'total_amount' => '2',
       'subject' => 'test subject1 - 测试'
   ]);
});

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
});

Route::get('products/{product}','ProductsController@show')->name('products.show');


