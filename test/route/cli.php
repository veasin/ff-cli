<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, route, test};
use function ff\cli\{route\cli, report};

// ---- ff\cli\route\cli() 直接测试 ----

// 测试 1: 基本匹配
$_SERVER['argv'] = ['script.php', 'hello'];
$result = cli(['cli:hello' => fn($next) => true], ['hello'], 'hello', 'cli');
test('基本匹配返回结果', $result !== null, true);
test('匹配的 key 为 cli:hello', $result['keys'], ['cli:hello']);
// 测试 1 追加
test('params 未被改写（无 * 键）', array_key_exists('*', $result['params']), false);

// 测试 2: 参数原样返回
test('参数原样返回', $result['params'], ['hello']);

// 测试 3 改为：cli() 单独调用不写容器（职责在 route）
$cliOnly = cli(['cli:hello' => fn($next) => true], ['hello'], 'hello', 'cli');
container('#in.params', null);   // 先清空
$cliOnly = cli(['cli:hello' => fn($next) => true], ['hello'], 'hello', 'cli');
test('cli() 单独不写 #in.params', container('#in.params'), null);
test('cli() 返回 params 正确', $cliOnly['params'], ['hello']);

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
// 测试 6 追加
test('通配 params 原样', $wildResult['params'], ['deploy', 'anything']);
test('通配 params 无 * 键', array_key_exists('*', $wildResult['params']), false);

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
test('route() 写入 #in.params', container('#in.params'), ['hello']);

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

// 新增：* 只匹配一个词
$_SERVER['argv'] = ['script.php', 'deploy', 'a', 'b'];
$noWild = cli(['cli:deploy *' => fn($next) => 'x'], ['deploy', 'a', 'b'], 'deploy a b', 'cli');
test('* 只匹配一个词，多词不命中', $noWild, null);

// 新增：固定词与 * 同时命中，全部收集
$both = cli(['cli:cmd' => fn($next) => 'c', 'cli:*' => fn($next) => 'w'], ['cmd'], 'cmd', 'cli');
test('已知命令不被 * 吞掉', $both['keys'], ['cli:cmd']);

$wild = cli(['cli:cmd' => fn($next) => 'c', 'cli:*' => fn($next) => 'w'], ['foo'], 'foo', 'cli');
test('未知词命中 *', $wild['keys'], ['cli:*']);
test('未知词命中 params 原样', $wild['params'], ['foo']);

// 新增：cli: 空模式对非空词不命中
$emptyMiss = cli(['cli:' => fn() => true], ['x'], 'x', 'cli');
test('cli: 对非空词不命中', $emptyMiss, null);

// 新增：通配路由不参与固定命令收集，字面 * 可作为未知词
$literal = cli(['cli:*' => fn($next) => 'w'], ['*'], '*', 'cli');
test('字面 * 命中顶层 cli:*', $literal['keys'], ['cli:*']);

test() |> report(...);
