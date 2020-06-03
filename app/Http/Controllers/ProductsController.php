<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CategoryService;
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
        $es_params = [
            'index' => 'products',
            'body' => [
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['on_sale' => true]]
                        ]
                    ]
                ]
            ]
        ];

        /**分类查询 START**/
        if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
            // 如果是一个父类目,则通过path查询子类目
            if ($category->is_directory) {
                $es_params['body']['query']['bool']['filter'][] = [
                    'prefix' => ['category_path' => $category->path . $category->id . '-']
                ];
            } else {
                // 否则直接通过 category_id 筛选
                $es_params['body']['query']['bool']['filter'][] = [
                    'term' => ['category_id' => $category->id]
                ];
            }
        }
        /**分类查询 END**/


        /**顺序查询 START**/
        //是否有提交 order 参数, 如果有就赋值给 $order 变量
        if ($order = $request->input('order', '')) {
            // 是否以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果字符串的开头是这 3 个字符串之一, 说明是一个合法的程序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 根据传入的排序值来构造排序参数
                    $es_params['body']['sort'] = [[$m[1] => $m[2]]];
                }
            }
        }
        /**顺序查询 END**/

        /**关键词查询 START**/
        if ($search = $request->input('search', '')) {
            // 将搜索词根据空格拆分成数组, 并过滤掉空选项
            $keywords = array_filter(explode(' ', $search));

            foreach ($keywords as $keyword) {
                $es_params['body']['query']['bool']['must'][] = [
                    'multi_match' => [
                        'query' => $keyword,
                        'fields' => [
                            'title^3',
                            'long_title^2',
                            'category^2',
                            'description',
                            'skus_title',
                            'skus_description',
                            'properties_value',
                        ]
                    ]
                ];
            }
        }
        /**关键词查询 END**/

        /** 分面聚合数据获取 START **/
        // 只有当用户有输入搜索词或者使用了类目筛选的时候才会做聚合
        if ($search || isset($category)) {
            $es_params['body']['aggs'] = [
                'properties' => [
                    'nested' => [
                        'path' => 'properties',
                    ],
                    'aggs' => [
                        'products_properties_name' =>[
                            'terms' => [
                                'field' => 'properties.name'
                            ],
                            'aggs'=> [
                                'products_properties_value' => [
                                    'terms' => [
                                        'field' => 'properties.value'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
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

                // 添加到 filter 类型中
                $es_params['body']['query']['bool']['filter'][] = [
                    // 由于我们要筛选的是 nested 类型下的属性，因此需要用 nested 查询
                    'nested' => [
                        // 指明 nested 字段
                        'path'  => 'properties',
                        'query' => [
                            ['term' => ['properties.search_value' => $filter]]
//                            ['term' => ['properties.name' => $name]],
//                            ['term' => ['properties.value' => $value]],
                        ],
                    ],
                ];
            }
        }
        /** 分面查询 filter  END **/

        $result = app('es')->search($es_params);

        // 通过 collect 函数将返回结果转为集合, 并通过集合的 pluck 方法去到返回的商品 ID 数组
        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();

        // 通过 whereIn 方法从数据库中读取商品数据
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $productIds)))
            ->get();


        // 返回一个 LengthAwarePaginator 对象
        $pager = new LengthAwarePaginator($products, $result['hits']['total']['value'], $perPage, $page, [
            'path' => route('products.index', false)
        ]);

        $properties = [];
        // 如果返回结果里面有aggregations 字段, 说明做了分面搜索
        if (isset($result['aggregations'])){
            // 使用collect函数将返回的数据转为集合
            $properties = collect($result['aggregations']['properties']['products_properties_name']['buckets'])->map(function ($bucket){
                // 通过 map 方法取出我们要的字段
                return [
                    'key' => $bucket['key'],
                    'values' => collect($bucket['products_properties_value']['buckets'])->pluck('key')->all(),
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
        return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews
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
