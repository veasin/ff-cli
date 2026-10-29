<?php
declare(strict_types=1);
namespace ff\cli\route;

/**
 * CLI 路由匹配。
 * 规则：
 * - 词序列 = `$params` 的数字索引项（`args()` 结果原样，匹配过程不改写）
 * - 路由键为 `cli:` + 空格分隔的模式；模式词数必须与词序列完全相等
 * - 固定词必须逐位相等
 * - `*` 匹配恰好一个词，但该词不能是本层已声明的固定命令（即“未知命令”语义）
 * - 通配路由本身不参与固定命令收集，因此字面 `*` 可作为未知词匹配 `cli:*`
 * - 顶层 `cli:*` 匹配任意一个未知词；`cli:` 只匹配空词序列
 * - 按 normalized 顺序收集全部命中项，由 middleware 链的 `$next()` 决定是否继续
 * - 只返回匹配结果，不写 `#in.params` / `#route.result`（由 `route()` 写入）
 * @param array  $normalized 扁平路由表
 * @param array  $params     args() 解析结果（原样使用，不修改）
 * @param string $uri        完整命令行字符串（当前不参与取词，保留 ext 签名）
 * @param string $method     固定 'cli'（当前不参与匹配，保留 ext 签名）
 * @return ?array ['handlers'=>callable[], 'keys'=>string[], 'params'=>array] 或 null
 */
function cli(array $normalized, array $params, string $uri, string $method): ?array{
	$words = [];
	foreach($params as $k => $v) if(is_int($k)) $words[] = (string)$v;
	$fixed = [];
	foreach($normalized as $key => $_){
		if(!str_starts_with($key, 'cli:')) continue;
		$p = substr($key, 4);
		if($p === '' || in_array('*', explode(' ', $p), true)) continue;
		$parts = explode(' ', $p);
		$word = array_pop($parts);
		$prefix = implode(' ', $parts);
		$fixed[$prefix][] = $word;
	}
	$handlers = [];
	$keys = [];
	foreach($normalized as $key => $fn){
		if(!str_starts_with($key, 'cli:')) continue;
		$pattern = substr($key, 4);
		$pats = $pattern === '' ? [] : explode(' ', $pattern);
		if(count($pats) !== count($words)) continue;
		$ok = true;
		foreach($pats as $i => $p){
			if($p === '*'){
				$prefix = implode(' ', array_slice($pats, 0, $i));
				if(in_array($words[$i], $fixed[$prefix] ?? [], true)){
					$ok = false;
					break;
				}
				continue;
			}
			if($p !== $words[$i]){
				$ok = false;
				break;
			}
		}
		if(!$ok) continue;
		$keys[] = $key;
		foreach((array)$fn as $f) $handlers[] = $f;
	}
	if(!$keys) return null;
	return ['handlers' => $handlers, 'keys' => $keys, 'params' => $params];
}