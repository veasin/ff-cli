<?php
declare(strict_types=1);
include __DIR__ . '/vendor/autoload.php';

use function ff\{container, enable, output, route};
use function ff\cli\route\script;

$argv = $_SERVER['argv'];
array_shift($argv);
$uri = implode(' ', $argv);
enable('cli');

$param = fn(mixed $key, mixed $default = null) => (container('#in.params') ?? [])[$key] ?? $default;

route(script([
	'文件处理工具 v1.0',
	['v|verbose' => '输出详细日志'],
	fn($next) => $next(),          // 顶层中间件
	false,
	true,
	'list' => [
		'列出所有文件',
		['a|all' => '包含隐藏文件'],
		fn($next) => output('列出文件' . (($param('a') ?? $param('all')) ? '（含隐藏）' : '')),
	],
	'run' => [
		'运行目标',
		['f|file' => '目标文件', 'force' => '强制执行'],
		false,
		true,
		'deploy' => [
			'部署到环境',
			['e|env' => '目标环境，默认 prod'],
			fn($next) => output('部署到 ' . ($param('e') ?? $param('env') ?? 'prod')),
		],
		'test' => fn($next) => output('运行测试'),
	],
	'remote' => [
		'远程仓库管理',
		fn($next) => $next(),
		false,
		true,
		'add' => [
			'添加远程',
			['n|name' => '仓库名'],
			fn($next) => output('添加远程：' . ($param('n') ?? $param('name', '(未提供)'))),
		],
		'rm' => [
			'删除远程',
			['n|name' => '仓库名', 'force' => '强制删除'],
			fn($next) => output('删除远程：' . ($param('n') ?? $param('name', '')) . (($param('f') ?? $param('force')) ? '（强制）' : '')),
		],
	],
])) ?? output("未知命令：$uri\n使用 --help 查看用法");
