<?php

class PageParser
{
	protected $data = array();
	protected $level = 0;

	protected $opts = array(
		'beforeSaveFunc' => null,
		'defaultSaveFunc' => null,
		'afterSaveFunc'	=> null,
	);

	protected $synonyms = array(
		'b' => 'begin',
		'e' => 'end',
		'sel' => 'select',
		'del,rm,delete' => 'remove',
		'selBI,selById' => 'selectById',
		'delBI,delById,rmBI,rmById,removeBI,deleteBI' => 'removeById',
	);

	public function __call($m,$a)
	{
		$a = $this->applyOpts($a);
		$m = $this->findMethod($m);

		switch ($m)
		{
			case 'begin':
				$this->data[] = end($this->data);
				break;
			case 'end':
				array_pop($this->data);
				break;
			case 'setOpt':
				$this->opts[$a[0]] = $a[1];
				break;
			case 'set':
				if (is_array($a[0])) $this->data[] = array_values($a[0]);
				else $this->data[] = $a;
				break;
			case 'each':
				$this->data[] = $this->recurse('createEach',end($this->data),$a,$this->level);
				$this->level++;
				break;
			case 'endEach':
				array_pop($this->data);
				$this->level--;
				break;
			default:
				if (!method_exists($this,$m)) return $this;
				$this->data[] = $this->recurse($m,array_pop($this->data),$a,$this->level);
		}

		return $this;
	}

	public function save(&$var,$func=null)
	{
		$var = $this->recurse('escapeVars',end($this->data),array(0=>$func),$this->level);
		while (is_array($var) && count($var) == 1 && $var[0])
			$var = $var[0];
		return $this;
	}




	// Workers
	// Theese methods take $data and $opts, where $data is one dimension array of htmls and return $result, that's also one dimension array of htmls.
	// Workers usually called through recurse method. 

	protected function createEach($data,$a)
	{
		foreach($data as &$v)
			$v = array(0=>$v);
		return $data;
	}

	protected function escapeVars($data,$a)
	{
		$funcs = array($this->opts['beforeSaveFunc'],$a[0]?$a[0]:$this->opts['defaultSaveFunc'],$this->opts['afterSaveFunc']);
		foreach($data as &$v)
			foreach($funcs as $func)
				if (isset($func) && function_exists($func))
					$v = $func($v);
		return $data;
	}

	protected function split($data,$a)
	{
		$res = array();
		foreach ($data as $i => $v)
		{
			$ar = preg_split($a[0],$v,(isset($a[1])?$a[1]:null));
			if (isset($a[2])) $res[$i] = (isset($ar[$a[2]])?$ar[$a[2]]:'');
			else
				foreach($ar as $tmp)
					$res[] = $tmp;
		}
		return $res;
	}

	protected function find($data,$a) { return $this->find_tmp($data,$a); }
	protected function findAll($data,$a) { return $this->find_tmp($data,$a,1); }
	protected function find_tmp($data,$a,$all=0)
	{
		if (count($a) == 1)
			return ($all?$this->matchAll($data,$a):$this->match($data,$a));

		$b = 0;
		$ar_before = $ar_after = array();
		foreach($a as $i => $v)
			if ($b || $i+1 == count($a)) $ar_after[] = $v;
			elseif ($v == '*') $b = 1;
			else $ar_before[] = $v;

		$res = array();
		foreach($data as $i => $html)
		{
			while ($html)
			{
				$html_val = $html;
				foreach($ar_before as $v)
				{
					$ar = preg_split($v,$html_val,2);
					$html_val = (isset($ar[1])?$ar[1]:'');
				}
				$html = null;
				foreach($ar_after as $v)
				{
					$ar = preg_split($v,$html_val,2);
					$html_val = (isset($ar[0])?$ar[0]:'');
					if (!isset($html)) $html = (isset($ar[1])?$ar[1]:'');
				}
				if ($html_val && $all) $res[] = $html_val;
				else $res[$i] = $html_val;
				if (!$all) break;
			}
		}
		return $res;			
	}

	protected function match($data,$a)
	{
		$all = 0;
		$res = $tmp = array();
		foreach($data as $i => $v)
			if (preg_match($a[0],$v,$m))
			{
				unset($m[0]);
				if (count($m) > 1) $all = 1;
				$tmp[$i] = $m;
			}
			else $tmp[$i] = array(1=>'');
		foreach($tmp as $i => $m)
			if ($all)
				foreach($m as $v)
					$res[] = $v;
			else
				$res[$i] = $m[1];
		return $res;
	}

