<?php

namespace App\Listeners;

use App\Events\OrderReviewed;
use App\Models\OrderItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class UpadteProductRating implements ShouldQueue
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
     * @param  OrderReviewed  $event
     * @return void
     */
    public function handle(OrderReviewed $event)
    {
        // 通过 with 方法体检加载数据, 避免 N + 1 性能问题
        $items = $event->getOrder()->items()->with(['product'])->get();

        // 寻找所有清单表中1.订单条目商品id和订单条目商品id相同的所有条目,2.已评价的,2.订单已支付的
        foreach ($items as $item){
            $result = OrderItem::query()
                        ->where('product_id', $item->product_id)
                        ->whereNotNull('reviewed_at')
                        ->whereHas('order', function ($query){
                           $query->whereNotNull('paid_at');
                        })
                        ->first([
                            DB::raw('count(*) as review_count'),
                            DB::raw('avg(rating) as rating')
                        ]);
            // 更新商品评分和评价数量
            $item->product->update([
                'rating' => $result->rating,
                'review_count' => $result->review_count,
            ]);
        }
    }
}
