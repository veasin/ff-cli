<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, route, test};
use function ff\cli\route\cli;

// ---- ff\cli\route\cli() 直接测试 ----

// 测试 1: 基本匹配
$_SERVER['argv'] = ['script.php', 'hello'];
$result = cli(['cli:hello' => fn($next) => true], ['hello'], 'hello', 'cli');
test('基本匹配返回结果', $result !== null, true);
test('匹配的 key 为 cli:hello', $result['keys'], ['cli:hello']);

// 测试 2: 参数原样返回
test('参数原样返回', $result['params'], ['hello']);

// 测试 3: container 写入
test('#in.params 已写入', container('#in.params'), ['hello']);

// 测试 4: 未匹配返回 null
$nullResult = cli(['other:' => fn() => 'other'], [], '', '');
test('未匹配返回 null', $nullResult, null);

// 测试 5: 空 argv 匹配 cli:
$_SERVER['argv'] = ['script.php'];
$emptyResult = cli(['cli:' => fn() => true], [], '', '');
test('空 argv 仍匹配', $emptyResult !== null, true);
test('空 argv 参数为空数组', $emptyResult['params'], []);

// 测试 6: 通配参数 *
$_SERVER['argv'] = ['script.php', 'deploy', 'anything'];
$wildResult = cli(['cli:deploy *' => fn($next) => 'deploy-any'], ['deploy', 'anything'], 'deploy anything', 'cli');
test('通配参数匹配', $wildResult !== null, true);
test('通配参数 keys', $wildResult['keys'], ['cli:deploy *']);

// 测试 7: 多个 cli: 路由，匹配第一个
$_SERVER['argv'] = ['script.php', 'cmd1'];
$keys2 = cli(['cli:cmd1' => fn($next) => 'c1', 'cli:cmd2' => fn($next) => 'c2'], ['cmd1'], 'cmd1', 'cli');
test('多个 cli: 路由匹配第一个', $keys2['keys'], ['cli:cmd1']);

// 测试 8: 非 cli: 路由被跳过
$_SERVER['argv'] = ['script.php', 'api'];
$noResult = cli(['get:/api' => fn($next) => 'api'], ['api'], 'api', 'cli');
test('非 cli: 路由跳过', $noResult, null);

// ---- route() + ^#ext.route.cli 集成测试 ----

// 测试 9: route() 通过 ext 调用 cli 路由
$_SERVER['argv'] = ['script.php', 'hello'];
container('#in.input', ['method' => 'cli', 'uri' => 'hello']);
container('#in.params', ['hello']);
$keys = route('cli:hello', fn($next) => 'hello');
test('route() cli: 路由匹配', $keys, ['cli:hello']);

// 测试 10: route() 结果写入 #route.result
$routeResult = container('#route.result');
test('route.result 已写入', $routeResult !== null, true);

// 测试 11: 未匹配路由返回 null
$_SERVER['argv'] = ['script.php', '/unknown'];
container('#in.input', ['method' => 'cli', 'uri' => '/unknown']);
route('get:/api', fn($next) => 'api');
$nullKeys = route('get:/unknown');
test('未匹配路由返回 null', $nullKeys, null);

// 测试 12: 多个 cli: 路由，匹配第一个
$_SERVER['argv'] = ['script.php', 'cmd1'];
container('#in.input', ['method' => 'cli', 'uri' => 'cmd1']);
container('#in.params', ['cmd1']);
route('cli:cmd1', fn($next) => 'c1');
route('cli:cmd2', fn($next) => 'c2');
$keys2 = route('cli:cmd1');
test('多个 cli: 路由匹配第一个', $keys2, ['cli:cmd1']);

test() |> (fn($a)=>json_encode($a, JSON_UNESCAPED_UNICODE |JSON_UNESCAPED_SLASHES)) |> printf(...);