	protected function matchAll($data,$a)
	{
		$res = array();
		foreach($data as $v)
			if (preg_match_all($a[0],$v,$m))
			{
				unset($m[0]);
				foreach($m as $mm)
					foreach($mm as $mmm)
						$res[] = $mmm;
			}
		return $res;
	}

	protected function DOMFind($data,$a) { return $this->DOMFind_tmp($data,$a); }
	protected function DOMFindAll($data,$a) { return $this->DOMFind_tmp($data,$a,1); }
	protected function DOMFind_tmp($data,$a,$all=0)
	{
		$res = array();
		foreach($data as $i => $html)
		{
			while (preg_match($a[0],$html))
			{
				$ar = preg_split($a[0],$html,2);
				$html = $base_html = $ar[1];
				$deep = 0;
				$ind = 0;
				while (true)
				{
					if (preg_match($a[1],$html))
					{
						$ar = preg_split($a[1],$html,2,PREG_SPLIT_OFFSET_CAPTURE);
						if (isset($a[2]))
							$deep += count(preg_split($a[2],$ar[0][0])) - 1;
						if (!$deep)
						{
							$ind += strlen($ar[0][0]);
							if ($all) $res[] = substr($base_html,0,$ind);
							else $res[$i] = substr($base_html,0,$ind);
							break;
						}
						$html = $ar[1][0];
						$ind += $ar[1][1];
						$deep--;
					}
					else
					{
						if ($all) $res[] = $base_html;
						else $res[$i] = $base_html;
						break;
					}
				}
				if (!$all) break;
			}
		}
		return $res;
	}

	protected function select($data,$a)
	{
		$res = array();
		foreach($data as $html)
			foreach($a as $v)
				if (preg_match($v,$html))
				{
					$res[] = $html;
					continue 2;
				}
		return $res;
	}

	protected function remove($data,$a)
	{
		$res = array();
		foreach($data as $html)
		{
			foreach($a as $v)
				if (preg_match($v,$html)) continue 2;
			$res[] = $html;
		}
		return $res;
	}

	protected function selectById($data,$a)
	{
		$ar = $res = array();
		foreach($a as $i)
			if ($i >= 0) $ar[$i] = 1;
			else $ar[count($data)+$i] = 1;
		foreach($data as $i => $html)
			if (isset($ar[$i]) && $ar[$i])
				$res[] = $html;
		return $res;
	}

	protected function removeById($data,$a)
	{
		$ar = $res = array();
		foreach($a as $i) 
			if ($i >= 0) $ar[$i] = 1;
			else $ar[count($data)+$i] = 1;
		foreach($data as $i => $html)
			if (!isset($ar[$i]) || !$ar[$i])
				$res[] = $html;
		return $res;
	}

	protected function rmempty($data,$a)
	{
		return $this->select($data,array(0=>"/[\w\W]+/"));
	}

	protected function apply($data,$a)
	{
		foreach($data as &$v)
			if (!function_exists($a[0]))
				$v = $a[0]($v);
		return $data;
	}

	protected function replace($data,$a)
	{
		foreach($data as &$v)
			$v = preg_replace($a[0],$a[1],$v);
		return $data;
	}




	// Very important recurse method

	protected function recurse($func,$data,$a,$level)
	{
		if ($level)
		{
			$res = array();
			foreach($data as $v)
			{
				$tmp = $this->recurse($func,$v,$a,$level-1);
				if ($tmp) $res[] = $tmp;
			}
			return $res;
		}
		elseif (method_exists($this,$func))
		{
			$tmp = $this->$func($data,$a);
			return ($tmp?$tmp:null);
		}
	}




	// Other functions

	protected function applyOpts($a)
	{
		foreach($a as $i => $v)
			if (isset($this->opts["i"]) && $this->opts["i"] && preg_match("/\/$/",$v))
				$a[$i] .= "i";
		return $a;
	}

	protected function findMethod($m)
	{
		foreach($this->synonyms as $i => $v)
			if (stripos(',,'.$i.',',','.$m.','))
				return $v;
		return $m;
	}
}
