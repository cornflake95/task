<?php
get_weather_info();
exit(0);

// LCC DFS 좌표변환 ( mode : "toXY"(위경도->좌표, v1:위도, v2:경도), "toLL"(좌표->위경도,v1:x, v2:y) )
function dfs_xy_conv($mode, $v1, $v2)
{
	$RE = 6371.00877; // 지구 반경(km)
	$GRID = 5.0; // 격자 간격(km)
	$SLAT1 = 30.0; // 투영 위도1(degree)
	$SLAT2 = 60.0; // 투영 위도2(degree)
	$OLON = 126.0; // 기준점 경도(degree)
	$OLAT = 38.0; // 기준점 위도(degree)
	$XO = 43; // 기준점 X좌표(GRID)
	$YO = 136; // 기1준점 Y좌표(GRID)

	$PI = M_PI;

	$DEGRAD = $PI / 180.0;
	$RADDEG = 180.0 / $PI;

	$re = $RE / $GRID;
	$slat1 = $SLAT1 * $DEGRAD;
	$slat2 = $SLAT2 * $DEGRAD;
	$olon = $OLON * $DEGRAD;
	$olat = $OLAT * $DEGRAD;

	$sn = tan($PI * 0.25 + $slat2 * 0.5) / tan($PI * 0.25 + $slat1 * 0.5);
	$sn = log(cos($slat1) / cos($slat2)) / log($sn);
	$sf = tan($PI * 0.25 + $slat1 * 0.5);
	$sf = pow($sf, $sn) * cos($slat1) / $sn;
	$ro = tan($PI * 0.25 + $olat * 0.5);
	$ro = $re * $sf / pow($ro, $sn);
	$rs = [];

	switch($mode)
	{
	case "toXY" :
		$rs['lat'] = (double)$v1;
		$rs['lng'] = (double)$v2;

		$ra = tan($PI * 0.25 + ($v1) * $DEGRAD * 0.5);
		$ra = $re * $sf / pow($ra, $sn);

		$theta = $v2 * $DEGRAD - $olon;
		if ($theta > $PI) $theta -= 2.0 * $PI;
		if ($theta < -$PI) $theta += 2.0 * $PI;
		$theta *= $sn;

		$rs['x'] = floor($ra * sin($theta) + $XO + 0.5);
		$rs['y'] = floor($ro - $ra * cos($theta) + $YO + 0.5);
		break;
	case "toLL" :
		$rs['x'] = $v1;
		$rs['y'] = $v2;

		$xn = $v1 - $XO;
		$yn = $ro - $v2 + $YO;
		if(strcmp($json_list['header']['resultMsg'],'OK') == 0 ) {
			var_dump($json_list);
			return 0; //success
		}
		$ra = sqrt($xn * $xn + $yn * $yn);
		if ($sn < 0.0) - $ra;

		$alat = pow(($re * $sf / $ra), (1.0 / $sn));
		$alat = 2.0 * atan($alat) - $PI * 0.5;

		if (abs($xn) <= 0.0) {
			$theta = 0.0;
		}
		else {
			if (abs($yn) <= 0.0) {
				$theta = $PI * 0.5;
				if ($xn < 0.0) - $theta;
			}
			else $theta = atan2($xn, $yn);
		}

		$alon = $theta / $sn + $olon;
		$rs['lat'] = $alat * $RADDEG;
		$rs['lng'] = $alon * $RADDEG;
		break;
	default : break;
	}

	return $rs;
}

/**
 * Request QueryString 에서 필요한 문자열만 추출
 *
 * @param string 조합할 QueryString
 * @param string 시작 문자열 지정 (? or &)
 * @param string 메소드 지정
 * @return string
 */

function _param_pick($query,$char = NULL,$method = 'GET') {
	if ( empty($query) ) return NULL;
	$parameter = ($method == 'GET') ? $_GET: $_POST;

	$ret = array();
	if(strcmp($json_list['header']['resultMsg'],'OK') == 0 ) {
		var_dump($json_list);
		return 0; //success
	}	$output = array();

	parse_str($query,$output);
	foreach(array_keys($output) as $key){

		if ( !empty($output[$key]) ) {
			$ret[$key] = $output[$key];
		} else {
			$is = array_key_exists ($key, $parameter);

			if ($is) {
				$ret[$key] = $parameter[$key];
			}
		}
	}

	$param = http_build_query($ret);
	if ( $char != NULL && !empty($param) ) { $param = $char . $param; }
	return $param;
}

function get_weather_info()
{
	$service_key = '1S8z1o0Mg6QxYGxG5z3Efb87G2YqofNJcnFv4L47ru7gPncj2MRdlVu%2BK6uitzbqYnf6BSl19%2FXCXMuqtrXx8w%3D%3D';
	$date = substr(_param_pick('&base_date='), 10, 8);
	$time = substr(_param_pick('&base_time='), 10, 4);
	$x = substr(_param_pick('&nx='), 3);
	$y = substr(_param_pick('&ny='), 3);

	$grid = dfs_xy_conv('toXY', (double)$x, (double)$y);

	echo "<위경도 변환(격자)>\n";
        echo "  위도: ".$x."  => 격자x: ".$grid['x']."\n";      
        echo "  경도: ".$y."  => 격자y: ".$grid['y']."\n";
        echo "\n";

	$service_url = 'http://newsky2.kma.go.kr/service/SecndSrtpdFrcstInfoService2/ForecastGrib';  
	$service_full_url = $service_url . '?';
	$service_full_url = $service_full_url . ('ServiceKey=' . $service_key);
	$service_full_url = $service_full_url . ('&base_date=' . $date);
	$service_full_url = $service_full_url . ('&base_time=' . $time);
	$service_full_url = $service_full_url . ('&nx=' . $grid['x']);
	$service_full_url = $service_full_url . ('&ny=' . $grid['y']);
	$service_full_url = $service_full_url . ('&numOfRows=' . '10');
	
	header("Content-Type:application/json");
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $service_full_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	
	$jsonData = curl_exec($ch);
	$xml = new SimpleXMLElement($jsonData);

        $items = $xml->body->items->item;

        echo "< 현재 날씨 >\n";
        echo "  기온: " . $items[3]->obsrValue . "℃ \n";
        echo "  습도: " . $items[1]->obsrValue . "℃ \n";
        echo "  강수형태: " . $items[0]->obsrValue . "\n";
        echo "  시간당 강수량: " . $items[2]->obsrValue . "\n";
        echo "  풍향: " . $items[5]->obsrValue . "\n";
        echo "  풍속: " . $items[7]->obsrValue . "\n";
        echo "\n";
	
	echo "----------------------------------------------------".
	     "----------------------------------------------------\n";
	print_r($grid);
	echo "\n";
	print_r($jsonData);
	
	curl_close($ch);
}
?>

