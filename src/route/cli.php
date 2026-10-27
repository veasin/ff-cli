<?php
declare(strict_types=1);
namespace ff\cli\route;
use function ff\{from, container, middleware};
use function ff\cli\args;
/**
 * CLI 路由匹配：基于标准化后的扁平 key 做参数匹配。
 * key 格式 "cli:deploy prod"，path 部分通过 args() 解析后与 params 按位置对比。
 * @param array $normalized 扁平化路由表
 * @param array $params 已解析参数（原样写入 #in.params）
 * @param string $uri 完整命令行字符串
 * @param string $method 固定为 'cli'
 * @return ?array
 */
function cli(array $normalized, array $params, string $uri, string $method): ?array{
	$handlers = [];
	$matchedKeys = [];
	foreach($normalized as $key => $fn){
		if(!str_starts_with($key, 'cli:')) continue;
		$routeArgs = args(substr($key, 4));
		$matched = true;
		foreach($routeArgs as $k => $v){
			if(!isset($params[$k]) || ($v !== '*' && $params[$k] != $v)){
				$matched = false;
				break;
			}
		}
		if($matched){
			foreach((array)$fn as $f) $handlers[] = $f;
			$matchedKeys[] = $key;
		}
	}
	if(!$matchedKeys) return null;
	container('#in.params', $params);
	container('#route.result', $handlers ? middleware(...$handlers) : null);
	return ['handlers' => $handlers, 'keys' => $matchedKeys, 'params' => $params];
}
