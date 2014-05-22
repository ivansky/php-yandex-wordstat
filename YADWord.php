<?php

class YADWord {
	
	private static $bind_region = array();
	private static $bind_crc = array();
	
	public $region_id = 213;
	
	public $original;
	public $word;
	public $crc;
	
	public $stat = -1;
	public $stat_strict = -1;
	
	public function __construct($w, $r){
		$this->region_id = (int)$r;
		$this->original = $w;
		$this->word = self::prepare($w);
		$this->crc = crc32(str_replace(' ','-',$this->word));
		
		// BIND link by region code
		self::$bind_region[$this->region_id][$this->crc] = &$this;
		
		// BIND link by crc32
		self::$bind_crc[$this->crc] = &$this;
	}
	
	public static function get($w, $r){
		$word = self::prepare($w);
		$r = (int)$r;
		$crc = crc32(str_replace(' ','-',$word));
		
		if(isset(self::$bind_region[$r][$crc])){
			return self::$bind_region[$r][$crc];
		}
			
		$i = new self($w, $r);
		
		return $i;
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
