<?php
function route_class()
{
    return str_replace('.', '-', Route::currentRouteName());
}


function ngrok_url($routeName, $parameters = [])
{
    // 开发环境,并且配置了 NGROK_URL
    if (app()->environment('local') && $url = config('app.ngrok_url')){
        // route() 函数第三个代表是否绝对路径
        return $url.route($routeName, $parameters, false);
    }
    return route($routeName, $parameters);
}

function big_number($number, $scale = 2)
{
    return new \Moontoast\Math\BigNumber($number,$scale);
}

//监听SQL语句
function log_sql(){
    \Illuminate\Support\Facades\DB::listen(function($query){
        $tmp = str_replace('?', '"'.'%s'.'"', $query->sql);
        $qBindings = [];
        foreach ($query->bindings as $key => $value) {
            if (is_numeric($key)) {
                $qBindings[] = $value;
            } else {
                $tmp = str_replace(':'.$key, '"'.$value.'"', $tmp);
            }
        }
        $tmp = vsprintf($tmp, $qBindings);
        $tmp = str_replace("\\", "", $tmp);
        \Illuminate\Support\Facades\Log::channel('sql')->debug(' execution time: '.$query->time.'ms; '.$tmp."\n\n\t");
    });
}
