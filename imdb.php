<?php

class IMDB
{
	static $last_cache = 0;
    
    public function __construct($url)
    {
        $this->url = ($url);  
    }

    public static function file_get_curl($sUrl, $bDownload = false)
    {
        $oCurl = curl_init($sUrl);
        curl_setopt_array($oCurl,
              [
                  CURLOPT_BINARYTRANSFER => ($bDownload ? true : false),
                  CURLOPT_CONNECTTIMEOUT => 15,
                  CURLOPT_ENCODING       => '',
                  CURLOPT_FOLLOWLOCATION => 0,
                  CURLOPT_FRESH_CONNECT  => 0,
                  CURLOPT_HEADER         => ($bDownload ? false : true),
                  CURLOPT_HTTPHEADER     => [
                      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                      'Accept-Charset: utf-8, iso-8859-1;q=0.5',
                      'Accept-Language: en-US,en;q=0.9'
                  ],
                  CURLOPT_REFERER        => 'https://www.imdb.com',
                  CURLOPT_RETURNTRANSFER => 1,
                  CURLOPT_SSL_VERIFYHOST => 0,
                  CURLOPT_SSL_VERIFYPEER => 0,
                  CURLOPT_TIMEOUT        => 15,
                  CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0',
                  CURLOPT_VERBOSE        => 0
              ]);

        $sOutput   = curl_exec($oCurl);
        $aCurlInfo = curl_getinfo($oCurl);
        curl_close($oCurl);

        $aCurlInfo['contents'] = $sOutput;

        if (200 !== $aCurlInfo['http_code'] && 302 !== $aCurlInfo['http_code']) 
        {
            if (true === false) {
            
                echo '<pre><b>cURL returned wrong HTTP code “' . $aCurlInfo['http_code'] . '”, aborting.</b></pre>';
            }

            return false;
        }

        return $aCurlInfo;
    }

	public static function getMatches($sContent, $sPattern, $iIndex = null)
	{
	    preg_match_all($sPattern, $sContent, $aMatches);
	    if ($aMatches === false) {
	        return false;
	    }
	    if ($iIndex !== null && is_int($iIndex)) {
	        if (isset($aMatches[$iIndex][0])) {
	            return $aMatches[$iIndex][0];
	        }

	        return false;
	    }

	    return $aMatches;
	}

	public static function get($url, $cache = true)
	{
	    $tmp = @file_get_contents("cache/" . md5($url) . ".cache");
 
	    if($tmp == false)
	    {
	        $tmp = IMDB::file_get_curl($url, true)['contents'];
	        if($cache)
            {
                $gzdata = gzencode($tmp, 9);
                file_put_contents("cache/" . md5($url) . ".cache", $gzdata);
            }
	    }else
        {
            $tmp = gzdecode($tmp);
        }

	    IMDB::$last_cache = @filemtime ( "cache/" . md5($url) . ".cache");

	    return $tmp;
	}

