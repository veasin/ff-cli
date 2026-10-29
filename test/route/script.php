<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\cli\route\{script};

// ---- 1. 顶层无命令：false 生成 cli: ----
$r = script([false]);
test('顶层 false 生成 cli:', array_keys($r), ['cli:']);
// ---- 2. 固定子命令展开（叶子只有闭包） ----
$r = script(['hello' => fn($input) => true]);
test('叶子子命令展开', array_keys($r), ['cli:hello']);
test('叶子子命令 handler 长度 1', count($r['cli:hello']), 1);
// ---- 3. 多层固定子命令 ----
$r = script(['remote' => ['add' => fn($i) => 1, 'rm' => fn($i) => 2]]);
test('多层展开', array_keys($r), ['cli:remote add', 'cli:remote rm']);
// ---- 4. '' 语义：显式 '' => fn ----
$h = fn($i) => 'empty';
$r = script(['' => $h]);
test('显式空字符串生成 cli:', array_keys($r), ['cli:']);
test("'' 链长度 1", count($r['cli:']), 1);   // 不再 === $h
// ---- 5. false 与 '' => fn 并存，显式覆盖简写 ----
$r = script([false, '' => $h]);
test('显式覆盖 false 生成 cli:', array_keys($r), ['cli:']);
test("覆盖后链长度 1", count($r['cli:']), 1);
// ---- 6. 多个 false 幂等，只生成一条 ----
$r = script([false, false]);
test('多个 false 幂等', array_keys($r), ['cli:']);
// ---- 7. '?' 语义：true 生成 cli:<path> * ----
$r = script([true]);
test('顶层 true 生成 cli:*', array_keys($r), ['cli:*']);
// ---- 8. 子层 true 生成 cli:remote * ----
$r = script(['remote' => [true]]);
test('子层 true 生成 cli:remote *', array_keys($r), ['cli:remote *']);
// ---- 9. 空数组等价 false/true ----
$r = script(['' => [], '?' => []]);
test('空数组等价 false/true', array_keys($r), ['cli:', 'cli:*']);
// ---- 10. 本层闭包栈作为前置中间件，叶子含父继承 ----
$mw = fn($next) => $next();
$h = fn($i) => 'run';
$r = script([$mw, 'run' => $h]);
test('叶子含父继承中间件', array_keys($r), ['cli:run']);
test('叶子链长度 2', count($r['cli:run']), 2);
// 顺序断言改为"前一个是 $mw"：包装闭包是最后一个，$mw 是第一个
test('链首为 mw', $r['cli:run'][0] === $mw, true);
// ---- 11. 闭包数组展平进栈 ----
$f1 = fn($next) => 1;
$f2 = fn($next) => 2;
$r = script([[$f1, $f2], 'run' => $h]);
test('闭包数组链长度 3', count($r['cli:run']), 3);
test('链前两位 [f1,f2]', [$r['cli:run'][0] === $f1, $r['cli:run'][1] === $f2], [true, true]);
// ---- 12. 子命令值为闭包数组 ----
$r = script(['run' => [$f1, $f2]]);
test('子命令闭包数组', array_keys($r), ['cli:run']);
test('子命令闭包数组长度 2', count($r['cli:run']), 2);
// ---- 13. 描述与 args 不影响展开 ----
$r = script(['描述一', '描述二', ['f|file' => '路径'], 'run' => $h]);
test('描述+args 仅生成 run', array_keys($r), ['cli:run']);
test('run 链仅 handler', count($r['cli:run']), 1);
// ---- 14. 每层最多 4 类：'' + '?' + 固定 + 递归 ----
$r = script([
	false,
	true,
	'remote' => [false, true, 'add' => fn($i) => 1],
]);
test('四类路由键齐全', array_keys($r), [
	'cli:',
	'cli:*',
	'cli:remote',
	'cli:remote *',
	'cli:remote add',
]);
// ---- 15. 子层中间件同时作用于 '' 与 '?' 路由 ----
$mw = fn($next) => $next();
$r = script(['remote' => [$mw, false, true]]);
test('remote "" 链含 gate+中间件+help', count($r['cli:remote']), 3);
test('remote * 链含中间件', count($r['cli:remote *']), 2);
// ---- 16. 深层递归 ----
$r = script(['a' => ['b' => ['c' => fn($i) => 1]]]);
test('三层递归', array_keys($r), ['cli:a b c']);
// ---- 17. 配置顺序即展开顺序 ----
$r = script(['x' => fn($i) => 1, 'y' => fn($i) => 2]);
test('展开顺序即配置顺序', array_keys($r), ['cli:x', 'cli:y']);
// ---- 18. 空配置 ----
$r = script([]);
test('空配置返回空数组', $r, []);
// ---- 19. 父继承栈跨多层传递 ----
$mw1 = fn($next) => 1;
$mw2 = fn($next) => 2;
$r = script([$mw1, 'a' => [$mw2, 'b' => fn($i) => 3]]);
test('跨层链前两位 [mw1,mw2]', [
	$r['cli:a b'][0] === $mw1,
	$r['cli:a b'][1] === $mw2,
], [true, true]);
// ---- 20. 有 '' 时栈作为其前置中间件，不额外生成叶子 ----
$r = script([$mw, '' => $h]);
test("有 '' 时栈并入其链", array_keys($r), ['cli:']);
test("有 '' 时链长度 2", count($r['cli:']), 2);

// ---- 21. false 在本层拦截业务闭包 ----
$biz = fn($next) => 'business';
$r = script([false, $biz]);
test('false + business 链为 gate,business,help', count($r['cli:']), 3);
test('false 链第二个为 business', $r['cli:'][1] === $biz, true);

$gate = $r['cli:'][0];
container('#in.params', []);
container('#out.op', null);
$nextCalled = false;
$gate(function() use (&$nextCalled){ $nextCalled = true; });
test('false 无参数时不执行业务', $nextCalled, false);
$op = container('#out.op');
test('false 无参数时输出 help', is_array($op) && str_contains($op[0] ?? '', '使用 --help'), true);

container('#in.params', ['file' => 'x.txt']);
container('#out.op', null);
$nextCalled = false;
$gate(function() use (&$nextCalled){ $nextCalled = true; });
test('false 有参数时继续执行', $nextCalled, true);

container('#in.params', ['help' => true]);
container('#out.op', null);
$nextCalled = false;
$gate(function() use (&$nextCalled){ $nextCalled = true; });
test('false --help 时拦截业务', $nextCalled, false);

$r = script(['remote' => [false]]);
$gate = $r['cli:remote'][0];
container('#in.params', ['remote']);
container('#out.op', null);
$nextCalled = false;
$gate(function() use (&$nextCalled){ $nextCalled = true; });
test('子层 false 命中即 help', $nextCalled, false);

container('#in.params', null);
container('#out.op', null);

// ---- 22. 特殊键类型错配 fail-fast ----
test("'' => true 抛 InvalidArgumentException", fn() => script(['' => true]), \InvalidArgumentException::class);
test("'?' => false 抛 InvalidArgumentException", fn() => script(['?' => false]), \InvalidArgumentException::class);
test("'' => 1 抛 InvalidArgumentException", fn() => script(['' => 1]), \InvalidArgumentException::class);
test("'?' => null 抛 InvalidArgumentException", fn() => script(['?' => null]), \InvalidArgumentException::class);
test("子层 '' => true 抛 InvalidArgumentException", fn() => script(['remote' => ['' => true]]), \InvalidArgumentException::class);
test("'?' => 闭包允许", array_keys(script(['?' => fn($next) => 'x'])), ['cli:*']);

test() |> \ff\cli\report(...);