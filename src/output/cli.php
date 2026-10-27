<?php
declare(strict_types=1);
namespace ff\cli\output;
/**
 * CLI 输出：打印内容并以退出码表达结果状态。
 * 0=成功(<400)，1=客户端错误(4xx)，2=服务端错误(5xx)
 */
function cli(mixed $content, array $meta): void{
	if($content !== null) echo is_string($content) ? $content : '';
	$code = $meta['code'] ?? 200;
	exit(match(true){
		$code < 400 => 0,
		$code < 500 => 1,
		default => 2,
	});
}