    public function fetch()
    {
        $this->html = IMDB::get($this->url); 


        $meta = $this->getMeta();

        $this->data = array( 
            'imdb' => trim(IMDB::getMatches($this->url, '~/title/(tt.*?)/~Ui',1)),
            'title' => $this->getTitle(), 
            'description' => $this->getDescription(), 
            'categories' => $this->getCategories(), 
            'rating' => $this->getRating(), 
            'year' => $this->getYear(),
            'directors' => @$meta['Directors'],
            'creators' => @$meta['Writers'],
            'actors' => @$meta['Stars'],
            'modified' => date ("F d Y H:i:s", IMDB::$last_cache)
        );

        $poster =  $this->getThumbnail();
        $this->data['poster']['small'] = preg_replace('/_V1.*?.jpg/ms', "_V1._SY200.jpg", $poster);
        $this->data['poster']['large'] = preg_replace('/_V1.*?.jpg/ms', "_V1._SY500.jpg", $poster);
        $this->data['poster']['full'] = preg_replace('/_V1.*?.jpg/ms', "_V1._SY0.jpg", $poster);

        $this->data['release_date'] = IMDB::getMatches($this->html, '/<a href="\/title\/tt\d+\/releaseinfo">(.*?)<\/a>/ms',1);

        $this->data['type'] = trim(IMDB::getMatches($this->html, '~>\s*((TV Series)|(Movie)|(TV Episode)|(TV Mini Series))\s*</li>~',1));

        if($this->data['type'] == 'TV Episode')
        {
            $this->data['show'] = $this->getShowDetails();

            if(true){ // fetch show details
                $ts = new IMDB("https://www.imdb.com/title/". $this->data['show']['imdb'] . "/reference");
                $this->data['show'] = array_merge($this->data['show'], $ts->fetch());
            }
            
        }elseif($this->data['type'] == 'TV Series' | $this->data['type'] == 'TV Mini Series')
        {
            $this->data['seasons'] = (IMDB::getMatches($this->html, '~(?:Seasons|Season):\s*<a\s*href="(?:.*)\?season=(\d*)">~Ui',1));
        }


        //gets titles
       $this->data['titles'] =  (IMDB::getMatches($this->html, '~Also Known As</td>\s*<td>((?:\s*.*\s*)*?)</td>~Ui',1));
       $this->data['titles'] =  (IMDB::getMatches($this->data['titles'], '~<li class="ipl-inline-list__item">\s*(.*)\s*\((.*)\)\s*<\/li>~Ui'));
       $aks = array();
       $aks_i = 0;
        foreach ($this->data['titles'][2] as $lang)
        {
           $aks[$lang] = trim($this->data['titles'][1][$aks_i]);
           $aks_i++;
        }

        $this->data['titles'] = $aks;

 
 		//languages
		$this->data['langs'] = (IMDB::getMatches($this->html, '~Language</td>\s*<td>((?:\s*.*\s*)*?)</td>~Ui',1));
		$this->data['langs'] =  (IMDB::getMatches($this->data['langs'] , '~<a (?:.*)>\s*(.*)\s*</a>~Ui')[1]);


        


       return $this->data;   
    }

    public function getTitle()
    {
        return trim(IMDB::getMatches($this->html, '~<h3 itemprop="name">\s*(.*)(?:<\/h3>|<span)~Ui',1));
    }

    public function getShowDetails()
    {
        //extract show title
        $data = IMDB::getMatches($this->html, '~itemprop="name">\s*<a\shref="(.+)"\s*(?:.*?)>\s*(.*?)(?:</a>|<span)~');

        $data['imdb'] = trim(IMDB::getMatches(@$data[1][0], '~/title/(tt.*?)/~Ui',1)); // extract show imdb
        $data['title'] = trim( @$data[2][0]); // extract show name


        $data['seasson'] = trim(IMDB::getMatches($this->html, '~>\s*Season\s(\d*)\s*</li>~i',1));
        $data['episode'] = trim(IMDB::getMatches($this->html, '~>\s*Episode\s(\d*)\s*</li>~i',1));

        unset($data[0]);
        unset($data[1]);
        unset($data[2]);

        return $data;
    }

    public function getRating()
    {
        return trim(IMDB::getMatches($this->html, '/<span class="ipl-rating-star__rating">(\d.*?\d*)<\/span>/ms', 1));
    }

    public function getYear()
    {
        return trim(IMDB::getMatches($this->html, '/<title>.*?\(.*?(\d{4}).*?\).*?<\/title>/ms', 1));
    }
    public function getDescription()
    {
        $sum =  trim(IMDB::getMatches($this->html, '~<div>(?:\s*)(.*)(?:\s*)</div>\s*<hr>\s*<div\s*class="titlereference-overview-section"~Ui',1));

        if(empty($sum))
        {
            $block =  trim(IMDB::getMatches($this->html, '~Plot Summary</td>\s*<td>((?:\s*.*\s*)*?)</td>~Ui',1));
            $sum = trim(IMDB::getMatches($block, '~\s*<p>(\s*.*\s*)<(?:.*?)>~Ui',1));//[1];
        }

        if(empty($sum))
        {
             $sum = "NONE";
        }

        return $sum;
    }

    public function getCategories()
    {
        $block =  trim(IMDB::getMatches($this->html, '~Genres</td>\s*<td>((?:\s*.*\s*)*?)</td>~Ui',1));
        return IMDB::getMatches($block, '~/genre/(.*?)">~Ui')[1];
    }

