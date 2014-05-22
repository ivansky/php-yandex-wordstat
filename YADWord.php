<?php

class YADWord {
	
	private static $bind_region = array();
	private static $bind_crc = array();
	
	public $region_id = 213;
	
	public $original;
	public $word;
	public $strict;
	public $crc;
	
	public $stat = -1;
	public $stat_strict = -1;
	
	public function __construct($w, $r, $s, $ss){
		$this->region_id = (int)$r;
		$this->original = $w;
		$this->word = self::prepare($w);
		$this->strict = '!'.str_replace(' ', ' !', $this->word);
		$this->crc = crc32(str_replace(' ','-',$this->word));
		$this->stat = (int)$s;
		$this->stat_strict = (int)$ss;
		
		if(!isset(self::$bind_region[$this->region_id])) self::$bind_region[$this->region_id] = array();
		
		// BIND link by region code
		self::$bind_region[$this->region_id][$this->crc] = &$this;
		
		// BIND link by crc32
		self::$bind_crc[$this->crc][] = &$this;
	}
	
	public static function get($w, $r, $stat = -1, $stat_strict = -1){
		if($i = self::findByRegionCRC($w, $r)){
			return $i;
		}
		
		$i = new self($w, $r, $stat, $stat_strict);
		
		return $i;
	}
	
	public static function findComplete($region_id){
		return self::findByStatus(true, $region_id);
	}
	
	public static function findIncomplete($region_id = false){
		return self::findByStatus(false, $region_id);
	}
	
	public static function findByStatus($complete, $region_id){
		$l = array();
		
		$complete = (boolean)$complete;
		
		if($region_id){
			$region_id = (int)$region_id;
			
			if(!isset(self::$bind_region[$region_id])){
				return $l;
			}else{
				foreach(self::$bind_region[$region_id] as $crc => $c){
					if(($complete == false && ($c->stat < 0 || $c->stat_strict < 0)) || ($complete == true && $c->stat >= 0 && $c->stat_strict >= 0)){
						$l[] = $c;
					}
				}
			}
		}else{
			foreach (self::$bind_crc as $cc){
				foreach($cc as $crc => $c){
					if(($complete == false && ($c->stat < 0 || $c->stat_strict < 0)) || ($complete == true && $c->stat >= 0 && $c->stat_strict >= 0)){
						$l[] = $c;
					}
				}
			}
		}
		
		return $l;
	}
	
	/**
	 * Return array of YADWord found by region code
	 * @param int|string $r Region code
	 * @param array $filter Search Options
	 * return YADWord|boolean
	 */
	public static function findByRegion($r, $filter = array()){
		$r = (int)$r;
		
		if(isset(self::$bind_region[$r])){
			if(!count($filter)) return self::$bind_region[$r];
			else{
				$l = array();
				foreach(self::$bind_region[$r] as $crc => $c){
					foreach($filer as $named => $val){
						switch($named){
							case 'original':
								if($c->original != $val)
									continue 2;
								break;
							case 'stat':
								if($val && ($c->stat < 0 || $c->stat_strict < 0))
									continue 2;
								if(!$val && ($c->stat >= 0 && $c->stat_strict >= 0))
									continue 2;
								break;
							case 'crc':
								if($crc != $val)
									continue 2;
								break;
						}
					}
					$l[] = &$c;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Return copy of YADWord found by region code
	 * @param string $w Word
	 * @param int|string $r Region code
	 * return YADWord|boolean
	 */
	public static function findByRegionCRC($w, $r){
		$word = self::prepare($w);
		$r = (int)$r;
		$crc = crc32(str_replace(' ','-',$word));
		
		if(isset(self::$bind_region[$r][$crc])){
			return self::$bind_region[$r][$crc];
		}
		
		return false;
	}
	
	/**
	 * Return found array of YADWord by CRC
	 * @param int|string $crc
	 * @return array|boolean
	 */
	public static function findByCRC($crc){
		$crc = (int)$crc;
		if(isset(self::$bind_crc[$crc])){
			return self::$bind_crc[$crc];
		}
		return false;
	}
	
	/**
	 * Remove from string all invalid characters
	 * Leave only English, Russian, Turkish, Kazakh and Numbers
	 * @link http://www.unicode.org/charts/
	 * @param string $w
	 * @return string
	 */
	public static function prepare($w){
		// АаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМм
		// НнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъ
		// ЫыЬьЭэЮюЯя
		$RUSSIAN = '\\x{0410}-\\x{045F}'; 
		
		// çğışöü ÇĞİŞÖÜ
		$TURKISH = '\\x{00E7}\\x{011F}\\x{0131}\\x{015F}'.
			'\\x{00F6}\\x{00FC}\\x{00C7}\\x{011E}'.
			'\\x{0130}\\x{015E}\\x{00D6}\\x{00DC}';
		
		//ӘҒҚҢӨҮҰҺІ әғқңөүұһі
		$KAZAKH = '\\x{04A2}\\x{04A3}\\x{0406}\\x{0456}'.
			'\\x{0492}\\x{0493}\\x{049A}\\x{049B}'.
			'\\x{04AE}\\x{04AF}\\x{04B0}\\x{04B1}'.
			'\\x{04BA}\\x{04BB}\\x{04D8}\\x{04D9}'.
			'\\x{04E8}\\x{04E9}'; 
		
		$w = preg_replace('/[^a-z0-9'.$RUSSIAN.$TURKISH.$KAZAKH.']+/ui', ' ', $w);
		$w = preg_replace('/[\s]+/', ' ', $w);
		
		return mb_strtolower(trim($w),'UTF-8');
	}
	
}
