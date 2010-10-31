<?php

class PageParser
{
	protected $htmls = array();
	protected $opts = array();

	public function __construct($data=null)
	{
		if (isset($data)) $this->set($data);
	}

	public function setOpt($p,$v)
	{
		$this->opts[$p] = $v;
		return $this;
	}

	public function set($data)
	{
		if (is_array($data)) $this->htmls[] = array_values($data);
		else $this->htmls[] = array(0=>$data);
		return $this;
	}

	public function b() { return $this->begin(); }
	public function e() { return $this->end(); }

	public function find()
	{
		$args = func_get_args();
		$args = $this->applyRegOpts($args);

		if (!$args || !is_array($args)) return $this;

		if (count($args) == 1)
			return $this->match($args[0]);

		$ar_before = $ar_after = array();

		foreach($args as $i => $v)
		{
			if ($b || $i+1 == count($args)) $ar_after[] = $v;
			elseif ($v == "*") $b = 1;
			else $ar_before[] = $v;
		}

		$htmls = array_pop($this->htmls);

		$res = array();
		foreach($htmls as $i => $html)
		{
			foreach($ar_before as $v)
			{
				$ar = preg_split($v,$html,2);
				$html = $ar[1];
			}

			foreach($ar_after as $v)
			{
				$ar = preg_split($v,$html,2);
				$html = $ar[0];
			}
		
			$res[$i] = $html;
		}
		$this->htmls[] = $res;

		return $this;
	}

	public function match($v)
	{
		$v = $this->applyRegOpts($v);

		$htmls = array_pop($this->htmls);

		$res = array();
		foreach($htmls as $i => $html)
		{
			if (preg_match($v,$html,$m))
			{
				if (count($m) == 2)
					$res[$i] = $m[1];
				else
					foreach($m as $tmp)
						$res[] = $tmp;
			}
			else
				$res[$i] = null;
		}
		$this->htmls[] = $res;

		return $this;
	}
		
	public function split($v,$limit=-1,$take=null)
	{
		$v = $this->applyRegOpts($v);

		$htmls = array_pop($this->htmls);

		$res = array();
		foreach($htmls as $i => $html)
		{
			$ar = preg_split($v,$html,$limit);
			if (isset($take)) $res[$i] = $ar[$take];
			else
				foreach($ar as $tmp)
					$res[] = $tmp;
		}
		$this->htmls[] = $res;

		return $this;
	}

	public function rm()
	{
		$args = func_get_args();
		if (!$args || !is_array($args)) return $this;

		foreach($args as $i)
			$ar[$i] = 1;

		$htmls = array_pop($this->htmls);

		$res = array();
		foreach($htmls as $i => $html)
		{
			if ($ar[$i]) continue;
			$res[] = $html;
		}

		$this->htmls[] = $res;

		return $this;
	}

	public function DOMFind($start,$end,$obs=null)
	{
		$start = $this->applyRegOpts($start);
		$end = $this->applyRegOpts($end);

		$htmls = array_pop($this->htmls);

		$res = array();
		foreach($htmls as $i => $html)
		{
			if (preg_match($start,$html))
			{
				$ar = preg_split($start,$html,2);
				$html = $base_html = $ar[1];
				$deep = 0;
				$ind = 0;

				while (1)
				{
					if (preg_match($end,$html))
					{
						$ar = preg_split($end,$html,2,PREG_SPLIT_OFFSET_CAPTURE);
						if (isset($obs))
							$deep += count(preg_split($obs,$ar[0][0])) - 1;

						if (!$deep)
						{
							$ind += strlen($ar[0][0]);
							$res[$i] = substr($base_html,0,$ind);
							break;
						}

						$html = $ar[1][0];
						$ind += $ar[1][1];
						$deep--;
					}
					else
					{
						$res[$i] = $base_html;
						break;
					}
				}
			}
			else $res[$i] = null;
		}
		$this->htmls[] = $res;

		return $this;
	}

	public function begin()
	{
		$htmls = end($this->htmls);
		$this->htmls[] = $htmls;
		return $this;
	}

	public function end()
	{
		$htmls = array_pop($this->htmls);
		return $this;
	}

	public function save(&$var,$func=null)
	{
		$out = end($this->htmls);

		$out_funcs = array($this->opts["beforeSaveFunc"],$func?null:$this->opts["defaultSaveFunc"],$func,$this->opts["afterSaveFunc"]);

		foreach($out_funcs as $func)
		{
			if (isset($func) && function_exists($func))
			{
				if (is_array($out))
					foreach($out as $i => $v)
						$out[$i] = $func($v);
				else $out = $func($out);
			}
		}

		if (is_array($out) && count($out) == 1)
			$var = reset($out);
		else
			$var = $out;

		return $this;
	}

	protected function applyRegOpts($p)
	{
		if (is_array($p))
		{
			foreach($p as $i => $v)
				$p[$i] = $this->applyRegOpts($v);
		}
		else
		{
			if ($this->opts["i"] && preg_match("/\/$/",$p))
				$p .= "i";
		}
		return $p;
	}
}
	