    public function getMeta()
    {
        $sections =  IMDB::getMatches($this->html, '~<div class="titlereference-overview-section">((?:(?:\s*)(?:.*)(?:\s*))*?)</div>~Ui')[1];
        $data = array();

        foreach ($sections as $s)
        {
            $key =  trim(IMDB::getMatches($s, '~(\w*):\s*<ul~Ui',1));
            $data[$key] = IMDB::getMatches($s, '~/name/(?:.*?)">(.*)</a>~Ui')[1];

        }

        if(@$data['Directors'] != null)
        $data['Directors'] = $data['Directors'];
        if(@$data['Director'] != null)
        $data['Directors'] = $data['Director'];

        if(@$data['Writers'] != null)
        $data['Writers'] = $data['Writers'];
        if(@$data['Writer'] != null)
        $data['Writers'] = $data['Writer'];

        if(@$data['Stars'] != null)
        $data['Stars'] = $data['Stars'];
        if(@$data['Star'] != null)
        $data['Stars'] = $data['Star'];

        return $data;
    }

    public function getThumbnail()
    {
        return trim(IMDB::getMatches($this->html, '~<link\s*rel=\'image_src\'\s*href="(.*)">~Ui',1));
    }

     public static function search($term)
    {
    	$html = IMDB::get("https://www.imdb.com/find?q=" . urlencode($term) . "&s=tt&exact=true&ref_=fn_tt_ex");
    	$links = (IMDB::getMatches($html, '~<td class="result_text">(.*)\s*</td>~Ui')[1]);
		$term = array();
		foreach ($links as $item)
        {
        	$imdb = (IMDB::getMatches($item, '~/title/(tt.*)/~Ui',1));
        	$title = (IMDB::getMatches($item, '~>\s*(.*?)\s*</a>\s*(.*)$~Ui'));
        	$title = trim($title[1][0]) . ' ' . trim($title[2][0]);

        	$title = preg_replace('/<[^>]*>/', '', $title); // remove all html tags

        	$item = array('imdb' =>  $imdb, 'title' => $title);;
        	$term[] = $item;
        }

        $thumbs = (IMDB::getMatches($html, '~<td class="primary_photo">(.*)\s*</td>~Ui')[1]);
        $c = 0;
        foreach ($thumbs as $item)
        {
        	$term[$c]['thumb'] = trim(IMDB::getMatches($item, '~<img\s*src="(.*)"\s/>~Ui',1));
        	$term[$c]['thumb']  = preg_replace('/_V1.*?.jpg/ms', "_V1._SY0.jpg", $term[$c]['thumb']);
        	$c ++;
        }
		return $term;

    }

    public static function getEpisodes($imdb, $s)
    {
        $html = IMDB::get("https://www.imdb.com/title/". $imdb . "/episodes?season=" . $s);

        $eps = IMDB::getMatches($html, '~<div class="list_item (?:odd|even)">(?:\s*(?:.*)\s*)*?<div class="clear">~m')[0];

        $s = intval(IMDB::getMatches($html, '~episode_top"\s*itemprop="name">Season&nbsp;(.*)</h3>~m',1));

        $e = array('seasson' => $s);
        foreach ($eps as $ep)
        {
            $pt = IMDB::getMatches($ep, '~<a href="(.+)"\s*title="(.+)"\s*itemprop="name">(.+)</a>~m');
  
            $title = $pt[2][0];
            $imdb = trim(IMDB::getMatches($pt[1][0], '~/title/(tt.*)/~Ui',1));
            $episode = intval(IMDB::getMatches($ep, '~<meta\s*itemprop="episodeNumber"\s*content="(.*?)"/>~m',1));
            $desc = IMDB::getMatches($ep, '~itemprop="description">\s*(.*)</div>~m',1);
            $airdate = IMDB::getMatches($ep, '~airdate">\s*(.*)?\s*</div>~m',1);
            $rating = trim(IMDB::getMatches($ep, '/<span class="ipl-rating-star__rating">(\d.*?\d*)<\/span>/ms', 1));
            

            $e['episodes'][$episode]  = array('imdb' => $imdb, 'title' => $title, 
                                            'description'=>  trim($desc), 
                                            'airdate' => trim($airdate), 'rating' => $rating);
        }

        return $e;
    }
}
?>
