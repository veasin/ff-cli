<?php
// bootstrap.php — ff-cli 自注册：只注册 ext handler、文案与 enable 入口，不改变运行时的输入/输出
// 会改变全局行为的写入（^#in.input/params、^#out.type、^#out.emit）只在 enable() handler 中执行
use function ff\{container, hook};

container([
	'^#ext.out.emit.cli' => \ff\cli\output\cli(...),
	'^#ext.route.cli' => [
		'match' => \ff\cli\route\cli(...),
		'normalize' => function(string $parentPath, string $childUnit): string{
			if($childUnit === '') return rtrim($parentPath, ' ');
			return rtrim($parentPath, ' ') . ' ' . $childUnit;
		},
	],
]);
container('^#route.methods.cli', 'cli');
container('^#ext.enable', [
	'cli' => fn() => container([
		'^#in.input' => fn() => [
			'method' => 'cli',
			'protocol' => null,
			'uri' => $_SERVER['argv'] |> array_slice(?, 1) |> implode(' ', ?),
		],
		'^#in.params' => fn() => $_SERVER['argv'] |> array_slice(?, 1) |> \ff\cli\args(...),
		'^#out.type' => 'raw',
		'^#out.emit' => 'cli',
	]),
	'cli-test' => function(): void{
		container('^#out.emit', 'cli');
		hook('after', static function(): void{
			// 测试收尾：失败时 JSON 输出 test() 结果并 exit(1)；全部通过时静默
			$r = \ff\test();
			if($r['failed'] ?? []){
				echo json_encode($r, JSON_UNESCAPED_UNICODE), PHP_EOL;
				exit(1);
			}
		});
	},
]);
container('^i18n.#ff.test', [
	'passed' => ['[g]✔ 全部通过[y]: {passed}/{total}', 'en_US' => '[g]✔ All passed[y]: {passed}/{total}'],
	'failed' => ['[r]● 测试失败[y]: {count}, [g]{passed}[y]/{total}', 'en_US' => '[r]● Test failed[y]: {count}, [g]{passed}[y]/{total}'],
	'case' => ["[r]▶ {label}\n\t[n]预期: [c]{expected}\n\t[n]实际: [m]{actual}", 'en_US' => "[r]▶ {label}\n\t[n]Expected: [c]{expected}\n\t[n]Actual: [y]{actual}"],
]);
container('^i18n.#ff.cli.script', [
	'help' => '[n]使用 --help 查看帮助。[:]',
	'unknown' => '[r]未知命令：{name}[:]',
]);