<?php
declare(strict_types=1);
namespace ff\cli\output;
/**
 * CLI 输出：打印字符串内容，并以退出码表达结果状态。
 * 仅输出 string；null、数组、对象等非字符串内容不输出。
 * 退出码：0=成功(code<400)，1=客户端错误(4xx)，2=服务端错误(5xx)
 * @param mixed $content 输出内容，仅 string 会打印
 * @param array $meta 输出元数据，读取 `code`，默认 200
 * @return void 函数内部调用 exit()
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
