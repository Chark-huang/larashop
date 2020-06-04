<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\SearchBuilders\ProductSearchBuilder;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use function foo\func;

class ProductsController extends Controller
{
    public function index(Request $request, CategoryService $categoryService)
    {
        $page = $request->input('page', 1);
        $perPage = 16;
        // 构建查询
        $builder = (new ProductSearchBuilder())->onSale()->paginate($perPage,$page);

        /**分类查询 START**/
        if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
            // 如果是一个父类目,则通过path查询子类目
            $builder->category($category);
        }
        /**分类查询 END**/


        /**顺序查询 START**/
        //是否有提交 order 参数, 如果有就赋值给 $order 变量
        if ($order = $request->input('order', '')) {
            // 是否以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 调用查询构造器的排序
                $builder->orderBy($m[1], $m[2]);
            }
        }
        /**顺序查询 END**/

        /**关键词查询 START**/
        if ($search = $request->input('search', '')) {
            // 将搜索词根据空格拆分成数组, 并过滤掉空选项
            $keywords = array_filter(explode(' ', $search));
            // 调用查询构造器的关键词筛选
            $builder->keywords($keywords);
        }
        /**关键词查询 END**/

        /** 分面聚合数据获取 START **/
        // 只有当用户有输入搜索词或者使用了类目筛选的时候才会做聚合
        if ($search || isset($category)) {
            // 调用查询构造器的分面搜索
            $builder->aggregateProperties();
        }
        /** 分面聚合数据获取 END **/
        /** 分面查询 filter  START **/
        $propertyFilters = [];
        if ($filterString = $request->input('filters')){
            // 将获取到的字符串用符号 | 拆分成数组
            $filterArray = explode('|', $filterString);
            foreach ($filterArray as $filter){
                // 将字符串用符号 : 才分成两部分赋值给 $name 和 $value 两个变量
                list($name, $value) = explode(':', $filter);
                // 将用户筛选的属性添加到数组中
                $propertyFilters[$name] = $value;

                // 调用查询构造器的属性筛选
                $builder->propertyFilter($name, $value);
            }
        }
        /** 分面查询 filter  END **/

        // 最后通过 getParams() 方法取回构造好的查询参数
        $result = app('es')->search($builder->getParams());

        // 通过 collect 函数将返回结果转为集合, 并通过集合的 pluck 方法去到返回的商品 ID 数组
        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();

        $products = Product::query()->byIds($productIds)->get();

        // 返回一个 LengthAwarePaginator 对象
        $pager = new LengthAwarePaginator($products, $result['hits']['total']['value'], $perPage, $page, [
            'path' => route('products.index', false)
        ]);

        $properties = [];
        // 如果返回结果里面有aggregations 字段, 说明做了分面搜索
        if (isset($result['aggregations'])){
            // 使用collect函数将返回的数据转为集合
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])->map(function ($bucket){
                // 通过 map 方法取出我们要的字段
                return [
                    'key' => $bucket['key'],
                    'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                ];
            })->filter(function ($property) use ($propertyFilters){
                // 过滤掉只剩下一个值 或者 已经在筛选条件里面的属性
                return count($property['values']) > 1 && !isset($propertyFilters[$property['key']]);
            });
        }


        return view('products.index', [
            'products' => $pager,
            'filters' => [
                'search' => $search,
                'order' => $order,
            ],
            'category' => $category ?? null,
            'properties' => $properties,
            'propertyFilters' => $propertyFilters,
        ]);
    }


    public function show(Request $request, Product $product)
    {
        // 判断商品是否已经上架
        if (!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }
        $favored = false;
        // 用户未登陆返回的是 null, 已登陆返回的是对应的用户对象
        if ($user = $request->user()) {
            // 从当前用户已收藏的商品中搜索 id 为当前商品 id 的商品
            // boolval() 函数用于把值转为布尔值
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }

        // 商品评价
        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku'])
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at', 'desc')
            ->limit(10) // 取10条
            ->get();

        $similarProductIds = (new ProductService())->getSimilarProductIds($product,4);
        // 根据 Elasticsearch 搜索出来的商品 ID 从数据库中读取商品数据
        $similarProducts = Product::query()->byIds($similarProductIds)->get();



        return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews,
            'similar' => $similarProducts,
        ]);
    }


    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);
        return [];
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product->id);
        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', ['products' => $products]);
    }

}
