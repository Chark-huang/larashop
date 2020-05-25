<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\OrderItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

// implement ShouldQueue 代表此监听器是异步执行的
class UpdateProductSoldCount implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderPaid  $event
     * @return void
     */
    public function handle(OrderPaid $event)
    {
        //1.从事件对象中获取对应的订单
        $order = $event->getOrder();
        //2.预加载商品数据
        $order->load('items.product');
        //3.循环订单的商品
        foreach ($order->items as $item){
            $product = $item->product;
            //4.计算对应商品的销量
            $soldCount = OrderItem::query()
                            ->where('product_id',$product->id) //找出素有product_id有关的item并且对应的订单已支付的
                            ->whereHas('order',function ($query){
                                $query->whereNotNull('paid_at'); // 关联的订单是否已支付
                            })->sum('amount');

            //5.更新商品销量
            $product->update([
                'sold_count' => $soldCount
            ]);
        }
    }


}
