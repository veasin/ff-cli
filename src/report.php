<?php
declare(strict_types=1);
namespace ff\cli;

use function ff\{i18n};

/**
 * 渲染 test() 结果为友好文本。
 * - 全部通过：输出一行统计
 * - 有失败：逐条输出用例名、预期、实际，最后输出汇总
 * - actual 为 Throwable 取 getMessage；expected 为 Throwable 取 getMessage，callable 显示 'assertion'，其余 JSON 编码
 * - 失败项兼容 test() 的关联数组结构，以及 [label, actual, expected] 列表
 * - 依赖 color() 与 bootstrap 注册的 #ff.test.* 文案
 * - 只渲染，不设置退出码；需要 CI 退出码请使用 enable('cli-test') 或自行 exit
 * @param array $result test() 返回值
 * @return void
 */
function report(array $result): void{
	$total = $result['total'] ?? 0;
	$passed = $result['passed'] ?? 0;
	$failed = $result['failed'] ?? [];
	if(!$failed){
		echo color(i18n('#ff.test.passed', ['passed' => $passed, 'total' => $total])), PHP_EOL;
		return;
	}
	foreach($failed as $f){
		[$label, $actual, $expected] = is_array($f) && array_is_list($f) ? [$f[0] ?? '', $f[1] ?? null, $f[2] ?? null] : [$f['label'] ?? '', $f['actual'] ?? null, $f['expected'] ?? null];
		$actualOut = $actual instanceof \Throwable ? $actual->getMessage() : json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($expected instanceof \Throwable) $expectOut = $expected->getMessage();
		elseif(is_callable($expected)) $expectOut = 'assertion';
		else $expectOut = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$case = i18n('#ff.test.case', ['label' => $label, 'expected' => $expectOut, 'actual' => $actualOut]);
		echo color($case), PHP_EOL;
	}
	echo color(i18n('#ff.test.failed', ['count' => count($failed), 'passed' => $passed, 'total' => $total])), PHP_EOL;
}