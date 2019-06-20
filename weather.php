<?php
get_weather_info();
exit(0);

// 위도, 경도 -> 격자좌표 x,y로 변환
function pos_to_grid_conv($v1, $v2)
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
	$time = substr(_param_pick('&base_time='), 10, 2) . substr(_param_pick('&base_time='), 15, 2);
	$x = substr(_param_pick('&nx='), 3);
	$y = substr(_param_pick('&ny='), 3);

//	echo $time. "\n";

	$grid = pos_to_grid_conv((double)$x, (double)$y);
/*
	echo "<위경도 변환(격자)>\n";
	echo "  위도: ".$x."  => 격자x: ".$grid['x']."\n";      
	echo "  경도: ".$y."  => 격자y: ".$grid['y']."\n";
	echo "\n";
 */
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

	$resultCode = $xml->header->resultCode;
	$resultMsg = $xml->header->resultMsg;

	if($resultCode == 0)
		$connectStat = "NORMAL_CODE(정상)";
	else if($resultCode == 1)
		$connectStat = "APPLICATION_ERROR(어플리케이션 에러)";
	else if($resultCode == 2)
		$connectStat = "DB_ERROR(데이터베이스 에러)";
	else if($resultCode == 3)
		$connectstat = "NODATA_ERROR(데이터없음 에러)";
	else if($resultCode == 4)
		$connectstat = "HTTP_ERROR(HTTP 에러)";
	else if($resultCode == 5)
		$connectstat = "SERVICETIMEOUT_ERROR(서비스 연결실패 에러)";
	else if($resultCode == 10)
		$connectstat = "INVALID_REQUEST_PARAMETER_ERROR(잘못된 요청 파라메터 에러)";
	else if($resultCode == 11)
		$connectstat = "NO_MANDATORY_REQUEST_PARAMETERS_ERROR(필수 요청 파라메터 없음)";
	else if($resultCode == 12)
		$connectstat = "NO_OPENAPI_SERVICE_ERROR(해당 오픈API 서비스가 없거나 폐기됨)";
	else if($resultCode == 20)
		$connectstat = "SERVICE_ACCESS_DENIED_ERROR(서비스 접근 거부)";
	else if($resultCode == 21)
		$connectstat = "TEMPORARILY_DISABLE_THE_SERVICEKEY_ERROR(일시적으로 사용할 수 없는 서비스키)";
	else if($resultCode == 22)
		$connectstat = "LIMITED_NUMBER_OF_SERVICE_REQUESTS_EXCEEDS_ERROR(일일 트래픽요청 횟수 초과)";
	else if($resultCode == 11)
		$connectstat = "SERVICE_KEY_IS_NOT_REGISTERED_ERROR(등록되지 않은 서비스키)";
	else if($resultCode == 12)
		$connectstat = "DEADLINE_HAS_EXPIRED_ERROR(기한만료된 서비스키)";
	else if($resultCode == 20)
		$connectstat = "UNREGISTERED_IP_ERROR(등록되지 않은 IP)";
	else if($resultCode == 21)
		$connectstat = "UNSIGNED_CALL_ERROR(서명되지 않은 호출)";
	else if($resultCode == 22)
		$connectstat = "UNKNOWN_ERROR(기타 에러)";
	
	$temp = $items[3]->obsrValue . "℃";//기온
	$humid = $items[1]->obsrValue . "%";	//습도
	$precForm = $items[0]->obsrValue;	//강수형태
	$prec = $items[2]->obsrValue . "mm";	//강수량(1H 기준)
	$windDirct = $items[5]->obsrValue;	//풍향
	$windspeed = $items[7]->obsrValue . "m/s";	// 풍속
	
	switch($precForm)
	{
	case 0: $precForm = "비가 안 내리는 중입니다."; break;
	case 1: $precForm = "비가 내리는 중입니다"; break;
	case 2: $precForm = "비와 눈이 함께 내리는 중입니다"; break;
	case 3: $precForm = "눈이 내리는 중입니다"; break;
	case 4: $precForm = "소나기가 내리는 중입니다"; break;
	}
	
	if(($windDirct >= 0 && $windDirct <= 22)||($windDirct >= 338 && $windDirct <= 360))
		$windDirct = "북풍↑";
	else if($windDirct >= 23 && $windDirct <= 67)
		$windDirct = "북동풍↗";
	else if($windDirct >= 68 && $windDirct <= 112)
		$windDirct = "동풍→";
	else if($windDirct >= 113 && $windDirct <= 157)
		$windDirct = "남동풍↘";
	else if($windDirct >= 158 && $windDirct <= 202)
		$windDirct = "남풍↓";
	else if($windDirct >= 203 && $windDirct <= 247)
		$windDirct = "남서풍↙";
	else if($windDirct >= 248 && $windDirct <= 292)
		$windDirct = "서풍←";
	else if($windDirct >= 293 && $windDirct <= 337)
		$windDirct = "북서풍↖";
/*
	echo "< 현재 날씨 >\n";
	echo "  기온: " . $temp . "℃ \n";
	echo "  습도: " . $humid . "℃ \n";
	echo "  강수형태: " . $precForm . "\n";
	echo "  강수량(1H 기준): " . $prec . "mm\n";
	echo "  풍향: " . $windDirct . "\n";
	echo "  풍속: " . $windspeed . "m/s\n";
	

	echo "\n----------------------------------------------------".
	   "----------------------------------------------------\n\n";
	
	print_r($grid);
	echo "\n";

	print_r(json_encode($xml));

	echo"\n\n오픈API로부터 XML형태로 받은 데이터를 Json형태로 인코드하고 해당 데이터를 클라이언트를 위해 더욱 간결화(encode)\n=> ";
 */
	$json_list = json_encode(array(
		'code' => $connectStat, 'msg' => (string)$resultMsg,
		'temp' => (string)$temp, 'humid' => (string)$humid, 
		'precForm' => $precForm, 'prec' => (string)$prec, 
		'windDirct' => $windDirct, 'windspeed' => (string)$windspeed
	), JSON_UNESCAPED_UNICODE);
	print_r(stripslashes($json_list));
	curl_close($ch);
}
?>

