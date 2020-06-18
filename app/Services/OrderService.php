<?php


namespace App\Services;


use App\Exceptions\CouponCodeUnavailableException;
use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Jobs\RefundInstallmentOrder;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderService
{
    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $coupon = null)
    {
        // 如果传入了优惠券, 则先检查是否可用
        if ($coupon) {
            // 但此时我们还没有计算出订单总金额, 因此先不校验
            $coupon->checkAvailable($user);
        }
        // 开启一个数据库事务
        $order = DB::transaction(function () use ($user, $address, $remark, $items, $coupon) {
            // 更新此地址的最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单
            $order = new Order([
                'address' => [ //将地址信息放入订单中
                    'address' => $address->full_address,
                    'zip' => $address->zip,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark' => $remark,
                'total_amount' => 0,
                'type' => Order::TYPE_NORMAL,
            ]);
            // 订单关联到当前用户当中
            $order->user()->associate($user);
            // 写入数据库
            $order->save();

            $totalAmount = 0;

            // 遍历用户提交的 SKU
            foreach ($items as $data) {
                $sku = ProductSku::find($data['sku_id']);
                // 创建一个OrderItem 并直接与当前订单关联
                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price' => $sku->price,
                ]);
                $item->product()->associate($sku->product_id);
                $item->productSku()->associate($sku->id);
                $item->save();
                $totalAmount += $sku->price * $data['amount'];
            }
            if ($coupon) {
                // 总金额计算出来了,检查是否符合优惠券规则
                $coupon->checkAvailable($user, $totalAmount);
                // 把订单金额修改为优惠后的金额
                $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                // 将订单与优惠券关联
                $order->couponCode()->associate($coupon);
                // 增加优惠券的用量,需要判断返回值
                if ($coupon->changeUsed() <= 0) {
                    throw new CouponCodeUnavailableException('该优惠券已被兑完');
                }
            }


            // 更新订单总金额
            $order->update(['total_amount' => $totalAmount]);
            if ($sku->decreaseStock($data['amount']) <= 0) {
                throw new InvalidRequestException('该商品库存不足');
            }
            // 将下单的商品从购物车中移除
            $skuIds = collect($items)->pluck('sku_id');
            $cartService = app(CartService::class);
            $cartService->remove($skuIds);

            return $order;
        });
        //触发定时关闭订单任务
        dispatch(new CloseOrder($order, config('app.order_ttl')));
        return $order;
    }


    // 新建一个 crowdfunding 方法用于实现众筹商品下单逻辑
    public function crowdfunding(User $user, UserAddress $address, ProductSku $sku, $amount)
    {
        // 开启事务
        $order = DB::transaction(function () use ($amount, $sku, $user, $address) {
            // 1.创建一个订单实例
            // 2.写入(关联)金额,数量,地址,用户
            // 3.创建order_item数据
            // 更新地址最后的使用事件
            $address->update(['last_used_at' => Carbon::now()]);
            //创建一个订单
            $order = new Order([
                'address' =>
                    [
                        'address' => $address->full_address,
                        'zip' => $address->zip,
                        'contact_name' => $address->contact_name,
                        'contact_phone' => $address->contact_phone,
                    ],
                'remark' => '',
                'total_amount' => $sku->price * $amount,
                'type' => Order::TYPE_CROWDFUNDING,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();
            $item = $order->items()->make([
                'amount' => $amount,
                'price' => $sku->price
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();
            // 扣减对应 SKU 库存
            if ($sku->decreaseStock($amount) <= 0) {
                throw new InvalidRequestException('该商品库存不足');
            }
            return $order;
        });

        // 众筹结束时间减去当前时间得到的剩余秒数
        $crowdfundingTtl = $sku->product->crowdfunding->end_at->getTimestamp() - time();

        // 剩余时间与默认订单关闭时间取较小值作为订单的关闭时间
        dispatch(new CloseOrder($order, min(config('app.order_ttl'), $crowdfundingTtl)));

        return $order;
    }

    public function seckill(User $user, array $addressData, ProductSku $sku)
    {
        $order = DB::transaction(function () use($user, $addressData, $sku){
            //1.优化  :  不更新地址最后的使用时间
            //2.扣除对应的sku库存
            if ($sku->decreaseStock(1) <= 0){
                throw new InvalidRequestException('该商品库存不足');
            }
            //3.创建一个订单
            $order = new Order([
                'address' => [
                    'address'       => $addressData['province'].$addressData['city'].$addressData['district'].$addressData['address'],
                    'zip'           => $addressData['zip'],
                    'contact_name'  => $addressData['contact_name'],
                    'contact_phone' => $addressData['contact_phone'],
                ],
                'remark'       => '',
                'total_amount' => $sku->price,
                'type'         => Order::TYPE_SECKILL,
            ]);
            //4.订单关联到当前用户
            $order->user()->associate($user);
            //5.写入数据
            $order->save();
            //6.创建一个新的订单项并与 sku 关联
            $item = $order->items()->make([
                'amount' => 1, // 秒杀商品只能一份
                'price'  => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();

            Redis::decr('seckill_sku_'.$sku->id);

            return $order;
        });
        // 秒杀订单的自动关闭时间与普通订单不同
        dispatch(new CloseOrder($order, config('app.seckill_order_ttl')));

        return $order;

    }


    public function refundOrder(Order $order)
    {
        // 判断该订单的支付方式
        switch ($order->payment_method) {
            case 'wechat' :
                // 微信的先留空
                // todo
                break;
            case 'alipay' :
                //用我们刚刚写的方法来生成一个退款订单号
                $refundNo = Order::getAvailableRefundNo();
                //调用支付宝支付实例的 refund 方法
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no, //之前的订单流水号
                    'refund_amount' => $order->total_amount, //退款金额,单位元
                    'out_request_no' => $refundNo,
                ]);
                // 根据支付宝的文档, 如果返回值里有 sub_code 字段说明退款失败
                if ($ret->sub_code) {
                    // 将退款失败的保存存入 extra 字段
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    // 将订单的退款状态标记为退款失败
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                } else {
                    // 将订单的退款状态标记为退款成功并保存退款订单号
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                }
                break;
            case 'installment':
                $order->update([
                    'refund_no' => Order::getAvailableRefundNo(), //生成退款订单号
                    'refund_status' => Order::REFUND_STATUS_PROCESSING, // 将退款状态改为退款中
                ]);
                // 触发退款异步任务
                dispatch(new RefundInstallmentOrder($order));
                break;
            default:
                // 原则上不可能出现，这个只是为了代码健壮性
                throw new InternalException('未知订单支付方式：' . $order->payment_method);
                break;
        }
    }
}
