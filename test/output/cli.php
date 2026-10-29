<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, output, test};
use function ff\cli\output\cli;

// 每个脚本是新进程，persist 层本来就是空的

// ——— output() 存储测试 ———

// 测试 1: output 存储 #out.op
output(['status' => 'ok']);
$op = container('#out.op');
test('output 存储 #out.op', $op !== null, true);
test('#out.op[0] 为内容', $op[0]['status'] ?? null, 'ok');

// 测试 2: 默认状态码为 200
test('默认状态码为 200', $op[1]['code'] ?? null, 200);

// ——— cli() 子进程测试 ———

$vendor = str_replace('\\', '/', dirname(__DIR__, 2) . '/vendor/autoload.php');

// 测试 3: 基本输出
$pid = getmypid();
$tmp = sys_get_temp_dir() . '/ff_cli_test_' . $pid . '.php';
file_put_contents($tmp, '<?php
require "' . $vendor . '";
use function ff\cli\output\cli;
cli("hello", ["code" => 200]);
');
$out = shell_exec(PHP_BINARY . ' ' . escapeshellarg($tmp) . ' 2>&1');
test('基本输出', $out, 'hello');
unlink($tmp);

// 测试 4: 404 空输出
$tmp = sys_get_temp_dir() . '/ff_cli_test2_' . $pid . '.php';
file_put_contents($tmp, '<?php
require "' . $vendor . '";
use function ff\cli\output\cli;
cli(null, ["code" => 404]);
');
$out = shell_exec(PHP_BINARY . ' ' . escapeshellarg($tmp) . ' 2>&1');
test('404 空输出', $out ?? '', '');
unlink($tmp);

test() |> \ff\cli\report(...);
